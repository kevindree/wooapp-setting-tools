<?php

/**
 * @package WooApp\API
 */

namespace WooApp\API;

use WooApp\Core\AbstractService;
use WooApp\Common\Constants;

defined('ABSPATH') || exit;

/**
 * REST API Module
 * Handles REST API endpoints
 */
class REST extends AbstractService
{

    /**
     * User authentication endpoints instance
     */
    private $user_auth_endpoints;

    /**
     * App position endpoints instance
     */
    private $app_position_endpoints;

    /**
     * App banners endpoints instance
     */
    private $app_banners_endpoints;

    /**
     * Boot service
     */
    public function boot()
    {
        $this->user_auth_endpoints = new UserAuthEndpoints();
        $this->app_position_endpoints = new AppPositionEndpoints();
        $this->app_banners_endpoints = new AppBannersEndpoints();
        parent::boot();
    }

    /**
     * Register REST API hooks
     */
    protected function registerHooks()
    {
        add_action('rest_api_init', array($this, 'registerRoutes'));
    }

    /**
     * Register REST routes
     */
    public function registerRoutes()
    {
        // Register user authentication endpoints
        $this->user_auth_endpoints->register_routes();

        // Register app position endpoints
        $this->app_position_endpoints->register_route();

        // Register app banners and banner groups endpoints
        $this->app_banners_endpoints->register_routes();
    }
}
