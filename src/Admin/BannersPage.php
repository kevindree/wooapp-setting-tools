<?php

/**
 * @package WooApp\Admin
 */

namespace WooApp\Admin;

use WooApp\Services\BannerManager;
use WooApp\Common\Constants;

defined('ABSPATH') || exit;

/**
 * Banners Admin Page
 * Handles rendering, AJAX handlers and form processing for app banners
 */
class BannersPage
{
    /**
     * Register hooks for banners
     */
    public function registerHooks()
    {
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
     * Verify AJAX request: check permissions and nonce
     *
     * @param string $nonce_action The nonce action to verify
     * @param string $nonce_key    The POST key containing the nonce (default: 'nonce')
     * @return void Sends JSON error and dies if verification fails
     */
    private function verifyAjaxRequest($nonce_action, $nonce_key = 'nonce')
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission.', WOOAPP_TEXT_DOMAIN)));
        }

        if (!isset($_POST[$nonce_key]) || !wp_verify_nonce($_POST[$nonce_key], $nonce_action)) {
            wp_send_json_error(array('message' => __('Security check failed.', WOOAPP_TEXT_DOMAIN)));
        }
    }

    /**
     * Render app banners configuration page
     */
    public function render()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to manage this option.', WOOAPP_TEXT_DOMAIN));
        }

        $groups = BannerManager::get_groups();
        $group_keys = array_keys($groups);
        $current_group = isset($_GET['group']) ? sanitize_key($_GET['group']) : (isset($group_keys[0]) ? $group_keys[0] : 'default');
        $current_group_label = isset($groups[$current_group]) ? $groups[$current_group] : $current_group;
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
                               placeholder="<?php esc_attr_e('Group Label (e.g., Home Page, Category Page)', WOOAPP_TEXT_DOMAIN); ?>"
                               title="<?php esc_attr_e('Enter a display name. A unique key will be generated automatically.', WOOAPP_TEXT_DOMAIN); ?>">
                        <button id="wooapp-create-group" class="button button-primary">
                            <?php esc_html_e('+ Create Group', WOOAPP_TEXT_DOMAIN); ?>
                        </button>
                    </div>

                    <div id="wooapp-groups-list" class="wooapp-groups-list">
                        <?php foreach ($groups as $group_key => $group_label) : ?>
                            <div class="wooapp-group-item <?php echo $current_group === $group_key ? 'active' : ''; ?>" data-group="<?php echo esc_attr($group_key); ?>" data-group-url="<?php echo esc_url(add_query_arg('group', $group_key)); ?>">
                                <span class="wooapp-group-name">
                                    <?php echo esc_html($group_label); ?>
                                </span>
                                <?php if ($group_key !== 'default') : ?>
                                    <button type="button" class="button button-link-delete wooapp-delete-group" data-group="<?php echo esc_attr($group_key); ?>">
                                        <?php esc_html_e('Delete', WOOAPP_TEXT_DOMAIN); ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Banner Management Section (Right) -->
                <div class="wooapp-banner-section">
                    <h2><?php echo esc_html(sprintf(__('Banners for "%s" Group', WOOAPP_TEXT_DOMAIN), $current_group_label)); ?></h2>

                    <div class="wooapp-group-key-field">
                        <label><?php esc_html_e('Group Key', WOOAPP_TEXT_DOMAIN); ?></label>
                        <p class="wooapp-group-key-display"><code><?php echo esc_html($current_group); ?></code></p>
                    </div>

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
                                <?php $this->renderBannerItem($banner); ?>
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
    private function renderBannerItem($banner)
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
        $this->verifyAjaxRequest('wooapp_upload_banner');

        if (empty($_FILES['file'])) {
            wp_send_json_error(array('message' => __('No file provided.', WOOAPP_TEXT_DOMAIN)));
        }

        $upload = wp_handle_upload(
            $_FILES['file'],
            array('test_form' => false)
        );

        if (isset($upload['error'])) {
            wp_send_json_error(array('message' => $upload['error']));
        }

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
        $this->verifyAjaxRequest('wooapp_reorder_banners');

        $banner_ids = isset($_POST['banner_ids']) ? (array) $_POST['banner_ids'] : array();
        
        if (empty($banner_ids)) {
            wp_send_json_error(array('message' => __('No banners provided.', WOOAPP_TEXT_DOMAIN)));
        }

        $banner_ids = array_map('sanitize_key', $banner_ids);

        if (BannerManager::reorder_banners($banner_ids)) {
            wp_send_json_success(array('message' => __('Banners reordered successfully.', WOOAPP_TEXT_DOMAIN)));
        } else {
            wp_send_json_error(array('message' => __('Failed to reorder banners.', WOOAPP_TEXT_DOMAIN)));
        }
    }

    /**
     * Handle saving banners (form POST)
     */
    public function handleSaveBanners()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to manage this option.', WOOAPP_TEXT_DOMAIN));
        }

        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'wooapp_save_banners')) {
            wp_die(__('Security check failed.', WOOAPP_TEXT_DOMAIN));
        }

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
     * Handle deleting a banner via AJAX
     */
    public function handleDeleteBanner()
    {
        $this->verifyAjaxRequest('wooapp_delete_banner');

        $banner_id = isset($_POST['banner_id']) ? sanitize_key($_POST['banner_id']) : '';

        if (empty($banner_id)) {
            wp_send_json_error(array('message' => __('No banner ID provided.', WOOAPP_TEXT_DOMAIN)));
        }

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
        $this->verifyAjaxRequest('wooapp_upload_banner');

        $banner_id = isset($_POST['banner_id']) ? sanitize_key($_POST['banner_id']) : '';
        
        if (empty($banner_id)) {
            wp_send_json_error(array('message' => __('No banner ID provided.', WOOAPP_TEXT_DOMAIN)));
        }

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

        $existing_banner = BannerManager::get_banner($banner_id);
        
        if ($existing_banner) {
            if (BannerManager::update_banner($banner_id, $update_data)) {
                wp_send_json_success(array('message' => __('Banner updated successfully.', WOOAPP_TEXT_DOMAIN)));
            } else {
                wp_send_json_error(array('message' => __('Failed to update banner.', WOOAPP_TEXT_DOMAIN)));
            }
        } else {
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
        $this->verifyAjaxRequest('wooapp_banners_nonce');

        $group_label = isset($_POST['group_name']) ? sanitize_text_field($_POST['group_name']) : '';
        
        if (empty($group_label)) {
            wp_send_json_error(array('message' => __('Group name is required.', WOOAPP_TEXT_DOMAIN)));
        }

        $result = BannerManager::add_group($group_label);
        if ($result) {
            wp_send_json_success(array(
                'message' => __('Group created successfully.', WOOAPP_TEXT_DOMAIN),
                'group_key' => $result['key'],
                'group_label' => $result['label'],
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
        $this->verifyAjaxRequest('wooapp_banners_nonce');

        $group_key = isset($_POST['group_key']) ? sanitize_key($_POST['group_key']) : '';
        
        if (empty($group_key)) {
            wp_send_json_error(array('message' => __('Group key is required.', WOOAPP_TEXT_DOMAIN)));
        }

        if ($group_key === 'default') {
            wp_send_json_error(array('message' => __('Cannot delete default group.', WOOAPP_TEXT_DOMAIN)));
        }

        if (BannerManager::delete_group($group_key)) {
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
        $this->verifyAjaxRequest('wooapp_banners_nonce');

        $groups = BannerManager::get_groups();
        $formatted = array();
        foreach ($groups as $key => $label) {
            $formatted[] = array('key' => $key, 'label' => $label);
        }
        wp_send_json_success(array('groups' => $formatted));
    }
}
