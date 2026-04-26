<?php

/**
 * @package WooApp\API
 * Social authentication endpoints for login/register via
 * Google, Facebook, Apple, and Microsoft.
 */

namespace WooApp\API;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WooApp\Common\Constants;
use WooApp\Services\SocialAuthManager;

defined('ABSPATH') || exit;

class SocialAuthEndpoints
{
    /**
     * Authentication instance
     */
    private $auth;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->auth = new Authentication();
    }

    /**
     * Register social authentication routes
     */
    public function register_routes()
    {
        // POST /social-login — Login or register via social provider
        register_rest_route(
            Constants::REST_NAMESPACE,
            'social-login',
            array(
                'methods'             => 'POST',
                'callback'            => array($this, 'social_login'),
                'permission_callback' => array($this, 'check_api_permission'),
            )
        );

        // POST /social-link — Link social account to an existing user
        register_rest_route(
            Constants::REST_NAMESPACE,
            'social-link',
            array(
                'methods'             => 'POST',
                'callback'            => array($this, 'social_link'),
                'permission_callback' => array($this, 'check_api_permission'),
            )
        );

        // GET /social-accounts — List linked social accounts for a user
        register_rest_route(
            Constants::REST_NAMESPACE,
            'social-accounts',
            array(
                'methods'             => 'GET',
                'callback'            => array($this, 'get_social_accounts'),
                'permission_callback' => array($this, 'check_api_permission'),
                'args'                => array(
                    'user_id' => array(
                        'required'          => true,
                        'type'              => 'integer',
                        'validate_callback' => function ($param) {
                            return is_numeric($param) && (int) $param > 0;
                        },
                    ),
                ),
            )
        );

        // DELETE /social-accounts/{provider} — Unlink a social account
        register_rest_route(
            Constants::REST_NAMESPACE,
            'social-accounts/(?P<provider>[a-z]+)',
            array(
                'methods'             => 'DELETE',
                'callback'            => array($this, 'unlink_social_account'),
                'permission_callback' => array($this, 'check_api_permission'),
                'args'                => array(
                    'user_id' => array(
                        'required'          => true,
                        'type'              => 'integer',
                        'validate_callback' => function ($param) {
                            return is_numeric($param) && (int) $param > 0;
                        },
                    ),
                ),
            )
        );
    }

    /**
     * API permission check (OAuth 1.0 / Basic Auth)
     */
    public function check_api_permission(WP_REST_Request $request)
    {
        return $this->auth->check_api_permission($request);
    }

    /**
     * Social Login / Register
     *
     * Endpoint:
     *   POST /wp-json/wooapp/v1/social-login
     *
     * Body:
     * {
     *   "provider": "google",         // required: google|facebook|apple|microsoft
     *   "token": "eyJhbGc...",        // required: ID token or access token
     *   "first_name": "Kevin",        // optional (Apple only — name sent on first auth)
     *   "last_name": "Zhu"            // optional
     * }
     *
     * Response: UserProfile with social_accounts and auth_method fields
     */
    public function social_login(WP_REST_Request $request)
    {
        $params = $request->get_json_params();

        $provider = isset($params['provider']) ? sanitize_key($params['provider']) : '';
        $token    = isset($params['token']) ? $params['token'] : '';

        if (empty($provider) || empty($token)) {
            return new WP_Error(
                'missing_fields',
                __('provider and token are required', 'wooapp-setting-tools'),
                array('status' => 400)
            );
        }

        if (!in_array($provider, Constants::SOCIAL_PROVIDERS, true)) {
            return new WP_Error(
                'invalid_provider',
                __('Unsupported provider. Allowed: google, facebook, apple, microsoft, wechat', 'wooapp-setting-tools'),
                array('status' => 400)
            );
        }

        // Verify token with the provider
        $social_data = SocialAuthManager::verify_token($provider, $token);
        if (is_wp_error($social_data)) {
            return $social_data;
        }

        // Extra fields from client (Apple first auth name)
        $extra = array(
            'first_name' => isset($params['first_name']) ? sanitize_text_field($params['first_name']) : '',
            'last_name'  => isset($params['last_name']) ? sanitize_text_field($params['last_name']) : '',
        );

        // Find or create user
        $result = SocialAuthManager::find_or_create_user($provider, $social_data, $extra);
        if (is_wp_error($result)) {
            return new WP_Error(
                'social_auth_failed',
                $result->get_error_message(),
                array('status' => 500)
            );
        }

        $user   = $result['user'];
        $is_new = $result['is_new'];

        // Build response (same format as userlogin + social fields)
        $response_data = $this->build_user_response($user, $provider, $is_new);

        return new WP_REST_Response($response_data, $is_new ? 201 : 200);
    }

    /**
     * Link a social account to an existing user
     *
     * Endpoint:
     *   POST /wp-json/wooapp/v1/social-link
     *
     * Body:
     * {
     *   "user_id": 1,                 // required
     *   "provider": "facebook",       // required: google|facebook|apple|microsoft
     *   "token": "EAAGm0PX4ZC..."     // required
     * }
     */
    public function social_link(WP_REST_Request $request)
    {
        $params = $request->get_json_params();

        $user_id  = isset($params['user_id']) ? (int) $params['user_id'] : 0;
        $provider = isset($params['provider']) ? sanitize_key($params['provider']) : '';
        $token    = isset($params['token']) ? $params['token'] : '';

        if (!$user_id || empty($provider) || empty($token)) {
            return new WP_Error(
                'missing_fields',
                __('user_id, provider, and token are required', 'wooapp-setting-tools'),
                array('status' => 400)
            );
        }

        if (!in_array($provider, Constants::SOCIAL_PROVIDERS, true)) {
            return new WP_Error(
                'invalid_provider',
                __('Unsupported provider. Allowed: google, facebook, apple, microsoft, wechat', 'wooapp-setting-tools'),
                array('status' => 400)
            );
        }

        $user = get_user_by('id', $user_id);
        if (!$user) {
            return new WP_Error(
                'user_not_found',
                __('User not found', 'wooapp-setting-tools'),
                array('status' => 404)
            );
        }

        // Verify token
        $social_data = SocialAuthManager::verify_token($provider, $token);
        if (is_wp_error($social_data)) {
            return $social_data;
        }

        // Check if this social account is already linked to another user
        $existing_user = SocialAuthManager::find_user_by_social_id($provider, $social_data['id']);
        if ($existing_user && $existing_user->ID !== $user_id) {
            return new WP_Error(
                'social_account_taken',
                __('This social account is already linked to another user', 'wooapp-setting-tools'),
                array('status' => 409)
            );
        }

        // Link
        SocialAuthManager::link_social_account($user_id, $provider, $social_data);

        return new WP_REST_Response(array(
            'success'         => true,
            'message'         => sprintf(
                __('%s account linked successfully', 'wooapp-setting-tools'),
                ucfirst($provider)
            ),
            'social_accounts' => SocialAuthManager::get_linked_accounts($user_id),
        ), 200);
    }

    /**
     * Get linked social accounts for a user
     *
     * Endpoint:
     *   GET /wp-json/wooapp/v1/social-accounts?user_id=1
     */
    public function get_social_accounts(WP_REST_Request $request)
    {
        $user_id = (int) $request->get_param('user_id');

        if (!get_user_by('id', $user_id)) {
            return new WP_Error(
                'user_not_found',
                __('User not found', 'wooapp-setting-tools'),
                array('status' => 404)
            );
        }

        $accounts = SocialAuthManager::get_linked_accounts($user_id);

        return new WP_REST_Response(array(
            'success' => true,
            'data'    => $accounts,
            'total'   => count($accounts),
        ), 200);
    }

    /**
     * Unlink a social account
     *
     * Endpoint:
     *   DELETE /wp-json/wooapp/v1/social-accounts/{provider}?user_id=1
     */
    public function unlink_social_account(WP_REST_Request $request)
    {
        $provider = sanitize_key($request->get_param('provider'));
        $user_id  = (int) $request->get_param('user_id');

        if (!in_array($provider, Constants::SOCIAL_PROVIDERS, true)) {
            return new WP_Error(
                'invalid_provider',
                __('Unsupported provider. Allowed: google, facebook, apple, microsoft, wechat', 'wooapp-setting-tools'),
                array('status' => 400)
            );
        }

        if (!get_user_by('id', $user_id)) {
            return new WP_Error(
                'user_not_found',
                __('User not found', 'wooapp-setting-tools'),
                array('status' => 404)
            );
        }

        // Check the account is actually linked
        $meta_key  = Constants::SOCIAL_META_PREFIX . $provider . '_id';
        $social_id = get_user_meta($user_id, $meta_key, true);
        if (empty($social_id)) {
            return new WP_Error(
                'not_linked',
                sprintf(__('%s account is not linked', 'wooapp-setting-tools'), ucfirst($provider)),
                array('status' => 404)
            );
        }

        SocialAuthManager::unlink_social_account($user_id, $provider);

        return new WP_REST_Response(array(
            'success'         => true,
            'message'         => sprintf(
                __('%s account unlinked successfully', 'wooapp-setting-tools'),
                ucfirst($provider)
            ),
            'social_accounts' => SocialAuthManager::get_linked_accounts($user_id),
        ), 200);
    }

    // ============================================
    // Response Builder
    // ============================================

    /**
     * Build a standard user profile response (consistent with userlogin format).
     *
     * @param \WP_User $user        WordPress user object
     * @param string   $auth_method Authentication method used
     * @param bool     $is_new      Whether the account was just created
     * @return array
     */
    private function build_user_response($user, $auth_method, $is_new)
    {
        $addresses = get_user_meta($user->ID, Constants::META_USER_ADDRESSES, true);
        if (!is_array($addresses)) {
            $addresses = array();
        }

        return array(
            'id'              => $user->ID,
            'username'        => $user->user_login,
            'email'           => $user->user_email,
            'display_name'    => $user->display_name,
            'nickname'        => $user->nickname,
            'first_name'      => get_user_meta($user->ID, 'first_name', true),
            'last_name'       => get_user_meta($user->ID, 'last_name', true),
            'phone'           => get_user_meta($user->ID, 'phone', true),
            'addresses'       => array_values($addresses),
            'social_accounts' => SocialAuthManager::get_linked_accounts($user->ID),
            'auth_method'     => $auth_method,
            'is_new_user'     => $is_new,
        );
    }
}
