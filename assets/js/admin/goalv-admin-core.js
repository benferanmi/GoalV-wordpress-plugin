/**
 * GoalV Admin Core - Main Coordinator
 * Version: 8.2.0
 * 
 * Initializes admin system and routes to appropriate tab modules
 */

(function($) {
    'use strict';

    window.GoalVAdmin = {
        initialized: false,
        currentTab: null,

        /**
         * Initialize admin system
         */
        init: function() {
            if (this.initialized) {
                console.warn('GoalV Admin already initialized');
                return;
            }

            console.log('GoalV Admin v8.2.0 initializing...');

            // Get current tab
            this.currentTab = this.getCurrentTab();

            // Initialize based on tab
            this.initCurrentTab();

            // Global bindings
            this.bindGlobalEvents();

            this.initialized = true;
            console.log('GoalV Admin initialized for tab:', this.currentTab);
        },

        /**
         * Get current active tab
         */
        getCurrentTab: function() {
            const urlParams = new URLSearchParams(window.location.search);
            return urlParams.get('tab') || 'dashboard';
        },

        /**
         * Initialize current tab module
         */
        initCurrentTab: function() {
            const tabModules = {
                'dashboard': 'Dashboard',
                'api-settings': 'ApiSettings',
                'competitions': 'Competitions',
                'sync': 'Sync',
                'voting': 'Voting',
                'system': 'System'
            };

            const moduleName = tabModules[this.currentTab];

            if (moduleName && window.GoalV && window.GoalV[moduleName]) {
                console.log('Initializing module:', moduleName);
                window.GoalV[moduleName].init();
            } else {
                console.warn('No module found for tab:', this.currentTab);
            }
        },

        /**
         * Bind global events
         */
        bindGlobalEvents: function() {
            // Form change indicators
            $('.goalv-admin-wrap form').on('change', 'input, select, textarea', function() {
                const $form = $(this).closest('form');
                if (!$form.hasClass('goalv-form-changed')) {
                    $form.addClass('goalv-form-changed');
                    GoalV.Toast.info('You have unsaved changes', 2000);
                }
            });

            // Form submission
            $('.goalv-admin-wrap form').on('submit', function() {
                $(this).removeClass('goalv-form-changed');
            });

            // Warn before leaving with unsaved changes
            $(window).on('beforeunload', function() {
                if ($('.goalv-form-changed').length > 0) {
                    return 'You have unsaved changes. Are you sure you want to leave?';
                }
            });

            // Keyboard shortcuts
            $(document).on('keydown', this.handleKeyboardShortcuts.bind(this));
        },

        /**
         * Keyboard shortcuts
         */
        handleKeyboardShortcuts: function(e) {
            // Ctrl/Cmd + S = Save form
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                const $form = $('.goalv-admin-wrap form:visible').first();
                if ($form.length) {
                    $form.submit();
                    GoalV.Toast.info('Saving...', 1000);
                }
            }

            // Ctrl/Cmd + R = Refresh/Reload data
            if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
                const $refreshBtn = $('.goalv-admin-wrap button[id*="refresh"]').first();
                if ($refreshBtn.length) {
                    e.preventDefault();
                    $refreshBtn.click();
                }
            }
        },

        /**
         * Navigate to tab
         */
        navigateToTab: function(tab) {
            const url = new URL(window.location);
            url.searchParams.set('tab', tab);
            window.location.href = url.toString();
        },

        /**
         * Reload current page
         */
        reload: function(delay) {
            delay = delay || 0;
            setTimeout(() => {
                window.location.reload();
            }, delay);
        },

        /**
         * Show loading overlay
         */
        showLoading: function(message) {
            message = message || 'Loading...';
            
            if (!$('#goalv-loading-overlay').length) {
                $('body').append(`
                    <div id="goalv-loading-overlay" class="goalv-loading-overlay">
                        <div class="goalv-loading-content">
                            <div class="goalv-loading-spinner"></div>
                            <p>${message}</p>
                        </div>
                    </div>
                `);
            }

            $('#goalv-loading-overlay').fadeIn();
        },

        /**
         * Hide loading overlay
         */
        hideLoading: function() {
            $('#goalv-loading-overlay').fadeOut();
        }
    };

    // Auto-initialize on document ready
    $(document).ready(function() {
        // Check if we're on a GoalV admin page
        if ($('.goalv-admin-wrap').length) {
            GoalVAdmin.init();
        }
    });

    // Expose globally
    window.GoalV = window.GoalV || {};
    window.GoalV.Admin = window.GoalVAdmin;

})(jQuery);