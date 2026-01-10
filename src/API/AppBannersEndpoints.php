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

        // POST /wooapp/v1/banner-groups (create group)
        register_rest_route(
            Constants::REST_NAMESPACE,
            '/banner-groups',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'create_group'),
                'permission_callback' => array($this, 'permission_callback'),
            )
        );

        // DELETE /wooapp/v1/banner-groups/{name} (delete group)
        register_rest_route(
            Constants::REST_NAMESPACE,
            '/banner-groups/(?P<name>[a-zA-Z0-9_-]+)',
            array(
                'methods' => 'DELETE',
                'callback' => array($this, 'delete_group'),
                'permission_callback' => array($this, 'permission_callback'),
            )
        );
    }

    /**
     * Check if user has permission to manage banners
     * 
     * @return bool
     */
    public function permission_callback()
    {
        return current_user_can('manage_options');
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
            'group' => isset($banner['group']) ? $banner['group'] : 'default',
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

            return rest_ensure_response(array(
                'success' => true,
                'data' => $groups,
                'total' => count($groups),
            ));
        } catch (\Exception $e) {
            return rest_ensure_response(array(
                'success' => false,
                'message' => $e->getMessage(),
            ));
        }
    }

    /**
     * Create a new banner group
     *
     * @param \WP_REST_Request $request REST API request
     * @return \WP_REST_Response Response object
     */
    public function create_group($request)
    {
        try {
            $group_name = $request->get_json_params();
            $group_name = isset($group_name['name']) ? sanitize_text_field($group_name['name']) : '';

            if (empty($group_name)) {
                return rest_ensure_response(array(
                    'success' => false,
                    'message' => __('Group name is required.', WOOAPP_TEXT_DOMAIN),
                ));
            }

            if (BannerManager::add_group($group_name)) {
                return rest_ensure_response(array(
                    'success' => true,
                    'message' => __('Group created successfully.', WOOAPP_TEXT_DOMAIN),
                    'data' => $group_name,
                ));
            } else {
                return rest_ensure_response(array(
                    'success' => false,
                    'message' => __('Failed to create group. Group may already exist.', WOOAPP_TEXT_DOMAIN),
                ));
            }
        } catch (\Exception $e) {
            return rest_ensure_response(array(
                'success' => false,
                'message' => $e->getMessage(),
            ));
        }
    }

    /**
     * Delete a banner group
     *
     * @param \WP_REST_Request $request REST API request
     * @return \WP_REST_Response Response object
     */
    public function delete_group($request)
    {
        try {
            $group_name = $request->get_param('name');
            $group_name = sanitize_text_field($group_name);

            if (empty($group_name)) {
                return rest_ensure_response(array(
                    'success' => false,
                    'message' => __('Group name is required.', WOOAPP_TEXT_DOMAIN),
                ));
            }

            if ($group_name === 'default') {
                return rest_ensure_response(array(
                    'success' => false,
                    'message' => __('Cannot delete default group.', WOOAPP_TEXT_DOMAIN),
                ));
            }

            if (BannerManager::delete_group($group_name)) {
                return rest_ensure_response(array(
                    'success' => true,
                    'message' => __('Group deleted successfully.', WOOAPP_TEXT_DOMAIN),
                ));
            } else {
                return rest_ensure_response(array(
                    'success' => false,
                    'message' => __('Failed to delete group.', WOOAPP_TEXT_DOMAIN),
                ));
            }
        } catch (\Exception $e) {
            return rest_ensure_response(array(
                'success' => false,
                'message' => $e->getMessage(),
            ));
        }
    }
}
