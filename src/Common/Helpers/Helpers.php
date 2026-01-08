<?php

/**
 * @package WooApp\Common\Helpers
 */

namespace WooApp\Common\Helpers;

defined('ABSPATH') || exit;

/**
 * Helper Functions
 */
class Helpers
{
    /**
     * Get plugin instance
     * @return \WooApp\Plugin
     */
    public static function plugin()
    {
        return \WooApp\Plugin::getInstance();
    }

    /**
     * Check if WooCommerce is active
     * @return bool
     */
    public static function isWooCommerceActive()
    {
        return class_exists('WooCommerce');
    }

    /**
     * Log error or info
     * @param string $message
     * @param string $level
     */
    public static function log($message, $level = 'info')
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[WooApp] [' . strtoupper($level) . '] ' . $message);
        }
    }

    /**
     * Get plugin URL
     * @param string $path
     * @return string
     */
    public static function getPluginUrl($path = '')
    {
        return WOOAPP_PLUGIN_URL . $path;
    }

    /**
     * Get plugin directory
     * @param string $path
     * @return string
     */
    public static function getPluginDir($path = '')
    {
        return WOOAPP_PLUGIN_DIR . $path;
    }
}
