/**
 * GoalV Admin Sync Manager
 * Version: 8.2.0
 * 
 * Handles all sync operations with real-time monitoring
 */

(function ($) {
    'use strict';

    window.GoalVSync = {
        // Active syncs tracking
        activeSyncs: new Set(),

        // Poll intervals
        intervals: {},

        init: function () {
            console.log('Initializing Sync Manager...');
            this.bindEvents();
            this.startLiveMatchMonitoring();
        },

        bindEvents: function () {
            console.log('GoalV Sync: Binding events to buttons...');

            // Get all buttons and log them
            const buttons = {
                competitions: $('#sync-competitions-btn'),
                matches: $('#sync-matches-btn'),
                live: $('#refresh-live-matches'),
                full: $('#force-full-sync-btn')
            };

            // Debug: Log button availability
            console.log('Button Status:');
            console.log('  - Competitions button found:', buttons.competitions.length > 0 ? 'YES' : 'NO');
            console.log('  - Matches button found:', buttons.matches.length > 0 ? 'YES' : 'NO');
            console.log('  - Live button found:', buttons.live.length > 0 ? 'YES' : 'NO');
            console.log('  - Full button found:', buttons.full.length > 0 ? 'YES' : 'NO');

            // Bind all button events
            if (buttons.competitions.length > 0) {
                buttons.competitions.on('click', () => {
                    console.log('Clicked: Competitions button');
                    this.triggerSync('competitions');
                });
            }

            if (buttons.matches.length > 0) {
                buttons.matches.on('click', () => {
                    console.log('Clicked: Matches button');
                    this.triggerSync('matches');
                });
            }

            if (buttons.live.length > 0) {
                buttons.live.on('click', () => {
                    console.log('Clicked: Live button');
                    this.triggerSync('live');
                });
            }

            if (buttons.full.length > 0) {
                buttons.full.on('click', () => {
                    console.log('Clicked: Full Resync button - THIS IS FIRING');
                    this.triggerSync('full');
                });
            } else {
                console.error('ERROR: #sync-full-btn not found in DOM!');
                console.log('Available elements:', {
                    syncFull: document.getElementById('sync-full-btn'),
                    allButtons: document.querySelectorAll('button')
                });
            }

            // Toggle live sync
            $('#toggle-live-sync').on('change', this.toggleLiveSync.bind(this));

            // Scheduled sync triggers
            $('.trigger-hourly-btn').on('click', () => this.triggerScheduled('hourly'));
            $('.trigger-live-btn').on('click', () => this.triggerScheduled('live'));
            $('.trigger-cleanup-btn').on('click', () => this.triggerScheduled('cleanup'));

            // Log management
            $('#refresh-sync-logs').on('click', () => location.reload());
            $('#clear-sync-logs').on('click', this.clearLogs.bind(this));
            $('#refresh-live-matches').on('click', () => location.reload());

            console.log('GoalV Sync: All events bound');
        },

        /**
         * Trigger manual sync operation with detailed error handling
         */
        triggerSync: function (type) {
            const config = {
                'competitions': {
                    action: 'goalv_manual_sync_competitions',
                    button: '#sync-competitions-btn',
                    text: 'Syncing competitions...',
                    buttonText: 'Fetch Competitions'
                },
                'matches': {
                    action: 'goalv_manual_sync_matches',
                    button: '#sync-matches-btn',
                    text: 'Syncing all matches...',
                    buttonText: 'Sync Matches (Next 7 Days)'
                },
                'live': {
                    action: 'goalv_manual_sync_live',
                    button: '#refresh-live-matches',
                    text: 'Updating live scores...',
                    buttonText: 'Update Live Scores'
                },
                'full': {
                    action: 'goalv_manual_sync_full',
                    button: '#force-full-sync-btn',
                    text: 'Running full system sync...',
                    buttonText: 'Force Full Resync'
                }
            };

            const syncConfig = config[type];
            if (!syncConfig) {
                console.error('Unknown sync type:', type);
                return;
            }

            // Prevent multiple simultaneous syncs
            if (this.activeSyncs.has(type)) {
                GoalV.Toast.warning('Sync already in progress');
                console.warn('Sync already in progress:', type);
                return;
            }

            const $button = $(syncConfig.button);
            const $progress = $('#sync-progress');
            const $statusText = $('#sync-status-text');
            const $result = $('#sync-result');

            // Mark as active
            this.activeSyncs.add(type);

            // Show progress
            $progress.show();
            $statusText.text(syncConfig.text);
            GoalV.Utils.setButtonLoading($button, true, 'Syncing...');

            // Clear previous results
            $result.html('');

            console.log('GoalV: Triggering sync -', {
                type: type,
                action: syncConfig.action,
                timestamp: new Date().toISOString()
            });

            // Execute sync
            GoalV.Ajax.request(syncConfig.action, {}, {
                success: (data) => {
                    console.log('GoalV: Sync success response:', data);

                    const message = data.message || 'Sync completed successfully';

                    $result.html(
                        '<div class="notice notice-success inline">' +
                        '<p><strong>✓ Success!</strong> ' + message + '</p>' +
                        '</div>'
                    );

                    GoalV.Toast.success(message);

                    // Refresh logs after 1 second
                    setTimeout(() => {
                        console.log('GoalV: Refreshing logs...');
                        this.refreshLogs();
                    }, 1000);

                    // Refresh page after 2 seconds for full sync
                    if (type === 'full') {
                        setTimeout(() => {
                            console.log('GoalV: Full sync complete, reloading page...');
                            location.reload();
                        }, 2000);
                    }
                },
                error: (error) => {
                    console.error('GoalV: Sync error response:', error);

                    const errorMessage = typeof error === 'string' ? error : 'Unknown error occurred';

                    $result.html(
                        '<div class="notice notice-error inline">' +
                        '<p><strong>✗ Error:</strong> ' + errorMessage + '</p>' +
                        '<p style="font-size: 12px; color: #666;">Check browser console and server debug.log for details</p>' +
                        '</div>'
                    );

                    GoalV.Toast.error('Sync failed: ' + errorMessage);
                },
                complete: () => {
                    console.log('GoalV: Sync complete callback for type:', type);
                    $progress.hide();
                    GoalV.Utils.setButtonLoading($button, false, syncConfig.buttonText);
                    this.activeSyncs.delete(type);
                }
            });
        },

        /**
         * Toggle live sync on/off
         */
        toggleLiveSync: function (e) {
            const $toggle = $(e.currentTarget);
            const enabled = $toggle.prop('checked');
            const $label = $toggle.next().next('.goalv-toggle-label');

            GoalV.Ajax.request('goalv_toggle_live_sync', {
                enabled: enabled ? 1 : 0
            }, {
                success: (data) => {
                    $label.text(enabled ? 'Enabled' : 'Disabled');
                    GoalV.Toast.success(data.message);

                    // Reload after 500ms to update schedule
                    setTimeout(() => {
                        location.reload();
                    }, 500);
                },
                error: (error) => {
                    // Revert toggle
                    $toggle.prop('checked', !enabled);
                    GoalV.Toast.error('Failed to toggle: ' + error);
                }
            });
        },

        /**
         * Trigger scheduled sync manually
         */
        triggerScheduled: function (type) {
            const config = {
                'hourly': {
                    action: 'goalv_trigger_hourly_sync',
                    text: 'Running hourly sync...'
                },
                'live': {
                    action: 'goalv_trigger_live_sync',
                    text: 'Running live sync...'
                },
                'cleanup': {
                    action: 'goalv_trigger_cleanup',
                    text: 'Running cleanup...'
                }
            };

            const syncConfig = config[type];
            if (!syncConfig) return;

            const $button = $(`.trigger-${type}-btn`);
            GoalV.Utils.setButtonLoading($button, true, 'Running...');

            GoalV.Ajax.request(syncConfig.action, {}, {
                success: (data) => {
                    GoalV.Toast.success(data.message);
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                },
                error: (error) => {
                    GoalV.Toast.error('Failed: ' + error);
                },
                complete: () => {
                    GoalV.Utils.setButtonLoading($button, false, 'Run Now');
                }
            });
        },

        /**
         * Clear sync logs
         */
        clearLogs: function (e) {
            e.preventDefault();

            if (!confirm('Clear old sync logs? This cannot be undone.')) {
                return;
            }

            GoalV.Ajax.request('goalv_clear_sync_logs', {}, {
                success: (data) => {
                    GoalV.Toast.success('Logs cleared successfully');
                    setTimeout(() => {
                        location.reload();
                    }, 500);
                },
                error: (error) => {
                    GoalV.Toast.error('Failed to clear logs: ' + error);
                }
            });
        },

        /**
         * Refresh logs section
         */
        refreshLogs: function () {
            const $logsContainer = $('#sync-logs-container');

            // Show loading state
            $logsContainer.css('opacity', '0.5');

            $.get(window.location.href, (html) => {
                const $newHtml = $(html);
                const $newLogs = $newHtml.find('#sync-logs-container');

                if ($newLogs.length) {
                    $logsContainer.html($newLogs.html());
                    $logsContainer.css('opacity', '1');
                }
            });
        },

        /**
         * Start live match monitoring
         */
        startLiveMatchMonitoring: function () {
            // Check if there are live matches
            const liveCount = parseInt($('.goalv-stat-live .goalv-stat-value').text()) || 0;

            if (liveCount > 0) {
                console.log(`Monitoring ${liveCount} live matches...`);

                // Refresh live match count every 30 seconds
                this.intervals.liveMatches = setInterval(() => {
                    this.refreshLiveMatchStats();
                }, 30000);

                // Add visual indicator
                this.addLiveIndicator();
            }
        },

        /**
         * Refresh live match statistics
         */
        refreshLiveMatchStats: function () {
            $.get(window.location.href, (html) => {
                const $newHtml = $(html);

                // Update live match count
                const newLiveCount = $newHtml.find('.goalv-stat-live .goalv-stat-value').text();
                const $liveCard = $('.goalv-stat-live .goalv-stat-value');

                if ($liveCard.text() !== newLiveCount) {
                    $liveCard.text(newLiveCount);

                    // Flash animation
                    $liveCard.closest('.goalv-stat-card').addClass('goalv-stat-updated');
                    setTimeout(() => {
                        $liveCard.closest('.goalv-stat-card').removeClass('goalv-stat-updated');
                    }, 500);
                }

                // If no more live matches, stop monitoring
                if (parseInt(newLiveCount) === 0) {
                    this.stopLiveMatchMonitoring();
                }
            });
        },

        /**
         * Add live indicator badge
         */
        addLiveIndicator: function () {
            const $liveCard = $('.goalv-stat-live');

            if (!$liveCard.find('.goalv-live-indicator').length) {
                $liveCard.prepend(
                    '<div class="goalv-live-indicator">🔴 Monitoring</div>'
                );
            }
        },

        /**
         * Stop live match monitoring
         */
        stopLiveMatchMonitoring: function () {
            if (this.intervals.liveMatches) {
                clearInterval(this.intervals.liveMatches);
                console.log('Live match monitoring stopped');

                $('.goalv-live-indicator').fadeOut();
            }
        },

        /**
         * Cleanup on page unload
         */
        cleanup: function () {
            Object.keys(this.intervals).forEach(key => {
                clearInterval(this.intervals[key]);
            });
        }
    };

    // Cleanup on page unload
    $(window).on('beforeunload', () => {
        if (window.GoalVSync) {
            window.GoalVSync.cleanup();
        }
    });

    // Expose globally
    window.GoalV = window.GoalV || {};
    window.GoalV.Sync = window.GoalVSync;

})(jQuery);