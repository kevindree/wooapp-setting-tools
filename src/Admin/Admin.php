<?php

/**
 * @package WooApp\Admin
 */

namespace WooApp\Admin;

use WooApp\Core\AbstractService;
use WooApp\Services\CategoryPositionManager;
use WooApp\Services\BannerManager;
use WooApp\Common\Constants;

defined('ABSPATH') || exit;

/**
 * Admin Module
 * Handles admin interface and settings
 */
class Admin extends AbstractService
{
    /**
     * Register admin hooks
     */
    protected function registerHooks()
    {
        add_action('admin_menu', array($this, 'addAdminMenu'));
        add_action('admin_init', array($this, 'initSettings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueueAssets'));
        add_action('admin_post_wooapp_save_category_positions', array($this, 'handleSaveCategoryPositions'));
        add_action('admin_post_wooapp_delete_position', array($this, 'handleDeletePosition'));
        add_action('admin_post_wooapp_save_banners', array($this, 'handleSaveBanners'));
        add_action('wp_ajax_wooapp_delete_banner', array($this, 'handleDeleteBanner'));
        add_action('wp_ajax_wooapp_upload_banner', array($this, 'handleBannerUpload'));
        add_action('wp_ajax_wooapp_reorder_banners', array($this, 'handleBannerReorder'));
        add_action('wp_ajax_wooapp_save_banner_data', array($this, 'handleSaveBannerData'));
        add_action('wp_ajax_wooapp_create_banner_group', array($this, 'handleCreateBannerGroup'));
        add_action('wp_ajax_wooapp_delete_banner_group', array($this, 'handleDeleteBannerGroup'));
        add_action('wp_ajax_wooapp_get_banner_groups', array($this, 'handleGetBannerGroups'));
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

        // Add submenu for category positions
        add_submenu_page(
            'wooapp-settings',
            __('Category Positions', WOOAPP_TEXT_DOMAIN),
            __('Category Positions', WOOAPP_TEXT_DOMAIN),
            'manage_options',
            'wooapp-category-positions',
            array($this, 'renderCategoryPositionsPage')
        );

        // Add submenu for app banners
        add_submenu_page(
            'wooapp-settings',
            __('App Banners', WOOAPP_TEXT_DOMAIN),
            __('App Banners', WOOAPP_TEXT_DOMAIN),
            'manage_options',
            'wooapp-app-banners',
            array($this, 'renderBannersPage')
        );
    }

    /**
     * Initialize settings
     */
    public function initSettings()
    {
        // Register settings here
    }

