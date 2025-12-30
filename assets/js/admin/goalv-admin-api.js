/**
 * GoalV Admin API Settings
 * Version: 8.2.0
 * 
 * Handles API-Football configuration and testing
 */

(function($) {
    'use strict';

    window.GoalVApiSettings = {
        
        init: function() {
            console.log('Initializing API Settings...');
            this.bindEvents();
            this.initValidation();
        },

        bindEvents: function() {
            // Toggle API key visibility
            $('#toggle-api-key').on('click', this.toggleKeyVisibility.bind(this));
            
            // Test API connection
            $('#test-api-connection').on('click', this.testConnection.bind(this));
            
            // API key input monitoring
            $('#goalv_api_football_key').on('input', GoalV.Utils.debounce(() => {
                this.validateApiKeyFormat();
                GoalV.Toast.info('Remember to save your API key', 2000);
            }, 1000));

            // Form submission
            $('#goalv-api-settings-form').on('submit', this.handleFormSubmit.bind(this));
        },

        /**
         * Toggle API key visibility
         */
        toggleKeyVisibility: function(e) {
            e.preventDefault();
            
            const $input = $('#goalv_api_football_key');
            const $button = $(e.currentTarget);
            const $icon = $button.find('.dashicons');

            if ($input.attr('type') === 'password') {
                $input.attr('type', 'text');
                $icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
                $button.attr('aria-label', 'Hide API key');
            } else {
                $input.attr('type', 'password');
                $icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
                $button.attr('aria-label', 'Show API key');
            }
        },

        /**
         * Test API connection
         */
        testConnection: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const $status = $('#api-connection-status');
            const $result = $('#api-test-result');
            const apiKey = $('#goalv_api_football_key').val();

            // Check if API key is entered
            if (!apiKey || apiKey.trim().length === 0) {
                GoalV.Toast.warning('Please enter an API key first');
                $('#goalv_api_football_key').focus();
                return;
            }

            GoalV.Utils.setButtonLoading($button, true, 'Testing...');

            // Update status to testing
            $status.html(
                '<span class="goalv-status-badge goalv-status-info">' +
                '<span class="dashicons dashicons-update dashicons-spin"></span> Testing...</span>'
            );

            GoalV.Ajax.request('goalv_test_api_connection', {}, {
                success: (data) => {
                    $status.html(
                        '<span class="goalv-status-badge goalv-status-success">' +
                        '<span class="dashicons dashicons-yes"></span> Connected</span>'
                    );

                    $result.html(
                        '<div class="notice notice-success inline">' +
                        '<p><strong>✅ Connection Successful!</strong><br>' + 
                        GoalV.Utils.sanitizeHtml(data.message) + '</p></div>'
                    );

                    GoalV.Toast.success('API connection successful!');

                    // Store connection status
                    GoalV.State.set('api_connected', true);
                },
                error: (error) => {
                    $status.html(
                        '<span class="goalv-status-badge goalv-status-error">' +
                        '<span class="dashicons dashicons-dismiss"></span> Failed</span>'
                    );

                    $result.html(
                        '<div class="notice notice-error inline">' +
                        '<p><strong>❌ Connection Failed</strong><br>' + 
                        GoalV.Utils.sanitizeHtml(error) + '</p></div>'
                    );

                    GoalV.Toast.error('API connection failed');

                    // Store connection status
                    GoalV.State.set('api_connected', false);
                },
                complete: () => {
                    GoalV.Utils.setButtonLoading($button, false, 'Test Connection');
                }
            });
        },

        /**
         * Validate API key format
         */
        validateApiKeyFormat: function() {
            const $input = $('#goalv_api_football_key');
            const apiKey = $input.val();

            if (!apiKey || apiKey.length === 0) {
                this.clearValidationFeedback();
                return;
            }

            // Basic format validation
            if (apiKey.length < 20) {
                this.showValidationFeedback('API key seems too short (minimum 20 characters)', 'error');
                return false;
            }

            if (!/^[a-zA-Z0-9_-]+$/.test(apiKey)) {
                this.showValidationFeedback('API key contains invalid characters', 'error');
                return false;
            }

            this.showValidationFeedback('API key format looks valid', 'success');
            return true;
        },

        /**
         * Show validation feedback
         */
        showValidationFeedback: function(message, type) {
            let $feedback = $('#api-key-feedback');
            
            if (!$feedback.length) {
                $feedback = $('<div id="api-key-feedback" style="margin-top: 5px;"></div>');
                $('#goalv_api_football_key').after($feedback);
            }

            const iconClass = type === 'success' ? 'dashicons-yes-alt' : 'dashicons-warning';
            const colorClass = type === 'success' ? 'goalv-validation-success' : 'goalv-validation-error';

            $feedback.html(
                `<span class="${colorClass}">
                    <span class="dashicons ${iconClass}"></span> ${message}
                </span>`
            ).show();
        },

        /**
         * Clear validation feedback
         */
        clearValidationFeedback: function() {
            $('#api-key-feedback').hide();
        },

        /**
         * Handle form submission
         */
        handleFormSubmit: function(e) {
            // Validate before submission
            if (!this.validateApiKeyFormat()) {
                e.preventDefault();
                GoalV.Toast.error('Please enter a valid API key');
                return false;
            }

            // Show saving indicator
            const $submitButton = $(e.target).find('[type="submit"]');
            GoalV.Utils.setButtonLoading($submitButton, true, 'Saving...');

            // Form will submit normally, show toast
            GoalV.Toast.info('Saving API settings...', 2000);
        },

        /**
         * Initialize validation
         */
        initValidation: function() {
            // Real-time validation on input
            $('#goalv_api_football_key').on('blur', () => {
                this.validateApiKeyFormat();
            });

            // Clear validation on focus
            $('#goalv_api_football_key').on('focus', () => {
                this.clearValidationFeedback();
            });
        },

        /**
         * Monitor API usage (if stats available)
         */
        monitorUsage: function() {
            const $usageStats = $('.goalv-stats-grid');
            
            if (!$usageStats.length) {
                return;
            }

            // Check for high usage warning
            const usagePercent = parseFloat($('.goalv-stat-value:contains("%")').text());
            
            if (usagePercent > 80) {
                GoalV.Toast.warning('API usage is high. Consider optimizing sync frequency.', 5000);
            }
        }
    };

    // Expose globally
    window.GoalV = window.GoalV || {};
    window.GoalV.ApiSettings = window.GoalVApiSettings;

})(jQuery);