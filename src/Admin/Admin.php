<?php

/**
 * @package WooApp\Admin
 */

namespace WooApp\Admin;

use WooApp\Core\AbstractService;
use WooApp\Common\Constants;

defined('ABSPATH') || exit;

/**
 * Admin Module
 * Coordinates admin menu, settings, and asset loading.
 * Page rendering and form handling are delegated to dedicated page classes.
 */
class Admin extends AbstractService
{
    /**
     * @var CategoryPositionsPage
     */
    private $categoryPositionsPage;

    /**
     * @var BannersPage
     */
    private $bannersPage;

    /**
     * Register admin hooks
     */
    protected function registerHooks()
    {
        $this->categoryPositionsPage = new CategoryPositionsPage();
        $this->bannersPage = new BannersPage();

        add_action('admin_menu', array($this, 'addAdminMenu'));
        add_action('admin_init', array($this, 'initSettings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueueAssets'));

        $this->categoryPositionsPage->registerHooks();
        $this->bannersPage->registerHooks();
    }

    /**
     * Add admin menu
     */
    public function addAdminMenu()
    {
        add_menu_page(
            __('WooApp Settings', WOOAPP_TEXT_DOMAIN),
            __('WooApp Settings', WOOAPP_TEXT_DOMAIN),
            'manage_options',
            'wooapp-settings',
            array($this, 'renderSettingsPage'),
            'dashicons-smartphone',
            80
        );

        add_submenu_page(
            'wooapp-settings',
            __('Category Positions', WOOAPP_TEXT_DOMAIN),
            __('Category Positions', WOOAPP_TEXT_DOMAIN),
            'manage_options',
            'wooapp-category-positions',
            array($this->categoryPositionsPage, 'render')
        );

        add_submenu_page(
            'wooapp-settings',
            __('App Banners', WOOAPP_TEXT_DOMAIN),
            __('App Banners', WOOAPP_TEXT_DOMAIN),
            'manage_options',
            'wooapp-app-banners',
            array($this->bannersPage, 'render')
        );
    }

    /**
     * Initialize settings
     */
    public function initSettings()
    {
        $positions = get_option(Constants::OPTION_CATEGORY_POSITIONS, array());
        
        if (empty($positions)) {
            $default_positions = array(
                'banner' => __('Banner Section', WOOAPP_TEXT_DOMAIN),
                'featured' => __('Featured Products', WOOAPP_TEXT_DOMAIN),
                'sidebar' => __('Sidebar', WOOAPP_TEXT_DOMAIN),
            );
            
            update_option(Constants::OPTION_CATEGORY_POSITIONS, $default_positions);
        }
    }

    /**
     * Enqueue admin styles and scripts
     */
    public function enqueueAssets($hook_suffix)
    {
        if ($hook_suffix !== 'wooapp-settings_page_wooapp-category-positions' && 
            $hook_suffix !== 'wooapp-settings_page_wooapp-app-banners') {
            return;
        }

        $plugin_url = WOOAPP_PLUGIN_URL;
        
        wp_enqueue_style(
            'wooapp-category-positions',
            $plugin_url . '/assets/css/category-positions.css',
            array(),
            Constants::PLUGIN_VERSION
        );

        wp_enqueue_style(
            'wooapp-app-banners',
            $plugin_url . '/assets/css/app-banners.css',
            array(),
            Constants::PLUGIN_VERSION
        );

        wp_enqueue_script(
            'wooapp-category-positions',
            $plugin_url . '/assets/js/category-positions.js',
            array(),
            Constants::PLUGIN_VERSION,
            true
        );
        
        wp_enqueue_script(
            'wooapp-app-banners',
            $plugin_url . '/assets/js/app-banners.js',
            array('jquery', 'jquery-ui-sortable'),
            Constants::PLUGIN_VERSION,
            true
        );

        wp_localize_script(
            'wooapp-category-positions',
            'wooappCategoryPositions',
            array(
                'deleteNonce' => wp_create_nonce('wooapp_delete_position'),
                'adminPostUrl' => admin_url('admin-post.php'),
            )
        );

        wp_localize_script(
            'wooapp-app-banners',
            'wooappBanners',
            array(
                'deleteNonce' => wp_create_nonce('wooapp_delete_banner'),
                'uploadNonce' => wp_create_nonce('wooapp_upload_banner'),
                'reorderNonce' => wp_create_nonce('wooapp_reorder_banners'),
                'bannersNonce' => wp_create_nonce('wooapp_banners_nonce'),
                'adminPostUrl' => admin_url('admin-post.php'),
                'ajaxUrl' => admin_url('admin-ajax.php'),
            )
        );

        wp_enqueue_media();
    }

    /**
     * Render settings page
     */
    public function renderSettingsPage()
    {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <div class="wooapp-container">
                <p><?php esc_html_e('WooApp Settings will be implemented here', WOOAPP_TEXT_DOMAIN); ?></p>
                <p><?php esc_html_e('API Keys are managed through WooCommerce Settings', WOOAPP_TEXT_DOMAIN); ?></p>
            </div>
        </div>
        <?php
    }
}
