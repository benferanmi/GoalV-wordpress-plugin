/**
 * GoalV Football Predictions - Admin JavaScript
 * Handles admin panel functionality
 */

(function ($) {
    'use strict';

    const GoalVAdmin = {
        init: function () {
            if (this.initialized) {
                console.warn('GoalVAdmin already initialized');
                return;
            }

            this.cacheElements();
            this.bindEvents();
            this.initSettings();
            this.handleFormValidation();
            this.initCustomOptions();

            this.initialized = true;
        },

        bindEvents: function () {
            // Sync matches button - namespaced
            $(document).off('click.goalvSync');
            $(document).on('click.goalvSync', '#sync-matches-btn', this.handleSync.bind(this));

            // Test API button - namespaced
            $(document).off('click.goalvApiTest');
            $(document).on('click.goalvApiTest', '#test-api-btn', this.testApiConnection.bind(this));

            // Week selector change - namespaced (handler used to update button text)
            $(document).off('change.goalvWeekSelector');
            $(document).on('change.goalvWeekSelector', '#sync_week_selector', this.handleWeekSelection.bind(this));

            // Settings form validation - namespaced
            $(document).off('submit.goalvSettings');
            $(document).on('submit.goalvSettings', '#goalv-settings-form', this.validateSettings.bind(this));

            // API key testing (legacy) - namespaced
            $(document).off('click.goalvApiKey');
            $(document).on('click.goalvApiKey', '#test-api-key', this.testApiKey.bind(this));

            // Competition change - namespaced
            $(document).off('change.goalvCompetition');
            $(document).on('change.goalvCompetition', '#goalv_competition_id', this.handleCompetitionChange.bind(this));
        },

        cleanup: function () {
            $(document).off('.goalvSync');
            $(document).off('.goalvApiTest');
            $(document).off('.goalvWeekSelector');
            $(document).off('.goalvSettings');
            $(document).off('.goalvApiKey');
            $(document).off('.goalvCompetition');
            $(document).off('.goalvCustomOptions');

            // Abort any pending AJAX requests
            $('.add-custom-option-btn').each(function () {
                const xhr = $(this).data('xhr');
                if (xhr && xhr.readyState !== 4) {
                    xhr.abort();
                }
            });

            this.initialized = false;
        },


        /**
         * Handle week selection change - NEW METHOD
         */
        handleWeekSelection: function (e) {
            const selectedWeek = $(e.currentTarget).val();
            const $syncBtn = $('#sync-matches-btn');

            if (selectedWeek) {
                $syncBtn.text(`Sync Game Week ${selectedWeek}`);
            } else {
                $syncBtn.text('Sync Next Week\'s Matches');
            }
        },

        handleAjaxError: function (xhr, action = 'request', retryCallback = null) {
            console.error(`GoalV ${action} failed:`, xhr.status, xhr.statusText);

            const errorMap = {
                403: 'Permission denied. Please refresh and try again.',
                404: 'Endpoint not found. Please check plugin configuration.',
                500: 'Server error. Please check configuration.',
                502: 'Bad gateway. Server may be overloaded.',
                503: 'Service unavailable. Please try again later.',
                0: 'Network error. Please check your connection.'
            };

            let errorMessage = errorMap[xhr.status] || `${action} failed. Please try again.`;

            // Add retry option for network errors
            if (xhr.status === 0 || xhr.status >= 500) {
                if (retryCallback && typeof retryCallback === 'function') {
                    errorMessage += ' <button class="button button-small retry-ajax">Retry</button>';

                    // Add retry functionality
                    setTimeout(() => {
                        $('.retry-ajax').on('click', function (e) {
                            e.preventDefault();
                            $(this).remove();
                            retryCallback();
                        });
                    }, 100);
                }
            }

            return errorMessage;
        },

        updateLastSyncStatus: function (weekInfo) {
            const $statusTable = $('.goalv-status-table');
            if (!$statusTable.length) return;

            const $lastWeekCell = $statusTable.find('td').eq(3);
            if ($lastWeekCell.length && weekInfo) {
                $lastWeekCell.text(weekInfo);
            }
        },
        /**
 * Get AJAX configuration with proper fallbacks
 */
        getAjaxConfig: function () {
            try {
                if (typeof goalv_ajax !== 'undefined' && goalv_ajax.ajax_url) {
                    return {
                        url: goalv_ajax.ajax_url,
                        nonce: goalv_ajax.nonce
                    };
                }

                if (typeof ajaxurl !== 'undefined') {
                    const nonce = $('#goalv-admin-nonce').val() ||
                        $('input[name="_wpnonce"]').val() ||
                        $('#goalv-vote-nonce').val();

                    if (!nonce) {
                        console.warn('No nonce available for AJAX requests');
                        return null;
                    }

                    return { url: ajaxurl, nonce: nonce };
                }

                console.error('No AJAX configuration available');
                return null;
            } catch (error) {
                console.error('Error getting AJAX config:', error);
                return null;
            }
        },

        improvedHandleSync: function (e) {
            e.preventDefault();

            const $btn = this.$syncBtn; // Use cached element
            const $loader = this.$syncLoader;
            const $result = this.$syncResult;

            // Add ARIA attributes for screen readers
            $btn.attr('aria-disabled', 'true');
            $result.attr('aria-live', 'polite');

            const selectedWeek = this.$weekSelector.val();

            // Show loading state with better accessibility
            $loader.addClass('is-active').attr('aria-hidden', 'false');
            $btn.prop('disabled', true).text('Syncing...');
            $result.html('<div class="notice notice-info"><p role="status">Syncing matches from API...</p></div>');

            const config = this.getAjaxConfig();
            if (!config) {
                this.showSyncError($btn, $loader, $result, 'AJAX configuration error. Please refresh the page.');
                return;
            }

            const requestData = {
                action: 'goalv_sync_matches',
                nonce: config.nonce
            };

            if (selectedWeek) {
                requestData.week = selectedWeek;
            }

            // Add timeout to prevent hanging requests
            $.ajax({
                url: config.url,
                type: 'POST',
                data: requestData,
                timeout: 30000 // 30 second timeout
            })
                .done((response) => {
                    this.handleSyncResponse(response, $btn, $loader, $result);
                })
                .fail((xhr, status, error) => {
                    if (status === 'timeout') {
                        error = 'Request timed out. Please try again.';
                    }
                    this.handleSyncFailure(xhr, status, error, $btn, $loader, $result);
                })
                .always(() => {
                    $btn.attr('aria-disabled', 'false');
                    $loader.attr('aria-hidden', 'true');
                });
        },



        /**
         * Handle match synchronization - UPDATED with week selector support
         */
        handleSync: function (e) {
            e.preventDefault();

            const $btn = $(e.currentTarget);
            const $loader = this.$syncLoader;
            const $result = this.$syncResult;

            // Get selected week from dropdown - NEW
            const selectedWeek = $('#sync_week_selector').val();

            // Show loading state
            $loader.addClass('is-active');
            $btn.prop('disabled', true).text('Syncing...');
            $result.html('<div class="notice notice-info"><p>Syncing matches from API...</p></div>');

            // Determine AJAX URL and nonce
            let ajaxUrl, ajaxNonce;

            if (typeof goalv_ajax !== 'undefined') {
                ajaxUrl = goalv_ajax.ajax_url;
                ajaxNonce = goalv_ajax.nonce;
            } else if (typeof ajaxurl !== 'undefined') {
                ajaxUrl = ajaxurl;
                ajaxNonce = $('#goalv-admin-nonce').val() || $('input[name="_wpnonce"]').val();
            } else {
                this.showSyncError($btn, $loader, $result, 'AJAX configuration error. Please refresh the page.');
                return;
            }

            // Prepare request data - UPDATED to include week
            const requestData = {
                action: 'goalv_sync_matches',
                nonce: ajaxNonce
            };

            // Add week parameter if selected - NEW
            if (selectedWeek) {
                requestData.week = selectedWeek;
                console.log(`GoalV Admin: Syncing specific week: GW${selectedWeek}`);
            } else {
                console.log('GoalV Admin: Auto-detecting next week with matches');
            }

            // Make sync request
            $.post(ajaxUrl, requestData)
                .done((response) => {
                    this.handleSyncResponse(response, $btn, $loader, $result);
                })
                .fail((xhr, status, error) => {
                    this.handleSyncFailure(xhr, status, error, $btn, $loader, $result);
                });
        },

        /**
         * Test API Connection - NEW METHOD
         */
        testApiConnection: function (e) {
            e.preventDefault();

            const $btn = $(e.currentTarget);
            const $result = $('#sync-result');
            const originalText = $btn.text();

            $btn.prop('disabled', true).text('Testing...');
            $result.html('<div class="notice notice-info"><p>Testing API connection...</p></div>');

            $.post(ajaxurl, {
                action: 'goalv_test_api',
                nonce: $('#goalv-admin-nonce').val() || (typeof goalv_ajax !== 'undefined' ? goalv_ajax.nonce : '')
            })
                .done((response) => {
                    if (response.success) {
                        $result.html(`<div class="notice notice-success"><p>API connection successful! Competition: ${response.data.competition || 'Unknown'}</p></div>`);
                    } else {
                        $result.html(`<div class="notice notice-error"><p>API test failed: ${response.data || 'Unknown error'}</p></div>`);
                    }
                })
                .fail((xhr, status, error) => {
                    console.error('API test failed:', xhr.status, xhr.statusText, error);
                    $result.html('<div class="notice notice-error"><p>Network error during API test. Please check your connection.</p></div>');
                })
                .always(() => {
                    $btn.prop('disabled', false).text(originalText);
                });
        },

        handleSyncResponse: function (response, $btn, $loader, $result) {
            console.log('Sync response:', response);

            if (response && response.success) {
                // Build success message with week info - UPDATED
                let message = response.data.message;
                if (response.data.week) {
                    message = `${response.data.week}: ${message}`;
                }

                $result.html(`<div class="notice notice-success"><p>${message}</p></div>`);

                // Update last sync display - UPDATED with week info
                const now = new Date();
                const timeString = now.toLocaleString();
                let syncInfo = `Last sync: ${timeString}`;
                if (response.data.week) {
                    syncInfo += ` (${response.data.week})`;
                }
                $('.goalv-last-sync-info').html(`<p class="description">${syncInfo}</p>`);

                // Update last synced week in status if element exists - NEW
                const $lastWeekStatus = $('.goalv-status-table').find('td').eq(3);
                if ($lastWeekStatus.length && response.data.week) {
                    $lastWeekStatus.text(response.data.week);
                }

                // Show reload notification
                this.showReloadNotification();

                // Auto-reload after delay
                setTimeout(() => {
                    if (confirm('Sync completed! Reload page to see new matches?')) {
                        location.reload();
                    }
                }, 2000);
            } else {
                const errorMsg = (response && response.data) ? response.data : 'Sync failed - unknown error';
                $result.html(`<div class="notice notice-error"><p>${errorMsg}</p></div>`);
            }

            this.resetSyncButton($btn, $loader);
        },

        handleSyncFailure: function (xhr, status, error, $btn, $loader, $result) {
            console.error('Sync request failed:', xhr.status, xhr.statusText, error);

            let errorMessage = 'Network error during sync. Please try again.';

            if (xhr.status === 403) {
                errorMessage = 'Permission denied. Please refresh the page and try again.';
            } else if (xhr.status === 500) {
                errorMessage = 'Server error during sync. Please check the API configuration.';
            } else if (xhr.responseJSON && xhr.responseJSON.data) {
                errorMessage = xhr.responseJSON.data;
            }

            $result.html(`<div class="notice notice-error"><p>${errorMessage}</p></div>`);
            this.resetSyncButton($btn, $loader);
        },

        showSyncError: function ($btn, $loader, $result, message) {
            $result.html(`<div class="notice notice-error"><p>${message}</p></div>`);
            this.resetSyncButton($btn, $loader);
        },

        resetSyncButton: function ($btn, $loader) {
            // Reset button text based on current week selection - UPDATED
            const selectedWeek = $('#sync_week_selector').val();
            const buttonText = selectedWeek ? `Sync Game Week ${selectedWeek}` : 'Sync Next Week\'s Matches';

            $btn.prop('disabled', false).text(buttonText);
            $loader.removeClass('is-active');
        },

        showReloadNotification: function () {
            const $notification = $('<div class="goalv-admin-notification notice notice-info is-dismissible"><p>Sync completed! You may want to reload the page to see new matches.</p></div>');
            $('.wrap h1').after($notification);

            setTimeout(() => {
                $notification.fadeOut();
            }, 10000);
        },

        /**
         * API Key Testing (legacy method)
         */
        testApiKey: function (e) {
            e.preventDefault();

            const $btn = $(e.currentTarget);
            const $result = $('#api-test-result');
            const apiKey = $('#goalv_api_key').val();

            if (!apiKey) {
                $result.html('<div class="notice notice-error"><p>Please enter an API key first.</p></div>');
                return;
            }

            $btn.prop('disabled', true).text('Testing...');
            $result.html('<div class="notice notice-info"><p>Testing API connection...</p></div>');

            $.post(ajaxurl, {
                action: 'goalv_test_api_key',
                api_key: apiKey,
                nonce: $('#goalv-admin-nonce').val()
            })
                .done((response) => {
                    if (response.success) {
                        $result.html(`<div class="notice notice-success"><p>API connection successful! ${response.data.message}</p></div>`);
                    } else {
                        $result.html(`<div class="notice notice-error"><p>API test failed: ${response.data || 'Unknown error'}</p></div>`);
                    }
                })
                .fail(() => {
                    $result.html('<div class="notice notice-error"><p>Network error during API test.</p></div>');
                })
                .always(() => {
                    $btn.prop('disabled', false).text('Test API Key');
                });
        },

        /**
         * Settings management
         */
        initSettings: function () {
            // Load saved settings state
            this.toggleAdvancedSettings();
            this.updateCompetitionInfo();
        },



        validateSettings: function (e) {
            const apiKey = $('#goalv_api_key').val();

            if (!apiKey) {
                e.preventDefault();
                alert('API Key is required. Please enter your football-data.org API key.');
                $('#goalv_api_key').focus();
                return false;
            }

            // Show saving indicator
            this.showSavingIndicator();
        },

        handleCompetitionChange: function (e) {
            const $select = $(e.currentTarget);
            const competitionId = $select.val();

            // Update competition info display
            this.updateCompetitionInfo(competitionId);

            // Mark settings as changed
            this.markSettingsChanged();

            // Refresh week selector for new competition - NEW
            this.refreshWeekSelector(competitionId);
        },

        /**
         * Refresh week selector for new competition - NEW METHOD
         */
        refreshWeekSelector: function (competitionId) {
            const $weekSelector = $('#sync_week_selector');
            const $result = $('#sync-result');

            // Show loading state
            $result.html('<div class="notice notice-info"><p>Updating available weeks for selected competition...</p></div>');

            $.post(ajaxurl, {
                action: 'goalv_get_available_weeks',
                competition_id: competitionId,
                nonce: $('#goalv-admin-nonce').val() || (typeof goalv_ajax !== 'undefined' ? goalv_ajax.nonce : '')
            })
                .done((response) => {
                    if (response.success && response.data.weeks) {
                        // Update dropdown options
                        $weekSelector.empty();
                        $weekSelector.append('<option value="">' + response.data.auto_detect_label + '</option>');

                        $.each(response.data.weeks, function (weekNum, weekLabel) {
                            $weekSelector.append(`<option value="${weekNum}">${weekLabel}</option>`);
                        });

                        $result.html('<div class="notice notice-success"><p>Available weeks updated for selected competition.</p></div>');

                        // Clear result message after 3 seconds
                        setTimeout(() => {
                            $result.html('');
                        }, 3000);
                    } else {
                        $result.html('<div class="notice notice-error"><p>Failed to update available weeks.</p></div>');
                    }
                })
                .fail(() => {
                    $result.html('<div class="notice notice-error"><p>Network error while updating weeks.</p></div>');
                });
        },

        updateCompetitionInfo: function (competitionId = null) {
            if (!competitionId) {
                competitionId = $('#goalv_competition_id').val();
            }

            const competitions = {
                '2021': { name: 'Premier League', country: 'England' },
                '2014': { name: 'La Liga', country: 'Spain' },
                '2002': { name: 'Bundesliga', country: 'Germany' },
                '2015': { name: 'Ligue 1', country: 'France' },
                '2019': { name: 'Serie A', country: 'Italy' },
                '2001': { name: 'UEFA Champions League', country: 'Europe' }
            };

            const competition = competitions[competitionId];
            if (competition) {
                $('.goalv-competition-info').html(`
                    <p class="description">
                        <strong>${competition.name}</strong> (${competition.country})
                    </p>
                `);
            }
        },

        toggleAdvancedSettings: function () {
            const $toggle = $('#goalv-advanced-toggle');
            const $advanced = $('.goalv-advanced-settings');

            $toggle.on('click', function (e) {
                e.preventDefault();
                $advanced.slideToggle();
                $(this).text($advanced.is(':visible') ? 'Hide Advanced Settings' : 'Show Advanced Settings');
            });
        },

        markSettingsChanged: function () {
            if (!$('.goalv-settings-changed').length) {
                $('.wrap h1').after('<div class="goalv-settings-changed notice notice-warning"><p>Settings have been changed. Don\'t forget to save!</p></div>');
            }
        },

        showSavingIndicator: function () {
            const $submit = $('#submit');
            $submit.prop('disabled', true).val('Saving...');

            setTimeout(() => {
                $submit.prop('disabled', false).val('Save Settings');
            }, 3000);
        },

        /**
         * Dashboard widgets and statistics
         */
        initDashboardWidgets: function () {
            this.loadVotingStats();
            this.loadRecentActivity();
        },

        loadVotingStats: function () {
            $.post(ajaxurl, {
                action: 'goalv_get_voting_stats',
                nonce: $('#goalv-admin-nonce').val()
            })
                .done((response) => {
                    if (response.success) {
                        this.displayVotingStats(response.data);
                    }
                });
        },

        displayVotingStats: function (stats) {
            const $statsContainer = $('.goalv-voting-stats');
            if ($statsContainer.length) {
                $statsContainer.html(`
                    <div class="goalv-stat-grid">
                        <div class="goalv-stat-item">
                            <span class="goalv-stat-number">${stats.total_votes}</span>
                            <span class="goalv-stat-label">Total Votes</span>
                        </div>
                        <div class="goalv-stat-item">
                            <span class="goalv-stat-number">${stats.active_matches}</span>
                            <span class="goalv-stat-label">Active Matches</span>
                        </div>
                        <div class="goalv-stat-item">
                            <span class="goalv-stat-number">${stats.users_voted}</span>
                            <span class="goalv-stat-label">Users Voted</span>
                        </div>
                    </div>
                `);
            }
        },

        loadRecentActivity: function () {
            $.post(ajaxurl, {
                action: 'goalv_get_recent_activity',
                nonce: $('#goalv-admin-nonce').val()
            })
                .done((response) => {
                    if (response.success) {
                        this.displayRecentActivity(response.data);
                    }
                });
        },

        displayRecentActivity: function (activities) {
            const $activityContainer = $('.goalv-recent-activity');
            if ($activityContainer.length && activities.length > 0) {
                let activityHtml = '<ul class="goalv-activity-list">';

                activities.forEach(activity => {
                    activityHtml += `
                        <li class="goalv-activity-item">
                            <span class="goalv-activity-time">${activity.time}</span>
                            <span class="goalv-activity-text">${activity.text}</span>
                        </li>
                    `;
                });

                activityHtml += '</ul>';
                $activityContainer.html(activityHtml);
            }
        },

        /**
         * Error log management
         */
        initErrorLogs: function () {
            $(document).on('click', '.goalv-clear-logs', this.clearErrorLogs.bind(this));
            $(document).on('click', '.goalv-refresh-logs', this.refreshErrorLogs.bind(this));
        },

        clearErrorLogs: function (e) {
            e.preventDefault();

            if (!confirm('Are you sure you want to clear all error logs?')) {
                return;
            }

            $.post(ajaxurl, {
                action: 'goalv_clear_error_logs',
                nonce: $('#goalv-admin-nonce').val()
            })
                .done((response) => {
                    if (response.success) {
                        $('.goalv-error-logs-container').html('<p>Error logs cleared.</p>');
                    }
                });
        },

        refreshErrorLogs: function (e) {
            e.preventDefault();

            $.post(ajaxurl, {
                action: 'goalv_get_error_logs',
                nonce: $('#goalv-admin-nonce').val()
            })
                .done((response) => {
                    if (response.success) {
                        $('.goalv-error-logs-container').html(response.data.html);
                    }
                });
        },

        /**
         * Bulk actions for matches
         */
        initBulkActions: function () {
            $(document).on('click', '#goalv-select-all-matches', this.toggleAllMatches.bind(this));
            $(document).on('click', '.goalv-bulk-action-btn', this.handleBulkAction.bind(this));
        },

        toggleAllMatches: function (e) {
            const isChecked = $(e.currentTarget).prop('checked');
            $('.goalv-match-checkbox').prop('checked', isChecked);
        },

        handleBulkAction: function (e) {
            e.preventDefault();

            const action = $('#goalv-bulk-action-select').val();
            const selectedMatches = $('.goalv-match-checkbox:checked').map(function () {
                return $(this).val();
            }).get();

            if (!action || selectedMatches.length === 0) {
                alert('Please select an action and at least one match.');
                return;
            }

            if (action === 'delete' && !confirm(`Delete ${selectedMatches.length} matches?`)) {
                return;
            }

            this.performBulkAction(action, selectedMatches);
        },

        performBulkAction: function (action, matchIds) {
            const $indicator = $('.goalv-bulk-loading');
            $indicator.show();

            $.post(ajaxurl, {
                action: 'goalv_bulk_action',
                bulk_action: action,
                match_ids: matchIds,
                nonce: $('#goalv-admin-nonce').val()
            })
                .done((response) => {
                    if (response.success) {
                        location.reload(); // Refresh to show changes
                    } else {
                        alert('Bulk action failed: ' + (response.data || 'Unknown error'));
                    }
                })
                .fail(() => {
                    alert('Network error during bulk action.');
                })
                .always(() => {
                    $indicator.hide();
                });
        },

        /**
         * Form validation and UX improvements
         */
        handleFormValidation: function () {
            // Real-time API key validation
            $('#goalv_api_key').on('input', this.debounce(this.validateApiKeyFormat.bind(this), 500));

            // Setting change indicators
            $('.goalv-settings-form input, .goalv-settings-form select').on('change', this.markSettingsChanged.bind(this));
        },

        validateApiKeyFormat: function () {
            const $input = $('#goalv_api_key');
            const apiKey = $input.val();
            const $feedback = $('#api-key-feedback');

            if (!apiKey) {
                $feedback.html('').hide();
                return;
            }

            if (apiKey.length < 20) {
                $feedback.html('<span class="goalv-validation-error">API key seems too short</span>').show();
            } else if (!/^[a-zA-Z0-9-_]+$/.test(apiKey)) {
                $feedback.html('<span class="goalv-validation-error">API key contains invalid characters</span>').show();
            } else {
                $feedback.html('<span class="goalv-validation-success">API key format looks valid</span>').show();
            }
        },

        /**
         * Match management utilities
         */
        initMatchManagement: function () {
            $(document).on('click', '.goalv-delete-match', this.deleteMatch.bind(this));
            $(document).on('click', '.goalv-reset-votes', this.resetMatchVotes.bind(this));
        },

        deleteMatch: function (e) {
            e.preventDefault();

            const $btn = $(e.currentTarget);
            const matchId = $btn.data('match-id');
            const matchTitle = $btn.data('match-title');

            if (!confirm(`Delete match "${matchTitle}"? This will also remove all votes.`)) {
                return;
            }

            $.post(ajaxurl, {
                action: 'goalv_delete_match',
                match_id: matchId,
                nonce: $('#goalv-admin-nonce').val()
            })
                .done((response) => {
                    if (response.success) {
                        $btn.closest('tr').fadeOut(() => {
                            $btn.closest('tr').remove();
                        });
                    } else {
                        alert('Failed to delete match: ' + (response.data || 'Unknown error'));
                    }
                });
        },

        resetMatchVotes: function (e) {
            e.preventDefault();

            const $btn = $(e.currentTarget);
            const matchId = $btn.data('match-id');

            if (!confirm('Reset all votes for this match? This cannot be undone.')) {
                return;
            }

            $.post(ajaxurl, {
                action: 'goalv_reset_match_votes',
                match_id: matchId,
                nonce: $('#goalv-admin-nonce').val()
            })
                .done((response) => {
                    if (response.success) {
                        alert('Votes reset successfully');
                        location.reload();
                    } else {
                        alert('Failed to reset votes: ' + (response.data || 'Unknown error'));
                    }
                });
        },

        /**
         * Utility functions
         */
        debounce: function (func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        },

        /**
         * Auto-save functionality
         */
        initAutoSave: function () {
            let autoSaveTimeout;

            $('.goalv-settings-form input, .goalv-settings-form select').on('change', function () {
                clearTimeout(autoSaveTimeout);
                autoSaveTimeout = setTimeout(() => {
                    GoalVAdmin.autoSaveSettings();
                }, 5000); // Auto-save 5 seconds after last change
            });
        },

        autoSaveSettings: function () {
            const $form = $('#goalv-settings-form');
            const formData = $form.serialize();

            $.post(ajaxurl, formData + '&action=goalv_auto_save_settings')
                .done((response) => {
                    if (response.success) {
                        this.showAutoSaveIndicator();
                    }
                });
        },


        initCustomOptions: function () {
            // Precise unbinds per event type
            $(document).off('click.goalvCustomOptions');
            $(document).off('keypress.goalvCustomOptions');

            // Bind with namespace (preserve original selectors)
            $(document).on('click.goalvCustomOptions', '.add-custom-option-btn', this.addCustomOption.bind(this));
            $(document).on('click.goalvCustomOptions', '.remove-custom-option', this.removeCustomOption.bind(this));
            $(document).on('keypress.goalvCustomOptions', '.custom-option-text', function (e) {
                if (e.which === 13) { // Enter key
                    e.preventDefault();
                    $(this).closest('.goalv-custom-options').find('.add-custom-option-btn').click();
                }
            });
        },

        addCustomOption: function (e) {
            e.preventDefault();

            const $btn = $(e.currentTarget);

            // More robust prevention of multiple clicks
            if ($btn.prop('disabled') || $btn.hasClass('processing')) {
                return;
            }

            const $container = $btn.closest('.goalv-custom-options');
            const $textInput = $container.find('.custom-option-text');
            const $typeSelect = $container.find('.custom-option-type');
            const $result = $container.find('.custom-option-result');

            const matchId = $container.data('match-id');
            const optionText = $textInput.val().trim();
            const optionType = $typeSelect.val();

            if (!optionText) {
                $result.html('<span style="color: red;">Please enter option text</span>');
                $textInput.focus();
                return;
            }

            // Enhanced loading state
            const originalText = $btn.text();
            $btn.prop('disabled', true).addClass('processing').text('Adding...');
            $result.html('<span style="color: blue;">Adding option...</span>');

            // Add request timeout and abort capability
            const xhr = $.post(ajaxurl, {
                action: 'goalv_add_custom_option',
                match_id: matchId,
                option_text: optionText,
                option_type: optionType,
                nonce: $('#goalv-vote-nonce').val()
            })
                .done((response) => {
                    if (response.success) {
                        $result.html('<span style="color: green;">Option added successfully!</span>');
                        $textInput.val('');

                        setTimeout(() => $result.html(''), 3000);
                    } else {
                        $result.html('<span style="color: red;">Failed: ' + (response.data || 'Unknown error') + '</span>');
                    }
                })
                .fail((xhr, status, error) => {
                    console.error('Add custom option failed:', xhr, status, error);
                    if (status !== 'abort') {
                        $result.html('<span style="color: red;">Network error: ' + error + '</span>');
                    }
                })
                .always(() => {
                    $btn.prop('disabled', false).removeClass('processing').text(originalText);
                });

            // Store xhr for potential abort
            $btn.data('xhr', xhr);
        },

        /**
         * Remove custom option
         */
        removeCustomOption: function (e) {
            e.preventDefault();

            const $btn = $(e.currentTarget);
            const optionId = $btn.data('option-id');
            const optionText = $btn.data('option-text');

            if (!confirm(`Remove option "${optionText}"?`)) {
                return;
            }

            $.post(ajaxurl, {
                action: 'goalv_remove_custom_option',
                option_id: optionId,
                nonce: $('#goalv-vote-nonce').val()
            })
                .done((response) => {
                    if (response.success) {
                        $btn.closest('.custom-option-item').fadeOut(() => {
                            $btn.closest('.custom-option-item').remove();
                        });
                    } else {
                        alert('Failed to remove option: ' + (response.data || 'Unknown error'));
                    }
                })
                .fail(() => {
                    alert('Network error while removing option');
                });
        },

        showAutoSaveIndicator: function () {
            const $indicator = $('.goalv-auto-save-indicator');
            if (!$indicator.length) {
                $('.wrap h1').after('<div class="goalv-auto-save-indicator notice notice-success is-dismissible"><p>Settings auto-saved</p></div>');
            }

            setTimeout(() => {
                $('.goalv-auto-save-indicator').fadeOut();
            }, 3000);
        },

        // 1. Add request debouncing for rapid clicks
        debounceSync: function () {
            if (this.syncTimeout) {
                clearTimeout(this.syncTimeout);
            }

            this.syncTimeout = setTimeout(() => {
                this.handleSync.apply(this, arguments);
            }, 300); // 300ms debounce
        },

        // 2. Add connection status monitoring
        checkConnection: function () {
            return navigator.onLine &&
                (!this.state.lastConnectionCheck ||
                    Date.now() - this.state.lastConnectionCheck > 30000);
        },

        // 3. Add better loading indicators
        showLoadingSpinner: function ($element, text = 'Loading...') {
            $element.html(`
        <span class="spinner is-active" style="float: left; margin-right: 5px;"></span>
        ${text}
    `);
        },

        // 4. Add form validation improvements
        validateForm: function ($form) {
            const requiredFields = $form.find('[required]');
            let isValid = true;

            requiredFields.each(function () {
                const $field = $(this);
                if (!$field.val().trim()) {
                    $field.addClass('error');
                    isValid = false;
                } else {
                    $field.removeClass('error');
                }
            });

            return isValid;
        },

        state: {
            syncInProgress: false,
            lastSyncTime: null,
            lastConnectionCheck: null,
            pendingRequests: new Map()
        },

        setSyncState: function (inProgress) {
            this.state.syncInProgress = inProgress;
            if (!inProgress) {
                this.state.lastSyncTime = new Date();
            }
        },
        // CACHE COMMON ELEMENTS:
        cacheElements: function () {
            this.$syncBtn = $('#sync-matches-btn');
            this.$syncLoader = $('#sync-loader');
            this.$syncResult = $('#sync-result');
            this.$weekSelector = $('#sync_week_selector');
            this.$settingsForm = $('#goalv-settings-form');
            this.$adminNonce = $('#goalv-admin-nonce');
        }

    };

    /**
     * Initialize admin functionality
     */
    $(document).ready(function () {
        // Only initialize on admin pages
        if ($('body').hasClass('wp-admin')) {
            GoalVAdmin.init();

            // Initialize additional features if elements exist
            if ($('.goalv-voting-stats').length) {
                GoalVAdmin.initDashboardWidgets();
            }

            if ($('.goalv-error-logs-container').length) {
                GoalVAdmin.initErrorLogs();
            }

            if ($('.goalv-match-checkbox').length) {
                GoalVAdmin.initBulkActions();
            }

            if ($('#goalv-settings-form').length) {
                GoalVAdmin.initMatchManagement();
                GoalVAdmin.initAutoSave();
            }
        }
    });

    // Expose for debugging
    window.GoalVAdmin = GoalVAdmin;

})(jQuery);