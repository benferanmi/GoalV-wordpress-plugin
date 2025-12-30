/**
 * GoalV Frontend Core
 * Version: 8.2.0
 * 
 * Frontend coordinator - initializes voting and live scores
 */

(function($) {
    'use strict';

    window.GoalVFrontend = {
        
        initialized: false,

        init: function() {
            if (this.initialized) {
                console.warn('GoalV Frontend already initialized');
                return;
            }

            console.log('GoalV Frontend v8.2.0 initializing...');

            // Check if AJAX config is available
            if (typeof goalv_ajax === 'undefined') {
                console.error('GoalV: goalv_ajax not defined. Check script localization.');
                return;
            }

            // Initialize voting system
            if (window.GoalV && window.GoalV.Voting) {
                console.log('Initializing Voting System...');
                window.GoalV.Voting.init();
            } else {
                console.warn('GoalV: Voting module not loaded');
            }

            // Initialize live scores
            if (window.GoalV && window.GoalV.LiveScores) {
                console.log('Initializing Live Scores...');
                window.GoalV.LiveScores.init();
            } else {
                console.warn('GoalV: Live Scores module not loaded');
            }

            // Global frontend features
            this.initResponsive();
            this.initAnimations();
            this.initAccessibility();

            this.initialized = true;
            console.log('GoalV Frontend initialized successfully');
        },

        /**
         * Initialize responsive features
         */
        initResponsive: function() {
            const checkMobile = () => {
                const isMobile = window.innerWidth <= 768;
                $('body').toggleClass('goalv-mobile', isMobile);
                
                // Store state
                GoalV.State.set('is_mobile', isMobile);
            };

            checkMobile();
            
            // Debounced resize handler
            let resizeTimeout;
            $(window).on('resize', () => {
                clearTimeout(resizeTimeout);
                resizeTimeout = setTimeout(checkMobile, 250);
            });
        },

        /**
         * Initialize animations
         */
        initAnimations: function() {
            // Fade in match cards on load
            $('.goalv-match-card, .goalv-match-grid-item').each(function(index) {
                $(this).css({
                    opacity: 0,
                    transform: 'translateY(20px)'
                });

                setTimeout(() => {
                    $(this).css({
                        opacity: 1,
                        transform: 'translateY(0)',
                        transition: 'all 0.3s ease'
                    });
                }, index * 50);
            });

            // Hover effects for vote buttons
            $(document).on('mouseenter', '.goalv-vote-btn', function() {
                if (!$(this).hasClass('loading') && !$(this).prop('disabled')) {
                    $(this).css('transform', 'translateY(-2px)');
                }
            }).on('mouseleave', '.goalv-vote-btn', function() {
                $(this).css('transform', 'translateY(0)');
            });
        },

        /**
         * Initialize accessibility features
         */
        initAccessibility: function() {
            // Keyboard navigation for vote buttons
            $('.goalv-vote-btn').attr('tabindex', '0');

            $(document).on('keydown', '.goalv-vote-btn', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    $(this).click();
                }
            });

            // Focus management
            $('.goalv-vote-btn').on('focus', function() {
                $(this).addClass('goalv-focus-visible');
            }).on('blur', function() {
                $(this).removeClass('goalv-focus-visible');
            });

            // ARIA live regions for vote updates
            if (!$('#goalv-aria-live').length) {
                $('body').append(
                    '<div id="goalv-aria-live" role="status" aria-live="polite" aria-atomic="true" ' +
                    'style="position: absolute; left: -10000px; width: 1px; height: 1px; overflow: hidden;"></div>'
                );
            }
        },

        /**
         * Announce to screen readers
         */
        announce: function(message) {
            const $announcer = $('#goalv-aria-live');
            if ($announcer.length) {
                $announcer.text(message);
            }
        },

        /**
         * Check if user is logged in
         */
        isUserLoggedIn: function() {
            return typeof goalv_ajax !== 'undefined' && goalv_ajax.is_user_logged_in === true;
        },

        /**
         * Get browser info
         */
        getBrowserInfo: function() {
            const ua = navigator.userAgent;
            let browserName = 'Unknown';
            
            if (ua.indexOf('Firefox') > -1) {
                browserName = 'Firefox';
            } else if (ua.indexOf('Chrome') > -1) {
                browserName = 'Chrome';
            } else if (ua.indexOf('Safari') > -1) {
                browserName = 'Safari';
            } else if (ua.indexOf('Edge') > -1) {
                browserName = 'Edge';
            }

            return {
                name: browserName,
                mobile: /Mobi|Android/i.test(ua),
                userAgent: ua
            };
        },

        /**
         * Handle connection issues
         */
        handleOffline: function() {
            if (!navigator.onLine) {
                GoalV.Toast.warning('You are offline. Some features may not work.', 5000);
            }
        },

        /**
         * Check connection status
         */
        checkConnection: function() {
            $(window).on('online', () => {
                GoalV.Toast.success('Connection restored', 2000);
            });

            $(window).on('offline', () => {
                this.handleOffline();
            });

            // Initial check
            if (!navigator.onLine) {
                this.handleOffline();
            }
        }
    };

    // Auto-initialize on document ready
    $(document).ready(function() {
        // Small delay to ensure all modules are loaded
        setTimeout(() => {
            if (typeof goalv_ajax !== 'undefined') {
                GoalVFrontend.init();
                GoalVFrontend.checkConnection();
            } else {
                console.warn('GoalV: AJAX configuration not loaded');
            }
        }, 100);
    });

    // Expose globally
    window.GoalV = window.GoalV || {};
    window.GoalV.Frontend = window.GoalVFrontend;

})(jQuery);