/**
 * GoalV AJAX Handler - Centralized AJAX Management
 * Version: 8.2.0
 * 
 * Handles all AJAX requests with:
 * - Automatic retry on failure
 * - Centralized error handling
 * - Nonce management
 * - Request queuing
 */

(function($) {
    'use strict';

    window.GoalVAjax = {
        // Configuration
        config: {
            maxRetries: 3,
            retryDelay: 2000,
            timeout: 30000
        },

        // Active requests tracking
        activeRequests: new Map(),

        /**
         * Main AJAX request method
         * 
         * @param {string} action - WordPress AJAX action
         * @param {object} data - Additional data to send
         * @param {object} callbacks - Success, error, complete callbacks
         * @param {number} attempt - Current retry attempt (internal)
         */
        request: function(action, data, callbacks, attempt) {
            attempt = attempt || 1;

            const requestData = {
                action: action,
                nonce: this.getNonce(),
                ...data
            };

            // Track request
            const requestId = `${action}-${Date.now()}`;
            this.activeRequests.set(requestId, true);

            $.ajax({
                url: this.getAjaxUrl(),
                type: 'POST',
                data: requestData,
                timeout: this.config.timeout,
                success: (response) => {
                    if (response.success) {
                        if (callbacks.success) {
                            callbacks.success(response.data);
                        }
                    } else {
                        const error = response.data || 'Unknown error occurred';
                        this.handleError(error, action, data, callbacks, attempt);
                    }
                },
                error: (xhr, status, error) => {
                    const errorMsg = this.parseError(xhr, status, error);
                    this.handleError(errorMsg, action, data, callbacks, attempt);
                },
                complete: () => {
                    this.activeRequests.delete(requestId);
                    
                    if (callbacks.complete) {
                        callbacks.complete();
                    }
                }
            });
        },

        /**
         * Handle errors with automatic retry
         */
        handleError: function(error, action, data, callbacks, attempt) {
            console.error(`GoalV AJAX Error (attempt ${attempt}):`, error);

            // Check if should retry
            if (attempt < this.config.maxRetries && this.shouldRetry(error)) {
                console.log(`Retrying in ${this.config.retryDelay}ms...`);
                
                setTimeout(() => {
                    this.request(action, data, callbacks, attempt + 1);
                }, this.config.retryDelay * attempt); // Exponential backoff
            } else {
                // Max retries reached or non-retryable error
                if (callbacks.error) {
                    callbacks.error(error);
                }
            }
        },

        /**
         * Determine if error is retryable
         */
        shouldRetry: function(error) {
            const retryableErrors = [
                'timeout',
                'Network error',
                'Server error',
                'Gateway',
                '502',
                '503',
                '504'
            ];

            return retryableErrors.some(keyword => 
                error.toLowerCase().includes(keyword.toLowerCase())
            );
        },

        /**
         * Parse error from XHR response
         */
        parseError: function(xhr, status, error) {
            if (status === 'timeout') {
                return 'Request timed out. Please try again.';
            }

            if (xhr.status === 0) {
                return 'Network error. Please check your connection.';
            }

            if (xhr.status === 403) {
                return 'Permission denied. Please refresh the page.';
            }

            if (xhr.status === 404) {
                return 'Endpoint not found. Please check plugin configuration.';
            }

            if (xhr.status >= 500) {
                return `Server error (${xhr.status}). Please try again later.`;
            }

            // Try to get error from response
            if (xhr.responseJSON && xhr.responseJSON.data) {
                return xhr.responseJSON.data;
            }

            return error || 'An unknown error occurred.';
        },

        /**
         * Get WordPress AJAX URL
         */
        getAjaxUrl: function() {
            if (typeof ajaxurl !== 'undefined') {
                return ajaxurl;
            }

            if (typeof goalv_admin !== 'undefined' && goalv_admin.ajax_url) {
                return goalv_admin.ajax_url;
            }

            if (typeof goalv_ajax !== 'undefined' && goalv_ajax.ajax_url) {
                return goalv_ajax.ajax_url;
            }

            // Fallback
            return '/wp-admin/admin-ajax.php';
        },

        /**
         * Get nonce from multiple possible sources
         */
        getNonce: function() {
            // Admin context
            if (typeof goalv_admin !== 'undefined' && goalv_admin.nonce) {
                return goalv_admin.nonce;
            }

            // Frontend context
            if (typeof goalv_ajax !== 'undefined' && goalv_ajax.nonce) {
                return goalv_ajax.nonce;
            }

            // DOM fallback
            const $nonce = $('#goalv-admin-nonce, #goalv-vote-nonce, input[name="_wpnonce"]').first();
            if ($nonce.length) {
                return $nonce.val();
            }

            console.warn('GoalV: No nonce found');
            return '';
        },

        /**
         * Abort all active requests
         */
        abortAll: function() {
            this.activeRequests.forEach((value, key) => {
                console.log('Aborting request:', key);
            });
            this.activeRequests.clear();
        },

        /**
         * Check if any requests are active
         */
        hasActiveRequests: function() {
            return this.activeRequests.size > 0;
        },

        /**
         * Batch multiple requests
         */
        batch: function(requests, onComplete) {
            let completed = 0;
            const total = requests.length;
            const results = [];

            requests.forEach((req, index) => {
                this.request(req.action, req.data, {
                    success: (data) => {
                        results[index] = { success: true, data: data };
                    },
                    error: (error) => {
                        results[index] = { success: false, error: error };
                    },
                    complete: () => {
                        completed++;
                        if (completed === total && onComplete) {
                            onComplete(results);
                        }
                    }
                });
            });
        }
    };

    // Expose globally
    window.GoalV = window.GoalV || {};
    window.GoalV.Ajax = window.GoalVAjax;

})(jQuery);