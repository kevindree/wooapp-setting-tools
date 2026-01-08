<?php

/**
 * @package WooApp\API
 * WooCommerce 兼容的 REST API 鉴权处理
 * 完全仿照 WooCommerce 的 authenticate 方法实现
 * 支持 Basic Auth（HTTPS）和 OAuth 1.0（HTTP）
 */

namespace WooApp\API;

use WP_REST_Request;
use WP_Error;

defined('ABSPATH') || exit;

class Authentication
{

    /**
     * WooCommerce 兼容的 REST API 鉴权方法
     * 完全仿照 WooCommerce 的 authenticate 方法实现
     * 支持 Basic Auth（HTTPS）和 OAuth 1.0（HTTP）
     */
    public function check_api_permission( WP_REST_Request $request ) {
        // 存储认证错误信息
        $auth_error = null;

        // 首先尝试 Basic Auth 校验
        $result = $this->perform_basic_authentication();
        if ( is_wp_error( $result ) ) {
            $auth_error = $result;
        } elseif ( $result ) {
            // Basic Auth 校验通过，检查是否是 SSL
            if ( ! is_ssl() ) {
                return new WP_Error(
                    'woocommerce_rest_authentication_error',
                    __( 'Basic authentication is only allowed over HTTPS. Use OAuth 1.0 for HTTP connections.', 'woocommerce' ),
                    array( 'status' => 401 )
                );
            }
            return true;
        }

        // Basic Auth 校验失败或无凭证，尝试 OAuth 1.0
        $result = $this->perform_oauth_authentication();
        if ( is_wp_error( $result ) ) {
            $auth_error = $result;
        } elseif ( $result ) {
            return true;
        }

        // 如果有认证错误，返回该错误；否则返回通用错误
        if ( is_wp_error( $auth_error ) ) {
            return $auth_error;
        }

        return new WP_Error(
            'woocommerce_rest_authentication_error',
            __( 'WooCommerce Authentication required.', 'woocommerce' ),
            array( 'status' => 401 )
        );
    }

    /**
     * Basic Authentication（仿照 WooCommerce）
     * 支持 GET 参数和 PHP_AUTH_USER/PHP_AUTH_PW
     * 返回: user_id（成功）、false（无凭证）、WP_Error（错误）
     */
    private function perform_basic_authentication() {
        $consumer_key    = '';
        $consumer_secret = '';

        // 如果 $_GET 参数存在，首先使用 GET 参数
        if ( ! empty( $_GET['consumer_key'] ) && ! empty( $_GET['consumer_secret'] ) ) {
            $consumer_key    = $_GET['consumer_key'];
            $consumer_secret = $_GET['consumer_secret'];
        }

        // 如果 GET 参数不存在，尝试 Basic Auth（PHP_AUTH_USER / PHP_AUTH_PW）
        if ( ! $consumer_key && ! empty( $_SERVER['PHP_AUTH_USER'] ) && ! empty( $_SERVER['PHP_AUTH_PW'] ) ) {
            $consumer_key    = $_SERVER['PHP_AUTH_USER'];
            $consumer_secret = $_SERVER['PHP_AUTH_PW'];
        }

        // 如果都没有凭证
        if ( ! $consumer_key || ! $consumer_secret ) {
            return false;
        }

        // 根据 consumer_key 获取用户数据
        $user = $this->get_user_data_by_consumer_key( $consumer_key );
        if ( empty( $user ) ) {
            return new WP_Error(
                'woocommerce_rest_authentication_error',
                __( 'Consumer key is invalid.', 'woocommerce' ),
                array( 'status' => 401 )
            );
        }

        // 验证 consumer_secret
        if ( ! hash_equals( $user->consumer_secret, $consumer_secret ) ) {
            return new WP_Error(
                'woocommerce_rest_authentication_error',
                __( 'Consumer secret is invalid.', 'woocommerce' ),
                array( 'status' => 401 )
            );
        }

        return $user->user_id;
    }

