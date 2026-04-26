<?php

/**
 * @package WooApp\Services
 */

namespace WooApp\Services;

use WooApp\Common\Constants;
use WP_Error;

defined('ABSPATH') || exit;

/**
 * Social Authentication Manager
 * Handles token verification, user lookup/creation, and social account linking
 * for Google, Facebook, Apple, Microsoft, and WeChat providers.
 */
class SocialAuthManager
{
    /**
     * Provider API URLs
     */
    const GOOGLE_TOKENINFO_URL = 'https://oauth2.googleapis.com/tokeninfo';
    const FACEBOOK_GRAPH_URL   = 'https://graph.facebook.com/v19.0/me';
    const APPLE_JWKS_URL       = 'https://appleid.apple.com/auth/keys';
    const APPLE_ISSUER         = 'https://appleid.apple.com';
    const MICROSOFT_GRAPH_URL  = 'https://graph.microsoft.com/v1.0/me';
    const WECHAT_SNS_USERINFO_URL = 'https://api.weixin.qq.com/sns/userinfo';

    /**
     * Transient key for caching Apple JWKS
     */
    const APPLE_JWKS_TRANSIENT = 'wooapp_apple_jwks';

    // ============================================
    // Token Verification
    // ============================================

    /**
     * Verify a social provider token and return normalized user data.
     *
     * @param string $provider Provider name (google|facebook|apple|microsoft|wechat)
     * @param string $token    Provider token (ID token or access token)
     * @return array|WP_Error  Normalized user data array or WP_Error
     */
    public static function verify_token($provider, $token)
    {
        switch ($provider) {
            case 'google':
                return self::verify_google_token($token);
            case 'facebook':
                return self::verify_facebook_token($token);
            case 'apple':
                return self::verify_apple_token($token);
            case 'microsoft':
                return self::verify_microsoft_token($token);
            case 'wechat':
                return self::verify_wechat_token($token);
            default:
                return new WP_Error(
                    'invalid_provider',
                    __('Unsupported social auth provider', 'wooapp-setting-tools'),
                    array('status' => 400)
                );
        }
    }

