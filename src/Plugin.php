<?php

/**
 * @package WooApp
 */

namespace WooApp;

defined('ABSPATH') || exit;

/**
 * Main Plugin Class
 * Single entry point for the entire plugin
 */
class Plugin
{
    /**
     * Plugin instance
     * @var Plugin
     */
    private static $instance = null;

    /**
     * Service container
     * @var Container
     */
    private $container;

    /**
     * Get singleton instance
     * @return Plugin
     */
    public static function getInstance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor - private to enforce singleton
     */
    private function __construct()
    {
        $this->setupConstants();
        $this->container = new Core\Container();
    }

    /**
     * Initialize the plugin
     */
    public function init()
    {
        // Check version compatibility (WordPress and PHP only)
        $version_check = Common\VersionChecker::check();
        if (!$version_check['success']) {
            // Display errors and prevent plugin initialization
            Common\VersionChecker::displayErrors($version_check['errors']);
            return;
        }

        // Register activation/deactivation hooks
        register_activation_hook(WOOAPP_PLUGIN_FILE, array($this, 'activate'));
        register_deactivation_hook(WOOAPP_PLUGIN_FILE, array($this, 'deactivate'));

        // Load text domain
        add_action('init', array($this, 'loadTextDomain'));

        // Register all services
        add_action('plugins_loaded', array($this, 'registerServices'), 10);
    }

    /**
     * Setup plugin constants
     */
    private function setupConstants()
    {
        if (!defined('WOOAPP_PLUGIN_FILE')) {
            define('WOOAPP_PLUGIN_FILE', dirname(dirname(__FILE__)) . '/wooapp-setting-tools.php');
        }
        if (!defined('WOOAPP_PLUGIN_DIR')) {
            define('WOOAPP_PLUGIN_DIR', plugin_dir_path(WOOAPP_PLUGIN_FILE));
        }
        if (!defined('WOOAPP_PLUGIN_URL')) {
            define('WOOAPP_PLUGIN_URL', plugin_dir_url(WOOAPP_PLUGIN_FILE));
        }
        if (!defined('WOOAPP_VERSION')) {
            define('WOOAPP_VERSION', '1.0.0');
        }
        if (!defined('WOOAPP_TEXT_DOMAIN')) {
            define('WOOAPP_TEXT_DOMAIN', 'wooapp-setting-tools');
        }
    }

    /**
     * Register all plugin services
     */
    public function registerServices()
    {
        try {
            // Register services with the container
            $this->container->register('hooks', Core\Hooks::class);
            $this->container->register('admin', Admin\Admin::class);
            $this->container->register('api', API\REST::class);

            // Boot all registered services
            $this->container->boot();
        } catch (\Exception $e) {
            // Log error but don't crash - WooCommerce might not be fully loaded yet
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('WooApp Error during service registration: ' . $e->getMessage());
            }
        }
    }

    /**
     * Plugin activation
     */
    public function activate()
    {
        do_action('wooapp_plugin_activate');
    }

    /**
     * Plugin deactivation
     */
    public function deactivate()
    {
        do_action('wooapp_plugin_deactivate');
    }

    /**
     * Load plugin text domain
     */
    public function loadTextDomain()
    {
        load_plugin_textdomain(
            WOOAPP_TEXT_DOMAIN,
            false,
            dirname(dirname(dirname(__FILE__))) . '/languages/'
        );
    }

    /**
     * Get service container
     * @return Container
     */
    public function getContainer()
    {
        return $this->container;
    }

}
