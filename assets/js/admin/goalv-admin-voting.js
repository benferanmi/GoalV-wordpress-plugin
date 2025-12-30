/**
 * GoalV Admin Voting Settings
 * Version: 8.2.0
 * 
 * Vote categories and permissions management
 */

(function($) {
    'use strict';

    window.GoalVVoting = {
        
        init: function() {
            console.log('Initializing Voting Settings...');
            this.bindEvents();
            this.initSortable();
            this.initPermissions();
        },

        bindEvents: function() {
            // Add category
            $('#add-category-btn').on('click', this.addCategory.bind(this));
            
            // Delete category
            $('.delete-category-btn').on('click', this.deleteCategory.bind(this));
            
            // Edit category (if inline editing enabled)
            $('.edit-category-btn').on('click', this.editCategory.bind(this));

            // Category key input validation
            $('#new_category_key').on('input', this.validateCategoryKey.bind(this));
            
            // Permission changes
            $('input[name^="goalv_allow"]').on('change', this.handlePermissionChange.bind(this));
        },

        /**
         * Add new category
         */
        addCategory: function(e) {
            e.preventDefault();
            
            const categoryKey = $('#new_category_key').val().trim();
            const categoryLabel = $('#new_category_label').val().trim();
            const $message = $('#category-message');

            // Validation
            if (!categoryKey || !categoryLabel) {
                GoalV.Toast.error('Please fill in both fields');
                $message.html(
                    '<div class="notice notice-error inline">' +
                    '<p>Both category key and label are required.</p></div>'
                );
                return;
            }

            // Validate key format
            if (!/^[a-z0-9_]+$/.test(categoryKey)) {
                GoalV.Toast.error('Category key must contain only lowercase letters, numbers, and underscores');
                $message.html(
                    '<div class="notice notice-error inline">' +
                    '<p>Invalid category key format.</p></div>'
                );
                return;
            }

            const $button = $(e.currentTarget);
            GoalV.Utils.setButtonLoading($button, true, 'Adding...');
            $message.html('');

            GoalV.Ajax.request('goalv_add_category', {
                category_key: categoryKey,
                category_label: categoryLabel
            }, {
                success: (data) => {
                    GoalV.Toast.success('Category added successfully!');
                    
                    // Clear inputs
                    $('#new_category_key, #new_category_label').val('');
                    
                    $message.html(
                        '<div class="notice notice-success inline">' +
                        '<p><strong>Success!</strong> ' + data.message + '<br>' +
                        'Reloading page...</p></div>'
                    );

                    // Reload after 1 second
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                },
                error: (error) => {
                    GoalV.Toast.error('Failed to add category');
                    
                    $message.html(
                        '<div class="notice notice-error inline">' +
                        '<p><strong>Error:</strong> ' + error + '</p></div>'
                    );
                },
                complete: () => {
                    GoalV.Utils.setButtonLoading($button, false, 'Add Category');
                }
            });
        },

        /**
         * Delete category
         */
        deleteCategory: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const categoryId = $button.data('category-id');
            const $row = $button.closest('tr');
            const categoryName = $row.find('.category-label').text();

            // Confirm deletion
            if (!confirm(`Delete category "${categoryName}"?\n\nAll voting options using this category will be moved to "Other".`)) {
                return;
            }

            GoalV.Utils.setButtonLoading($button, true, 'Deleting...');

            GoalV.Ajax.request('goalv_delete_category', {
                category_id: categoryId
            }, {
                success: (data) => {
                    GoalV.Toast.success('Category deleted');
                    
                    // Remove row with animation
                    $row.addClass('goalv-row-deleting');
                    setTimeout(() => {
                        $row.fadeOut(300, function() {
                            $(this).remove();
                            
                            // Check if no categories left
                            if ($('#categories-table tbody tr').length === 0) {
                                $('#categories-table tbody').html(
                                    '<tr><td colspan="5" class="no-items">No categories found.</td></tr>'
                                );
                            }
                        });
                    }, 200);
                },
                error: (error) => {
                    GoalV.Toast.error('Failed to delete category');
                    GoalV.Utils.setButtonLoading($button, false, 'Delete');
                }
            });
        },

        /**
         * Edit category (inline editing)
         */
        editCategory: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const $row = $button.closest('tr');
            const categoryId = $button.data('category-id');
            const $labelCell = $row.find('.category-label');
            const currentLabel = $labelCell.text();

            // Check if already editing
            if ($row.hasClass('editing')) {
                return;
            }

            // Create inline editor
            const $input = $('<input>', {
                type: 'text',
                class: 'regular-text',
                value: currentLabel
            });

            const $saveBtn = $('<button>', {
                type: 'button',
                class: 'button button-small',
                text: 'Save'
            });

            const $cancelBtn = $('<button>', {
                type: 'button',
                class: 'button button-small',
                text: 'Cancel'
            });

            // Replace label with input
            $labelCell.html($input).append(' ', $saveBtn, ' ', $cancelBtn);
            $row.addClass('editing');
            $input.focus();

            // Save handler
            $saveBtn.on('click', () => {
                const newLabel = $input.val().trim();
                
                if (!newLabel) {
                    GoalV.Toast.error('Label cannot be empty');
                    return;
                }

                if (newLabel === currentLabel) {
                    // No change, just cancel
                    this.cancelEdit($row, $labelCell, currentLabel);
                    return;
                }

                // Save via AJAX
                GoalV.Ajax.request('goalv_update_category', {
                    category_id: categoryId,
                    category_label: newLabel
                }, {
                    success: (data) => {
                        $labelCell.html(`<strong class="category-label">${GoalV.Utils.sanitizeHtml(newLabel)}</strong>`);
                        $row.removeClass('editing');
                        GoalV.Toast.success('Category updated');
                    },
                    error: (error) => {
                        GoalV.Toast.error('Failed to update category');
                        this.cancelEdit($row, $labelCell, currentLabel);
                    }
                });
            });

            // Cancel handler
            $cancelBtn.on('click', () => {
                this.cancelEdit($row, $labelCell, currentLabel);
            });

            // Enter key to save
            $input.on('keypress', (e) => {
                if (e.which === 13) {
                    $saveBtn.click();
                }
            });

            // Escape to cancel
            $input.on('keydown', (e) => {
                if (e.key === 'Escape') {
                    $cancelBtn.click();
                }
            });
        },

        /**
         * Cancel inline editing
         */
        cancelEdit: function($row, $labelCell, originalLabel) {
            $labelCell.html(`<strong class="category-label">${GoalV.Utils.sanitizeHtml(originalLabel)}</strong>`);
            $row.removeClass('editing');
        },

        /**
         * Validate category key format
         */
        validateCategoryKey: function(e) {
            const $input = $(e.currentTarget);
            const value = $input.val();

            // Remove invalid characters in real-time
            const cleaned = value.toLowerCase().replace(/[^a-z0-9_]/g, '');
            
            if (cleaned !== value) {
                $input.val(cleaned);
                
                if (!$('#category-key-hint').length) {
                    $input.after(
                        '<p id="category-key-hint" class="description" style="color: #d63638;">' +
                        'Only lowercase letters, numbers, and underscores allowed</p>'
                    );
                    
                    setTimeout(() => {
                        $('#category-key-hint').fadeOut(() => {
                            $('#category-key-hint').remove();
                        });
                    }, 3000);
                }
            }
        },

        /**
         * Initialize jQuery UI sortable
         */
        initSortable: function() {
            if (!$.fn.sortable) {
                console.warn('jQuery UI Sortable not loaded');
                return;
            }

            const $sortable = $('#categories-sortable');
            
            if (!$sortable.length) {
                return;
            }

            $sortable.sortable({
                handle: '.drag-handle',
                placeholder: 'goalv-sortable-placeholder',
                cursor: 'move',
                opacity: 0.8,
                update: (event, ui) => {
                    this.saveOrder();
                }
            });

            console.log('Category sorting enabled');
        },

        /**
         * Save category order
         */
        saveOrder: function() {
            const order = $('#categories-sortable').sortable('toArray', {
                attribute: 'data-category-id'
            });

            GoalV.Ajax.request('goalv_reorder_categories', {
                category_order: JSON.stringify(order)
            }, {
                success: (data) => {
                    GoalV.Toast.success('Category order saved', 2000);
                    
                    // Update order numbers in UI
                    $('#categories-sortable tr').each(function(index) {
                        $(this).find('td:first').text(index + 1);
                    });
                },
                error: (error) => {
                    GoalV.Toast.error('Failed to save order');
                    console.error('Order save failed:', error);
                }
            });
        },

        /**
         * Initialize permission dependencies
         */
        initPermissions: function() {
            const $globalVoteChange = $('input[name="goalv_allow_vote_change"]');
            const $homepageChange = $('input[name="goalv_allow_homepage_vote_change"]');
            const $detailsChange = $('input[name="goalv_allow_details_vote_change"]');

            // Update dependencies on global change
            $globalVoteChange.on('change', function() {
                const isEnabled = $(this).prop('checked');
                $homepageChange.prop('disabled', !isEnabled);
                $detailsChange.prop('disabled', !isEnabled);
                
                if (!isEnabled) {
                    $homepageChange.prop('checked', false);
                    $detailsChange.prop('checked', false);
                }
            });

            // Trigger initial state
            $globalVoteChange.trigger('change');
        },

        /**
         * Handle permission change
         */
        handlePermissionChange: function(e) {
            const $checkbox = $(e.currentTarget);
            const permission = $checkbox.attr('name');
            const enabled = $checkbox.prop('checked');

            // Show warning for multiple votes
            if (permission === 'goalv_allow_multiple_votes' && enabled) {
                if (!confirm('Allow multiple votes per match?\n\nUsers will be able to vote in multiple categories for the same match.')) {
                    $checkbox.prop('checked', false);
                    return;
                }
            }

            // Visual feedback
            $checkbox.closest('tr').addClass('goalv-permission-changed');
            setTimeout(() => {
                $checkbox.closest('tr').removeClass('goalv-permission-changed');
            }, 500);
        }
    };

    // Expose globally
    window.GoalV = window.GoalV || {};
    window.GoalV.Voting = window.GoalVVoting;

})(jQuery);