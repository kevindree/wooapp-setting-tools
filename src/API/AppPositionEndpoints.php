<?php

/**
 * @package WooApp\API
 * App category positions endpoints
 */

namespace WooApp\API;

use WP_REST_Request;
use WP_REST_Response;
use WooApp\Services\CategoryPositionManager;
use WooApp\Common\Constants;

defined('ABSPATH') || exit;

class AppPositionEndpoints
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
     * Register category position endpoint
     */
    public function register_route()
    {
        register_rest_route(
            Constants::REST_NAMESPACE,
            'category-positions',
            array(
                'methods'             => 'GET',
                'callback'            => array($this, 'get_category_positions'),
                'permission_callback' => array($this, 'check_api_permission'),
            )
        );

        register_rest_route(
            Constants::REST_NAMESPACE,
            'category-positions/(?P<position_key>[\w-]+)',
            array(
                'methods'             => 'GET',
                'callback'            => array($this, 'get_position_categories'),
                'permission_callback' => array($this, 'check_api_permission'),
                'args'                => array(
                    'position_key' => array(
                        'required'          => true,
                        'validate_callback' => function($param) {
                            return is_string($param);
                        },
                    ),
                ),
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
     * Get all category positions
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_category_positions(WP_REST_Request $request)
    {
        $positions = CategoryPositionManager::get_all_positions();

        // Transform category IDs to include category details
        $result = array();
        foreach ($positions as $position_key => $position_data) {
            $categories = array();
            if (!empty($position_data['categories'])) {
                foreach ($position_data['categories'] as $category_id) {
                    $category = get_term($category_id, 'product_cat');
                    if ($category && !is_wp_error($category)) {
                        $categories[] = array(
                            'id'   => $category->term_id,
                            'name' => $category->name,
                            'slug' => $category->slug,
                        );
                    }
                }
            }

            $result[$position_key] = array(
                'label'      => $position_data['label'],
                'categories' => $categories,
            );
        }

        return new WP_REST_Response($result, 200);
    }

    /**
     * Get categories for a specific position
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_position_categories(WP_REST_Request $request)
    {
        $position_key = $request->get_param('position_key');

        $label = CategoryPositionManager::get_position_label($position_key);
        if (empty($label)) {
            return new WP_REST_Response(
                array('error' => 'Position not found'),
                404
            );
        }

        $category_ids = CategoryPositionManager::get_position_categories($position_key);
        $categories = array();

        if (!empty($category_ids)) {
            foreach ($category_ids as $category_id) {
                $category = get_term($category_id, 'product_cat');
                if ($category && !is_wp_error($category)) {
                    $categories[] = array(
                        'id'   => $category->term_id,
                        'name' => $category->name,
                        'slug' => $category->slug,
                    );
                }
            }
        }

        return new WP_REST_Response(
            array(
                'label'        => $label,
                'categories'   => $categories,
            ),
            200
        );
    }
}
