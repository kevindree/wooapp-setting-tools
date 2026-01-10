<?php

/**
 * @package WooApp\API
 */

namespace WooApp\API;

use WooApp\Services\BannerManager;
use WooApp\Common\Constants;

defined('ABSPATH') || exit;

/**
 * App Banners REST API Endpoints
 */
class AppBannersEndpoints
{
    /**
     * Register REST routes
     */
    public function register_routes()
    {
        // GET /wooapp/v1/banners
        register_rest_route(
            Constants::REST_NAMESPACE,
            '/banners',
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_banners'),
                'permission_callback' => '__return_true',
            )
        );

        // GET /wooapp/v1/banners/{id}
        register_rest_route(
            Constants::REST_NAMESPACE,
            '/banners/(?P<id>[a-zA-Z0-9_]+)',
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_banner'),
                'permission_callback' => '__return_true',
            )
        );
    }

    /**
     * Get all banners
     *
     * @param \WP_REST_Request $request REST API request
     * @return \WP_REST_Response Response object
     */
    public function get_banners($request)
    {
        try {
            $banners = BannerManager::get_banners();

            // Format banners for API response
            $formatted_banners = array();
            foreach ($banners as $banner) {
                $formatted_banners[] = $this->format_banner_response($banner);
            }

            return rest_ensure_response(array(
                'success' => true,
                'data' => $formatted_banners,
                'total' => count($formatted_banners),
            ));
        } catch (\Exception $e) {
            return rest_ensure_response(array(
                'success' => false,
                'message' => $e->getMessage(),
            ));
        }
    }

    /**
     * Get a single banner
     *
     * @param \WP_REST_Request $request REST API request
     * @return \WP_REST_Response Response object
     */
    public function get_banner($request)
    {
        try {
            $banner_id = $request->get_param('id');
            $banner = BannerManager::get_banner($banner_id);

            if (!$banner) {
                return rest_ensure_response(array(
                    'success' => false,
                    'message' => __('Banner not found.', WOOAPP_TEXT_DOMAIN),
                ));
            }

            return rest_ensure_response(array(
                'success' => true,
                'data' => $this->format_banner_response($banner),
            ));
        } catch (\Exception $e) {
            return rest_ensure_response(array(
                'success' => false,
                'message' => $e->getMessage(),
            ));
        }
    }

    /**
     * Format banner data for API response
     *
     * @param array $banner Banner data
     * @return array Formatted banner data
     */
    private function format_banner_response($banner)
    {
        return array(
            'id' => isset($banner['id']) ? $banner['id'] : '',
            'image_id' => isset($banner['image_id']) ? (int)$banner['image_id'] : 0,
            'image_url' => isset($banner['image_url']) ? $banner['image_url'] : '',
            'deeplink' => isset($banner['deeplink']) ? $banner['deeplink'] : '',
            'order' => isset($banner['order']) ? (int)$banner['order'] : 0,
            'created_at' => isset($banner['created_at']) ? $banner['created_at'] : '',
            'updated_at' => isset($banner['updated_at']) ? $banner['updated_at'] : '',
        );
    }
}
