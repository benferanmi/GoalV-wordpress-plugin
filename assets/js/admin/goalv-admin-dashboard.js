/**
 * GoalV Admin Dashboard
 * Version: 8.2.0
 * 
 * Dashboard overview with real-time stats
 */

(function($) {
    'use strict';

    window.GoalVDashboard = {
        
        refreshInterval: null,

        init: function() {
            console.log('Initializing Dashboard...');
            this.bindEvents();
            this.startAutoRefresh();
            this.initQuickActions();
            this.checkSystemStatus();
        },

        bindEvents: function() {
            // Quick action buttons
            $('.goalv-quick-actions .button').on('click', this.handleQuickAction.bind(this));
            
            // Refresh stats manually
            $('.goalv-refresh-stats').on('click', this.refreshStats.bind(this));
        },

        /**
         * Start automatic stat refresh
         */
        startAutoRefresh: function() {
            // Refresh every 60 seconds
            this.refreshInterval = setInterval(() => {
                this.refreshStats();
            }, 60000);

            console.log('Dashboard auto-refresh enabled (60s interval)');
        },

        /**
         * Stop automatic refresh
         */
        stopAutoRefresh: function() {
            if (this.refreshInterval) {
                clearInterval(this.refreshInterval);
                this.refreshInterval = null;
                console.log('Dashboard auto-refresh disabled');
            }
        },

        /**
         * Refresh dashboard statistics
         */
        refreshStats: function(showToast = false) {
            if (showToast) {
                GoalV.Toast.info('Refreshing stats...', 1000);
            }

            // Fetch updated HTML
            $.get(window.location.href, (html) => {
                const $newHtml = $(html);
                
                // Update stat cards
                $('.goalv-stat-card').each(function(index) {
                    const $oldCard = $(this);
                    const $newCard = $newHtml.find('.goalv-stat-card').eq(index);

                    if ($newCard.length) {
                        const newValue = $newCard.find('h3').text();
                        const oldValue = $oldCard.find('h3').text();

                        // Update value
                        $oldCard.find('h3').text(newValue);

                        // Add animation if value changed
                        if (newValue !== oldValue) {
                            $oldCard.addClass('goalv-stat-updated');
                            setTimeout(() => {
                                $oldCard.removeClass('goalv-stat-updated');
                            }, 500);
                        }
                    }
                });

                // Update system status indicators
                $('.goalv-status-item').each(function(index) {
                    const $newStatus = $newHtml.find('.goalv-status-item').eq(index);
                    
                    if ($newStatus.length) {
                        $(this).html($newStatus.html());
                    }
                });

                if (showToast) {
                    GoalV.Toast.success('Stats updated', 1500);
                }

                console.log('Dashboard stats refreshed');
            }).fail(() => {
                if (showToast) {
                    GoalV.Toast.error('Failed to refresh stats');
                }
            });
        },

        /**
         * Initialize quick action buttons
         */
        initQuickActions: function() {
            // Add keyboard shortcuts
            $(document).on('keydown', (e) => {
                // Alt + 1-6 for quick actions
                if (e.altKey && e.key >= '1' && e.key <= '6') {
                    e.preventDefault();
                    const index = parseInt(e.key) - 1;
                    const $button = $('.goalv-quick-actions .button').eq(index);
                    
                    if ($button.length) {
                        $button.click();
                    }
                }
            });
        },

        /**
         * Handle quick action button clicks
         */
        handleQuickAction: function(e) {
            const $button = $(e.currentTarget);
            const href = $button.attr('href');

            // If external link, track it
            if (href && href.startsWith('http')) {
                console.log('Quick action: External link -', href);
            }
        },

        /**
         * Check system status and show alerts
         */
        checkSystemStatus: function() {
            // Check for critical issues
            const hasErrors = $('.goalv-status-item .dashicons-dismiss').length > 0;
            const hasWarnings = $('.goalv-status-item .dashicons-minus').length > 0;

            if (hasErrors) {
                this.showSystemAlert('error');
            } else if (hasWarnings) {
                this.showSystemAlert('warning');
            }

            // Check for setup required
            const $setupNotice = $('.notice-warning:contains("Initial Setup Required")');
            if ($setupNotice.length) {
                this.highlightSetupSteps();
            }
        },

        /**
         * Show system alert
         */
        showSystemAlert: function(type) {
            if (type === 'error') {
                GoalV.Toast.error('System errors detected. Please check status.', 5000);
            } else if (type === 'warning') {
                GoalV.Toast.warning('System warnings detected. Review recommended.', 4000);
            }
        },

        /**
         * Highlight setup steps
         */
        highlightSetupSteps: function() {
            const $setupNotice = $('.notice-warning:contains("Initial Setup Required")');
            
            if ($setupNotice.length) {
                // Make setup steps clickable
                $setupNotice.find('a').each(function() {
                    $(this).css({
                        'font-weight': 'bold',
                        'text-decoration': 'underline'
                    });
                });

                // Add pulse animation
                $setupNotice.addClass('goalv-pulse-notice');
            }
        },

        /**
         * Monitor live matches
         */
        monitorLiveMatches: function() {
            const $liveCard = $('.goalv-stat-live');
            const liveCount = parseInt($liveCard.find('h3').text()) || 0;

            if (liveCount > 0) {
                // Add live indicator
                if (!$liveCard.find('.goalv-live-pulse').length) {
                    $liveCard.prepend(
                        '<div class="goalv-live-pulse" style="position: absolute; top: 10px; right: 10px; ' +
                        'width: 10px; height: 10px; background: red; border-radius: 50%; ' +
                        'animation: goalv-pulse 2s infinite;"></div>'
                    );
                }

                console.log(`Dashboard: Monitoring ${liveCount} live match(es)`);
            }
        },

        /**
         * Show welcome message for new installs
         */
        showWelcome: function() {
            const isNewInstall = GoalV.Utils.storage.get('goalv_first_visit');

            if (isNewInstall === null) {
                GoalV.Toast.info('Welcome to GoalV v8.2.0! 🎉', 5000);
                GoalV.Utils.storage.set('goalv_first_visit', false);
            }
        },

        /**
         * Cleanup on page leave
         */
        cleanup: function() {
            this.stopAutoRefresh();
        }
    };

    // Auto-refresh on page visibility change
    $(document).on('visibilitychange', () => {
        if (document.hidden) {
            if (window.GoalV && window.GoalV.Dashboard) {
                window.GoalV.Dashboard.stopAutoRefresh();
            }
        } else {
            if (window.GoalV && window.GoalV.Dashboard) {
                window.GoalV.Dashboard.startAutoRefresh();
                window.GoalV.Dashboard.refreshStats();
            }
        }
    });

    // Cleanup on page unload
    $(window).on('beforeunload', () => {
        if (window.GoalV && window.GoalV.Dashboard) {
            window.GoalV.Dashboard.cleanup();
        }
    });

    // Expose globally
    window.GoalV = window.GoalV || {};
    window.GoalV.Dashboard = window.GoalVDashboard;

})(jQuery);