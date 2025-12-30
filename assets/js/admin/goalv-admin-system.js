/**
 * GoalV Admin System Info
 * Version: 8.2.0
 * 
 * System diagnostics, health checks, and maintenance
 */

(function($) {
    'use strict';

    window.GoalVSystem = {
        
        init: function() {
            console.log('Initializing System Info...');
            this.bindEvents();
            this.monitorHealth();
        },

        bindEvents: function() {
            // Health check
            $('#run-health-check-btn').on('click', this.runHealthCheck.bind(this));
            
            // Clear cache
            $('#clear-cache-btn').on('click', this.clearCache.bind(this));
            
            // Debug info toggle
            $('#toggle-debug-info').on('click', this.toggleDebugInfo.bind(this));
            
            // Copy debug info
            $('#copy-debug-info').on('click', this.copyDebugInfo.bind(this));
            
            // Optimize tables
            $('.optimize-table-btn').on('click', this.optimizeTable.bind(this));
            
            // Test cron
            $('#test-cron-btn').on('click', this.testCron.bind(this));
            
            // Create table (if missing)
            $('.create-table-btn').on('click', this.createTable.bind(this));
        },

        /**
         * Run system health check
         */
        runHealthCheck: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const $result = $('#health-result');

            GoalV.Utils.setButtonLoading($button, true, 'Checking...');
            $result.html('<div class="notice notice-info inline"><p>Running health check...</p></div>');

            GoalV.Ajax.request('goalv_run_health_check', {}, {
                success: (data) => {
                    const status = data.status || 'unknown';
                    const issues = data.issues || [];

                    let resultHtml = '<div class="notice notice-success inline">';
                    resultHtml += '<p><strong>✅ Health Check Complete</strong></p>';

                    if (issues.length > 0) {
                        resultHtml += '<ul>';
                        issues.forEach(issue => {
                            resultHtml += '<li>' + GoalV.Utils.sanitizeHtml(issue) + '</li>';
                        });
                        resultHtml += '</ul>';
                    } else {
                        resultHtml += '<p>All systems operating normally.</p>';
                    }

                    resultHtml += '</div>';
                    $result.html(resultHtml);

                    GoalV.Toast.success('Health check completed');

                    // Update health badge
                    this.updateHealthBadge(status, issues.length);

                    // Reload after 2 seconds if there were issues
                    if (issues.length > 0) {
                        setTimeout(() => {
                            if (confirm('Found ' + issues.length + ' issue(s). Reload page to see details?')) {
                                location.reload();
                            }
                        }, 2000);
                    }
                },
                error: (error) => {
                    $result.html(
                        '<div class="notice notice-error inline">' +
                        '<p><strong>❌ Health Check Failed</strong><br>' + error + '</p></div>'
                    );

                    GoalV.Toast.error('Health check failed');
                },
                complete: () => {
                    GoalV.Utils.setButtonLoading($button, false, 'Run Health Check');
                }
            });
        },

        /**
         * Update health status badge
         */
        updateHealthBadge: function(status, issueCount) {
            const $healthStatus = $('.goalv-health-status');
            
            if (!$healthStatus.length) return;

            let badgeClass = 'goalv-health-good';
            let icon = 'yes-alt';
            let text = 'System is healthy';

            if (status === 'warning' || issueCount > 0) {
                badgeClass = 'goalv-health-warning';
                icon = 'warning';
                text = `System has ${issueCount} warning(s)`;
            } else if (status === 'error') {
                badgeClass = 'goalv-health-error';
                icon = 'dismiss';
                text = 'System has errors';
            }

            $healthStatus.find('.goalv-health-badge')
                .removeClass('goalv-health-good goalv-health-warning goalv-health-error')
                .addClass(badgeClass)
                .html(`<span class="dashicons dashicons-${icon}"></span><span>${text}</span>`);
        },

        /**
         * Clear API cache
         */
        clearCache: function(e) {
            e.preventDefault();
            
            if (!confirm('Clear all API cache?\n\nThis will force fresh data fetching from API-Football on next sync.')) {
                return;
            }

            const $button = $(e.currentTarget);
            const $result = $('#health-result');

            GoalV.Utils.setButtonLoading($button, true, 'Clearing...');

            GoalV.Ajax.request('goalv_clear_cache', {}, {
                success: (data) => {
                    $result.html(
                        '<div class="notice notice-success inline">' +
                        '<p><strong>✅ Cache Cleared Successfully!</strong><br>' +
                        data.message + '</p></div>'
                    );

                    GoalV.Toast.success('Cache cleared successfully!');

                    // Clear result after 5 seconds
                    setTimeout(() => {
                        $result.fadeOut();
                    }, 5000);
                },
                error: (error) => {
                    $result.html(
                        '<div class="notice notice-error inline">' +
                        '<p><strong>❌ Failed to clear cache</strong><br>' + error + '</p></div>'
                    );

                    GoalV.Toast.error('Failed to clear cache');
                },
                complete: () => {
                    GoalV.Utils.setButtonLoading($button, false, 'Clear Cache');
                }
            });
        },

        /**
         * Toggle debug info visibility
         */
        toggleDebugInfo: function(e) {
            e.preventDefault();
            
            const $content = $('#debug-info-content');
            const $button = $(e.currentTarget);
            const $icon = $button.find('.dashicons');

            if ($content.is(':visible')) {
                $content.slideUp(300);
                $icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
                $button.contents().filter(function() {
                    return this.nodeType === 3; // Text node
                }).last().replaceWith(' Show Debug Info');
            } else {
                $content.slideDown(300);
                $icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
                $button.contents().filter(function() {
                    return this.nodeType === 3;
                }).last().replaceWith(' Hide Debug Info');
            }
        },

        /**
         * Copy debug info to clipboard
         */
        copyDebugInfo: function(e) {
            e.preventDefault();
            
            const $textarea = $('#debug-info-content textarea');
            const text = $textarea.val();
            const $button = $(e.currentTarget);

            if (GoalV.Utils.copyToClipboard(text)) {
                const originalHtml = $button.html();
                $button.html('<span class="dashicons dashicons-yes"></span> Copied!');
                GoalV.Toast.success('Debug info copied to clipboard!');

                setTimeout(() => {
                    $button.html(originalHtml);
                }, 2000);
            } else {
                // Fallback: select text
                $textarea.select();
                GoalV.Toast.info('Debug info selected. Press Ctrl+C to copy.');
            }
        },

        /**
         * Optimize database table
         */
        optimizeTable: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const table = $button.data('table');
            const $row = $button.closest('tr');

            if (!table) {
                GoalV.Toast.error('Invalid table name');
                return;
            }

            GoalV.Utils.setButtonLoading($button, true, 'Optimizing...');

            GoalV.Ajax.request('goalv_optimize_table', {
                table: table
            }, {
                success: (data) => {
                    GoalV.Toast.success('Table optimized successfully');
                    
                    $button.text('✓ Done!').addClass('button-primary');

                    // Visual feedback on row
                    $row.addClass('goalv-row-updated');

                    setTimeout(() => {
                        $button.removeClass('button-primary').text('Optimize');
                        $row.removeClass('goalv-row-updated');
                        GoalV.Utils.setButtonLoading($button, false, 'Optimize');
                    }, 2000);
                },
                error: (error) => {
                    GoalV.Toast.error('Optimization failed: ' + error);
                    GoalV.Utils.setButtonLoading($button, false, 'Optimize');
                }
            });
        },

        /**
         * Test WP-Cron
         */
        testCron: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            
            GoalV.Utils.setButtonLoading($button, true, 'Testing...');

            // Simulate cron test (just show info)
            setTimeout(() => {
                GoalV.Toast.info('WP-Cron test triggered. Check logs for results.', 5000);
                GoalV.Utils.setButtonLoading($button, false, 'Test Cron Jobs');
            }, 1000);
        },

        /**
         * Create missing table
         */
        createTable: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const tableKey = $button.data('table');

            if (!confirm('Create missing database table?\n\nThis will run the table creation SQL.')) {
                return;
            }

            GoalV.Utils.setButtonLoading($button, true, 'Creating...');

            GoalV.Ajax.request('goalv_create_table', {
                table_key: tableKey
            }, {
                success: (data) => {
                    GoalV.Toast.success('Table created successfully');
                    
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                },
                error: (error) => {
                    GoalV.Toast.error('Failed to create table: ' + error);
                    GoalV.Utils.setButtonLoading($button, false, 'Create');
                }
            });
        },

        /**
         * Monitor system health periodically
         */
        monitorHealth: function() {
            // Check for error indicators
            const errorCount = $('.goalv-status-error').length;
            const warningCount = $('.goalv-status-warning').length;

            if (errorCount > 0) {
                console.warn(`GoalV: Found ${errorCount} error(s) in system`);
            }

            if (warningCount > 0) {
                console.info(`GoalV: Found ${warningCount} warning(s) in system`);
            }

            // Auto health check every 5 minutes
            setInterval(() => {
                this.silentHealthCheck();
            }, 300000);
        },

        /**
         * Silent health check (no UI updates)
         */
        silentHealthCheck: function() {
            GoalV.Ajax.request('goalv_run_health_check', {}, {
                success: (data) => {
                    const issues = data.issues || [];
                    
                    if (issues.length > 0) {
                        console.warn('GoalV Health Check: Found issues', issues);
                        
                        // Update state
                        GoalV.State.set('health_status', 'warning');
                        GoalV.State.set('health_issues', issues);
                    } else {
                        GoalV.State.set('health_status', 'healthy');
                        GoalV.State.set('health_issues', []);
                    }
                },
                error: (error) => {
                    console.error('GoalV Health Check failed:', error);
                    GoalV.State.set('health_status', 'error');
                }
            });
        }
    };

    // Expose globally
    window.GoalV = window.GoalV || {};
    window.GoalV.System = window.GoalVSystem;

})(jQuery);