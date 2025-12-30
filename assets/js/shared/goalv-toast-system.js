/**
 * GoalV Toast Notification System
 * Version: 8.2.0
 * 
 * Modern, accessible toast notifications for admin and frontend
 * 
 * Features:
 * - Auto-dismiss with configurable duration
 * - Queue management
 * - Multiple types (success, error, warning, info)
 * - Accessible (ARIA labels)
 * - Smooth animations
 */

(function($) {
    'use strict';

    window.GoalVToast = {
        // Configuration
        config: {
            duration: 3000,
            maxToasts: 3,
            position: 'top-right' // top-right, top-left, bottom-right, bottom-left
        },

        // Toast queue
        queue: [],
        activeToasts: new Map(),

        /**
         * Initialize toast system
         */
        init: function() {
            if (this.initialized) return;

            this.createContainer();
            this.bindEvents();
            this.initialized = true;
        },

        /**
         * Create toast container
         */
        createContainer: function() {
            if ($('#goalv-toast-container').length) return;

            const container = $('<div>', {
                id: 'goalv-toast-container',
                class: 'goalv-toast-container goalv-toast-' + this.config.position,
                role: 'region',
                'aria-label': 'Notifications',
                'aria-live': 'polite'
            });

            $('body').append(container);
        },

        /**
         * Bind global events
         */
        bindEvents: function() {
            $(document).on('click', '.goalv-toast-close', (e) => {
                const toastId = $(e.currentTarget).closest('.goalv-toast').attr('id');
                this.close(toastId);
            });

            // Close on escape key
            $(document).on('keydown', (e) => {
                if (e.key === 'Escape' && this.activeToasts.size > 0) {
                    this.closeAll();
                }
            });
        },

        /**
         * Show toast notification
         * 
         * @param {string} message - Message to display
         * @param {string} type - Type: success, error, warning, info
         * @param {number} duration - Duration in ms (0 = no auto-close)
         */
        show: function(message, type, duration) {
            type = type || 'info';
            duration = duration !== undefined ? duration : this.config.duration;

            const toast = {
                id: 'toast-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9),
                message: message,
                type: type,
                duration: duration,
                timestamp: Date.now()
            };

            // Add to queue
            this.queue.push(toast);

            // Process queue
            this.processQueue();

            return toast.id;
        },

        /**
         * Process toast queue
         */
        processQueue: function() {
            // Limit active toasts
            while (this.activeToasts.size < this.config.maxToasts && this.queue.length > 0) {
                const toast = this.queue.shift();
                this.render(toast);
            }
        },

        /**
         * Render toast
         */
        render: function(toast) {
            const iconMap = {
                success: 'yes-alt',
                error: 'dismiss',
                warning: 'warning',
                info: 'info'
            };

            const labelMap = {
                success: 'Success',
                error: 'Error',
                warning: 'Warning',
                info: 'Information'
            };

            const $toast = $(`
                <div class="goalv-toast goalv-toast-${toast.type}" 
                     id="${toast.id}" 
                     role="alert"
                     aria-labelledby="${toast.id}-message">
                    <div class="goalv-toast-icon">
                        <span class="dashicons dashicons-${iconMap[toast.type]}" 
                              aria-hidden="true"></span>
                    </div>
                    <div class="goalv-toast-content">
                        <span class="goalv-toast-label" aria-label="${labelMap[toast.type]}">
                            ${labelMap[toast.type]}
                        </span>
                        <div class="goalv-toast-message" id="${toast.id}-message">
                            ${this.escapeHtml(toast.message)}
                        </div>
                    </div>
                    <button class="goalv-toast-close" 
                            aria-label="Close notification"
                            type="button">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                    ${toast.duration > 0 ? `
                    <div class="goalv-toast-progress">
                        <div class="goalv-toast-progress-bar" 
                             style="animation-duration: ${toast.duration}ms"></div>
                    </div>
                    ` : ''}
                </div>
            `);

            // Add to container with animation
            $('#goalv-toast-container').append($toast);
            
            // Trigger animation
            setTimeout(() => {
                $toast.addClass('goalv-toast-show');
            }, 10);

            // Track active toast
            this.activeToasts.set(toast.id, toast);

            // Auto-close if duration is set
            if (toast.duration > 0) {
                setTimeout(() => {
                    this.close(toast.id);
                }, toast.duration);
            }

            // Announce to screen readers
            this.announce(toast.message, toast.type);
        },

        /**
         * Close toast
         */
        close: function(toastId) {
            const $toast = $('#' + toastId);
            
            if ($toast.length) {
                $toast.removeClass('goalv-toast-show').addClass('goalv-toast-hide');
                
                setTimeout(() => {
                    $toast.remove();
                    this.activeToasts.delete(toastId);
                    
                    // Process queue if there are pending toasts
                    if (this.queue.length > 0) {
                        this.processQueue();
                    }
                }, 300); // Match CSS animation duration
            }
        },

        /**
         * Close all toasts
         */
        closeAll: function() {
            this.activeToasts.forEach((toast, id) => {
                this.close(id);
            });
            this.queue = [];
        },

        /**
         * Shorthand methods
         */
        success: function(message, duration) {
            return this.show(message, 'success', duration);
        },

        error: function(message, duration) {
            return this.show(message, 'error', duration);
        },

        warning: function(message, duration) {
            return this.show(message, 'warning', duration);
        },

        info: function(message, duration) {
            return this.show(message, 'info', duration);
        },

        /**
         * Announce to screen readers
         */
        announce: function(message, type) {
            const priority = type === 'error' ? 'assertive' : 'polite';
            
            let $announcer = $('#goalv-toast-announcer');
            if (!$announcer.length) {
                $announcer = $('<div>', {
                    id: 'goalv-toast-announcer',
                    class: 'sr-only',
                    role: 'status',
                    'aria-live': priority,
                    'aria-atomic': 'true'
                });
                $('body').append($announcer);
            }

            $announcer.attr('aria-live', priority).text(message);
        },

        /**
         * Escape HTML to prevent XSS
         */
        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        /**
         * Get toast by ID
         */
        get: function(toastId) {
            return this.activeToasts.get(toastId);
        },

        /**
         * Update existing toast
         */
        update: function(toastId, message, type) {
            const $toast = $('#' + toastId);
            
            if ($toast.length) {
                $toast.find('.goalv-toast-message').text(this.escapeHtml(message));
                
                if (type) {
                    $toast.removeClass('goalv-toast-success goalv-toast-error goalv-toast-warning goalv-toast-info');
                    $toast.addClass('goalv-toast-' + type);
                }
            }
        }
    };

    // Auto-initialize
    $(document).ready(() => {
        GoalVToast.init();
    });

    // Expose globally
    window.GoalV = window.GoalV || {};
    window.GoalV.Toast = window.GoalVToast;

})(jQuery);