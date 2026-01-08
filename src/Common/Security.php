<?php

/**
 * @package WooApp\Common
 */

namespace WooApp\Common;

defined('ABSPATH') || exit;

/**
 * Security Manager
 * Handles all security-related operations for WordPress 6.9+ and WooCommerce 10.3+
 */
class Security
{
    /**
     * Verify nonce with enhanced security for WordPress 6.9+
     * @param string $nonce The nonce value
     * @param string $action The nonce action
     * @return bool
     */
    public static function verifyNonce($nonce, $action)
    {
        // Use wp_verify_nonce with proper return value handling
        $verification = wp_verify_nonce($nonce, $action);
        
        // WordPress 6.9+ requires explicit true check
        return $verification === 1 || $verification === 2;
    }

    /**
     * Create a secure REST API response
     * @param mixed $data
     * @param int $status_code HTTP status code
     * @return \WP_REST_Response
     */
    public static function restResponse($data, $status_code = 200)
    {
        $response = new \WP_REST_Response($data, $status_code);
        
        // Set proper headers for WordPress 6.9+
        $response->set_headers(array(
            'Content-Type' => 'application/json; charset=utf-8',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
        ));

        return $response;
    }

    /**
     * Create a secure REST error response
     * @param string $code Error code
     * @param string $message Error message
     * @param int $status_code HTTP status code
     * @return \WP_Error|\WP_REST_Response
     */
    public static function restError($code, $message, $status_code = 400)
    {
        $error = new \WP_Error($code, $message);
        return self::restResponse(
            array('code' => $code, 'message' => $message),
            $status_code
        );
    }

    /**
     * Sanitize and validate user input
     * @param string $input Raw user input
     * @param string $type Type of input (text, email, url, number)
     * @return string|bool Sanitized input or false if invalid
     */
    public static function sanitizeInput($input, $type = 'text')
    {
        switch ($type) {
            case 'email':
                $sanitized = sanitize_email($input);
                return is_email($sanitized) ? $sanitized : false;

            case 'url':
                $sanitized = esc_url_raw($input);
                return $sanitized;

            case 'number':
                return is_numeric($input) ? absint($input) : false;

            case 'text':
            default:
                return sanitize_text_field($input);
        }
    }

    /**
     * Check user capability with fallback for WordPress 6.9+
     * @param string $capability Capability to check
     * @param int $user_id User ID (optional, defaults to current user)
     * @return bool
     */
    public static function userCanDo($capability, $user_id = 0)
    {
        $user_id = $user_id ?: get_current_user_id();
        
        if (!$user_id) {
            return false;
        }

        return user_can($user_id, $capability);
    }

    /**
     * Escape data for safe output in HTML
     * @param mixed $data Data to escape
     * @param string $context Context (html, attr, url, js)
     * @return string
     */
    public static function escapeOutput($data, $context = 'html')
    {
        switch ($context) {
            case 'attr':
                return esc_attr($data);
            case 'url':
                return esc_url($data);
            case 'js':
                return wp_json_encode($data);
            case 'html':
            default:
                return wp_kses_post($data);
        }
    }

    /**
     * Hash password using WooCommerce 10.3+ standards
     * @param string $password Plain text password
     * @return string Hashed password
     */
    public static function hashPassword($password)
    {
        // Use WordPress standard password hashing
        return wp_hash_password($password);
    }

    /**
     * Verify password against hash
     * @param string $password Plain text password
     * @param string $hash Password hash
     * @return bool
     */
    public static function verifyPassword($password, $hash)
    {
        return wp_check_password($password, $hash);
    }
}
