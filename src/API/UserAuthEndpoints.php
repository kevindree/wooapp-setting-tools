<?php

/**
 * @package WooApp\API
 * User authentication endpoints for registration and login
 */

namespace WooApp\API;

use WP_REST_Request;
use WP_REST_Response;
use WP_User_Query;
use WP_Error;
use WooApp\Common\Constants;

defined('ABSPATH') || exit;

class UserAuthEndpoints
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
     * Register user authentication routes
     */
    public function register_routes()
    {
        // Register user login route
        register_rest_route(
            Constants::REST_NAMESPACE,
            'userlogin',
            array(
                'methods'             => 'POST',
                'callback'            => array($this, 'user_login'),
                'permission_callback' => array($this, 'check_api_permission'),
            )
        );

        // Register user registration route
        register_rest_route(
            Constants::REST_NAMESPACE,
            'register',
            array(
                'methods'             => 'POST',
                'callback'            => array($this, 'customer_register'),
                'permission_callback' => array($this, 'check_api_permission'),
            )
        );
    }

    /**
     * API permission check
     */
    public function check_api_permission(WP_REST_Request $request)
    {
        return $this->auth->check_api_permission($request);
    }

    /**
     * User Login API
     *
     * Endpoint:
     *   POST /wp-json/wooapp/v1/userlogin
     *
     * Body example：
     * {
     *   "username": "kevin",
     *   "password": "Passw0rd!"
     * }
     */
    public function user_login(WP_REST_Request $request)
    {
        $params = $request->get_json_params();

        $username = isset($params['username']) ? sanitize_user($params['username']) : '';
        $password = isset($params['password']) ? $params['password'] : '';

        // Validate input parameters to ensure they are not empty
        if (!$username || !$password) {
            return new WP_Error(
                'invalid_credentials',
                __('username or password is missing', 'woocommerce'),
                array('status' => 400)
            );
        }

        // Attempt to authenticate the user
        $user = wp_authenticate($username, $password);

        // Check if authentication was successful
        if (is_wp_error($user)) {
            return new WP_Error(
                'invalid_credentials',
                __('invalid username or password', 'woocommerce'),
                array('status' => 403)
            );
        }

        // Authentication successful, return user info
        $userInfo = array(
            'id'           => $user->ID,
            'username'     => $user->user_login,
            'email'        => $user->user_email,
            'display_name' => $user->display_name,
            'nickname'     => $user->nickname,
            'first_name'   => get_user_meta($user->ID, 'first_name', true),
            'last_name'    => get_user_meta($user->ID, 'last_name', true),
            'phone'        => get_user_meta($user->ID, 'phone', true),
        );

        return new WP_REST_Response($userInfo, 200);
    }

    /**
     * Customer Register API
     *
     * Endpoint:
     *   POST /wp-json/wooapp/v1/register
     *
     * Body example：
     * {
     *   "username": "kevin",           // required, must be unique
     *   "email": "kevin@example.com",  // required, must be unique
     *   "password": "Passw0rd!",       // required
     *   "phone": "1234567890",         // required, must be unique
     *   "display_name": "Kevin Zhu",
     *   "first_name": "Kevin",
     *   "last_name": "Zhu",
     *   "billing": {
     *     "phone": "12345678",
     *     "country": "AE",
     *     "city": "Dubai",
     *     "address_1": "Some street 1"
     *   }
     * }
     */
    public function customer_register(WP_REST_Request $request)
    {
        // Get parameters from JSON Body
        $params = $request->get_json_params();

        $username     = isset($params['username']) ? sanitize_user($params['username']) : '';
        $email        = isset($params['email']) ? sanitize_email($params['email']) : '';
        $password     = isset($params['password']) ? $params['password'] : '';
        $phone        = isset($params['phone']) ? sanitize_text_field($params['phone']) : '';
        $display_name = isset($params['display_name']) ? sanitize_text_field($params['display_name']) : '';
        $first_name   = isset($params['first_name']) ? sanitize_text_field($params['first_name']) : '';
        $last_name    = isset($params['last_name']) ? sanitize_text_field($params['last_name']) : '';
        $billing      = isset($params['billing']) && is_array($params['billing'])
                        ? $params['billing'] : array();

        // 1) Basic validation: required fields
        if (empty($username) || empty($email) || empty($password) || empty($phone)) {
            return new WP_Error(
                'missing_fields',
                __('username, email, password and phone are required', 'woocommerce'),
                array('status' => 400)
            );
        }

        if (!is_email($email)) {
            return new WP_Error(
                'invalid_email',
                __('email format is invalid', 'woocommerce'),
                array('status' => 400)
            );
        }

        // 2) Check if username/email/phone already exists
        if (username_exists($username)) {
            return new WP_Error(
                'username_exists',
                __('username already exists', 'woocommerce'),
                array('status' => 409)
            );
        }

        if (email_exists($email)) {
            return new WP_Error(
                'email_exists',
                __('email already exists', 'woocommerce'),
                array('status' => 409)
            );
        }

        // Check if phone already exists
        $existing_phone_user = new WP_User_Query(array(
            'meta_key'   => 'phone',
            'meta_value' => $phone,
            'fields'     => 'ID',
        ));
        if (!empty($existing_phone_user->results)) {
            return new WP_Error(
                'phone_exists',
                __('phone already exists', 'woocommerce'),
                array('status' => 409)
            );
        }

        // 3) Create user (prioritize WooCommerce utility methods)
        if (function_exists('wc_create_new_customer')) {
            // WooCommerce enabled: automatically sets role=customer
            $user_id = wc_create_new_customer($email, $username, $password);
        } else {
            // Fallback: use WordPress to create user and set role to customer
            $user_id = wp_create_user($username, $password, $email);
            if (!is_wp_error($user_id)) {
                $user = new \WP_User($user_id);
                $user->set_role('customer');
            }
        }

        // Check creation result, return 500 with error message if failed
        if (is_wp_error($user_id)) {
            return new WP_Error(
                'create_failed',
                $user_id->get_error_message(),
                array('status' => 500)
            );
        }

        // 4) Write basic profile information
        if ($phone) {
            update_user_meta($user_id, 'phone', $phone);
        }
        if ($first_name) {
            update_user_meta($user_id, 'first_name', $first_name);
        }
        if ($last_name) {
            update_user_meta($user_id, 'last_name', $last_name);
        }

        // 4.1) Update display_name (decided by client)
        if ($display_name) {
            wp_update_user(array(
                'ID'           => $user_id,
                'display_name' => $display_name,
            ));
        }

        // 5) Write WooCommerce billing information (if provided)
        if (!empty($billing)) {
            $billing_map = array(
                'first_name' => 'billing_first_name',
                'last_name'  => 'billing_last_name',
                'company'    => 'billing_company',
                'address_1'  => 'billing_address_1',
                'address_2'  => 'billing_address_2',
                'city'       => 'billing_city',
                'state'      => 'billing_state',
                'postcode'   => 'billing_postcode',
                'country'    => 'billing_country',
                'email'      => 'billing_email',
                'phone'      => 'billing_phone',
            );

            foreach ($billing_map as $field => $meta_key) {
                if (isset($billing[$field])) {
                    update_user_meta(
                        $user_id,
                        $meta_key,
                        sanitize_text_field($billing[$field])
                    );
                }
            }
        }

        // 6) Assemble return data
        $user = get_user_by('id', $user_id);

        $data = array(
            'id'           => $user_id,
            'username'     => $user->user_login,
            'email'        => $user->user_email,
            'display_name' => $user->display_name,
            'nickname'     => $user->nickname,
            'first_name'   => get_user_meta($user->ID, 'first_name', true),
            'last_name'    => get_user_meta($user->ID, 'last_name', true),
            'phone'        => get_user_meta($user->ID, 'phone', true),
        );

        return new WP_REST_Response($data, 201); // 201 Created
    }
}
