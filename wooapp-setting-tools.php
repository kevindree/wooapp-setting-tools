<?php

/**
 * Plugin Name: WooApp Setting Tools
 * Plugin URI: https://kevindree.geehootek.com
 * Description: WooCommerce APP Support Tools - API Authentication, Category Grouping & Custom Settings
 * Version: 1.0.1
 * Author: Kevindree
 * Author URI: https://kevindree.geehootek.com
 * License: GPL v3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: wooapp-setting-tools
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.2
 * Requires Plugins: woocommerce
 * WC requires at least: 10.3
 * WC tested up to: 10.3
 */

defined('ABSPATH') || exit;

// Load the autoloader first
require_once __DIR__ . '/src/Autoloader.php';

// Initialize autoloader
WooApp\Autoloader::init(__DIR__ . '/src');

// Load the main plugin class
require_once __DIR__ . '/src/Plugin.php';

// Declare compatibility with WooCommerce Custom Order Tables feature
add_action( 'before_woocommerce_init', function() {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            __FILE__,
            true
        );
    }
});

// Initialize the plugin on plugins_loaded hook
// Priority 5 ensures this runs early, before default priority 10
add_action('plugins_loaded', function() {
    WooApp\Plugin::getInstance()->init();
}, 5);


