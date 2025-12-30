/**
 * GoalV Admin Competitions
 * Version: 8.2.0
 * 
 * Multi-league management and synchronization
 */

(function($) {
    'use strict';

    window.GoalVCompetitions = {
        
        selectedCompetitions: new Set(),

        init: function() {
            console.log('Initializing Competitions...');
            this.bindEvents();
            this.restoreSelections();
        },

        bindEvents: function() {
            // Select all checkbox
            $('#select-all-competitions').on('change', this.selectAll.bind(this));
            
            // Individual checkboxes
            $('.competition-checkbox').on('change', this.updateSelection.bind(this));
            
            // Fetch from API
            $('#fetch-competitions-btn').on('click', this.fetchFromApi.bind(this));
            
            // Sync single competition
            $('.sync-single-btn').on('click', this.syncSingle.bind(this));
            
            // Bulk actions
            $('#bulk-enable-btn').on('click', () => this.bulkAction('enable'));
            $('#bulk-disable-btn').on('click', () => this.bulkAction('disable'));
        },

        /**
         * Select all competitions
         */
        selectAll: function(e) {
            const isChecked = $(e.currentTarget).prop('checked');
            
            $('.competition-checkbox').prop('checked', isChecked);
            
            if (isChecked) {
                $('.competition-checkbox').each((i, checkbox) => {
                    this.selectedCompetitions.add($(checkbox).val());
                });
            } else {
                this.selectedCompetitions.clear();
            }

            this.updateBulkActionButtons();
        },

        /**
         * Update selection state
         */
        updateSelection: function(e) {
            const $checkbox = $(e.currentTarget);
            const competitionId = $checkbox.val();

            if ($checkbox.prop('checked')) {
                this.selectedCompetitions.add(competitionId);
            } else {
                this.selectedCompetitions.delete(competitionId);
            }

            // Update select all checkbox
            const totalCheckboxes = $('.competition-checkbox').length;
            const checkedCount = $('.competition-checkbox:checked').length;
            
            $('#select-all-competitions').prop('checked', checkedCount === totalCheckboxes);

            this.updateBulkActionButtons();
        },

        /**
         * Update bulk action buttons state
         */
        updateBulkActionButtons: function() {
            const hasSelection = this.selectedCompetitions.size > 0;
            
            $('#bulk-enable-btn, #bulk-disable-btn').prop('disabled', !hasSelection);

            if (hasSelection) {
                $('#bulk-enable-btn, #bulk-disable-btn').removeClass('disabled');
            } else {
                $('#bulk-enable-btn, #bulk-disable-btn').addClass('disabled');
            }
        },

        /**
         * Fetch competitions from API
         */
        fetchFromApi: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const $message = $('#competitions-message');

            // Confirm action
            if (!confirm('Fetch all available competitions from API-Football? This may take a moment.')) {
                return;
            }

            GoalV.Utils.setButtonLoading($button, true, 'Fetching...');
            $message.html('<div class="notice notice-info inline"><p>Fetching competitions from API...</p></div>');

            GoalV.Ajax.request('goalv_fetch_competitions', {}, {
                success: (data) => {
                    GoalV.Toast.success(data.message);
                    
                    $message.html(
                        '<div class="notice notice-success inline">' +
                        '<p><strong>Success!</strong> ' + data.message + '<br>' +
                        'Page will reload in 2 seconds...</p></div>'
                    );

                    // Reload after 2 seconds
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                },
                error: (error) => {
                    GoalV.Toast.error('Failed to fetch competitions');
                    
                    $message.html(
                        '<div class="notice notice-error inline">' +
                        '<p><strong>Error:</strong> ' + error + '</p></div>'
                    );
                },
                complete: () => {
                    GoalV.Utils.setButtonLoading($button, false, 'Fetch from API');
                }
            });
        },

        /**
         * Toggle competition status
         */
        toggleStatus: function(e) {
            const $toggle = $(e.currentTarget);
            const competitionId = $toggle.data('competition-id');
            const type = $toggle.hasClass('toggle-active') ? 'active' : 'sync';
            const status = $toggle.prop('checked') ? 1 : 0;
            const $row = $toggle.closest('tr');

            // Prevent rapid toggling
            $toggle.prop('disabled', true);

            GoalV.Ajax.request('goalv_toggle_competition', {
                competition_id: competitionId,
                type: type,
                status: status
            }, {
                success: (data) => {
                    const $label = $toggle.next().next('.goalv-toggle-label');
                    
                    if (type === 'active') {
                        $label.text(status ? 'Active' : 'Inactive');
                        
                        // If deactivating, also disable sync
                        if (!status) {
                            $row.find('.toggle-sync').prop('checked', false).prop('disabled', true);
                            $row.find('.toggle-sync').next().next('.goalv-toggle-label').text('Disabled');
                        } else {
                            $row.find('.toggle-sync').prop('disabled', false);
                        }
                    } else {
                        $label.text(status ? 'Enabled' : 'Disabled');
                    }

                    GoalV.Toast.success(data.message);

                    // Visual feedback
                    $row.addClass('goalv-row-updated');
                    setTimeout(() => {
                        $row.removeClass('goalv-row-updated');
                    }, 500);
                },
                error: (error) => {
                    // Revert toggle on error
                    $toggle.prop('checked', !status);
                    GoalV.Toast.error('Failed to update: ' + error);
                },
                complete: () => {
                    $toggle.prop('disabled', false);
                }
            });
        },

        /**
         * Sync single competition
         */
        syncSingle: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const competitionId = $button.data('competition-id');
            const $icon = $button.find('.dashicons');
            const $row = $button.closest('tr');

            GoalV.Utils.setButtonLoading($button, true, 'Syncing...');
            $icon.addClass('dashicons-update-spin');

            GoalV.Ajax.request('goalv_sync_single_competition', {
                competition_id: competitionId
            }, {
                success: (data) => {
                    GoalV.Toast.success(data.message);
                    
                    // Update last synced time
                    const now = new Date();
                    const timeAgo = 'Just now';
                    $row.find('td:eq(5)').text(timeAgo);

                    // Visual feedback
                    $row.addClass('goalv-row-updated');
                    setTimeout(() => {
                        $row.removeClass('goalv-row-updated');
                    }, 500);

                    // If this was a big sync, offer to reload
                    setTimeout(() => {
                        if (confirm('Sync completed! View updated matches?')) {
                            location.reload();
                        }
                    }, 1000);
                },
                error: (error) => {
                    GoalV.Toast.error('Sync failed: ' + error);
                },
                complete: () => {
                    GoalV.Utils.setButtonLoading($button, false, 'Sync Now');
                    $icon.removeClass('dashicons-update-spin');
                }
            });
        },

        /**
         * Bulk action (enable/disable)
         */
        bulkAction: function(action) {
            const selectedIds = Array.from(this.selectedCompetitions);

            if (selectedIds.length === 0) {
                GoalV.Toast.warning('Please select at least one competition');
                return;
            }

            const actionText = action === 'enable' ? 'enable' : 'disable';
            const confirmText = `${actionText.charAt(0).toUpperCase() + actionText.slice(1)} ${selectedIds.length} competition(s)?`;

            if (!confirm(confirmText)) {
                return;
            }

            const status = action === 'enable' ? 1 : 0;
            let processed = 0;
            let successful = 0;
            let failed = 0;

            GoalV.Toast.info(`${actionText}ing ${selectedIds.length} competitions...`, 5000);

            // Process each competition
            selectedIds.forEach(id => {
                GoalV.Ajax.request('goalv_toggle_competition', {
                    competition_id: id,
                    type: 'active',
                    status: status
                }, {
                    success: () => {
                        successful++;
                        processed++;

                        // Update UI for this row
                        const $row = $(`.competition-checkbox[value="${id}"]`).closest('tr');
                        const $toggle = $row.find('.toggle-active');
                        const $label = $toggle.next().next('.goalv-toggle-label');
                        
                        $toggle.prop('checked', status === 1);
                        $label.text(status === 1 ? 'Active' : 'Inactive');

                        if (processed === selectedIds.length) {
                            this.completeBulkAction(successful, failed, actionText);
                        }
                    },
                    error: (error) => {
                        failed++;
                        processed++;
                        console.error(`Failed to ${actionText} competition ${id}:`, error);

                        if (processed === selectedIds.length) {
                            this.completeBulkAction(successful, failed, actionText);
                        }
                    }
                });
            });
        },

        /**
         * Complete bulk action
         */
        completeBulkAction: function(successful, failed, actionText) {
            if (failed === 0) {
                GoalV.Toast.success(`Successfully ${actionText}d ${successful} competitions!`);
            } else if (successful === 0) {
                GoalV.Toast.error(`Failed to ${actionText} competitions`);
            } else {
                GoalV.Toast.warning(`${actionText}d ${successful} competitions, ${failed} failed`);
            }

            // Clear selections
            this.selectedCompetitions.clear();
            $('.competition-checkbox').prop('checked', false);
            $('#select-all-competitions').prop('checked', false);
            this.updateBulkActionButtons();
        },

        /**
         * Restore selections from session
         */
        restoreSelections: function() {
            const saved = GoalV.Utils.storage.get('competition_selections');
            
            if (saved && Array.isArray(saved)) {
                saved.forEach(id => {
                    this.selectedCompetitions.add(id);
                    $(`.competition-checkbox[value="${id}"]`).prop('checked', true);
                });
                
                this.updateBulkActionButtons();
            }
        },

        /**
         * Save selections to session
         */
        saveSelections: function() {
            GoalV.Utils.storage.set('competition_selections', Array.from(this.selectedCompetitions));
        }
    };

    // Save selections on page unload
    $(window).on('beforeunload', () => {
        if (window.GoalV && window.GoalV.Competitions) {
            window.GoalV.Competitions.saveSelections();
        }
    });

    // Expose globally
    window.GoalV = window.GoalV || {};
    window.GoalV.Competitions = window.GoalVCompetitions;

})(jQuery);