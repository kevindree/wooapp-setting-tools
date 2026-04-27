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
        $allowed_hooks = array(
            'toplevel_page_wooapp-settings',
            'wooapp-settings_page_wooapp-category-positions',
            'wooapp-settings_page_wooapp-app-banners',
        );

        if (!in_array($hook_suffix, $allowed_hooks, true)) {
            return;
        }

        wp_enqueue_style(
            'wooapp-admin',
            WOOAPP_PLUGIN_URL . '/assets/css/admin.css',
            array(),
            Constants::PLUGIN_VERSION
        );

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
        $base_url = esc_url(get_rest_url(null, 'wooapp/v1'));

        $endpoint_groups = array(
            array(
                'title'     => __('User Authentication', WOOAPP_TEXT_DOMAIN),
                'endpoints' => array(
                    array('method' => 'POST',   'path' => '/userlogin',           'desc' => __('User login', WOOAPP_TEXT_DOMAIN)),
                    array('method' => 'POST',   'path' => '/register',            'desc' => __('User registration', WOOAPP_TEXT_DOMAIN)),
                    array('method' => 'GET',    'path' => '/addresses',           'desc' => __('Get user address list', WOOAPP_TEXT_DOMAIN)),
                    array('method' => 'POST',   'path' => '/addresses',           'desc' => __('Add address', WOOAPP_TEXT_DOMAIN)),
                    array('method' => 'PUT',    'path' => '/addresses/{id}',      'desc' => __('Update address', WOOAPP_TEXT_DOMAIN)),
                    array('method' => 'DELETE', 'path' => '/addresses/{id}',      'desc' => __('Delete address', WOOAPP_TEXT_DOMAIN)),
                ),
            ),
            array(
                'title'     => __('Category Positions', WOOAPP_TEXT_DOMAIN),
                'endpoints' => array(
                    array('method' => 'GET', 'path' => '/category-positions',                  'desc' => __('Get all category positions', WOOAPP_TEXT_DOMAIN)),
                    array('method' => 'GET', 'path' => '/category-positions/{position_key}',   'desc' => __('Get categories for a specific position', WOOAPP_TEXT_DOMAIN)),
                ),
            ),
            array(
                'title'     => __('App Banners', WOOAPP_TEXT_DOMAIN),
                'endpoints' => array(
                    array('method' => 'GET', 'path' => '/banners',                             'desc' => __('Get all banners', WOOAPP_TEXT_DOMAIN)),
                    array('method' => 'GET', 'path' => '/banners/{id}',                        'desc' => __('Get a single banner', WOOAPP_TEXT_DOMAIN)),
                    array('method' => 'GET', 'path' => '/banner-groups',                       'desc' => __('Get all banner groups', WOOAPP_TEXT_DOMAIN)),
                    array('method' => 'GET', 'path' => '/banner-groups/{banner_group_key}',    'desc' => __('Get a specific banner group', WOOAPP_TEXT_DOMAIN)),
                ),
            ),
            array(
                'title'     => __('Social Authentication', WOOAPP_TEXT_DOMAIN),
                'endpoints' => array(
                    array('method' => 'POST',   'path' => '/social-login',                    'desc' => __('Social login or register', WOOAPP_TEXT_DOMAIN)),
                    array('method' => 'POST',   'path' => '/social-link',                     'desc' => __('Link social account to existing user', WOOAPP_TEXT_DOMAIN)),
                    array('method' => 'GET',    'path' => '/social-accounts',                 'desc' => __('List linked social accounts for a user', WOOAPP_TEXT_DOMAIN)),
                    array('method' => 'DELETE', 'path' => '/social-accounts/{provider}',      'desc' => __('Unlink a social account', WOOAPP_TEXT_DOMAIN)),
                ),
            ),
        );
        $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'general';
        $page_slug  = 'wooapp-settings';
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <nav class="nav-tab-wrapper wooapp-nav-tabs">
                <a href="<?php echo esc_url(admin_url('admin.php?page=' . $page_slug . '&tab=general')); ?>"
                   class="nav-tab<?php echo $active_tab === 'general' ? ' nav-tab-active' : ''; ?>">
                    <?php esc_html_e('General', WOOAPP_TEXT_DOMAIN); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=' . $page_slug . '&tab=rest-api')); ?>"
                   class="nav-tab<?php echo $active_tab === 'rest-api' ? ' nav-tab-active' : ''; ?>">
                    <?php esc_html_e('REST API Endpoints', WOOAPP_TEXT_DOMAIN); ?>
                </a>
            </nav>

            <div class="wooapp-container">
                <?php if ($active_tab === 'general') : ?>

                <?php elseif ($active_tab === 'rest-api') : ?>

                <div class="wooapp-panel">
                    <h2><?php esc_html_e('REST API Endpoints', WOOAPP_TEXT_DOMAIN); ?></h2>
                    <p class="wooapp-api-base">
                        <?php esc_html_e('Base URL:', WOOAPP_TEXT_DOMAIN); ?>
                        <code><?php echo $base_url; ?></code>
                    </p>
                    <p class="description"><?php esc_html_e('API Keys are managed through WooCommerce Settings → Advanced → REST API.', WOOAPP_TEXT_DOMAIN); ?></p>
                </div>

                <?php foreach ($endpoint_groups as $group) : ?>
                <div class="wooapp-panel">
                    <h2><?php echo esc_html($group['title']); ?></h2>
                    <table class="wooapp-endpoint-table widefat striped">
                        <thead>
                            <tr>
                                <th class="wooapp-col-method"><?php esc_html_e('Method', WOOAPP_TEXT_DOMAIN); ?></th>
                                <th class="wooapp-col-path"><?php esc_html_e('Endpoint', WOOAPP_TEXT_DOMAIN); ?></th>
                                <th><?php esc_html_e('Description', WOOAPP_TEXT_DOMAIN); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($group['endpoints'] as $ep) : ?>
                            <tr>
                                <td>
                                    <span class="wooapp-method wooapp-method-<?php echo esc_attr(strtolower($ep['method'])); ?>">
                                        <?php echo esc_html($ep['method']); ?>
                                    </span>
                                </td>
                                <td><code class="wooapp-endpoint-path"><?php echo esc_html('wooapp/v1' . $ep['path']); ?></code></td>
                                <td><?php echo esc_html($ep['desc']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endforeach; ?>

                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}
