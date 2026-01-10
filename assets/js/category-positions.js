(function() {
    'use strict';

    var WooAppPositions = {
        init: function() {
            this.cacheElements();
            this.bindEvents();
            this.initCategorySelect();
        },

        cacheElements: function() {
            this.$container = document.getElementById('wooapp-positions-container');
            this.$positionsList = document.getElementById('wooapp-positions-list');
            this.$createPositionBtn = document.getElementById('wooapp-create-position');
            this.$positionKeyInput = document.getElementById('wooapp-new-position-key');
            this.$positionLabelInput = document.getElementById('wooapp-new-position-label');
            this.$deleteNonce = document.getElementById('wooapp-delete-nonce');
        },

        bindEvents: function() {
            var self = this;

            // Create position button
            if (this.$createPositionBtn) {
                this.$createPositionBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    self.createPosition();
                });
            }

            // Handle Enter key in position inputs
            if (this.$positionKeyInput) {
                this.$positionKeyInput.addEventListener('keypress', function(e) {
                    if (e.which === 13 || e.key === 'Enter') {
                        e.preventDefault();
                        self.createPosition();
                    }
                });
            }

            if (this.$positionLabelInput) {
                this.$positionLabelInput.addEventListener('keypress', function(e) {
                    if (e.which === 13 || e.key === 'Enter') {
                        e.preventDefault();
                        self.createPosition();
                    }
                });
            }

            // Position item click - switch position
            var self = this;
            if (this.$positionsList) {
                this.$positionsList.addEventListener('click', function(e) {
                    // Check if click is on delete button
                    var deleteBtn = e.target.closest('.wooapp-delete-position');
                    if (deleteBtn) {
                        e.preventDefault();
                        e.stopPropagation();
                        var positionKey = deleteBtn.getAttribute('data-position-key');
                        if (confirm('Are you sure you want to delete this position and all its category assignments?')) {
                            self.deletePosition(positionKey);
                        }
                        return;
                    }

                    // Check if click is on position item
                    var positionItem = e.target.closest('.wooapp-position-item');
                    if (positionItem) {
                        var url = positionItem.getAttribute('data-position-url');
                        console.log('Navigating to:', url);
                        if (url) {
                            window.location.href = url;
                        }
                    }
                });
            }
        },

        createPosition: function() {
            var key = this.$positionKeyInput.value.trim();
            var label = this.$positionLabelInput.value.trim();

            if (!key || !label) {
                alert('Please enter both position key and label');
                return;
            }

            // Validate key format (lowercase, numbers, underscores, hyphens)
            if (!/^[a-z0-9_-]+$/.test(key)) {
                alert('Position key can only contain lowercase letters, numbers, underscores, and hyphens');
                return;
            }

            // Check if key already exists
            var existingItem = this.$positionsList.querySelector('[data-position="' + key + '"]');
            if (existingItem) {
                alert('This position key already exists');
                return;
            }

            // Create form and submit
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = this.getAdminPostUrl();

            // Add action
            var actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'wooapp_save_category_positions';
            form.appendChild(actionInput);

            // Add nonce (find it from existing nonce field in page)
            var existingNonce = document.querySelector('input[name="_wpnonce"]');
            if (existingNonce) {
                var nonceInput = document.createElement('input');
                nonceInput.type = 'hidden';
                nonceInput.name = '_wpnonce';
                nonceInput.value = existingNonce.value;
                form.appendChild(nonceInput);
            }

            // Add position data
            var addNewInput = document.createElement('input');
            addNewInput.type = 'hidden';
            addNewInput.name = 'add_new_position';
            addNewInput.value = '1';
            form.appendChild(addNewInput);

            var keyInput = document.createElement('input');
            keyInput.type = 'hidden';
            keyInput.name = 'new_position_key';
            keyInput.value = key;
            form.appendChild(keyInput);

            var labelInput = document.createElement('input');
            labelInput.type = 'hidden';
            labelInput.name = 'new_position_label';
            labelInput.value = label;
            form.appendChild(labelInput);

            document.body.appendChild(form);
            form.submit();
        },

        deletePosition: function(positionKey) {
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = this.getAdminPostUrl();

            var actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'wooapp_delete_position';
            form.appendChild(actionInput);

            var keyInput = document.createElement('input');
            keyInput.type = 'hidden';
            keyInput.name = 'position_key';
            keyInput.value = positionKey;
            form.appendChild(keyInput);

            // Use the cached delete-specific nonce
            if (this.$deleteNonce) {
                var nonceInput = document.createElement('input');
                nonceInput.type = 'hidden';
                nonceInput.name = '_wpnonce';
                nonceInput.value = this.$deleteNonce.value;
                form.appendChild(nonceInput);
                console.log('Delete form data:', {
                    action: 'wooapp_delete_position',
                    position_key: positionKey,
                    nonce: this.$deleteNonce.value
                });
            } else {
                console.error('Delete nonce element not found!');
            }

            document.body.appendChild(form);
            form.submit();
        },

        getAdminPostUrl: function() {
            var wooappData = typeof wooappCategoryPositions !== 'undefined' ? wooappCategoryPositions : {};
            return wooappData.adminPostUrl || '/wp-admin/admin-post.php';
        },

        initCategorySelect: function() {
            var selects = document.querySelectorAll('.wooapp-category-select');

            selects.forEach(function(select) {
                var options = Array.from(select.querySelectorAll('option'));
                var expanded = {};

                // Initialize expanded state for parents
                options.forEach(function(opt) {
                    if (opt.classList.contains('wooapp-parent')) {
                        var parentId = opt.getAttribute('data-parent');
                        expanded[parentId] = true;
                    }
                });

                // Create custom dropdown
                var customSelect = document.createElement('div');
                customSelect.className = 'wooapp-custom-select';
                select.parentNode.insertBefore(customSelect, select);

                // Render options function
                function renderOptions() {
                    customSelect.innerHTML = '';
                    options.forEach(function(opt) {
                        if (opt.style.display === 'none') return;

                        var optDiv = document.createElement('div');
                        optDiv.className = 'wooapp-option';
                        optDiv.dataset.optionId = opt.value;

                        if (opt.classList.contains('wooapp-parent')) {
                            optDiv.classList.add('wooapp-parent');
                        }
                        if (opt.selected) {
                            optDiv.classList.add('selected');
                        }

                        var depth = parseInt(opt.getAttribute('data-depth')) || 0;
                        var hasChildren = opt.classList.contains('wooapp-parent');
                        var parentId = opt.getAttribute('data-parent');

                        // Indent
                        var indent = document.createElement('span');
                        indent.className = 'wooapp-option-indent';
                        indent.style.marginLeft = (depth * 20) + 'px';

                        // Toggle button (only for parents)
                        var toggle = document.createElement('span');
                        toggle.className = 'wooapp-option-toggle' + (hasChildren ? '' : ' no-children');
                        if (hasChildren) {
                            toggle.textContent = expanded[parentId] ? '−' : '+';
                            toggle.dataset.parentId = parentId;
                        }

                        // Name
                        var name = document.createElement('span');
                        name.className = 'wooapp-option-name';
                        name.textContent = opt.getAttribute('data-original-text') || opt.textContent;

                        optDiv.appendChild(indent);
                        optDiv.appendChild(toggle);
                        optDiv.appendChild(name);

                        customSelect.appendChild(optDiv);
                    });
                }

                renderOptions();

                // Helper to toggle children visibility
                function toggleChildren(parentId, collapse) {
                    var parentOpt = select.querySelector('option[data-parent="' + parentId + '"]');
                    var parentIdx = options.indexOf(parentOpt);
                    var parentDepth = parseInt(parentOpt.getAttribute('data-depth'));
                    var childDepth = parentDepth + 1;

                    for (var i = parentIdx + 1; i < options.length; i++) {
                        var opt = options[i];
                        var optDepth = parseInt(opt.getAttribute('data-depth'));

                        if (optDepth <= parentDepth) {
                            break;
                        }

                        if (optDepth === childDepth) {
                            opt.style.display = collapse ? 'none' : 'block';
                        } else if (optDepth > childDepth) {
                            if (collapse) {
                                opt.style.display = 'none';
                            } else {
                                // Check if all ancestors are expanded
                                var showIt = true;
                                for (var j = parentIdx + 1; j < i; j++) {
                                    var checkOpt = options[j];
                                    if (checkOpt.classList.contains('wooapp-parent')) {
                                        var ancestorId = checkOpt.getAttribute('data-parent');
                                        if (!expanded[ancestorId]) {
                                            showIt = false;
                                            break;
                                        }
                                    }
                                }
                                opt.style.display = showIt ? 'block' : 'none';
                            }
                        }
                    }
                }

                // Toggle click handler
                customSelect.addEventListener('click', function(e) {
                    if (e.target.classList.contains('wooapp-option-toggle') && !e.target.classList.contains('no-children')) {
                        e.stopPropagation();
                        var parentId = e.target.dataset.parentId;
                        var wasExpanded = expanded[parentId];

                        expanded[parentId] = !wasExpanded;
                        toggleChildren(parentId, wasExpanded);
                        renderOptions();
                    }
                });

                // Name click handler - toggle selection
                customSelect.addEventListener('click', function(e) {
                    if (e.target.classList.contains('wooapp-option-name') || e.target.classList.contains('wooapp-option')) {
                        var optDiv = e.target.classList.contains('wooapp-option') ? e.target : e.target.closest('.wooapp-option');
                        if (optDiv) {
                            var optionId = optDiv.dataset.optionId;
                            var opt = select.querySelector('option[value="' + optionId + '"]');
                            opt.selected = !opt.selected;
                            renderOptions();
                        }
                    }
                });

                // Store reference to form and sync on submit
                var formElement = select.closest('form');
                if (formElement) {
                    formElement.addEventListener('submit', function(e) {
                        // Sync all selected items from customSelect to the hidden select
                        var selectedDivs = customSelect.querySelectorAll('.wooapp-option.selected');

                        // First clear all selections in the hidden select
                        options.forEach(function(opt) {
                            opt.selected = false;
                        });

                        // Then set selected for items that are marked as selected in customSelect
                        selectedDivs.forEach(function(optDiv) {
                            var optionId = optDiv.dataset.optionId;
                            var opt = select.querySelector('option[value="' + optionId + '"]');
                            if (opt) {
                                opt.selected = true;
                            }
                        });
                    }, false);
                }
            });
        }
    };

    // Initialize when DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
        WooAppPositions.init();
    });
})();
