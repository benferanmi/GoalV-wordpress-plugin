/**
 * GoalV Frontend Live Scores
 * Version: 8.2.0
 * 
 * Real-time score polling for live matches
 */

(function($) {
    'use strict';

    window.GoalVLiveScores = {
        
        config: {
            pollInterval: 30000, // 30 seconds
            enabled: true,
            maxRetries: 3
        },

        intervals: {},
        liveMatches: new Set(),
        retryCount: 0,

        init: function() {
            console.log('Initializing Live Scores...');
            
            // Detect live matches on page
            this.detectLiveMatches();

            // Start polling if there are live matches
            if (this.liveMatches.size > 0) {
                console.log(`Found ${this.liveMatches.size} live match(es)`);
                this.startPolling();
            } else {
                console.log('No live matches detected');
            }

            // Monitor for new live matches
            this.startMonitoring();
        },

        /**
         * Detect live matches on page
         */
        detectLiveMatches: function() {
            $('.goalv-match-card, .goalv-match-grid-item, .goalv-detailed-voting').each((index, element) => {
                const $match = $(element);
                const matchId = $match.data('match-id');
                
                if (!matchId) return;

                // Check for live status indicators
                const $status = $match.find('.goalv-match-status, .goalv-status-badge');
                const statusText = $status.text().toLowerCase();

                const isLive = statusText.includes('live') || 
                              statusText.includes("'") || // Match minute indicator
                              statusText.includes('ht') || // Half-time
                              statusText.includes('paused');

                if (isLive) {
                    this.liveMatches.add(matchId);
                    
                    // Mark as live
                    $match.addClass('goalv-match-live');
                    
                    // Lock voting
                    this.lockVoting($match);
                }
            });
        },

        /**
         * Start polling for live scores
         */
        startPolling: function() {
            if (!this.config.enabled) {
                console.log('Live score polling is disabled');
                return;
            }

            console.log('Starting live score polling...');

            // Initial update
            this.updateLiveScores();

            // Set interval
            this.intervals.liveScores = setInterval(() => {
                this.updateLiveScores();
            }, this.config.pollInterval);

            // Visual indicator
            this.showPollingIndicator();
        },

        /**
         * Stop polling
         */
        stopPolling: function() {
            if (this.intervals.liveScores) {
                clearInterval(this.intervals.liveScores);
                delete this.intervals.liveScores;
                console.log('Live score polling stopped');
                
                this.hidePollingIndicator();
            }
        },

        /**
         * Update live scores from API
         */
        updateLiveScores: function() {
            console.log('Fetching live scores...');

            GoalV.Ajax.request('goalv_get_live_matches', {}, {
                success: (data) => {
                    if (data.matches && data.matches.length > 0) {
                        console.log(`Received ${data.matches.length} live match(es)`);
                        
                        data.matches.forEach(match => {
                            this.updateMatchDisplay(match);
                        });

                        // Reset retry count on success
                        this.retryCount = 0;
                    } else {
                        console.log('No live matches returned');
                        
                        // No more live matches, stop polling
                        if (this.liveMatches.size === 0) {
                            this.stopPolling();
                        }
                    }
                },
                error: (error) => {
                    console.error('Failed to fetch live scores:', error);
                    
                    this.retryCount++;
                    
                    if (this.retryCount >= this.config.maxRetries) {
                        console.warn('Max retries reached, stopping live score polling');
                        this.stopPolling();
                        GoalV.Toast.warning('Live score updates temporarily unavailable');
                    }
                }
            });
        },

        /**
         * Update individual match display
         */
        updateMatchDisplay: function(match) {
            const $match = $(`.goalv-match-card[data-match-id="${match.id}"], ` +
                           `.goalv-match-grid-item[data-match-id="${match.id}"], ` +
                           `.goalv-detailed-voting[data-match-id="${match.id}"]`);

            if (!$match.length) return;

            // Update score
            if (match.status === 'live' || match.status === 'paused') {
                const scoreHtml = `<div class="goalv-score-display">${match.home_score} - ${match.away_score}</div>`;
                
                const $scoreContainer = $match.find('.goalv-score-display, .goalv-match-center');
                if ($scoreContainer.length) {
                    $scoreContainer.html(scoreHtml);
                } else {
                    $match.find('.goalv-match-center').html(scoreHtml);
                }
            }

            // Update status with minute
            const $status = $match.find('.goalv-match-status, .goalv-status-badge');
            if ($status.length) {
                const statusDisplay = this.getStatusDisplay(match.status, match.minute, match.half);
                $status.html(statusDisplay);
            }

            // Add pulsing animation to LIVE badge
            if (match.status === 'live') {
                $status.addClass('goalv-status-live-pulse');
            }

            // Lock voting
            this.lockVoting($match);

            // Update timestamp
            $match.attr('data-last-updated', new Date().toISOString());

            // Visual feedback - flash animation
            $match.addClass('goalv-match-updated');
            setTimeout(() => {
                $match.removeClass('goalv-match-updated');
            }, 500);

            // If match finished, remove from live set
            if (match.status === 'finished') {
                this.liveMatches.delete(match.id);
                $match.removeClass('goalv-match-live');
                
                // Reload if all matches finished
                if (this.liveMatches.size === 0) {
                    setTimeout(() => {
                        if (confirm('All matches finished! Reload to see final results?')) {
                            location.reload();
                        }
                    }, 2000);
                }
            }
        },

        /**
         * Get status display HTML
         */
        getStatusDisplay: function(status, minute, half) {
            const statusMap = {
                'live': `<span class="goalv-status-badge goalv-status-live">🔴 LIVE ${minute ? minute+"'" : ''}</span>`,
                'paused': `<span class="goalv-status-badge goalv-status-paused">HT</span>`,
                'finished': `<span class="goalv-status-badge goalv-status-finished">FT</span>`
            };

            return statusMap[status] || `<span class="goalv-status-badge">${status.toUpperCase()}</span>`;
        },

        /**
         * Lock voting for live match
         */
        lockVoting: function($match) {
            // Disable all vote buttons
            $match.find('.goalv-vote-btn, .goalv-grid-vote-btn, .goalv-vote-btn-inline').each(function() {
                $(this).prop('disabled', true)
                       .addClass('goalv-vote-locked')
                       .css({
                           'cursor': 'not-allowed',
                           'opacity': '0.5'
                       });
            });

            // Show locked message if not already shown
            if (!$match.find('.goalv-voting-locked-msg').length) {
                const $votingSection = $match.find('.goalv-voting-section, .goalv-grid-voting, .goalv-detailed-voting');
                
                if ($votingSection.length) {
                    $votingSection.prepend(
                        '<div class="goalv-voting-locked-msg">' +
                        '<span class="dashicons dashicons-lock"></span> ' +
                        'Voting closed - Match in progress' +
                        '</div>'
                    );
                }
            }
        },

        /**
         * Show polling indicator
         */
        showPollingIndicator: function() {
            if ($('#goalv-live-indicator').length) return;

            const $indicator = $('<div>', {
                id: 'goalv-live-indicator',
                class: 'goalv-live-indicator',
                html: '<span class="goalv-live-dot"></span> Live updates active'
            });

            $('body').append($indicator);

            // Position in bottom right
            $indicator.css({
                position: 'fixed',
                bottom: '20px',
                right: '20px',
                background: '#dc3545',
                color: '#fff',
                padding: '8px 12px',
                borderRadius: '4px',
                fontSize: '12px',
                fontWeight: '600',
                zIndex: '9999',
                boxShadow: '0 2px 8px rgba(0,0,0,0.2)'
            });

            // Pulsing dot
            $indicator.find('.goalv-live-dot').css({
                display: 'inline-block',
                width: '8px',
                height: '8px',
                background: '#fff',
                borderRadius: '50%',
                marginRight: '6px',
                animation: 'goalv-pulse 2s infinite'
            });
        },

        /**
         * Hide polling indicator
         */
        hidePollingIndicator: function() {
            $('#goalv-live-indicator').fadeOut(300, function() {
                $(this).remove();
            });
        },

        /**
         * Start monitoring for status changes
         */
        startMonitoring: function() {
            // Check every 60 seconds for new live matches
            this.intervals.monitoring = setInterval(() => {
                const previousSize = this.liveMatches.size;
                this.detectLiveMatches();
                
                // If new live matches detected, start polling
                if (this.liveMatches.size > previousSize && !this.intervals.liveScores) {
                    this.startPolling();
                }
            }, 60000);
        },

        /**
         * Stop monitoring
         */
        stopMonitoring: function() {
            if (this.intervals.monitoring) {
                clearInterval(this.intervals.monitoring);
                delete this.intervals.monitoring;
            }
        },

        /**
         * Cleanup
         */
        cleanup: function() {
            this.stopPolling();
            this.stopMonitoring();
            
            Object.keys(this.intervals).forEach(key => {
                clearInterval(this.intervals[key]);
            });
            
            this.intervals = {};
            console.log('Live scores cleanup complete');
        }
    };

    // Cleanup on page unload
    $(window).on('beforeunload', () => {
        if (window.GoalV && window.GoalV.LiveScores) {
            window.GoalV.LiveScores.cleanup();
        }
    });

    // Pause polling when page is hidden
    $(document).on('visibilitychange', () => {
        if (window.GoalV && window.GoalV.LiveScores) {
            if (document.hidden) {
                console.log('Page hidden - pausing live score polling');
                window.GoalV.LiveScores.stopPolling();
            } else {
                console.log('Page visible - resuming live score polling');
                if (window.GoalV.LiveScores.liveMatches.size > 0) {
                    window.GoalV.LiveScores.startPolling();
                }
            }
        }
    });

    // Expose globally
    window.GoalV = window.GoalV || {};
    window.GoalV.LiveScores = window.GoalVLiveScores;

})(jQuery);