    /**
     * OAuth 1.0 Authentication（仿照 WooCommerce）
     * 用于非 SSL 请求
     * 返回: user_id（成功）、false（无凭证）、WP_Error（错误）
     */
    private function perform_oauth_authentication() {
        $params = $this->get_oauth_parameters();
        if ( empty( $params ) ) {
            return false;
        }

        // 根据 oauth_consumer_key 获取用户数据
        $user = $this->get_user_data_by_consumer_key( $params['oauth_consumer_key'] );
        if ( empty( $user ) ) {
            return new WP_Error(
                'woocommerce_rest_authentication_error',
                __( 'Consumer key is invalid.', 'woocommerce' ),
                array( 'status' => 401 )
            );
        }

        // 验证 OAuth 签名
        $signature = $this->check_oauth_signature( $user, $params );
        if ( is_wp_error( $signature ) ) {
            return $signature;
        }

        // 验证时间戳和 nonce
        $timestamp_and_nonce = $this->check_oauth_timestamp_and_nonce( $user, $params['oauth_timestamp'], $params['oauth_nonce'] );
        if ( is_wp_error( $timestamp_and_nonce ) ) {
            return $timestamp_and_nonce;
        }

        return $user->user_id;
    }

    /**
     * 获取 OAuth 参数（仿照 WooCommerce）
     */
    private function get_oauth_parameters() {
        $params = array_merge( $_GET, $_POST );
        $params = wp_unslash( $params );
        $header = $this->get_authorization_header();

        if ( ! empty( $header ) ) {
            $header        = trim( $header );
            $header_params = $this->parse_header( $header );

            if ( ! empty( $header_params ) ) {
                $params = array_merge( $params, $header_params );
            }
        }

        $param_names = array(
            'oauth_consumer_key',
            'oauth_timestamp',
            'oauth_nonce',
            'oauth_signature',
            'oauth_signature_method',
        );

        $errors   = array();
        $have_one = false;

        // 检查必需的 OAuth 参数
        foreach ( $param_names as $param_name ) {
            if ( empty( $params[ $param_name ] ) ) {
                $errors[] = $param_name;
            } else {
                $have_one = true;
            }
        }

        // 所有参数都缺失，说明没有尝试使用 OAuth
        if ( ! $have_one ) {
            return array();
        }

        // 如果有至少一个参数但有错误，返回空
        if ( ! empty( $errors ) ) {
            return array();
        }

        return $params;
    }

    /**
     * 从 Authorization 请求头解析 OAuth 参数（仿照 WooCommerce）
     */
    private function parse_header( $header ) {
        if ( 'OAuth ' !== substr( $header, 0, 6 ) ) {
            return array();
        }

        $params = array();
        if ( preg_match_all( '/(oauth_[a-z_-]*)=(:?"([^"]*)"|([^,]*))/', $header, $matches ) ) {
            foreach ( $matches[1] as $i => $h ) {
                $params[ $h ] = urldecode( empty( $matches[3][ $i ] ) ? $matches[4][ $i ] : $matches[3][ $i ] );
            }
            if ( isset( $params['realm'] ) ) {
                unset( $params['realm'] );
            }
        }

        return $params;
    }

    /**
     * 获取 Authorization 请求头（仿照 WooCommerce）
     */
    private function get_authorization_header() {
        if ( ! empty( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
            return wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] );
        }

        if ( function_exists( 'getallheaders' ) ) {
            $headers = getallheaders();
            foreach ( $headers as $key => $value ) {
                if ( 'authorization' === strtolower( $key ) ) {
                    return $value;
                }
            }
        }

