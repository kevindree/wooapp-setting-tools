(function() {
    document.addEventListener('DOMContentLoaded', function() {
        // Get localized data
        const wooappData = typeof wooappCategoryPositions !== 'undefined' ? wooappCategoryPositions : {};
        const deleteNonce = wooappData.deleteNonce || '';
        const adminPostUrl = wooappData.adminPostUrl || '/wp-admin/admin-post.php';
        
        // Handle delete buttons
        const deleteButtons = document.querySelectorAll('.wooapp-delete-position');
        deleteButtons.forEach(function(btn) {
            btn.addEventListener('click', function() {
                const positionKey = this.getAttribute('data-position-key');
                if (confirm('Are you sure you want to delete this position?')) {
                    // Create a form and submit it
                    const form = document.createElement('form');
                    form.method = 'post';
                    form.action = adminPostUrl;
                    
                    const actionInput = document.createElement('input');
                    actionInput.type = 'hidden';
                    actionInput.name = 'action';
                    actionInput.value = 'wooapp_delete_position';
                    form.appendChild(actionInput);
                    
                    const positionKeyInput = document.createElement('input');
                    positionKeyInput.type = 'hidden';
                    positionKeyInput.name = 'position_key';
                    positionKeyInput.value = positionKey;
                    form.appendChild(positionKeyInput);
                    
                    // Add nonce
                    const nonceInput = document.createElement('input');
                    nonceInput.type = 'hidden';
                    nonceInput.name = '_wpnonce';
                    nonceInput.value = deleteNonce;
                    form.appendChild(nonceInput);
                    
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        });
        
        const selects = document.querySelectorAll('.wooapp-category-select');
        
        selects.forEach(function(select) {
            const options = Array.from(select.querySelectorAll('option'));
            const expanded = {}; // Track expanded state
            
            // Initialize expanded state for parents
            options.forEach(function(opt) {
                if (opt.classList.contains('wooapp-parent')) {
                    const parentId = opt.getAttribute('data-parent');
                    expanded[parentId] = true;
                }
            });
            
            // Create custom dropdown
            const customSelect = document.createElement('div');
            customSelect.className = 'wooapp-custom-select';
            select.parentNode.insertBefore(customSelect, select);
            
            // Create resize handle
            const resizeHandle = document.createElement('div');
            resizeHandle.className = 'wooapp-resize-handle';
            document.body.appendChild(resizeHandle);
            
            // Update resize handle position
            function updateHandlePosition() {
                const rect = customSelect.getBoundingClientRect();
                resizeHandle.style.bottom = (window.innerHeight - rect.bottom) + 'px';
                resizeHandle.style.right = (window.innerWidth - rect.right) + 'px';
                resizeHandle.style.display = rect.bottom > 0 && rect.right > 0 ? 'block' : 'none';
            }
            
            // Render options
            function renderOptions() {
                customSelect.innerHTML = '';
                options.forEach(function(opt) {
                    if (opt.style.display === 'none') return;
                    
                    const optDiv = document.createElement('div');
                    optDiv.className = 'wooapp-option';
                    optDiv.dataset.optionId = opt.value;
                    
                    if (opt.classList.contains('wooapp-parent')) {
                        optDiv.classList.add('wooapp-parent');
                    }
                    if (opt.selected) {
                        optDiv.classList.add('selected');
                    }
                    
                    const depth = parseInt(opt.getAttribute('data-depth')) || 0;
                    const hasChildren = opt.classList.contains('wooapp-parent');
                    const parentId = opt.getAttribute('data-parent');
                    
                    // Indent
                    const indent = document.createElement('span');
                    indent.className = 'wooapp-option-indent';
                    indent.style.marginLeft = (depth * 20) + 'px';
                    
                    // Toggle button (only for parents)
                    const toggle = document.createElement('span');
                    toggle.className = 'wooapp-option-toggle' + (hasChildren ? '' : ' no-children');
                    if (hasChildren) {
                        toggle.textContent = expanded[parentId] ? '−' : '+';
                        toggle.dataset.parentId = parentId;
                    }
                    
                    // Name - get from data-original-text attribute
                    const name = document.createElement('span');
                    name.className = 'wooapp-option-name';
                    name.textContent = opt.getAttribute('data-original-text') || opt.textContent;
                    
                    optDiv.appendChild(indent);
                    optDiv.appendChild(toggle);
                    optDiv.appendChild(name);
                    
                    customSelect.appendChild(optDiv);
                });
                
                // Update handle position after rendering
                setTimeout(updateHandlePosition, 0);
            }
            
            renderOptions();
            // Initial position update
            setTimeout(updateHandlePosition, 0);
            window.addEventListener('scroll', updateHandlePosition);
            window.addEventListener('resize', updateHandlePosition);
            
            // Helper to toggle children visibility
            function toggleChildren(parentId, collapse) {
                const childDepth = parseInt(select.querySelector('option[data-parent="' + parentId + '"]').getAttribute('data-depth')) + 1;
                const parentOpt = select.querySelector('option[data-parent="' + parentId + '"]');
                const parentIdx = options.indexOf(parentOpt);
                
                for (let i = parentIdx + 1; i < options.length; i++) {
                    const opt = options[i];
                    const optDepth = parseInt(opt.getAttribute('data-depth'));
                    
                    if (optDepth <= parseInt(parentOpt.getAttribute('data-depth'))) {
                        break;
                    }
                    
                    if (optDepth === childDepth) {
                        opt.style.display = collapse ? 'none' : 'block';
                    } else if (optDepth > childDepth) {
                        if (collapse) {
                            opt.style.display = 'none';
                        } else {
                            // Check if all ancestors are expanded
                            let showIt = true;
                            for (let j = parentIdx + 1; j < i; j++) {
                                if (options[j].classList.contains('wooapp-parent')) {
                                    const ancestorId = options[j].getAttribute('data-parent');
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
                    const parentId = e.target.dataset.parentId;
                    const wasExpanded = expanded[parentId];
                    
                    expanded[parentId] = !wasExpanded;
                    toggleChildren(parentId, wasExpanded);
                    renderOptions();
                }
            });
            
            // Name click handler - toggle selection
            customSelect.addEventListener('click', function(e) {
                if (e.target.classList.contains('wooapp-option-name') || e.target.classList.contains('wooapp-option')) {
                    const optDiv = e.target.classList.contains('wooapp-option') ? e.target : e.target.closest('.wooapp-option');
                    if (optDiv) {
                        const optionId = optDiv.dataset.optionId;
                        const opt = select.querySelector('option[value="' + optionId + '"]');
                        opt.selected = !opt.selected;
                        renderOptions();
                    }
                }
            });
            
            // Drag to resize functionality
            let isResizing = false;
            let startY = 0;
            let startHeight = 0;
            
            resizeHandle.addEventListener('mousedown', function(e) {
                isResizing = true;
                startY = e.clientY;
                startHeight = customSelect.offsetHeight;
                e.preventDefault();
            });
            
            document.addEventListener('mousemove', function(e) {
                if (!isResizing) return;
                
                const diff = e.clientY - startY;
                const newHeight = startHeight + diff;
                
                if (newHeight >= 100) {
                    customSelect.style.height = newHeight + 'px';
                    customSelect.style.maxHeight = 'none';
                    updateHandlePosition();
                }
            });
            
            document.addEventListener('mouseup', function() {
                isResizing = false;
            });
            
            // Store reference to form and sync on submit
            const formElement = select.closest('form');
            if (formElement) {
                // Add a hidden submit handler to sync selected state before form submission
                formElement.addEventListener('submit', function(e) {
                    // Sync all selected items from customSelect to the hidden select
                    const selectedDivs = customSelect.querySelectorAll('.wooapp-option.selected');
                    
                    // First clear all selections in the hidden select
                    options.forEach(function(opt) {
                        opt.selected = false;
                    });
                    
                    // Then set selected for items that are marked as selected in customSelect
                    selectedDivs.forEach(function(optDiv) {
                        const optionId = optDiv.dataset.optionId;
                        const opt = select.querySelector('option[value="' + optionId + '"]');
                        if (opt) {
                            opt.selected = true;
                            console.log('Selected:', optionId, opt.textContent);
                        }
                    });
                    
                    // Log the final state for debugging
                    const selected = Array.from(options)
                        .filter(opt => opt.selected)
                        .map(opt => opt.value);
                    console.log('Final selected values:', selected);
                }, false);
            }
        });
    });
})();
