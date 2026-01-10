(function($) {
    'use strict';

    // Define string translations
    var bannerStrings = {
        selectImage: 'Select Banner Image',
        useImage: 'Use Image',
        bannerImage: 'Banner Image',
        uploadImage: 'Upload Image',
        remove: 'Remove',
        deeplink: 'Deeplink (Optional)',
        deeplinkPlaceholder: 'e.g., wooapp://product/123 or https://example.com',
        deeplinkDescription: 'Users will be redirected to this URL when clicking the banner.',
        delete: 'Delete',
        noImage: 'No image selected',
        noBanners: 'No banners in this group yet. Click "Add Banner to this Group" to create one.',
        confirmDeleteBanner: 'Are you sure you want to delete this banner?',
        confirmDeleteGroup: 'Are you sure you want to delete this group and all its banners?',
        deleteError: 'Error deleting banner',
        deleteGroupError: 'Error deleting group'
    };

    var WooAppBanners = {
        init: function() {
            this.cacheElements();
            this.bindEvents();
            this.initSortable();
        },

        cacheElements: function() {
            this.$container = $('#wooapp-banners-container');
            this.$groupsList = $('#wooapp-groups-list');
            this.$list = $('#wooapp-banners-list');
            this.$addBannerBtn = $('#wooapp-add-banner');
            this.$createGroupBtn = $('#wooapp-create-group');
            this.$newGroupInput = $('#wooapp-new-group-name');
        },

        bindEvents: function() {
            var self = this;

            // Create group button
            this.$createGroupBtn.on('click', function(e) {
                e.preventDefault();
                self.createGroup();
            });

            // Handle Enter key in group name input
            this.$newGroupInput.on('keypress', function(e) {
                if (e.which === 13) { // Enter key
                    e.preventDefault();
                    self.createGroup();
                }
            });

            // Group item click - switch group
            $(document).on('click', '.wooapp-group-item', function(e) {
                // Only navigate if the click is not on the delete button
                if ($(e.target).hasClass('wooapp-delete-group') || $(e.target).closest('.wooapp-delete-group').length) {
                    return;
                }
                var url = $(this).data('group-url');
                if (url) {
                    window.location.href = url;
                }
            });

            // Delete group button
            $(document).on('click', '.wooapp-delete-group', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var group = $(this).data('group');
                if (confirm(bannerStrings.confirmDeleteGroup)) {
                    self.deleteGroup(group);
                }
            });

            // Add banner button
            this.$addBannerBtn.on('click', function(e) {
                e.preventDefault();
                var group = $(this).data('group');
                self.addBanner(group);
            });

            // Upload image button
            $(document).on('click', '.wooapp-upload-image', function(e) {
                e.preventDefault();
                var bannerId = $(this).data('banner-id');
                self.uploadImage(bannerId);
            });

            // Remove image button
            $(document).on('click', '.wooapp-remove-image', function(e) {
                e.preventDefault();
                var bannerId = $(this).data('banner-id');
                self.removeImage(bannerId);
            });

            // Delete banner button
            $(document).on('click', '.wooapp-delete-banner', function(e) {
                e.preventDefault();
                var bannerId = $(this).data('banner-id');
                if (confirm(bannerStrings.confirmDeleteBanner)) {
                    self.deleteBanner(bannerId);
                }
            });

            // Save deeplink changes
            $(document).on('change', '.wooapp-banner-deeplink', function() {
                var bannerId = $(this).data('banner-id');
                var deeplink = $(this).val();
                self.saveBannerData(bannerId, { deeplink: deeplink });
            });
        },

        initSortable: function() {
            var self = this;

            this.$list.sortable({
                items: '.wooapp-banner-item',
                handle: '.wooapp-banner-handle',
                placeholder: 'ui-sortable-placeholder',
                axis: 'y',
                opacity: 0.8,
                update: function() {
                    self.saveBannerOrder();
                }
            });
        },

        createGroup: function() {
            var self = this;
            var groupName = this.$newGroupInput.val().trim();

            if (!groupName) {
                alert('Please enter a group name');
                return;
            }

            var nonce = wooappBanners ? wooappBanners.bannersNonce : '';
            var ajaxUrl = wooappBanners ? wooappBanners.ajaxUrl : '';

            if (!nonce || !ajaxUrl) {
                console.error('Missing required data for AJAX request');
                return;
            }

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wooapp_create_banner_group',
                    nonce: nonce,
                    group_name: groupName
                },
                success: function(response) {
                    if (response.success) {
                        // Add group to list
                        var groupHtml = `
                            <div class="wooapp-group-item" data-group="${groupName}">
                                <span class="wooapp-group-name">
                                    <a href="?page=wooapp-app-banners&group=${groupName}" class="wooapp-group-link">
                                        ${groupName}
                                    </a>
                                </span>
                                <button type="button" class="button button-link-delete wooapp-delete-group" data-group="${groupName}">
                                    Delete
                                </button>
                            </div>
                        `;
                        self.$groupsList.append(groupHtml);
                        self.$newGroupInput.val('').focus();
                    } else {
                        alert(response.data.message || 'Failed to create group');
                    }
                },
                error: function(xhr, status, error) {
                    alert('Error creating group: ' + error);
                    console.error('AJAX error:', error);
                }
            });
        },

        deleteGroup: function(groupName) {
            var self = this;

            var nonce = wooappBanners ? wooappBanners.bannersNonce : '';
            var ajaxUrl = wooappBanners ? wooappBanners.ajaxUrl : '';

            if (!nonce || !ajaxUrl) {
                console.error('Missing required data for delete');
                return;
            }

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wooapp_delete_banner_group',
                    nonce: nonce,
                    group_name: groupName
                },
                success: function(response) {
                    if (response.success) {
                        self.$groupsList.find(`[data-group="${groupName}"]`).fadeOut(300, function() {
                            $(this).remove();
                            // Redirect to first group
                            window.location.href = '?page=wooapp-app-banners';
                        });
                    } else {
                        alert(response.data.message || bannerStrings.deleteGroupError);
                    }
                },
                error: function(xhr, status, error) {
                    alert(bannerStrings.deleteGroupError);
                    console.error('AJAX error:', error);
                }
            });
        },

        addBanner: function(group) {
            var self = this;

            // Check if wp.media exists
            if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
                alert('WordPress Media Library is not available');
                return;
            }

            // Open WordPress media uploader
            var mediaUploader = wp.media({
                title: bannerStrings.selectImage,
                button: {
                    text: bannerStrings.useImage
                },
                multiple: true,
                library: {
                    type: 'image'
                }
            });

            mediaUploader.on('select', function() {
                var attachments = mediaUploader.state().get('selection').toJSON();
                
                attachments.forEach(function(attachment) {
                    self.createBannerItem(attachment.id, attachment.url, group);
                });
            });

            mediaUploader.open();
        },

        createBannerItem: function(imageId, imageUrl, group) {
            var self = this;
            
            // Check if no banners message exists and remove it
            this.$list.find('.wooapp-no-banners').remove();

            // Generate unique banner ID
            var bannerId = 'banner_' + Date.now();

            // Create banner HTML
            var bannerHtml = `
                <div class="wooapp-banner-item" data-banner-id="${bannerId}" data-group="${group}">
                    <div class="wooapp-banner-handle">
                        <span class="dashicons dashicons-menu"></span>
                    </div>

                    <div class="wooapp-banner-preview">
                        <img src="${imageUrl}" alt="Banner" class="wooapp-banner-image-preview">
                    </div>

                    <div class="wooapp-banner-content">
                        <div class="wooapp-banner-field">
                            <label>${bannerStrings.bannerImage}</label>
                            <div class="wooapp-image-upload">
                                <input type="hidden" 
                                       class="wooapp-banner-image-id" 
                                       value="${imageId}" 
                                       data-banner-id="${bannerId}">
                                <input type="hidden" 
                                       class="wooapp-banner-image-url" 
                                       value="${imageUrl}" 
                                       data-banner-id="${bannerId}">
                                <button type="button" class="button wooapp-upload-image" data-banner-id="${bannerId}">
                                    ${bannerStrings.uploadImage}
                                </button>
                                <button type="button" class="button wooapp-remove-image" data-banner-id="${bannerId}" style="margin-left: 5px;">
                                    ${bannerStrings.remove}
                                </button>
                            </div>
                        </div>

                        <div class="wooapp-banner-field">
                            <label>${bannerStrings.deeplink}</label>
                            <input type="text" 
                                   class="wooapp-banner-deeplink" 
                                   value="" 
                                   placeholder="${bannerStrings.deeplinkPlaceholder}"
                                   data-banner-id="${bannerId}">
                            <p class="description">
                                ${bannerStrings.deeplinkDescription}
                            </p>
                        </div>

                        <div class="wooapp-banner-actions-item">
                            <button type="button" class="button button-link-delete wooapp-delete-banner" data-banner-id="${bannerId}">
                                ${bannerStrings.delete}
                            </button>
                        </div>
                    </div>
                </div>
            `;

            // Add to list
            this.$list.append(bannerHtml);

            // Save the new banner to database
            this.saveBannerData(bannerId, {
                image_id: imageId,
                image_url: imageUrl,
                group: group,
                deeplink: ''
            });
        },

        uploadImage: function(bannerId) {
            var self = this;

            // Check if wp.media exists
            if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
                alert('WordPress Media Library is not available');
                return;
            }

            var mediaUploader = wp.media({
                title: bannerStrings.selectImage,
                button: {
                    text: bannerStrings.useImage
                },
                multiple: false,
                library: {
                    type: 'image'
                }
            });

            mediaUploader.on('select', function() {
                var attachment = mediaUploader.state().get('selection').first().toJSON();
                
                var $item = self.$list.find(`[data-banner-id="${bannerId}"]`);
                
                if ($item.length === 0) {
                    console.error('Banner item not found:', bannerId);
                    return;
                }

                // Update image ID and URL
                $item.find('.wooapp-banner-image-id').val(attachment.id);
                $item.find('.wooapp-banner-image-url').val(attachment.url);
                
                // Update preview - replace placeholder with image
                var $preview = $item.find('.wooapp-banner-preview');
                $preview.html(`<img src="${attachment.url}" alt="Banner" class="wooapp-banner-image-preview">`);
                
                // Show remove button if it doesn't exist
                if (!$item.find('.wooapp-remove-image').length) {
                    $item.find('.wooapp-image-upload').append(
                        `<button type="button" class="button wooapp-remove-image" data-banner-id="${bannerId}" style="margin-left: 5px;">
                            ${bannerStrings.remove}
                        </button>`
                    );
                }

                // Save the change
                self.saveBannerData(bannerId, {
                    image_id: attachment.id,
                    image_url: attachment.url
                });
            });

            mediaUploader.open();
        },

        removeImage: function(bannerId) {
            var $item = this.$list.find(`[data-banner-id="${bannerId}"]`);
            
            // Update preview
            var $preview = $item.find('.wooapp-banner-preview');
            $preview.html(`<div class="wooapp-banner-placeholder">${bannerStrings.noImage}</div>`);
            
            // Clear image data
            $item.find('.wooapp-banner-image-id').val('');
            $item.find('.wooapp-banner-image-url').val('');
            
            // Remove the remove button
            $item.find('.wooapp-remove-image').remove();

            // Save the change
            this.saveBannerData(bannerId, {
                image_id: '',
                image_url: ''
            });
        },

        saveBannerData: function(bannerId, data) {
            // Get nonce from page
            var nonce = wooappBanners ? wooappBanners.uploadNonce : '';
            var ajaxUrl = wooappBanners ? wooappBanners.ajaxUrl : '';
            
            if (!nonce || !ajaxUrl) {
                console.error('Missing required data for AJAX request');
                return;
            }

            // Prepare AJAX request data
            var ajaxData = {
                action: 'wooapp_save_banner_data',
                nonce: nonce,
                banner_id: bannerId
            };
            
            // Merge data
            $.extend(ajaxData, data);

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: ajaxData,
                success: function(response) {
                    if (response.success) {
                        console.log('Banner data saved successfully');
                    } else {
                        console.error('Error saving banner:', response.data);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', error);
                }
            });
        },

        saveBannerOrder: function() {
            var self = this;
            var bannerIds = [];

            this.$list.find('.wooapp-banner-item').each(function() {
                bannerIds.push($(this).data('banner-id'));
            });

            var nonce = wooappBanners ? wooappBanners.reorderNonce : '';
            var ajaxUrl = wooappBanners ? wooappBanners.ajaxUrl : '';
            
            if (!nonce || !ajaxUrl) {
                console.error('Missing required data for reorder');
                return;
            }

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wooapp_reorder_banners',
                    nonce: nonce,
                    banner_ids: bannerIds
                },
                success: function(response) {
                    if (response.success) {
                        console.log('Banners reordered successfully');
                    } else {
                        console.error('Error reordering banners:', response.data);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', error);
                }
            });
        },

        deleteBanner: function(bannerId) {
            var $item = this.$list.find(`[data-banner-id="${bannerId}"]`);
            
            $item.addClass('loading');

            var nonce = wooappBanners ? wooappBanners.deleteNonce : '';
            var ajaxUrl = wooappBanners ? wooappBanners.ajaxUrl : '';
            
            if (!nonce || !ajaxUrl) {
                $item.removeClass('loading');
                console.error('Missing required data for delete');
                return;
            }

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wooapp_delete_banner',
                    nonce: nonce,
                    banner_id: bannerId
                },
                success: function(response) {
                    $item.fadeOut(300, function() {
                        $(this).remove();
                        
                        // Show "no banners" message if list is empty
                        if (WooAppBanners.$list.find('.wooapp-banner-item').length === 0) {
                            WooAppBanners.$list.html(
                                `<p class="wooapp-no-banners">${bannerStrings.noBanners}</p>`
                            );
                        }
                    });
                },
                error: function(xhr, status, error) {
                    $item.removeClass('loading');
                    alert(bannerStrings.deleteError);
                    console.error('AJAX error:', error);
                }
            });
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        if ($('#wooapp-banners-container').length) {
            WooAppBanners.init();
        }
    });

})(jQuery);
