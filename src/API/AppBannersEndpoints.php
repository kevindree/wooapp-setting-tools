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
 * Handles both banner and banner group operations
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
                'args' => array(
                    'group' => array(
                        'type' => 'string',
                        'description' => __('Filter banners by group', WOOAPP_TEXT_DOMAIN),
                        'required' => false,
                    ),
                ),
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

        // GET /wooapp/v1/banner-groups
        register_rest_route(
            Constants::REST_NAMESPACE,
            '/banner-groups',
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_groups'),
                'permission_callback' => '__return_true',
            )
        );

        // GET /wooapp/v1/banner-groups/{banner_group_key}
        register_rest_route(
            Constants::REST_NAMESPACE,
            '/banner-groups/(?P<banner_group_key>[\w-]+)',
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_group'),
                'permission_callback' => '__return_true',
                'args' => array(
                    'banner_group_key' => array(
                        'required' => true,
                        'validate_callback' => function ($param) {
                            return is_string($param);
                        },
                    ),
                ),
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
            $group = $request->get_param('group');
            $banners = BannerManager::get_banners($group);

            // Format banners for API response and filter out banners without images
            $formatted_banners = array();
            foreach ($banners as $banner) {
                // Skip banners without image_url
                if (empty($banner['image_url'])) {
                    continue;
                }
                $formatted_banners[] = $this->format_banner_response($banner);
            }

            return rest_ensure_response(array(
                'success' => true,
                'data' => $formatted_banners,
                'total' => count($formatted_banners),
            ));
        } catch (\Exception $e) {
            return new \WP_Error(
                'internal_error',
                $e->getMessage(),
                array('status' => 500)
            );
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
                return new \WP_Error(
                    'banner_not_found',
                    __('Banner not found.', WOOAPP_TEXT_DOMAIN),
                    array('status' => 404)
                );
            }

            return rest_ensure_response(array(
                'success' => true,
                'data' => $this->format_banner_response($banner),
            ));
        } catch (\Exception $e) {
            return new \WP_Error(
                'internal_error',
                $e->getMessage(),
                array('status' => 500)
            );
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
        $group_key = isset($banner['group']) ? $banner['group'] : 'default';
        return array(
            'id' => isset($banner['id']) ? $banner['id'] : '',
            'group' => $group_key,
            'group_label' => BannerManager::get_group_label($group_key),
            'image_id' => isset($banner['image_id']) ? (int)$banner['image_id'] : 0,
            'image_url' => isset($banner['image_url']) ? $banner['image_url'] : '',
            'deeplink' => isset($banner['deeplink']) ? $banner['deeplink'] : '',
            'order' => isset($banner['order']) ? (int)$banner['order'] : 0,
            'created_at' => isset($banner['created_at']) ? $banner['created_at'] : '',
            'updated_at' => isset($banner['updated_at']) ? $banner['updated_at'] : '',
        );
    }

    // ============================================
    // Banner Group Management Methods
    // ============================================

    /**
     * Get all banner groups
     *
     * @param \WP_REST_Request $request REST API request
     * @return \WP_REST_Response Response object
     */
    public function get_groups($request)
    {
        try {
            $groups = BannerManager::get_groups();
            $all_banners = BannerManager::get_banners();

            $formatted = array();
            foreach ($groups as $key => $label) {
                // Filter banners belonging to this group (with image)
                $group_banners = array();
                foreach ($all_banners as $banner) {
                    $banner_group = isset($banner['group']) ? $banner['group'] : 'default';
                    if ($banner_group === $key && !empty($banner['image_url'])) {
                        $group_banners[] = $this->format_banner_response($banner);
                    }
                }

                $formatted[] = array(
                    'banner_group_key' => $key,
                    'label'            => $label,
                    'banners'          => $group_banners,
                );
            }

            return rest_ensure_response(array(
                'success' => true,
                'data' => $formatted,
                'total' => count($formatted),
            ));
        } catch (\Exception $e) {
            return new \WP_Error(
                'internal_error',
                $e->getMessage(),
                array('status' => 500)
            );
        }
    }

    /**
     * Get a single banner group with its banners
     *
     * @param \WP_REST_Request $request REST API request
     * @return \WP_REST_Response Response object
     */
    public function get_group($request)
    {
        try {
            $banner_group_key = $request->get_param('banner_group_key');
            $groups = BannerManager::get_groups();

            if (!isset($groups[$banner_group_key])) {
                return new \WP_Error(
                    'group_not_found',
                    __('Banner group not found.', WOOAPP_TEXT_DOMAIN),
                    array('status' => 404)
                );
            }

            $all_banners = BannerManager::get_banners($banner_group_key);
            $group_banners = array();
            foreach ($all_banners as $banner) {
                if (!empty($banner['image_url'])) {
                    $group_banners[] = $this->format_banner_response($banner);
                }
            }

            return rest_ensure_response(array(
                'success' => true,
                'data' => array(
                    'banner_group_key' => $banner_group_key,
                    'label'            => $groups[$banner_group_key],
                    'banners'          => $group_banners,
                ),
            ));
        } catch (\Exception $e) {
            return new \WP_Error(
                'internal_error',
                $e->getMessage(),
                array('status' => 500)
            );
        }
    }
}
