/**
 * GoalV Admin Utilities - FIXED
 * Version: 8.2.0
 * 
 * CHANGE: Line 191 - Added .request() method wrapper
 */

(function ($) {
    'use strict';

    window.GoalVAdminUtils = {

        /**
         * Set button loading state
         */
        setButtonLoading: function ($button, isLoading, text) {
            if (!$button.data('original-text')) {
                $button.data('original-text', $button.text());
            }

            if (isLoading) {
                $button.prop('disabled', true)
                    .addClass('goalv-btn-loading')
                    .html('<span class="spinner is-active" style="float: left; margin-right: 5px;"></span>' + text);
            } else {
                $button.prop('disabled', false)
                    .removeClass('goalv-btn-loading')
                    .text(text || $button.data('original-text'));
            }
        },

        /**
         * Show/hide spinner
         */
        showSpinner: function ($element) {
            $element.addClass('is-active');
        },

        hideSpinner: function ($element) {
            $element.removeClass('is-active');
        },

        /**
         * Format date
         */
        formatDate: function (date, format) {
            if (!(date instanceof Date)) {
                date = new Date(date);
            }

            const formats = {
                'short': date.toLocaleDateString(),
                'long': date.toLocaleString(),
                'time': date.toLocaleTimeString(),
                'relative': this.getRelativeTime(date)
            };

            return formats[format] || formats['long'];
        },

        /**
         * Get relative time (e.g., "2 hours ago")
         */
        getRelativeTime: function (date) {
            const now = new Date();
            const diff = now - date;
            const seconds = Math.floor(diff / 1000);
            const minutes = Math.floor(seconds / 60);
            const hours = Math.floor(minutes / 60);
            const days = Math.floor(hours / 24);

            if (seconds < 60) return 'Just now';
            if (minutes < 60) return minutes + ' minute' + (minutes > 1 ? 's' : '') + ' ago';
            if (hours < 24) return hours + ' hour' + (hours > 1 ? 's' : '') + ' ago';
            if (days < 30) return days + ' day' + (days > 1 ? 's' : '') + ' ago';

            return date.toLocaleDateString();
        },

        /**
         * Debounce function
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
         * Throttle function
         */
        throttle: function (func, limit) {
            let inThrottle;
            return function (...args) {
                if (!inThrottle) {
                    func.apply(this, args);
                    inThrottle = true;
                    setTimeout(() => inThrottle = false, limit);
                }
            };
        },

        /**
         * Validate input
         */
        validateInput: function (value, type) {
            const validators = {
                'email': /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
                'url': /^https?:\/\/.+/,
                'number': /^\d+$/,
                'api_key': /^[a-zA-Z0-9_-]{20,}$/
            };

            if (validators[type]) {
                return validators[type].test(value);
            }

            return value.trim().length > 0;
        },

        /**
         * Sanitize HTML
         */
        sanitizeHtml: function (html) {
            const div = document.createElement('div');
            div.textContent = html;
            return div.innerHTML;
        },

        /**
         * Parse query string
         */
        getQueryParam: function (param) {
            const urlParams = new URLSearchParams(window.location.search);
            return urlParams.get(param);
        },

        /**
         * Update URL without reload
         */
        updateUrl: function (params) {
            const url = new URL(window.location);
            Object.keys(params).forEach(key => {
                url.searchParams.set(key, params[key]);
            });
            window.history.pushState({}, '', url);
        },

        /**
         * Confirm dialog with custom message
         */
        confirm: function (message, onConfirm, onCancel) {
            if (window.confirm(message)) {
                if (onConfirm) onConfirm();
            } else {
                if (onCancel) onCancel();
            }
        },

        /**
         * Check if element is in viewport
         */
        isInViewport: function ($element) {
            const rect = $element[0].getBoundingClientRect();
            return (
                rect.top >= 0 &&
                rect.left >= 0 &&
                rect.bottom <= $(window).height() &&
                rect.right <= $(window).width()
            );
        },

        /**
         * Scroll to element smoothly
         */
        scrollTo: function ($element, offset) {
            offset = offset || 0;
            $('html, body').animate({
                scrollTop: $element.offset().top - offset
            }, 500);
        },

        /**
         * Copy to clipboard
         */
        copyToClipboard: function (text) {
            const $temp = $('<textarea>');
            $('body').append($temp);
            $temp.val(text).select();
            document.execCommand('copy');
            $temp.remove();

            return true;
        },

        /**
         * Format number with commas
         */
        formatNumber: function (num) {
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        },

        /**
         * Parse error message
         */
        parseError: function (error) {
            if (typeof error === 'string') {
                return error;
            }

            if (error.message) {
                return error.message;
            }

            if (error.responseJSON && error.responseJSON.data) {
                return error.responseJSON.data;
            }

            return 'An unknown error occurred';
        },

        /**
         * Local storage helpers
         */
        storage: {
            set: function (key, value) {
                try {
                    localStorage.setItem('goalv_' + key, JSON.stringify(value));
                    return true;
                } catch (e) {
                    console.error('Storage error:', e);
                    return false;
                }
            },

            get: function (key) {
                try {
                    const value = localStorage.getItem('goalv_' + key);
                    return value ? JSON.parse(value) : null;
                } catch (e) {
                    console.error('Storage error:', e);
                    return null;
                }
            },

            remove: function (key) {
                try {
                    localStorage.removeItem('goalv_' + key);
                    return true;
                } catch (e) {
                    return false;
                }
            },

            clear: function () {
                try {
                    Object.keys(localStorage).forEach(key => {
                        if (key.startsWith('goalv_')) {
                            localStorage.removeItem(key);
                        }
                    });
                    return true;
                } catch (e) {
                    return false;
                }
            }
        },

        /**
         * AJAX Helper - Standardized AJAX request wrapper with proper nonce handling
         */
        ajax: function (action, data, callbacks) {
            data = data || {};
            callbacks = callbacks || {};

            // Get nonce from global config
            const nonce = window.goalvAjaxConfig ? window.goalvAjaxConfig.nonce : '';
            const ajax_url = window.goalvAjaxConfig ? window.goalvAjaxConfig.ajax_url : ajaxurl;

            // Verify we have required values
            if (!nonce) {
                console.error('ERROR: Nonce not found in window.goalvAjaxConfig');
                if (callbacks.error) {
                    callbacks.error('Security check failed: nonce missing');
                }
                return;
            }

            // Add nonce and action to data
            data.nonce = nonce;
            data.action = action;

            console.log('GoalV AJAX Request:', {
                action: action,
                url: ajax_url,
                nonce_present: !!nonce,
                data_keys: Object.keys(data)
            });

            $.ajax({
                url: ajax_url,
                type: 'POST',
                data: data,
                dataType: 'json',
                success: function (response) {
                    console.log('GoalV AJAX Success Response:', response);

                    if (response.success && callbacks.success) {
                        callbacks.success(response.data);
                    } else if (!response.success && callbacks.error) {
                        const errorMsg = response.data || 'Unknown error occurred';
                        callbacks.error(errorMsg);
                    }
                },
                error: function (xhr, status, error) {
                    console.error('GoalV AJAX Error:', {
                        status: xhr.status,
                        statusText: xhr.statusText,
                        responseText: xhr.responseText,
                        error: error
                    });

                    // Check for nonce failure (403)
                    if (xhr.status === 403) {
                        if (callbacks.error) {
                            callbacks.error('Security check failed (403). Please refresh the page and try again.');
                        }
                    } else {
                        if (callbacks.error) {
                            callbacks.error(error || 'Request failed');
                        }
                    }
                },
                complete: function () {
                    console.log('GoalV AJAX Complete for action:', action);
                    if (callbacks.complete) {
                        callbacks.complete();
                    }
                }
            });
        }
    };

    // Expose globally
    window.GoalV = window.GoalV || {};
    window.GoalV.Utils = window.GoalVAdminUtils;

    // FIXED: Create Ajax object with request method
    window.GoalV.Ajax = {
        request: window.GoalVAdminUtils.ajax
    };

})(jQuery);