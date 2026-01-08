<?php

/**
 * @package WooApp\Admin
 */

namespace WooApp\Admin;

use WooApp\Core\AbstractService;
use WooApp\Services\CategoryPositionManager;
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
        // Only enqueue on our category positions page
        if ($hook_suffix !== 'wooapp-settings_page_wooapp-category-positions') {
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

        // Enqueue JavaScript
        wp_enqueue_script(
            'wooapp-category-positions',
            $plugin_url . '/assets/js/category-positions.js',
            array(),
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
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <?php if (isset($_GET['settings-updated'])) : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e('Settings saved successfully.', WOOAPP_TEXT_DOMAIN); ?></p>
                </div>
            <?php endif; ?>

            <div class="wooapp-container-layout">
                <div class="wooapp-left-panel">
                    <h2><?php esc_html_e('Add New Position', WOOAPP_TEXT_DOMAIN); ?></h2>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="wooapp-add-position">
                        <?php wp_nonce_field('wooapp_save_category_positions'); ?>
                        <input type="hidden" name="action" value="wooapp_save_category_positions">
                        <input type="hidden" name="add_new_position" value="1">

                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="new_position_key"><?php esc_html_e('Position Key', WOOAPP_TEXT_DOMAIN); ?></label>
                                </th>
                                <td>
                                    <input type="text" 
                                           id="new_position_key" 
                                           name="new_position_key" 
                                           class="regular-text" 
                                           placeholder="<?php esc_attr_e('e.g., section_1', WOOAPP_TEXT_DOMAIN); ?>"
                                           required>
                                    <p class="description"><?php esc_html_e('Use lowercase letters, numbers, and underscores only.', WOOAPP_TEXT_DOMAIN); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="new_position_label"><?php esc_html_e('Position Label', WOOAPP_TEXT_DOMAIN); ?></label>
                                </th>
                                <td>
                                    <input type="text" 
                                           id="new_position_label" 
                                           name="new_position_label" 
                                           class="regular-text" 
                                           placeholder="<?php esc_attr_e('e.g., Featured Section', WOOAPP_TEXT_DOMAIN); ?>"
                                           required>
                                </td>
                            </tr>
                            <tr class="submit-row">
                                <th scope="row"></th>
                                <td>
                                    <p class="submit">
                                        <?php submit_button(__('Add Position', WOOAPP_TEXT_DOMAIN), 'secondary', 'submit', false); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </form>
                </div>

                <div class="wooapp-right-panel">
                    <h2><?php esc_html_e('Configure Positions', WOOAPP_TEXT_DOMAIN); ?></h2>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('wooapp_save_category_positions'); ?>
                        <input type="hidden" name="action" value="wooapp_save_category_positions">

                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th width="5%"></th>
                                    <th width="15%"><?php esc_html_e('Position Key', WOOAPP_TEXT_DOMAIN); ?></th>
                                    <th width="30%"><?php esc_html_e('Position Label', WOOAPP_TEXT_DOMAIN); ?></th>
                                    <th width="55%"><?php esc_html_e('Categories', WOOAPP_TEXT_DOMAIN); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($positions as $position_key => $position_label) : ?>
                                    <tr>
                                        <td style="text-align: center; vertical-align: top;">
                                            <button type="button" 
                                                    class="wooapp-delete-position" 
                                                    data-position-key="<?php echo esc_attr($position_key); ?>"
                                                    style="background: none; border: none; cursor: pointer; color: inherit; font-size: 16px; padding: 4px 8px;" 
                                                    title="<?php esc_attr_e('Delete', WOOAPP_TEXT_DOMAIN); ?>">
                                                <span class="dashicons dashicons-trash"></span>
                                            </button>
                                        </td>
                                        <td>
                                            <code><?php echo esc_html($position_key); ?></code>
                                            <input type="hidden" name="position_keys[]" value="<?php echo esc_attr($position_key); ?>">
                                        </td>
                                        <td>
                                            <input type="text" 
                                                   name="position_labels[<?php echo esc_attr($position_key); ?>]" 
                                                   value="<?php echo esc_attr($position_label); ?>" 
                                                   class="regular-text">
                                        </td>
                                        <td>
                                            <select name="position_categories[<?php echo esc_attr($position_key); ?>][]" 
                                                    class="wooapp-category-select" 
                                                    multiple="multiple">
                                                <?php self::render_category_options_with_collapse($all_categories, isset($mapping[$position_key]) ? $mapping[$position_key] : array()); ?>
                                            </select>
                                            <small><?php esc_html_e('Click category name to select/deselect. Click +/- icon to expand/collapse parent categories.', WOOAPP_TEXT_DOMAIN); ?></small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <p class="submit">
                            <?php submit_button(__('Save Positions', WOOAPP_TEXT_DOMAIN), 'primary', 'submit', false); ?>
                        </p>
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

        // Handle adding new position
        if (isset($_POST['add_new_position']) && $_POST['add_new_position']) {
            $position_key = isset($_POST['new_position_key']) ? sanitize_key($_POST['new_position_key']) : '';
            $position_label = isset($_POST['new_position_label']) ? sanitize_text_field($_POST['new_position_label']) : '';

            if (empty($position_key) || empty($position_label)) {
                wp_safe_remote_post(admin_url('admin.php'), array(
                    'blocking' => false,
                    'sslverify' => apply_filters('https_local_ssl_verify', false),
                ));
                wp_redirect(add_query_arg('page', 'wooapp-category-positions', admin_url('admin.php?page=wooapp-settings')));
                exit;
            }

            // Add new position
            $positions = get_option(Constants::OPTION_CATEGORY_POSITIONS, array());
            $positions[$position_key] = $position_label;
            update_option(Constants::OPTION_CATEGORY_POSITIONS, $positions);
        } else {
            // Update existing positions
            $position_keys = isset($_POST['position_keys']) ? (array) $_POST['position_keys'] : array();
            $position_labels = isset($_POST['position_labels']) ? $_POST['position_labels'] : array();
            $position_categories = isset($_POST['position_categories']) ? $_POST['position_categories'] : array();

            if (!empty($position_keys) && !empty($position_labels)) {
                // Update labels only for valid position keys
                $positions = get_option(Constants::OPTION_CATEGORY_POSITIONS, array());
                foreach ($position_keys as $position_key) {
                    $position_key = sanitize_key($position_key);
                    if (isset($position_labels[$position_key])) {
                        $positions[$position_key] = sanitize_text_field($position_labels[$position_key]);
                    }
                }
                update_option(Constants::OPTION_CATEGORY_POSITIONS, $positions);
            }

            // Update category mappings - only update positions that are in the form
            $mapping = get_option(Constants::OPTION_CATEGORY_POSITION_MAPPING, array());
            $position_keys = isset($_POST['position_keys']) ? (array) $_POST['position_keys'] : array();
            $position_categories = isset($_POST['position_categories']) ? $_POST['position_categories'] : array();

            // Update mappings for all positions submitted in the form
            foreach ($position_keys as $position_key) {
                $position_key = sanitize_key($position_key);
                
                if (isset($position_categories[$position_key]) && is_array($position_categories[$position_key]) && !empty($position_categories[$position_key])) {
                    // This position has categories selected
                    $mapping[$position_key] = array_map('intval', $position_categories[$position_key]);
                } else {
                    // This position has no categories selected, clear its mapping
                    if (isset($mapping[$position_key])) {
                        unset($mapping[$position_key]);
                    }
                }
            }
            update_option(Constants::OPTION_CATEGORY_POSITION_MAPPING, $mapping);
        }

        // Redirect back to settings page
        wp_safe_remote_post(admin_url('admin.php'), array(
            'blocking' => false,
            'sslverify' => apply_filters('https_local_ssl_verify', false),
        ));
        wp_redirect(add_query_arg('settings-updated', 'true', admin_url('admin.php?page=wooapp-category-positions')));
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
}