    /**
     * Verify Google ID token via tokeninfo endpoint.
     *
     * @param string $id_token Google ID token (JWT)
     * @return array|WP_Error
     */
    private static function verify_google_token($id_token)
    {
        $response = wp_remote_get(
            self::GOOGLE_TOKENINFO_URL . '?id_token=' . urlencode($id_token),
            array('timeout' => 15)
        );

        if (is_wp_error($response)) {
            return new WP_Error(
                'verification_failed',
                __('Failed to verify Google token', 'wooapp-setting-tools'),
                array('status' => 502)
            );
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($status_code !== 200 || empty($body['sub'])) {
            return new WP_Error(
                'invalid_token',
                __('Invalid Google token', 'wooapp-setting-tools'),
                array('status' => 401)
            );
        }

        return array(
            'id'             => $body['sub'],
            'email'          => isset($body['email']) ? $body['email'] : '',
            'name'           => isset($body['name']) ? $body['name'] : '',
            'first_name'     => isset($body['given_name']) ? $body['given_name'] : '',
            'last_name'      => isset($body['family_name']) ? $body['family_name'] : '',
            'picture'        => isset($body['picture']) ? $body['picture'] : '',
            'email_verified' => isset($body['email_verified']) && $body['email_verified'] === 'true',
        );
    }

    /**
     * Verify Facebook access token via Graph API.
     *
     * @param string $access_token Facebook access token
     * @return array|WP_Error
     */
    private static function verify_facebook_token($access_token)
    {
        $url = add_query_arg(
            array(
                'access_token' => $access_token,
                'fields'       => 'id,email,name,first_name,last_name,picture.type(large)',
            ),
            self::FACEBOOK_GRAPH_URL
        );

        $response = wp_remote_get($url, array('timeout' => 15));

        if (is_wp_error($response)) {
            return new WP_Error(
                'verification_failed',
                __('Failed to verify Facebook token', 'wooapp-setting-tools'),
                array('status' => 502)
            );
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($status_code !== 200 || empty($body['id'])) {
            return new WP_Error(
                'invalid_token',
                __('Invalid Facebook token', 'wooapp-setting-tools'),
                array('status' => 401)
            );
        }

        $picture = '';
        if (isset($body['picture']['data']['url'])) {
            $picture = $body['picture']['data']['url'];
        }

        return array(
            'id'             => $body['id'],
            'email'          => isset($body['email']) ? $body['email'] : '',
            'name'           => isset($body['name']) ? $body['name'] : '',
            'first_name'     => isset($body['first_name']) ? $body['first_name'] : '',
            'last_name'      => isset($body['last_name']) ? $body['last_name'] : '',
            'picture'        => $picture,
            'email_verified' => true,
        );
    }

    /**
     * Verify Apple ID token (JWT) using Apple's JWKS public keys.
     *
     * @param string $id_token Apple ID token (JWT)
     * @return array|WP_Error
     */
    private static function verify_apple_token($id_token)
    {
        // Split JWT into parts
        $parts = explode('.', $id_token);
        if (count($parts) !== 3) {
            return new WP_Error(
                'invalid_token',
                __('Invalid Apple token format', 'wooapp-setting-tools'),
                array('status' => 401)
            );
        }

        $header  = json_decode(self::base64url_decode($parts[0]), true);
        $payload = json_decode(self::base64url_decode($parts[1]), true);

        if (!$header || !$payload) {
            return new WP_Error(
                'invalid_token',
                __('Unable to decode Apple token', 'wooapp-setting-tools'),
                array('status' => 401)
            );
        }

        $kid = isset($header['kid']) ? $header['kid'] : '';
        $alg = isset($header['alg']) ? $header['alg'] : '';

        if (empty($kid) || empty($alg)) {
            return new WP_Error(
                'invalid_token',
                __('Missing kid or alg in Apple token header', 'wooapp-setting-tools'),
                array('status' => 401)
            );
        }

        // Fetch Apple's public keys (cached via transient)
        $jwks = self::get_apple_jwks();
        if (is_wp_error($jwks)) {
            return $jwks;
        }

        // Find the matching key by kid
        $public_key_jwk = null;
        foreach ($jwks['keys'] as $key) {
            if (isset($key['kid']) && $key['kid'] === $kid) {
                $public_key_jwk = $key;
                break;
            }
        }

        if (!$public_key_jwk) {
            // Key might have rotated — clear cache and retry once
            delete_transient(self::APPLE_JWKS_TRANSIENT);
            $jwks = self::get_apple_jwks();
            if (is_wp_error($jwks)) {
                return $jwks;
            }
            foreach ($jwks['keys'] as $key) {
                if (isset($key['kid']) && $key['kid'] === $kid) {
                    $public_key_jwk = $key;
                    break;
                }
            }
        }

        if (!$public_key_jwk) {
            return new WP_Error(
                'invalid_token',
                __('Unable to find matching Apple public key', 'wooapp-setting-tools'),
                array('status' => 401)
            );
        }

        // Convert JWK to PEM
        $pem = self::jwk_to_pem($public_key_jwk);
        if (!$pem) {
            return new WP_Error(
                'verification_failed',
                __('Failed to construct Apple public key', 'wooapp-setting-tools'),
                array('status' => 500)
            );
        }

        // Verify JWT signature
        $data      = $parts[0] . '.' . $parts[1];
        $signature = self::base64url_decode($parts[2]);

        $key_resource = openssl_pkey_get_public($pem);
        if (!$key_resource) {
            return new WP_Error(
                'verification_failed',
                __('Failed to load Apple public key', 'wooapp-setting-tools'),
                array('status' => 500)
            );
        }

        $alg_map = array(
            'RS256' => OPENSSL_ALGO_SHA256,
            'RS384' => OPENSSL_ALGO_SHA384,
            'RS512' => OPENSSL_ALGO_SHA512,
        );
        $openssl_alg = isset($alg_map[$alg]) ? $alg_map[$alg] : OPENSSL_ALGO_SHA256;
        $verified    = openssl_verify($data, $signature, $key_resource, $openssl_alg);

        if ($verified !== 1) {
            return new WP_Error(
                'invalid_token',
                __('Apple token signature verification failed', 'wooapp-setting-tools'),
                array('status' => 401)
            );
        }

        // Validate issuer
        if (!isset($payload['iss']) || $payload['iss'] !== self::APPLE_ISSUER) {
            return new WP_Error(
                'invalid_token',
                __('Invalid Apple token issuer', 'wooapp-setting-tools'),
                array('status' => 401)
            );
        }

        // Validate expiry
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return new WP_Error(
                'invalid_token',
                __('Apple token has expired', 'wooapp-setting-tools'),
                array('status' => 401)
            );
        }

        // Validate subject
        if (empty($payload['sub'])) {
            return new WP_Error(
                'invalid_token',
                __('Missing subject in Apple token', 'wooapp-setting-tools'),
                array('status' => 401)
            );
        }

        return array(
            'id'             => $payload['sub'],
            'email'          => isset($payload['email']) ? $payload['email'] : '',
            'name'           => '',
            'first_name'     => '',
            'last_name'      => '',
            'picture'        => '',
            'email_verified' => isset($payload['email_verified']) ? (bool) $payload['email_verified'] : false,
        );
    }

    /**
     * Verify Microsoft access token via Graph API.
     *
     * @param string $access_token Microsoft access token
     * @return array|WP_Error
     */
    private static function verify_microsoft_token($access_token)
    {
        $response = wp_remote_get(self::MICROSOFT_GRAPH_URL, array(
            'timeout' => 15,
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
            ),
        ));

        if (is_wp_error($response)) {
            return new WP_Error(
                'verification_failed',
                __('Failed to verify Microsoft token', 'wooapp-setting-tools'),
                array('status' => 502)
            );
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($status_code !== 200 || empty($body['id'])) {
            return new WP_Error(
                'invalid_token',
                __('Invalid Microsoft token', 'wooapp-setting-tools'),
                array('status' => 401)
            );
        }

        $email = '';
        if (!empty($body['mail'])) {
            $email = $body['mail'];
        } elseif (!empty($body['userPrincipalName']) && strpos($body['userPrincipalName'], '@') !== false) {
            $email = $body['userPrincipalName'];
        }

        return array(
            'id'             => $body['id'],
            'email'          => $email,
            'name'           => isset($body['displayName']) ? $body['displayName'] : '',
            'first_name'     => isset($body['givenName']) ? $body['givenName'] : '',
            'last_name'      => isset($body['surname']) ? $body['surname'] : '',
            'picture'        => '',
            'email_verified' => true,
        );
    }

    /**
     * Verify WeChat access token via SNS userinfo endpoint.
     *
     * The client sends a JSON-encoded string: {"access_token":"...","openid":"..."}
     * Both fields come from WeChat's OAuth2 token exchange.
     *
     * @param string $token JSON string with access_token and openid
     * @return array|WP_Error
     */
    private static function verify_wechat_token($token)
    {
        $credentials = json_decode($token, true);

        if (empty($credentials['access_token']) || empty($credentials['openid'])) {
            return new WP_Error(
                'invalid_token',
                __('WeChat token must be JSON with access_token and openid', 'wooapp-setting-tools'),
                array('status' => 401)
            );
        }

        $url = add_query_arg(
            array(
                'access_token' => $credentials['access_token'],
                'openid'       => $credentials['openid'],
                'lang'         => 'en',
            ),
            self::WECHAT_SNS_USERINFO_URL
        );

        $response = wp_remote_get($url, array('timeout' => 15));

        if (is_wp_error($response)) {
            return new WP_Error(
                'verification_failed',
                __('Failed to verify WeChat token', 'wooapp-setting-tools'),
                array('status' => 502)
            );
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!empty($body['errcode'])) {
            return new WP_Error(
                'invalid_token',
                __('Invalid WeChat token', 'wooapp-setting-tools'),
                array('status' => 401)
            );
        }

        if (empty($body['openid'])) {
            return new WP_Error(
                'invalid_token',
                __('Invalid WeChat token response', 'wooapp-setting-tools'),
                array('status' => 401)
            );
        }

        // Use unionid if available (cross-app identifier), else openid
        $id = !empty($body['unionid']) ? $body['unionid'] : $body['openid'];

        return array(
            'id'             => $id,
            'email'          => '',
            'name'           => isset($body['nickname']) ? $body['nickname'] : '',
            'first_name'     => '',
            'last_name'      => '',
            'picture'        => isset($body['headimgurl']) ? $body['headimgurl'] : '',
            'email_verified' => false,
        );
    }

    // ============================================
    // User Lookup and Account Linking
    // ============================================

    /**
     * Find a WordPress user by social provider ID.
     *
     * @param string $provider  Provider name
     * @param string $social_id Provider-specific user ID
     * @return \WP_User|null
     */
    public static function find_user_by_social_id($provider, $social_id)
    {
        $meta_key = Constants::SOCIAL_META_PREFIX . sanitize_key($provider) . '_id';

        $users = get_users(array(
            'meta_key'   => $meta_key,
            'meta_value' => $social_id,
            'number'     => 1,
        ));

        return !empty($users) ? $users[0] : null;
    }

    /**
     * Link a social account to a WordPress user.
     *
     * @param int    $user_id     WordPress user ID
     * @param string $provider    Provider name
     * @param array  $social_data Normalized social user data
     */
    public static function link_social_account($user_id, $provider, $social_data)
    {
        $provider = sanitize_key($provider);
        $prefix   = Constants::SOCIAL_META_PREFIX . $provider;

        update_user_meta($user_id, $prefix . '_id', sanitize_text_field($social_data['id']));
        update_user_meta($user_id, $prefix . '_email', sanitize_email($social_data['email']));
        update_user_meta($user_id, $prefix . '_linked_at', current_time('mysql'));

        if (!empty($social_data['picture'])) {
            update_user_meta($user_id, $prefix . '_picture', esc_url_raw($social_data['picture']));
        }
    }

    /**
     * Unlink a social account from a WordPress user.
     *
     * @param int    $user_id  WordPress user ID
     * @param string $provider Provider name
     */
    public static function unlink_social_account($user_id, $provider)
    {
        $provider = sanitize_key($provider);
        $prefix   = Constants::SOCIAL_META_PREFIX . $provider;

        delete_user_meta($user_id, $prefix . '_id');
        delete_user_meta($user_id, $prefix . '_email');
        delete_user_meta($user_id, $prefix . '_linked_at');
        delete_user_meta($user_id, $prefix . '_picture');
    }

    /**
     * Get all linked social accounts for a WordPress user.
     *
     * @param int $user_id WordPress user ID
     * @return array Array of linked account info
     */
    public static function get_linked_accounts($user_id)
    {
        $accounts = array();

        foreach (Constants::SOCIAL_PROVIDERS as $provider) {
            $prefix    = Constants::SOCIAL_META_PREFIX . $provider;
            $social_id = get_user_meta($user_id, $prefix . '_id', true);

            if (!empty($social_id)) {
                $accounts[] = array(
                    'provider'  => $provider,
                    'social_id' => $social_id,
                    'email'     => get_user_meta($user_id, $prefix . '_email', true),
                    'picture'   => get_user_meta($user_id, $prefix . '_picture', true),
                    'linked_at' => get_user_meta($user_id, $prefix . '_linked_at', true),
                );
            }
        }

        return $accounts;
    }

    /**
     * Find an existing WordPress user or create a new one based on social auth data.
     *
     * Lookup order:
     *  1. Match by provider + social_id (returning user via same provider)
     *  2. Match by email (link social account to existing WP user)
     *  3. Create brand-new user
     *
     * @param string $provider    Provider name
     * @param array  $social_data Normalized social user data
     * @param array  $extra       Optional extra fields from client (first_name, last_name for Apple)
     * @return array|WP_Error     { 'user' => WP_User, 'is_new' => bool } or WP_Error
     */
    public static function find_or_create_user($provider, $social_data, $extra = array())
    {
        // Merge client-supplied name fields (used primarily for Apple)
        if (!empty($extra['first_name']) && empty($social_data['first_name'])) {
            $social_data['first_name'] = $extra['first_name'];
        }
        if (!empty($extra['last_name']) && empty($social_data['last_name'])) {
            $social_data['last_name'] = $extra['last_name'];
        }
        if (empty($social_data['name']) && !empty($social_data['first_name'])) {
            $social_data['name'] = trim($social_data['first_name'] . ' ' . $social_data['last_name']);
        }

        // 1. Look up by social ID
        $user = self::find_user_by_social_id($provider, $social_data['id']);
        if ($user) {
            // Update picture if changed
            if (!empty($social_data['picture'])) {
                $prefix = Constants::SOCIAL_META_PREFIX . sanitize_key($provider);
                update_user_meta($user->ID, $prefix . '_picture', esc_url_raw($social_data['picture']));
            }
            return array('user' => $user, 'is_new' => false);
        }

        // 2. Look up by email
        if (!empty($social_data['email'])) {
            $user = get_user_by('email', $social_data['email']);
            if ($user) {
                self::link_social_account($user->ID, $provider, $social_data);
                return array('user' => $user, 'is_new' => false);
            }
        }

        // 3. Create new user
        $email = !empty($social_data['email']) ? sanitize_email($social_data['email']) : '';
        if (empty($email)) {
            $email = sanitize_key($provider) . '_' . $social_data['id'] . '@social.noreply';
        }

        $username = self::generate_unique_username($social_data, $provider);
        $password = wp_generate_password(24, true, true);

        if (function_exists('wc_create_new_customer')) {
            $user_id = wc_create_new_customer($email, $username, $password);
        } else {
            $user_id = wp_create_user($username, $password, $email);
            if (!is_wp_error($user_id)) {
                $wp_user = new \WP_User($user_id);
                $wp_user->set_role('customer');
            }
        }

        if (is_wp_error($user_id)) {
            return $user_id;
        }

        // Set profile fields
        $update_data = array('ID' => $user_id);
        if (!empty($social_data['name'])) {
            $update_data['display_name'] = sanitize_text_field($social_data['name']);
        }
        if (count($update_data) > 1) {
            wp_update_user($update_data);
        }
        if (!empty($social_data['first_name'])) {
            update_user_meta($user_id, 'first_name', sanitize_text_field($social_data['first_name']));
        }
        if (!empty($social_data['last_name'])) {
            update_user_meta($user_id, 'last_name', sanitize_text_field($social_data['last_name']));
        }

        // Link social account
        self::link_social_account($user_id, $provider, $social_data);

        $user = get_user_by('id', $user_id);
        return array('user' => $user, 'is_new' => true);
    }

    // ============================================
    // JWT / Crypto Helpers
    // ============================================

    /**
     * Fetch Apple's JWKS (cached in transient for 24 hours).
     *
     * @return array|WP_Error
     */
    private static function get_apple_jwks()
    {
        $cached = get_transient(self::APPLE_JWKS_TRANSIENT);
        if ($cached) {
            return $cached;
        }

        $response = wp_remote_get(self::APPLE_JWKS_URL, array('timeout' => 15));
        if (is_wp_error($response)) {
            return new WP_Error(
                'verification_failed',
                __('Failed to fetch Apple public keys', 'wooapp-setting-tools'),
                array('status' => 502)
            );
        }

        $jwks = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($jwks['keys'])) {
            return new WP_Error(
                'verification_failed',
                __('Invalid Apple JWKS response', 'wooapp-setting-tools'),
                array('status' => 502)
            );
        }

        set_transient(self::APPLE_JWKS_TRANSIENT, $jwks, DAY_IN_SECONDS);
        return $jwks;
    }

