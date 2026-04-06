<?php

/**
 * @package WooApp\Admin
 */

namespace WooApp\Admin;

use WooApp\Services\CategoryPositionManager;
use WooApp\Common\Constants;

defined('ABSPATH') || exit;

/**
 * Category Positions Admin Page
 * Handles rendering and form processing for category positions
 */
class CategoryPositionsPage
{
    /**
     * Register hooks for category positions
     */
    public function registerHooks()
    {
        add_action('admin_post_wooapp_save_category_positions', array($this, 'handleSave'));
        add_action('admin_post_wooapp_delete_position', array($this, 'handleDelete'));
    }

    /**
     * Render category positions configuration page
     */
    public function render()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to manage this option.', WOOAPP_TEXT_DOMAIN));
        }

        $positions = get_option(Constants::OPTION_CATEGORY_POSITIONS, array());
        $mapping = get_option(Constants::OPTION_CATEGORY_POSITION_MAPPING, array());
        $all_categories = CategoryPositionManager::get_product_categories();

        if (empty($positions)) {
            $positions = array(
                'banner' => __('Banner Section', WOOAPP_TEXT_DOMAIN),
                'featured' => __('Featured Products', WOOAPP_TEXT_DOMAIN),
                'sidebar' => __('Sidebar', WOOAPP_TEXT_DOMAIN),
            );
        }

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
                <input type="hidden" id="wooapp-delete-nonce" value="<?php echo esc_attr(wp_create_nonce('wooapp_delete_position')); ?>">
                
                <!-- Position Management Section (Left) -->
                <div class="wooapp-position-section">
                    <h2><?php esc_html_e('Positions', WOOAPP_TEXT_DOMAIN); ?></h2>
                    
                    <div class="wooapp-position-actions">
                        <input type="text" 
                               id="wooapp-new-position-label" 
                               class="wooapp-input"
                               placeholder="<?php esc_attr_e('Position Label (e.g., Banner Section, Featured Products)', WOOAPP_TEXT_DOMAIN); ?>"
                               title="<?php esc_attr_e('Enter a display name. A unique key will be generated automatically.', WOOAPP_TEXT_DOMAIN); ?>">
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
                                <?php self::renderCategoryOptions($all_categories, $current_position_categories); ?>
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
    public function handleSave()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to manage this option.', WOOAPP_TEXT_DOMAIN));
        }

        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'wooapp_save_category_positions')) {
            wp_die(__('Security check failed.', WOOAPP_TEXT_DOMAIN));
        }

        // Adding a new position
        if (isset($_POST['add_new_position']) && $_POST['add_new_position']) {
            $position_label = isset($_POST['new_position_label']) ? sanitize_text_field($_POST['new_position_label']) : '';

            if (!empty($position_label)) {
                $result = CategoryPositionManager::add_position($position_label);
                if ($result) {
                    wp_redirect(add_query_arg(array('settings-updated' => 'true', 'position' => $result['key']), admin_url('admin.php?page=wooapp-category-positions')));
                    exit;
                }
            }
        }

        // Updating existing position
        $current_position = isset($_POST['current_position']) ? sanitize_key($_POST['current_position']) : '';
        
        if (!empty($current_position)) {
            $position_label = isset($_POST['position_label']) ? sanitize_text_field($_POST['position_label']) : '';
            $position_categories = isset($_POST['position_categories']) && is_array($_POST['position_categories']) ? 
                array_map('intval', $_POST['position_categories']) : array();
            
            CategoryPositionManager::update_position($current_position, $position_label, $position_categories);

            wp_redirect(add_query_arg(array('settings-updated' => 'true', 'position' => $current_position), admin_url('admin.php?page=wooapp-category-positions')));
        } else {
            wp_redirect(add_query_arg('settings-updated', 'true', admin_url('admin.php?page=wooapp-category-positions')));
        }
        exit;
    }

    /**
     * Handle deleting a position
     */
    public function handleDelete()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to manage this option.', WOOAPP_TEXT_DOMAIN));
        }

        $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field($_POST['_wpnonce']) : '';
        if (!$nonce || !wp_verify_nonce($nonce, 'wooapp_delete_position')) {
            wp_die(__('Security check failed.', WOOAPP_TEXT_DOMAIN));
        }

        $position_key = isset($_POST['position_key']) ? sanitize_key($_POST['position_key']) : '';

        if (empty($position_key)) {
            wp_redirect(add_query_arg('page', 'wooapp-category-positions', admin_url('admin.php')));
            exit;
        }

        CategoryPositionManager::delete_position($position_key);

        wp_redirect(add_query_arg('settings-updated', 'true', admin_url('admin.php?page=wooapp-category-positions')));
        exit;
    }

    /**
     * Render category options with collapse/expand functionality
     *
     * @param array $categories Category structure with hierarchy info
     * @param array $selected Selected category IDs
     */
    private static function renderCategoryOptions($categories, $selected = array())
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
