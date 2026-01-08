<?php

/**
 * @package WooApp\Core
 */

namespace WooApp\Core;

defined('ABSPATH') || exit;

/**
 * Hooks Manager
 * Centralizes all WordPress hooks and filters
 */
class Hooks extends AbstractService
{
    /**
     * Register all plugin hooks
     */
    protected function registerHooks()
    {
        // Enqueue assets
        add_action('admin_enqueue_scripts', array($this, 'enqueueAdminAssets'));
        add_action('wp_enqueue_scripts', array($this, 'enqueueFrontendAssets'));
    }

    /**
     * Enqueue admin assets
     */
    public function enqueueAdminAssets()
    {
        wp_enqueue_style(
            'wooapp-admin',
            WOOAPP_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            WOOAPP_VERSION
        );

        wp_enqueue_script(
            'wooapp-admin',
            WOOAPP_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'wp-api'),
            WOOAPP_VERSION,
            true
        );

        // Localize script with admin data for WordPress 6.9 compatibility
        wp_localize_script(
            'wooapp-admin',
            'wooappAdmin',
            array(
                'nonce'   => wp_create_nonce('wooapp_nonce'),
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'restUrl' => esc_url_raw(rest_url()),
            )
        );
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueueFrontendAssets()
    {
        // Frontend assets here if needed
    }
}