    /**
     * Enqueue admin styles and scripts
     */
    public function enqueueAssets($hook_suffix)
    {
        // Only enqueue on our specific pages
        if ($hook_suffix !== 'wooapp-settings_page_wooapp-category-positions' && 
            $hook_suffix !== 'wooapp-settings_page_wooapp-app-banners') {
            return;
        }

        // Get plugin URL correctly (Admin.php is in src/Admin, so we need 3 levels up)
        $plugin_dir = dirname(dirname(dirname(__FILE__)));
        $plugin_url = plugins_url('', $plugin_dir . '/wooapp-setting-tools.php');
        
        // Enqueue CSS
        wp_enqueue_style(
            'wooapp-category-positions',
            $plugin_url . '/assets/css/category-positions.css',
            array(),
            Constants::PLUGIN_VERSION
        );

        // Enqueue banner CSS
        wp_enqueue_style(
            'wooapp-app-banners',
            $plugin_url . '/assets/css/app-banners.css',
            array(),
            Constants::PLUGIN_VERSION . '.' . time()
        );

        // Enqueue JavaScript
        wp_enqueue_script(
            'wooapp-category-positions',
            $plugin_url . '/assets/js/category-positions.js',
            array(),
            Constants::PLUGIN_VERSION,
            true
        );
        
        // Enqueue banner JavaScript
        wp_enqueue_script(
            'wooapp-app-banners',
            $plugin_url . '/assets/js/app-banners.js',
            array('jquery', 'jquery-ui-sortable'),
            Constants::PLUGIN_VERSION,
            true
        );

        // Localize script with nonce and admin URL
        wp_localize_script(
            'wooapp-category-positions',
            'wooappCategoryPositions',
            array(
                'deleteNonce' => wp_create_nonce('wooapp_delete_position'),
                'adminPostUrl' => admin_url('admin-post.php'),
            )
        );

        // Localize banner script
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

        // Enqueue media library scripts
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

    /**
     * Render category positions configuration page
     */
    public function renderCategoryPositionsPage()
    {
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to manage this option.', WOOAPP_TEXT_DOMAIN));
        }

        $positions = get_option(Constants::OPTION_CATEGORY_POSITIONS, array());
        $mapping = get_option(Constants::OPTION_CATEGORY_POSITION_MAPPING, array());
        $all_categories = CategoryPositionManager::get_product_categories();

        // Default demo positions if none exist
        if (empty($positions)) {
            $positions = array(
                'banner' => __('Banner Section', WOOAPP_TEXT_DOMAIN),
                'featured' => __('Featured Products', WOOAPP_TEXT_DOMAIN),
                'sidebar' => __('Sidebar', WOOAPP_TEXT_DOMAIN),
            );
        }

        // Get current position from URL parameter or use first position
        $current_position = isset($_GET['position']) ? sanitize_key($_GET['position']) : key($positions);
        $current_position_label = isset($positions[$current_position]) ? $positions[$current_position] : '';
        $current_position_categories = isset($mapping[$current_position]) ? $mapping[$current_position] : array();

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <?php if (isset($_GET['settings-updated'])) : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e('Settings saved successfully.', WOOAPP_TEXT_DOMAIN); ?></p>
                </div>
            <?php endif; ?>

            <div id="wooapp-positions-container" class="wooapp-positions-container wooapp-layout-horizontal">
                <!-- Position Management Section (Left) -->
                <div class="wooapp-position-section">
                    <h2><?php esc_html_e('Positions', WOOAPP_TEXT_DOMAIN); ?></h2>
                    
                    <div class="wooapp-position-actions">
                        <input type="text" 
                               id="wooapp-new-position-key" 
                               class="wooapp-input"
                               placeholder="<?php esc_attr_e('Position Key (e.g., banner, featured)', WOOAPP_TEXT_DOMAIN); ?>"
                               title="<?php esc_attr_e('Position Key: Used internally for API calls. Must be lowercase, no spaces.', WOOAPP_TEXT_DOMAIN); ?>">
                        <input type="text" 
                               id="wooapp-new-position-label" 
                               class="wooapp-input"
                               placeholder="<?php esc_attr_e('Position Label (e.g., Banner Section)', WOOAPP_TEXT_DOMAIN); ?>"
                               title="<?php esc_attr_e('Position Label: Display name shown in the interface.', WOOAPP_TEXT_DOMAIN); ?>">
                        <button id="wooapp-create-position" class="button button-primary">
                            <?php esc_html_e('+ Create Position', WOOAPP_TEXT_DOMAIN); ?>
                        </button>
                    </div>

                    <div id="wooapp-positions-list" class="wooapp-positions-list">
                        <?php foreach ($positions as $pos_key => $pos_label) : ?>
                            <div class="wooapp-position-item <?php echo $current_position === $pos_key ? 'active' : ''; ?>" data-position="<?php echo esc_attr($pos_key); ?>" data-position-url="<?php echo esc_url(admin_url('admin.php?page=wooapp-category-positions&position=' . $pos_key)); ?>">
                                <span class="wooapp-position-name">
                                    <?php echo esc_html($pos_label); ?>
                                </span>
                                <button type="button" class="button button-link-delete wooapp-delete-position" data-position-key="<?php echo esc_attr($pos_key); ?>">
                                    <?php esc_html_e('Delete', WOOAPP_TEXT_DOMAIN); ?>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Position Configuration Section (Right) -->
                <div class="wooapp-position-config-section">
                    <h2><?php esc_html_e('Configure "' . esc_html($current_position_label) . '" Position', WOOAPP_TEXT_DOMAIN); ?></h2>

                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="wooapp-position-config-form">
                        <?php wp_nonce_field('wooapp_save_category_positions'); ?>
                        <input type="hidden" name="action" value="wooapp_save_category_positions">
                        <input type="hidden" name="current_position" value="<?php echo esc_attr($current_position); ?>">

                        <div class="wooapp-position-field">
                            <label><?php esc_html_e('Position Key', WOOAPP_TEXT_DOMAIN); ?></label>
                            <p class="wooapp-position-key-display"><code><?php echo esc_html($current_position); ?></code></p>
                        </div>

                        <div class="wooapp-position-field">
                            <label for="position-label"><?php esc_html_e('Position Label', WOOAPP_TEXT_DOMAIN); ?></label>
                            <input type="text" 
                                   id="position-label" 
                                   name="position_label" 
                                   class="regular-text"
                                   value="<?php echo esc_attr($current_position_label); ?>"
                                   required>
                            <p class="description"><?php esc_html_e('Display name for this position.', WOOAPP_TEXT_DOMAIN); ?></p>
                        </div>

                        <div class="wooapp-position-field">
                            <label><?php esc_html_e('Select Categories', WOOAPP_TEXT_DOMAIN); ?></label>
                            <select id="position-categories" 
                                    name="position_categories[]" 
                                    class="wooapp-category-select" 
                                    multiple="multiple">
                                <?php self::render_category_options_with_collapse($all_categories, $current_position_categories); ?>
                            </select>
                            <small><?php esc_html_e('Click category name to select/deselect. Click +/- icon to expand/collapse parent categories.', WOOAPP_TEXT_DOMAIN); ?></small>
                        </div>

                        <div class="wooapp-position-actions-buttons">
                            <button type="submit" class="button button-primary">
                                <?php esc_html_e('Save Position', WOOAPP_TEXT_DOMAIN); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Handle saving category positions
     */
    public function handleSaveCategoryPositions()
    {
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to manage this option.', WOOAPP_TEXT_DOMAIN));
        }

        // Verify nonce
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'wooapp_save_category_positions')) {
            wp_die(__('Security check failed.', WOOAPP_TEXT_DOMAIN));
        }

        // Check if adding a new position
        if (isset($_POST['add_new_position']) && $_POST['add_new_position']) {
            $position_key = isset($_POST['new_position_key']) ? sanitize_key($_POST['new_position_key']) : '';
            $position_label = isset($_POST['new_position_label']) ? sanitize_text_field($_POST['new_position_label']) : '';

            if (!empty($position_key) && !empty($position_label)) {
                // Add new position
                $positions = get_option(Constants::OPTION_CATEGORY_POSITIONS, array());
                $positions[$position_key] = $position_label;
                update_option(Constants::OPTION_CATEGORY_POSITIONS, $positions);
                
                // Redirect to the new position's configuration page
                wp_redirect(add_query_arg(array('settings-updated' => 'true', 'position' => $position_key), admin_url('admin.php?page=wooapp-category-positions')));
                exit;
            }
        }

        // Get current position from form
        $current_position = isset($_POST['current_position']) ? sanitize_key($_POST['current_position']) : '';
        
        if (!empty($current_position)) {
            // Update single position label
            $position_label = isset($_POST['position_label']) ? sanitize_text_field($_POST['position_label']) : '';
            
            $positions = get_option(Constants::OPTION_CATEGORY_POSITIONS, array());
            if (isset($positions[$current_position])) {
                $positions[$current_position] = $position_label;
                update_option(Constants::OPTION_CATEGORY_POSITIONS, $positions);
            }

            // Update position categories
            $position_categories = isset($_POST['position_categories']) && is_array($_POST['position_categories']) ? 
                array_map('intval', $_POST['position_categories']) : array();
            
            $mapping = get_option(Constants::OPTION_CATEGORY_POSITION_MAPPING, array());
            if (!empty($position_categories)) {
                $mapping[$current_position] = $position_categories;
            } else {
                if (isset($mapping[$current_position])) {
                    unset($mapping[$current_position]);
                }
            }
            update_option(Constants::OPTION_CATEGORY_POSITION_MAPPING, $mapping);

            wp_redirect(add_query_arg(array('settings-updated' => 'true', 'position' => $current_position), admin_url('admin.php?page=wooapp-category-positions')));
        } else {
            wp_redirect(add_query_arg('settings-updated', 'true', admin_url('admin.php?page=wooapp-category-positions')));
        }
        exit;
    }

    /**
     * Handle deleting a position
     */
    public function handleDeletePosition()
    {
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to manage this option.', WOOAPP_TEXT_DOMAIN));
        }

        // Verify nonce
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'wooapp_delete_position')) {
            wp_die(__('Security check failed.', WOOAPP_TEXT_DOMAIN));
        }

        // Get and validate position key
        $position_key = isset($_POST['position_key']) ? sanitize_key($_POST['position_key']) : '';

        if (empty($position_key)) {
            wp_redirect(add_query_arg('page', 'wooapp-category-positions', admin_url('admin.php')));
            exit;
        }

        // Delete from positions option
        $positions = get_option(Constants::OPTION_CATEGORY_POSITIONS, array());
        if (isset($positions[$position_key])) {
            unset($positions[$position_key]);
            update_option(Constants::OPTION_CATEGORY_POSITIONS, $positions);
        }

        // Delete from mapping option
        $mapping = get_option(Constants::OPTION_CATEGORY_POSITION_MAPPING, array());
        if (isset($mapping[$position_key])) {
            unset($mapping[$position_key]);
            update_option(Constants::OPTION_CATEGORY_POSITION_MAPPING, $mapping);
        }

        // Redirect back to settings page with success message
        wp_redirect(add_query_arg('settings-updated', 'true', admin_url('admin.php?page=wooapp-category-positions')));
        exit;
    }

    /**
     * Render category options with collapse/expand functionality
     *
     * @param array $categories Category structure with hierarchy info
     * @param array $selected Selected category IDs
     */
    private static function render_category_options_with_collapse($categories, $selected = array())
    {
        foreach ($categories as $cat_id => $cat_data) {
            $indent = isset($cat_data['depth']) ? str_repeat('&nbsp;&nbsp;', $cat_data['depth']) : '';
            $cat_name = isset($cat_data['name']) ? $cat_data['name'] : $cat_data;
            $has_children = isset($cat_data['has_children']) ? $cat_data['has_children'] : false;
            $depth = isset($cat_data['depth']) ? $cat_data['depth'] : 0;
            
            $is_selected = in_array($cat_id, $selected) ? 'selected="selected"' : '';
            $data_attrs = 'data-original-text="' . esc_attr($cat_name) . '"';
            
            if ($has_children) {
                $data_attrs .= ' data-parent="' . esc_attr($cat_id) . '" data-depth="' . esc_attr($depth) . '"';
            } else if ($depth > 0) {
                $parent_id = isset($cat_data['parent']) ? $cat_data['parent'] : 0;
                $data_attrs .= ' data-parent-id="' . esc_attr($parent_id) . '" data-depth="' . esc_attr($depth) . '"';
            }
            
            $option_text = esc_html($cat_name);
            
            echo '<option value="' . esc_attr($cat_id) . '" ' . $is_selected . ' ' . $data_attrs . ' class="wooapp-category-option' . ($has_children ? ' wooapp-parent' : '') . ' wooapp-depth-' . esc_attr($depth) . '">';
            echo $indent . $option_text;
            echo "</option>\n";
        }
    }

    /**
     * Render app banners configuration page
     */
    public function renderBannersPage()
    {
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to manage this option.', WOOAPP_TEXT_DOMAIN));
        }

        $groups = BannerManager::get_groups();
        $current_group = isset($_GET['group']) ? sanitize_text_field($_GET['group']) : (isset($groups[0]) ? $groups[0] : 'default');
        $banners = BannerManager::get_banners($current_group);
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <?php if (isset($_GET['settings-updated'])) : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e('Settings saved successfully.', WOOAPP_TEXT_DOMAIN); ?></p>
                </div>
            <?php endif; ?>

            <div id="wooapp-banners-container" class="wooapp-banners-container wooapp-layout-horizontal">
                <!-- Group Management Section (Left) -->
                <div class="wooapp-group-section">
                    <h2><?php esc_html_e('Banner Groups', WOOAPP_TEXT_DOMAIN); ?></h2>
                    
                    <div class="wooapp-group-actions">
                        <input type="text" 
                               id="wooapp-new-group-name" 
                               class="wooapp-input"
                               placeholder="<?php esc_attr_e('Enter group name (e.g., home, category, product)', WOOAPP_TEXT_DOMAIN); ?>">
                        <button id="wooapp-create-group" class="button button-primary">
                            <?php esc_html_e('+ Create Group', WOOAPP_TEXT_DOMAIN); ?>
                        </button>
                    </div>

                    <div id="wooapp-groups-list" class="wooapp-groups-list">
                        <?php foreach ($groups as $group) : ?>
                            <div class="wooapp-group-item <?php echo $current_group === $group ? 'active' : ''; ?>" data-group="<?php echo esc_attr($group); ?>" data-group-url="<?php echo esc_url(add_query_arg('group', $group)); ?>">
                                <span class="wooapp-group-name">
                                    <?php echo esc_html($group); ?>
                                </span>
                                <?php if ($group !== 'default') : ?>
                                    <button type="button" class="button button-link-delete wooapp-delete-group" data-group="<?php echo esc_attr($group); ?>">
                                        <?php esc_html_e('Delete', WOOAPP_TEXT_DOMAIN); ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Banner Management Section (Right) -->
                <div class="wooapp-banner-section">
                    <h2><?php esc_html_e('Banners for "' . esc_html($current_group) . '" Group', WOOAPP_TEXT_DOMAIN); ?></h2>

                    <div class="wooapp-banner-actions">
                        <button id="wooapp-add-banner" class="button button-primary" data-group="<?php echo esc_attr($current_group); ?>">
                            <?php esc_html_e('+ Add Banner to this Group', WOOAPP_TEXT_DOMAIN); ?>
                        </button>
                        <p class="description">
                            <?php esc_html_e('Upload banner images for this group. You can drag to reorder them.', WOOAPP_TEXT_DOMAIN); ?>
                        </p>
                    </div>

                    <div id="wooapp-banners-list" class="wooapp-banners-list" data-group="<?php echo esc_attr($current_group); ?>">
                        <?php if (!empty($banners)) : ?>
                            <?php foreach ($banners as $banner) : ?>
                                <?php $this->render_banner_item($banner); ?>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <p class="wooapp-no-banners">
                                <?php esc_html_e('No banners in this group yet. Click "Add Banner to this Group" to create one.', WOOAPP_TEXT_DOMAIN); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render a single banner item
     *
     * @param array $banner Banner data
     */
    private function render_banner_item($banner)
    {
        $banner_id = isset($banner['id']) ? $banner['id'] : '';
        $image_url = isset($banner['image_url']) ? $banner['image_url'] : '';
        $image_id = isset($banner['image_id']) ? $banner['image_id'] : 0;
        $deeplink = isset($banner['deeplink']) ? $banner['deeplink'] : '';
        $group = isset($banner['group']) ? $banner['group'] : 'default';
        
        ?>
        <div class="wooapp-banner-item" data-banner-id="<?php echo esc_attr($banner_id); ?>" data-group="<?php echo esc_attr($group); ?>">
            <div class="wooapp-banner-handle">
                <span class="dashicons dashicons-menu"></span>
            </div>

            <div class="wooapp-banner-preview">
                <?php if (!empty($image_url)) : ?>
                    <img src="<?php echo esc_url($image_url); ?>" alt="Banner" class="wooapp-banner-image-preview">
                <?php else : ?>
                    <div class="wooapp-banner-placeholder">
                        <?php esc_html_e('No image selected', WOOAPP_TEXT_DOMAIN); ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="wooapp-banner-content">
                <div class="wooapp-banner-field">
                    <label><?php esc_html_e('Banner Image', WOOAPP_TEXT_DOMAIN); ?></label>
                    <div class="wooapp-image-upload">
                        <input type="hidden" 
                               class="wooapp-banner-image-id" 
                               value="<?php echo esc_attr($image_id); ?>" 
                               data-banner-id="<?php echo esc_attr($banner_id); ?>">
                        <input type="hidden" 
                               class="wooapp-banner-image-url" 
                               value="<?php echo esc_attr($image_url); ?>" 
                               data-banner-id="<?php echo esc_attr($banner_id); ?>">
                        <button type="button" class="button wooapp-upload-image" data-banner-id="<?php echo esc_attr($banner_id); ?>">
                            <?php esc_html_e('Upload Image', WOOAPP_TEXT_DOMAIN); ?>
                        </button>
                        <?php if (!empty($image_id)) : ?>
                            <button type="button" class="button wooapp-remove-image" data-banner-id="<?php echo esc_attr($banner_id); ?>" style="margin-left: 5px;">
                                <?php esc_html_e('Remove', WOOAPP_TEXT_DOMAIN); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="wooapp-banner-field">
                    <label><?php esc_html_e('Deeplink (Optional)', WOOAPP_TEXT_DOMAIN); ?></label>
                    <input type="text" 
                           class="wooapp-banner-deeplink" 
                           value="<?php echo esc_attr($deeplink); ?>" 
                           placeholder="<?php esc_attr_e('e.g., wooapp://product/123 or https://example.com', WOOAPP_TEXT_DOMAIN); ?>"
                           data-banner-id="<?php echo esc_attr($banner_id); ?>">
                    <p class="description">
                        <?php esc_html_e('Users will be redirected to this URL when clicking the banner.', WOOAPP_TEXT_DOMAIN); ?>
                    </p>
                </div>

                <div class="wooapp-banner-actions-item">
                    <button type="button" class="button button-link-delete wooapp-delete-banner" data-banner-id="<?php echo esc_attr($banner_id); ?>">
                        <?php esc_html_e('Delete', WOOAPP_TEXT_DOMAIN); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Handle banner upload via AJAX
     */
    public function handleBannerUpload()
    {
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to upload banners.', WOOAPP_TEXT_DOMAIN)));
        }

        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wooapp_upload_banner')) {
            wp_send_json_error(array('message' => __('Security check failed.', WOOAPP_TEXT_DOMAIN)));
        }

        // Check if files were uploaded
        if (empty($_FILES['file'])) {
            wp_send_json_error(array('message' => __('No file provided.', WOOAPP_TEXT_DOMAIN)));
        }

        // Handle file upload
        $upload = wp_handle_upload(
            $_FILES['file'],
            array('test_form' => false)
        );

        if (isset($upload['error'])) {
            wp_send_json_error(array('message' => $upload['error']));
        }

        // Create attachment
        $attachment_id = wp_insert_attachment(
            array(
                'guid' => $upload['url'],
                'post_mime_type' => $upload['type'],
                'post_title' => sanitize_file_name($_FILES['file']['name']),
                'post_content' => '',
                'post_status' => 'inherit',
            ),
            $upload['file']
        );

        if (is_wp_error($attachment_id)) {
            wp_send_json_error(array('message' => $attachment_id->get_error_message()));
        }

        // Generate attachment metadata
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $attach_data = wp_generate_attachment_metadata($attachment_id, $upload['file']);
        wp_update_attachment_metadata($attachment_id, $attach_data);

        wp_send_json_success(array(
            'id' => $attachment_id,
            'url' => $upload['url'],
        ));
    }

    /**
     * Handle banner reorder via AJAX
     */
    public function handleBannerReorder()
    {
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to reorder banners.', WOOAPP_TEXT_DOMAIN)));
        }

        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wooapp_reorder_banners')) {
            wp_send_json_error(array('message' => __('Security check failed.', WOOAPP_TEXT_DOMAIN)));
        }

        // Get banner IDs
        $banner_ids = isset($_POST['banner_ids']) ? (array) $_POST['banner_ids'] : array();
        
        if (empty($banner_ids)) {
            wp_send_json_error(array('message' => __('No banners provided.', WOOAPP_TEXT_DOMAIN)));
        }

        // Sanitize banner IDs
        $banner_ids = array_map('sanitize_key', $banner_ids);

        // Update order
        if (BannerManager::reorder_banners($banner_ids)) {
            wp_send_json_success(array('message' => __('Banners reordered successfully.', WOOAPP_TEXT_DOMAIN)));
        } else {
            wp_send_json_error(array('message' => __('Failed to reorder banners.', WOOAPP_TEXT_DOMAIN)));
        }
    }

    /**
     * Handle saving banners
     */
    public function handleSaveBanners()
    {
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to manage this option.', WOOAPP_TEXT_DOMAIN));
        }

        // Verify nonce
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'wooapp_save_banners')) {
            wp_die(__('Security check failed.', WOOAPP_TEXT_DOMAIN));
        }

        // Get all banners
        $banners = BannerManager::get_banners();
        
        // Update deeplinks and images based on POST data
        if (isset($_POST['banners']) && is_array($_POST['banners'])) {
            foreach ($_POST['banners'] as $banner_id => $banner_data) {
                $banner_id = sanitize_key($banner_id);
                
                $update_data = array();
                
                if (isset($banner_data['deeplink'])) {
                    $update_data['deeplink'] = sanitize_text_field($banner_data['deeplink']);
                }
                
                if (isset($banner_data['image_id'])) {
                    $update_data['image_id'] = (int) $banner_data['image_id'];
                }
                
                if (isset($banner_data['image_url'])) {
                    $update_data['image_url'] = sanitize_url($banner_data['image_url']);
                }
                
                if (!empty($update_data)) {
                    BannerManager::update_banner($banner_id, $update_data);
                }
            }
        }

        wp_redirect(add_query_arg('settings-updated', 'true', admin_url('admin.php?page=wooapp-app-banners')));
        exit;
    }

    /**
     * Handle deleting a banner
     */
    public function handleDeleteBanner()
    {
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to manage this option.', WOOAPP_TEXT_DOMAIN));
        }

        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wooapp_delete_banner')) {
            wp_die(__('Security check failed.', WOOAPP_TEXT_DOMAIN));
        }

        // Get and validate banner ID
        $banner_id = isset($_POST['banner_id']) ? sanitize_key($_POST['banner_id']) : '';

        if (empty($banner_id)) {
            wp_send_json_error(array('message' => __('No banner ID provided.', WOOAPP_TEXT_DOMAIN)));
        }

        // Delete the banner
        if (BannerManager::delete_banner($banner_id)) {
            wp_send_json_success(array('message' => __('Banner deleted successfully.', WOOAPP_TEXT_DOMAIN)));
        } else {
            wp_send_json_error(array('message' => __('Failed to delete banner.', WOOAPP_TEXT_DOMAIN)));
        }
    }

    /**
     * Handle saving banner data via AJAX
     */
    public function handleSaveBannerData()
    {
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission.', WOOAPP_TEXT_DOMAIN)));
        }

        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wooapp_upload_banner')) {
            wp_send_json_error(array('message' => __('Security check failed.', WOOAPP_TEXT_DOMAIN)));
        }

        $banner_id = isset($_POST['banner_id']) ? sanitize_key($_POST['banner_id']) : '';
        
        if (empty($banner_id)) {
            wp_send_json_error(array('message' => __('No banner ID provided.', WOOAPP_TEXT_DOMAIN)));
        }

        // Prepare update data
        $update_data = array();
        
        if (isset($_POST['image_id'])) {
            $update_data['image_id'] = (int) $_POST['image_id'];
        }
        
        if (isset($_POST['image_url'])) {
            $update_data['image_url'] = sanitize_url($_POST['image_url']);
        }
        
        if (isset($_POST['deeplink'])) {
            $update_data['deeplink'] = sanitize_text_field($_POST['deeplink']);
        }
        
        if (isset($_POST['group'])) {
            $update_data['group'] = sanitize_text_field($_POST['group']);
        }

        // Check if banner exists, if not create it
        $existing_banner = BannerManager::get_banner($banner_id);
        
        if ($existing_banner) {
            // Update existing banner
            if (BannerManager::update_banner($banner_id, $update_data)) {
                wp_send_json_success(array('message' => __('Banner updated successfully.', WOOAPP_TEXT_DOMAIN)));
            } else {
                wp_send_json_error(array('message' => __('Failed to update banner.', WOOAPP_TEXT_DOMAIN)));
            }
        } else {
            // Create new banner
            $update_data['id'] = $banner_id;
            $result = BannerManager::add_banner($update_data);
            
            if ($result) {
                wp_send_json_success(array('message' => __('Banner created successfully.', WOOAPP_TEXT_DOMAIN)));
            } else {
                wp_send_json_error(array('message' => __('Failed to create banner.', WOOAPP_TEXT_DOMAIN)));
            }
        }
    }

    /**
     * Handle creating a banner group via AJAX
     */
    public function handleCreateBannerGroup()
    {
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission.', WOOAPP_TEXT_DOMAIN)));
        }

        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wooapp_banners_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', WOOAPP_TEXT_DOMAIN)));
        }

        $group_name = isset($_POST['group_name']) ? sanitize_text_field($_POST['group_name']) : '';
        
        if (empty($group_name)) {
            wp_send_json_error(array('message' => __('Group name is required.', WOOAPP_TEXT_DOMAIN)));
        }

        if (BannerManager::add_group($group_name)) {
            wp_send_json_success(array(
                'message' => __('Group created successfully.', WOOAPP_TEXT_DOMAIN),
                'group' => $group_name,
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to create group. Group may already exist.', WOOAPP_TEXT_DOMAIN)));
        }
    }

    /**
     * Handle deleting a banner group via AJAX
     */
    public function handleDeleteBannerGroup()
    {
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission.', WOOAPP_TEXT_DOMAIN)));
        }

        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wooapp_banners_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', WOOAPP_TEXT_DOMAIN)));
        }

        $group_name = isset($_POST['group_name']) ? sanitize_text_field($_POST['group_name']) : '';
        
        if (empty($group_name)) {
            wp_send_json_error(array('message' => __('Group name is required.', WOOAPP_TEXT_DOMAIN)));
        }

        if ($group_name === 'default') {
            wp_send_json_error(array('message' => __('Cannot delete default group.', WOOAPP_TEXT_DOMAIN)));
        }

        if (BannerManager::delete_group($group_name)) {
            wp_send_json_success(array('message' => __('Group deleted successfully.', WOOAPP_TEXT_DOMAIN)));
        } else {
            wp_send_json_error(array('message' => __('Failed to delete group.', WOOAPP_TEXT_DOMAIN)));
        }
    }

    /**
     * Handle getting banner groups via AJAX
     */
    public function handleGetBannerGroups()
    {
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission.', WOOAPP_TEXT_DOMAIN)));
        }

        $groups = BannerManager::get_groups();
        wp_send_json_success(array('groups' => $groups));
    }
}

