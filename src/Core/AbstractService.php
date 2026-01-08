<?php

/**
 * @package WooApp\Core
 */

namespace WooApp\Core;

defined('ABSPATH') || exit;

/**
 * Abstract Service Class
 * Base class for all services
 */
abstract class AbstractService
{
    /**
     * Service boot method - called when service is initialized
     */
    public function boot()
    {
        $this->registerHooks();
    }

    /**
     * Register hooks for this service
     * Override in child classes
     */
    protected function registerHooks()
    {
    }

    /**
     * Get plugin instance
     * @return \WooApp\Plugin
     */
    protected function plugin()
    {
        return \WooApp\Plugin::getInstance();
    }
}