        return '';
    }

    /**
     * 验证 OAuth 签名（仿照 WooCommerce）
     */
    private function check_oauth_signature( $user, $params ) {
        $http_method  = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( $_SERVER['REQUEST_METHOD'] ) : '';
        $request_path = isset( $_SERVER['REQUEST_URI'] ) ? wp_parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ) : '';
        $wp_base      = get_home_url( null, '/', 'relative' );
        
        if ( substr( $request_path, 0, strlen( $wp_base ) ) === $wp_base ) {
            $request_path = substr( $request_path, strlen( $wp_base ) );
        }
        
        $base_request_uri = rawurlencode( get_home_url( null, $request_path, is_ssl() ? 'https' : 'http' ) );

        // 获取客户端提供的签名并从参数中删除
        $consumer_signature = rawurldecode( str_replace( ' ', '+', $params['oauth_signature'] ) );
        unset( $params['oauth_signature'] );

        // 排序参数
        if ( ! uksort( $params, 'strcmp' ) ) {
            return new WP_Error( 
                'woocommerce_rest_authentication_error', 
                'Invalid signature - failed to sort parameters.', 
                array( 'status' => 401 ) );
        }

        // 规范化参数
        $params         = $this->normalize_parameters( $params );
        $query_string   = implode( '%26', $this->join_with_equals_sign( $params ) );
        $string_to_sign = $http_method . '&' . $base_request_uri . '&' . $query_string;

        // 验证签名方法
        if ( 'HMAC-SHA1' !== $params['oauth_signature_method'] && 'HMAC-SHA256' !== $params['oauth_signature_method'] ) {
            return new WP_Error( 
                'woocommerce_rest_authentication_error', 
                'Invalid signature - signature method is invalid.', 
                array( 'status' => 401 ) );
        }

        $hash_algorithm = strtolower( str_replace( 'HMAC-', '', $params['oauth_signature_method'] ) );
        $secret         = $user->consumer_secret . '&';
        $signature      = base64_encode( hash_hmac( $hash_algorithm, $string_to_sign, $secret, true ) );

        if ( ! hash_equals( $signature, $consumer_signature ) ) {
            return new WP_Error( 
                'woocommerce_rest_authentication_error', 
                'Invalid signature - provided signature does not match.', 
                array( 'status' => 401 ) );
        }

        return true;
    }

    /**
     * 验证 OAuth 时间戳和 nonce（仿照 WooCommerce）
     */
    private function check_oauth_timestamp_and_nonce( $user, $timestamp, $nonce ) {
        global $wpdb;

        $valid_window = 15 * 60; // 15 分钟窗口

        if ( ( $timestamp < time() - $valid_window ) || ( $timestamp > time() + $valid_window ) ) {
            return new WP_Error( 
                'woocommerce_rest_authentication_error', 
                'Invalid timestamp.', 
                array( 'status' => 401 ) );
        }

        $used_nonces = maybe_unserialize( $user->nonces );

        if ( empty( $used_nonces ) ) {
            $used_nonces = array();
        }

        if ( in_array( $nonce, $used_nonces, true ) ) {
            return new WP_Error( 
                'woocommerce_rest_authentication_error', 
                'Invalid nonce - nonce has already been used.', 
                array( 'status' => 401 ) );
        }

        $used_nonces[ $timestamp ] = $nonce;

        // 删除过期的 nonce
        foreach ( $used_nonces as $nonce_timestamp => $nonce_value ) {
            if ( $nonce_timestamp < ( time() - $valid_window ) ) {
                unset( $used_nonces[ $nonce_timestamp ] );
            }
        }

        $used_nonces = maybe_serialize( $used_nonces );

        $wpdb->update(
            $wpdb->prefix . 'woocommerce_api_keys',
            array( 'nonces' => $used_nonces ),
            array( 'key_id' => $user->key_id ),
            array( '%s' ),
            array( '%d' )
        );

        return true;
    }

    /**
     * 规范化参数（仿照 WooCommerce）
     */
    private function normalize_parameters( $parameters ) {
        $keys       = $this->urlencode_rfc3986( array_keys( $parameters ) );
        $values     = $this->urlencode_rfc3986( array_values( $parameters ) );
        $parameters = array_combine( $keys, $values );

        return $parameters;
    }

    /**
     * RFC 3986 编码（仿照 WooCommerce 的 wc_rest_urlencode_rfc3986）
     */
    private function urlencode_rfc3986( $input ) {
        if ( is_array( $input ) ) {
            return array_map( array( $this, 'urlencode_rfc3986' ), $input );
        } else {
            return str_replace( array( '+', ' ' ), '%20', str_replace( '%7E', '~', rawurlencode( $input ) ) );
        }
    }

    /**
     * 使用等号连接参数（仿照 WooCommerce）
     */
    private function join_with_equals_sign( $params, $query_params = array(), $key = '' ) {
        foreach ( $params as $param_key => $param_value ) {
            if ( $key ) {
                $param_key = $key . '%5B' . $param_key . '%5D';
            }

            if ( is_array( $param_value ) ) {
                $query_params = $this->join_with_equals_sign( $param_value, $query_params, $param_key );
            } else {
                $string         = $param_key . '=' . $param_value;
                $query_params[] = $this->urlencode_rfc3986( $string );
            }
        }

        return $query_params;
    }

    /**
     * 根据 consumer_key 获取 API key 的用户数据（仿照 WooCommerce）
     */
    private function get_user_data_by_consumer_key( $consumer_key ) {
        global $wpdb;

        $consumer_key = hash_hmac( 'sha256', sanitize_text_field( $consumer_key ), 'wc-api' );
        
        $user = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT key_id, user_id, permissions, consumer_key, consumer_secret, nonces
                FROM {$wpdb->prefix}woocommerce_api_keys
                WHERE consumer_key = %s",
                $consumer_key
            )
        );

        return $user;
    }

}
