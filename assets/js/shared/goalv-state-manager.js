/**
 * GoalV State Manager - Global State Management
 * Version: 8.2.0
 * 
 * Reactive state management for cross-module communication
 */

(function($) {
    'use strict';

    window.GoalVState = {
        // State storage
        state: {},

        // Subscribers (callbacks)
        subscribers: {},

        /**
         * Get state value
         */
        get: function(key) {
            return this.state[key];
        },

        /**
         * Set state value and notify subscribers
         */
        set: function(key, value) {
            const oldValue = this.state[key];
            this.state[key] = value;

            // Notify subscribers if value changed
            if (oldValue !== value && this.subscribers[key]) {
                this.subscribers[key].forEach(callback => {
                    callback(value, oldValue);
                });
            }

            return value;
        },

        /**
         * Subscribe to state changes
         */
        subscribe: function(key, callback) {
            if (!this.subscribers[key]) {
                this.subscribers[key] = [];
            }

            this.subscribers[key].push(callback);

            // Return unsubscribe function
            return () => {
                const index = this.subscribers[key].indexOf(callback);
                if (index > -1) {
                    this.subscribers[key].splice(index, 1);
                }
            };
        },

        /**
         * Clear all state
         */
        clear: function() {
            this.state = {};
        },

        /**
         * Clear specific key
         */
        delete: function(key) {
            delete this.state[key];
        },

        /**
         * Get all state keys
         */
        keys: function() {
            return Object.keys(this.state);
        }
    };

    // Expose globally
    window.GoalV = window.GoalV || {};
    window.GoalV.State = window.GoalVState;

})(jQuery);