/**
 * GoalV Admin Matches
 * Version: 8.2.0
 * 
 * Comprehensive matches management with inline editing and bulk operations
 */

(function ($) {
    'use strict';

    window.GoalVMatches = {

        selectedMatches: new Set(),
        currentMatchId: null,
        filters: {},

        init: function () {
            console.log('Initializing Matches Management...');
            this.bindEvents();
            this.restoreSelections();
            this.initFilters();
        },

        bindEvents: function () {
            // Select all checkbox
            $('#select-all-matches').on('change', this.selectAll.bind(this));

            // Individual checkboxes
            $('.match-checkbox').on('change', this.updateSelection.bind(this));

            // Bulk actions
            $('#apply-bulk-action').on('click', this.applyBulkAction.bind(this));

            // View match details
            $('.view-details-btn').on('click', this.viewMatchDetails.bind(this));

            // Resync match
            $('.resync-match-btn').on('click', this.resyncMatch.bind(this));

            // Delete match
            $('.delete-match-btn').on('click', this.deleteMatch.bind(this));

            // Export CSV
            $('#export-csv-btn').on('click', this.exportCsv.bind(this));

            // Fix orphaned matches
            $('#fix-orphaned-btn').on('click', this.fixOrphanedMatches.bind(this));

            // Modal close
            $('.goalv-modal-close').on('click', this.closeModal.bind(this));
            $(window).on('click', this.handleModalClick.bind(this));

            // Filter form auto-submit on change
            $('#filter_competition, #filter_status').on('change', function () {
                $('#matches-filter-form').submit();
            });
        },

        /**
         * Initialize filters from URL
         */
        initFilters: function () {
            const urlParams = new URLSearchParams(window.location.search);

            this.filters = {
                competition: urlParams.get('filter_competition') || '',
                status: urlParams.get('filter_status') || '',
                dateFrom: urlParams.get('filter_date_from') || '',
                dateTo: urlParams.get('filter_date_to') || '',
                search: urlParams.get('filter_search') || ''
            };
        },

        /**
         * Select all matches
         */
        selectAll: function (e) {
            const isChecked = $(e.currentTarget).prop('checked');

            $('.match-checkbox').prop('checked', isChecked);

            if (isChecked) {
                $('.match-checkbox').each((i, checkbox) => {
                    this.selectedMatches.add($(checkbox).val());
                });
            } else {
                this.selectedMatches.clear();
            }

            this.updateBulkActionState();
        },

        /**
         * Update selection state
         */
        updateSelection: function (e) {
            const $checkbox = $(e.currentTarget);
            const matchId = $checkbox.val();

            if ($checkbox.prop('checked')) {
                this.selectedMatches.add(matchId);
            } else {
                this.selectedMatches.delete(matchId);
            }

            // Update select all checkbox
            const totalCheckboxes = $('.match-checkbox').length;
            const checkedCount = $('.match-checkbox:checked').length;

            $('#select-all-matches').prop('checked', checkedCount === totalCheckboxes);

            this.updateBulkActionState();
        },

        /**
         * Update bulk action button state
         */
        updateBulkActionState: function () {
            const hasSelection = this.selectedMatches.size > 0;

            $('#apply-bulk-action').prop('disabled', !hasSelection);

            if (hasSelection) {
                $('#apply-bulk-action').removeClass('disabled');
            } else {
                $('#apply-bulk-action').addClass('disabled');
            }
        },

        /**
         * Apply bulk action
         */
        applyBulkAction: function (e) {
            e.preventDefault();

            const action = $('#bulk-action-select').val();
            const selectedIds = Array.from(this.selectedMatches);

            if (!action) {
                GoalV.Toast.warning('Please select an action');
                return;
            }

            if (selectedIds.length === 0) {
                GoalV.Toast.warning('Please select at least one match');
                return;
            }

            // Route to appropriate handler
            if (action === 'delete') {
                this.bulkDelete(selectedIds);
            } else if (action.startsWith('status_')) {
                const status = action.replace('status_', '');
                this.bulkUpdateStatus(selectedIds, status);
            } else if (action === 'resync') {
                this.bulkResync(selectedIds);
            }
        },

        /**
         * Bulk delete matches
         */
        bulkDelete: function (matchIds) {
            const confirmText = `Delete ${matchIds.length} match(es)? This will also delete all votes and vote options. This action cannot be undone.`;

            if (!confirm(confirmText)) {
                return;
            }

            const $message = $('#matches-message');
            let processed = 0;
            let successful = 0;
            let failed = 0;

            GoalV.Toast.info(`Deleting ${matchIds.length} matches...`, 5000);
            $message.html('<div class="notice notice-info inline"><p>Deleting matches...</p></div>');

            matchIds.forEach(id => {
                GoalV.Ajax.request('goalv_delete_match', {
                    match_id: id
                }, {
                    success: () => {
                        successful++;
                        processed++;

                        // Remove row from table
                        $(`tr[data-match-id="${id}"]`).fadeOut(300, function () {
                            $(this).remove();
                        });

                        if (processed === matchIds.length) {
                            this.completeBulkAction(successful, failed, 'deleted', $message);
                        }
                    },
                    error: (error) => {
                        failed++;
                        processed++;
                        console.error(`Failed to delete match ${id}:`, error);

                        if (processed === matchIds.length) {
                            this.completeBulkAction(successful, failed, 'deleted', $message);
                        }
                    }
                });
            });
        },

        /**
         * Bulk update status
         */
        bulkUpdateStatus: function (matchIds, status) {
            const statusLabel = status.charAt(0).toUpperCase() + status.slice(1);
            const confirmText = `Set status to "${statusLabel}" for ${matchIds.length} match(es)?`;

            if (!confirm(confirmText)) {
                return;
            }

            const $message = $('#matches-message');
            let processed = 0;
            let successful = 0;
            let failed = 0;

            GoalV.Toast.info(`Updating ${matchIds.length} matches...`, 5000);
            $message.html('<div class="notice notice-info inline"><p>Updating match statuses...</p></div>');

            matchIds.forEach(id => {
                GoalV.Ajax.request('goalv_update_match_status', {
                    match_id: id,
                    status: status
                }, {
                    success: () => {
                        successful++;
                        processed++;

                        // Update status badge in table
                        const $row = $(`tr[data-match-id="${id}"]`);
                        const $badge = $row.find('.goalv-status-badge');

                        $badge.removeClass().addClass('goalv-status-badge goalv-status-' + status);
                        $badge.text(statusLabel);

                        // Visual feedback
                        $row.addClass('goalv-row-updated');
                        setTimeout(() => {
                            $row.removeClass('goalv-row-updated');
                        }, 500);

                        if (processed === matchIds.length) {
                            this.completeBulkAction(successful, failed, 'updated', $message);
                        }
                    },
                    error: (error) => {
                        failed++;
                        processed++;
                        console.error(`Failed to update match ${id}:`, error);

                        if (processed === matchIds.length) {
                            this.completeBulkAction(successful, failed, 'updated', $message);
                        }
                    }
                });
            });
        },

        /**
         * Bulk resync matches
         */
        bulkResync: function (matchIds) {
            const confirmText = `Resync ${matchIds.length} match(es) from API? This will update scores and match details.`;

            if (!confirm(confirmText)) {
                return;
            }

            const $message = $('#matches-message');
            let processed = 0;
            let successful = 0;
            let failed = 0;

            GoalV.Toast.info(`Resyncing ${matchIds.length} matches...`, 5000);
            $message.html('<div class="notice notice-info inline"><p>Resyncing matches from API...</p></div>');

            matchIds.forEach(id => {
                GoalV.Ajax.request('goalv_resync_match', {
                    match_id: id
                }, {
                    success: () => {
                        successful++;
                        processed++;

                        if (processed === matchIds.length) {
                            this.completeBulkAction(successful, failed, 'resynced', $message);

                            // Offer to reload page
                            setTimeout(() => {
                                if (confirm('Resync completed! Reload page to see updates?')) {
                                    location.reload();
                                }
                            }, 1000);
                        }
                    },
                    error: (error) => {
                        failed++;
                        processed++;
                        console.error(`Failed to resync match ${id}:`, error);

                        if (processed === matchIds.length) {
                            this.completeBulkAction(successful, failed, 'resynced', $message);
                        }
                    }
                });
            });
        },

        /**
         * Complete bulk action
         */
        completeBulkAction: function (successful, failed, action, $message) {
            if (failed === 0) {
                GoalV.Toast.success(`Successfully ${action} ${successful} matches!`);
                $message.html(
                    `<div class="notice notice-success inline">` +
                    `<p><strong>Success!</strong> ${successful} matches ${action}.</p></div>`
                );
            } else if (successful === 0) {
                GoalV.Toast.error(`Failed to ${action.replace('ed', '')} matches`);
                $message.html(
                    `<div class="notice notice-error inline">` +
                    `<p><strong>Error:</strong> All operations failed.</p></div>`
                );
            } else {
                GoalV.Toast.warning(`${action} ${successful} matches, ${failed} failed`);
                $message.html(
                    `<div class="notice notice-warning inline">` +
                    `<p><strong>Partial Success:</strong> ${successful} ${action}, ${failed} failed.</p></div>`
                );
            }

            // Clear selections
            this.selectedMatches.clear();
            $('.match-checkbox').prop('checked', false);
            $('#select-all-matches').prop('checked', false);
            $('#bulk-action-select').val('');
            this.updateBulkActionState();

            // Hide message after 5 seconds
            setTimeout(() => {
                $message.fadeOut();
            }, 5000);
        },

        /**
         * View match details
         */
        viewMatchDetails: function (e) {
            e.preventDefault();

            const $button = $(e.currentTarget);
            const matchId = $button.data('match-id');

            this.currentMatchId = matchId;
            this.openModal();
            this.loadMatchDetails(matchId);
        },

        /**
         * Open modal
         */
        openModal: function () {
            $('#match-details-modal').fadeIn(300);
            $('body').css('overflow', 'hidden');
        },

        /**
         * Close modal
         */
        closeModal: function () {
            $('#match-details-modal').fadeOut(300);
            $('body').css('overflow', 'auto');
            this.currentMatchId = null;
        },

        /**
         * Handle modal background click
         */
        handleModalClick: function (e) {
            if ($(e.target).is('#match-details-modal')) {
                this.closeModal();
            }
        },

        /**
         * Load match details
         */
        loadMatchDetails: function (matchId) {
            const $content = $('#match-details-content');

            $content.html('<div style="text-align: center; padding: 40px;"><span class="spinner is-active"></span></div>');

            GoalV.Ajax.request('goalv_get_match_details', {
                match_id: matchId
            }, {
                success: (data) => {
                    this.renderMatchDetails(data);
                },
                error: (error) => {
                    $content.html(
                        '<div style="padding: 40px; text-align: center;">' +
                        '<p style="color: #dc3545;"><strong>Error:</strong> ' + error + '</p>' +
                        '<button type="button" class="button" onclick="GoalV.Matches.closeModal()">Close</button>' +
                        '</div>'
                    );
                }
            });
        },

        /**
         * Render match details
         */
        renderMatchDetails: function (data) {
            const match = data.match;
            const stats = data.stats;
            const options = data.vote_options;

            let html = `
                <div class="goalv-match-details">
                    <h2 style="margin-top: 0; border-bottom: 2px solid #ddd; padding-bottom: 15px;">
                        Match #${match.id} Details
                    </h2>

                    <!-- Match Info -->
                    <div style="display: grid; grid-template-columns: 1fr auto 1fr; gap: 20px; align-items: center; margin: 30px 0;">
                        <div style="text-align: center;">
                            ${match.home_team_logo ? `<img src="${match.home_team_logo}" style="width: 60px; height: 60px; object-fit: contain; margin-bottom: 10px;" />` : ''}
                            <h3 style="margin: 0;">${match.home_team_name}</h3>
                        </div>
                        
                        <div style="text-align: center;">
                            <div style="font-size: 36px; font-weight: bold; color: #333;">
                                ${match.home_score !== null ? match.home_score : '-'} - ${match.away_score !== null ? match.away_score : '-'}
                            </div>
                            ${match.status === 'live' && match.match_minute ? `<div class="goalv-match-minute">${match.match_minute}'</div>` : ''}
                        </div>
                        
                        <div style="text-align: center;">
                            ${match.away_team_logo ? `<img src="${match.away_team_logo}" style="width: 60px; height: 60px; object-fit: contain; margin-bottom: 10px;" />` : ''}
                            <h3 style="margin: 0;">${match.away_team_name}</h3>
                        </div>
                    </div>

                    <!-- Match Meta -->
                    <div style="background: #f9f9f9; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
                            <div>
                                <strong>Competition:</strong><br/>
                                ${match.competition_logo ? `<img src="${match.competition_logo}" style="width: 20px; height: 20px; object-fit: contain; vertical-align: middle;" /> ` : ''}
                                ${match.competition_name}
                            </div>
                            <div>
                                <strong>Status:</strong><br/>
                                <span class="goalv-status-badge goalv-status-${match.status}">
                                    ${match.status.charAt(0).toUpperCase() + match.status.slice(1)}
                                </span>
                            </div>
                            <div>
                                <strong>Date:</strong><br/>
                                ${new Date(match.match_date).toLocaleString()}
                            </div>
                            <div>
                                <strong>Venue:</strong><br/>
                                ${match.venue || 'N/A'}
                            </div>
                            ${match.referee ? `
                            <div>
                                <strong>Referee:</strong><br/>
                                ${match.referee}
                            </div>
                            ` : ''}
                            ${match.attendance ? `
                            <div>
                                <strong>Attendance:</strong><br/>
                                ${Number(match.attendance).toLocaleString()}
                            </div>
                            ` : ''}
                        </div>
                    </div>

                    <!-- Voting Statistics -->
                    <h3 style="border-bottom: 1px solid #ddd; padding-bottom: 10px;">Voting Statistics</h3>
                    <div style="background: #f9f9f9; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; text-align: center;">
                            <div>
                                <div style="font-size: 32px; font-weight: bold; color: #0073aa;">${stats.total_votes}</div>
                                <div style="color: #666; font-size: 14px;">Total Votes</div>
                            </div>
                            <div>
                                <div style="font-size: 32px; font-weight: bold; color: #28a745;">${options.length}</div>
                                <div style="color: #666; font-size: 14px;">Vote Options</div>
                            </div>
                            <div>
                                <div style="font-size: 20px; font-weight: bold; color: #dc3545;">
                                    ${stats.most_popular ? stats.most_popular.option_text : 'N/A'}
                                </div>
                                <div style="color: #666; font-size: 14px;">Most Popular</div>
                            </div>
                        </div>
                    </div>

                    <!-- Vote Options -->
                    ${options.length > 0 ? `
                    <h3 style="border-bottom: 1px solid #ddd; padding-bottom: 10px;">Vote Options</h3>
                    <div style="max-height: 300px; overflow-y: auto;">
                        <table class="wp-list-table widefat fixed striped" style="margin-top: 0;">
                            <thead>
                                <tr>
                                    <th>Option</th>
                                    <th>Category</th>
                                    <th>Type</th>
                                    <th style="text-align: center;">Votes</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${options.map(opt => `
                                <tr>
                                    <td><strong>${opt.option_text}</strong></td>
                                    <td>${opt.category}</td>
                                    <td><span class="goalv-option-category">${opt.option_type}</span></td>
                                    <td style="text-align: center;"><strong>${opt.votes_count}</strong></td>
                                </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                    ` : '<p style="color: #666; text-align: center; padding: 20px;">No vote options available.</p>'}

                    <!-- Actions -->
                    <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #ddd; display: flex; gap: 10px; justify-content: flex-end;">
                        <button type="button" class="button button-primary" onclick="GoalV.Matches.editMatch(${match.id})">
                            <span class="dashicons dashicons-edit"></span> Edit Match
                        </button>
                        <button type="button" class="button" onclick="GoalV.Matches.resyncMatchFromModal(${match.id})">
                            <span class="dashicons dashicons-update"></span> Resync from API
                        </button>
                        <button type="button" class="button button-link-delete" onclick="GoalV.Matches.deleteMatchFromModal(${match.id})">
                            <span class="dashicons dashicons-trash"></span> Delete Match
                        </button>
                        <button type="button" class="button" onclick="GoalV.Matches.closeModal()">Close</button>
                    </div>
                </div>
            `;

            $('#match-details-content').html(html);
        },

        /**
         * Resync single match
         */
        resyncMatch: function (e) {
            e.preventDefault();

            const $button = $(e.currentTarget);
            const matchId = $button.data('match-id');
            const $icon = $button.find('.dashicons');
            const $row = $button.closest('tr');

            GoalV.Utils.setButtonLoading($button, true, '');
            $icon.addClass('dashicons-update-spin');

            GoalV.Ajax.request('goalv_resync_match', {
                match_id: matchId
            }, {
                success: (data) => {
                    GoalV.Toast.success('Match resynced successfully');

                    // Visual feedback
                    $row.addClass('goalv-row-updated');
                    setTimeout(() => {
                        $row.removeClass('goalv-row-updated');
                    }, 500);

                    // Offer to reload
                    setTimeout(() => {
                        if (confirm('Match resynced! Reload to see updates?')) {
                            location.reload();
                        }
                    }, 1000);
                },
                error: (error) => {
                    GoalV.Toast.error('Resync failed: ' + error);
                },
                complete: () => {
                    GoalV.Utils.setButtonLoading($button, false, '');
                    $icon.removeClass('dashicons-update-spin');
                }
            });
        },

        /**
         * Resync from modal
         */
        resyncMatchFromModal: function (matchId) {
            if (!confirm('Resync this match from API?')) {
                return;
            }

            GoalV.Ajax.request('goalv_resync_match', {
                match_id: matchId
            }, {
                success: (data) => {
                    GoalV.Toast.success('Match resynced successfully');
                    this.loadMatchDetails(matchId); // Reload modal content
                },
                error: (error) => {
                    GoalV.Toast.error('Resync failed: ' + error);
                }
            });
        },

        /**
         * Delete single match
         */
        deleteMatch: function (e) {
            e.preventDefault();

            const $button = $(e.currentTarget);
            const matchId = $button.data('match-id');
            const $row = $button.closest('tr');

            if (!confirm('Delete this match? This will also delete all votes and vote options. This action cannot be undone.')) {
                return;
            }

            GoalV.Utils.setButtonLoading($button, true, '');

            GoalV.Ajax.request('goalv_delete_match', {
                match_id: matchId
            }, {
                success: () => {
                    GoalV.Toast.success('Match deleted successfully');

                    $row.fadeOut(300, function () {
                        $(this).remove();
                    });
                },
                error: (error) => {
                    GoalV.Toast.error('Delete failed: ' + error);
                    GoalV.Utils.setButtonLoading($button, false, '');
                }
            });
        },

        /**
         * Delete from modal
         */
        deleteMatchFromModal: function (matchId) {
            if (!confirm('Delete this match? This will also delete all votes and vote options. This action cannot be undone.')) {
                return;
            }

            GoalV.Ajax.request('goalv_delete_match', {
                match_id: matchId
            }, {
                success: () => {
                    GoalV.Toast.success('Match deleted successfully');
                    this.closeModal();

                    // Remove from table
                    $(`tr[data-match-id="${matchId}"]`).fadeOut(300, function () {
                        $(this).remove();
                    });
                },
                error: (error) => {
                    GoalV.Toast.error('Delete failed: ' + error);
                }
            });
        },

        /**
         * Export to CSV
         */
        exportCsv: function (e) {
            e.preventDefault();

            const $button = $(e.currentTarget);

            GoalV.Utils.setButtonLoading($button, true, 'Exporting...');

            // Build export URL with current filters
            const baseUrl = goalvAjaxConfig.ajax_url;
            const params = new URLSearchParams({
                action: 'goalv_export_matches_csv',
                nonce: goalvAjaxConfig.nonce,
                competition_id: this.filters.competition || '',
                status: this.filters.status || '',
                date_from: this.filters.dateFrom || '',
                date_to: this.filters.dateTo || '',
                search: this.filters.search || ''
            });

            // Trigger download
            window.location.href = baseUrl + '?' + params.toString();

            GoalV.Toast.success('CSV export started');

            setTimeout(() => {
                GoalV.Utils.setButtonLoading($button, false, 'Export CSV');
            }, 1000);
        },

        /**
         * Fix orphaned matches
         */
        fixOrphanedMatches: function (e) {
            e.preventDefault();

            const $button = $(e.currentTarget);

            if (!confirm('Create vote options for all matches without them?')) {
                return;
            }

            GoalV.Utils.setButtonLoading($button, true, 'Fixing...');

            GoalV.Ajax.request('goalv_fix_orphaned_matches', {}, {
                success: (data) => {
                    GoalV.Toast.success(data.message);

                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                },
                error: (error) => {
                    GoalV.Toast.error('Failed to fix orphaned matches: ' + error);
                    GoalV.Utils.setButtonLoading($button, false, 'Fix Orphaned Matches');
                }
            });
        },

        /**
         * Edit match (placeholder for future enhancement)
         */
        editMatch: function (matchId) {
            GoalV.Toast.info('Edit functionality coming soon!');
            // TODO: Implement inline editing or edit modal
        },

        /**
         * Restore selections from session
         */
        restoreSelections: function () {
            const saved = GoalV.Utils.storage.get('match_selections');

            if (saved && Array.isArray(saved)) {
                saved.forEach(id => {
                    this.selectedMatches.add(id);
                    $(`.match-checkbox[value="${id}"]`).prop('checked', true);
                });

                this.updateBulkActionState();
            }
        },

        /**
         * Save selections to session
         */
        saveSelections: function () {
            GoalV.Utils.storage.set('match_selections', Array.from(this.selectedMatches));
        }
    };

    // Save selections on page unload
    $(window).on('beforeunload', () => {
        if (window.GoalV && window.GoalV.Matches) {
            window.GoalV.Matches.saveSelections();
        }
    });

    // Expose globally
    window.GoalV = window.GoalV || {};
    window.GoalV.Matches = window.GoalVMatches;

})(jQuery);