    /**
     * Base64url decode (RFC 7515).
     *
     * @param string $data
     * @return string
     */
    private static function base64url_decode($data)
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }

    /**
     * Convert a JWK (RSA) to PEM format.
     *
     * @param array $jwk JWK key data with 'n' and 'e' components
     * @return string|false PEM string or false on failure
     */
    private static function jwk_to_pem($jwk)
    {
        if (!isset($jwk['n']) || !isset($jwk['e'])) {
            return false;
        }

        $modulus  = self::base64url_decode($jwk['n']);
        $exponent = self::base64url_decode($jwk['e']);

        // Add leading zero byte if high bit is set (unsigned integer encoding)
        if (ord($modulus[0]) > 0x7f) {
            $modulus = "\x00" . $modulus;
        }
        if (ord($exponent[0]) > 0x7f) {
            $exponent = "\x00" . $exponent;
        }

        // SEQUENCE { INTEGER modulus, INTEGER exponent }
        $modulus_der  = self::der_encode_integer($modulus);
        $exponent_der = self::der_encode_integer($exponent);
        $rsa_sequence = self::der_encode_sequence($modulus_der . $exponent_der);

        // BIT STRING wrapping
        $bit_string     = "\x00" . $rsa_sequence;
        $bit_string_der = "\x03" . self::der_encode_length(strlen($bit_string)) . $bit_string;

        // Algorithm identifier: rsaEncryption OID (1.2.840.113549.1.1.1)
        $algorithm_oid      = "\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01";
        $algorithm_null     = "\x05\x00";
        $algorithm_sequence = self::der_encode_sequence($algorithm_oid . $algorithm_null);

        // SubjectPublicKeyInfo SEQUENCE
        $public_key_info = self::der_encode_sequence($algorithm_sequence . $bit_string_der);

        $pem  = "-----BEGIN PUBLIC KEY-----\n";
        $pem .= chunk_split(base64_encode($public_key_info), 64, "\n");
        $pem .= "-----END PUBLIC KEY-----";

        return $pem;
    }

    private static function der_encode_length($length)
    {
        if ($length < 0x80) {
            return chr($length);
        }
        $temp = ltrim(pack('N', $length), "\x00");
        return chr(0x80 | strlen($temp)) . $temp;
    }

    private static function der_encode_integer($data)
    {
        return "\x02" . self::der_encode_length(strlen($data)) . $data;
    }

    private static function der_encode_sequence($data)
    {
        return "\x30" . self::der_encode_length(strlen($data)) . $data;
    }

    // ============================================
    // Username Generation
    // ============================================

    /**
     * Generate a unique WordPress username from social data.
     *
     * @param array  $social_data Normalized social user data
     * @param string $provider    Provider name (fallback prefix)
     * @return string Unique username
     */
    private static function generate_unique_username($social_data, $provider)
    {
        $base = '';

        if (!empty($social_data['first_name'])) {
            $base = sanitize_user($social_data['first_name'], true);
            if (!empty($social_data['last_name'])) {
                $base .= '.' . sanitize_user($social_data['last_name'], true);
            }
        } elseif (!empty($social_data['name'])) {
            $base = sanitize_user(str_replace(' ', '.', $social_data['name']), true);
        } elseif (!empty($social_data['email'])) {
            $parts = explode('@', $social_data['email']);
            $base  = sanitize_user($parts[0], true);
        } else {
            $base = $provider . '_user';
        }

        $base = strtolower($base);

        if (empty($base)) {
            $base = $provider . '_user';
        }

        if (!username_exists($base)) {
            return $base;
        }

        $i = 1;
        while (username_exists($base . $i)) {
            $i++;
            if ($i > 999) {
                return $provider . '_' . wp_generate_password(8, false);
            }
        }

        return $base . $i;
    }
}
