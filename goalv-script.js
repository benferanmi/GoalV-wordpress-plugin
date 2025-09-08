/**
 * GoalV Football Predictions - Frontend JavaScript
 * Handles voting functionality for both homepage and single match pages
 */

(function ($) {
    'use strict';

    // Plugin state
    const GoalV = {
        init: function () {
            // Check if goalv_ajax is available
            if (typeof goalv_ajax === 'undefined') {
                console.warn('GoalV: goalv_ajax not found. Plugin may not be properly enqueued.');
                return;
            }

            this.bindEvents();
            this.initBrowserId();
            this.updateVoteResults();
            this.initInfiniteScroll();
        },

        // Store voting states
        votingInProgress: new Set(),
        userVotes: new Map(),

        /**
         * Check if GoalV is properly initialized
         */
        isInitialized: function () {
            return typeof goalv_ajax !== 'undefined';
        },

        /**
         * Initialize browser ID for guest users
         */
        initBrowserId: function () {
            if (!this.isInitialized()) {
                return;
            }

            if (!goalv_ajax.is_user_logged_in) {
                // Browser ID is already generated in footer script
                // Just ensure it's available
                if (typeof window.goalvBrowserId === 'undefined') {
                    window.goalvBrowserId = this.generateBrowserId();
                }
            }
        },

        infiniteScroll: {
            isLoading: false,
            hasMoreMatches: true,
            currentPage: 1,
            perPage: 10,
            container: null
        },

        // Add this to your init function:
        initInfiniteScroll: function () {
            const $container = $('.goalv-table-body');
            if ($container.length) {
                this.infiniteScroll.container = $container;
                this.bindScrollEvents();
                this.addLoadingIndicator();
            }
        },

        // New methods for infinite scroll:
        bindScrollEvents: function () {
            let scrollTimeout;
            $(window).on('scroll', () => {
                clearTimeout(scrollTimeout);
                scrollTimeout = setTimeout(() => {
                    this.checkScrollPosition();
                }, 100);
            });
        },

        checkScrollPosition: function () {
            if (this.infiniteScroll.isLoading || !this.infiniteScroll.hasMoreMatches) {
                return;
            }

            const scrollTop = $(window).scrollTop();
            const windowHeight = $(window).height();
            const documentHeight = $(document).height();

            // Load more when user is 200px from bottom
            if (scrollTop + windowHeight >= documentHeight - 200) {
                this.loadMoreMatches();
            }
        },

        loadMoreMatches: function () {
            if (this.infiniteScroll.isLoading || !this.infiniteScroll.hasMoreMatches) {
                return;
            }

            this.infiniteScroll.isLoading = true;
            this.showLoadingIndicator();

            const nextPage = this.infiniteScroll.currentPage + 1;

            $.post(goalv_ajax.ajax_url, {
                action: 'goalv_load_more_matches',
                page: nextPage,
                per_page: this.infiniteScroll.perPage,
                nonce: goalv_ajax.nonce
            })
                .done((response) => {
                    if (response.success && response.data.matches.length > 0) {
                        this.appendNewMatches(response.data.matches);
                        this.infiniteScroll.currentPage = nextPage;

                        if (response.data.matches.length < this.infiniteScroll.perPage) {
                            this.infiniteScroll.hasMoreMatches = false;
                            this.showEndMessage();
                        }
                    } else {
                        this.infiniteScroll.hasMoreMatches = false;
                        this.showEndMessage();
                    }
                })
                .fail(() => {
                    console.error('Failed to load more matches');
                })
                .always(() => {
                    this.infiniteScroll.isLoading = false;
                    this.hideLoadingIndicator();
                });
        },

        appendNewMatches: function (matchesHtml) {
            const $newMatches = $(matchesHtml);
            $newMatches.addClass('goalv-new-loaded');
            this.infiniteScroll.container.append($newMatches);

            // Trigger animations
            setTimeout(() => {
                $newMatches.removeClass('goalv-new-loaded');
            }, 50);
        },

        addLoadingIndicator: function () {
            const loadingHtml = `
        <div class="goalv-loading-container" style="display: none;">
            <div class="goalv-loading-spinner"></div>
            <p>Loading more matches...</p>
        </div>
    `;
            this.infiniteScroll.container.after(loadingHtml);
        },

        showLoadingIndicator: function () {
            $('.goalv-loading-container').show();
        },

        hideLoadingIndicator: function () {
            $('.goalv-loading-container').hide();
        },

        showEndMessage: function () {
            if (!$('.goalv-end-message').length) {
                const endHtml = `
            <div class="goalv-end-message">
                <p>No more matches to load</p>
            </div>
        `;
                this.infiniteScroll.container.after(endHtml);
            }
        },



        /**
         * Generate browser ID fallback
         */
        generateBrowserId: function () {
            let browserId = localStorage.getItem('goalv_browser_id');
            if (!browserId) {
                browserId = 'guest_' + Math.random().toString(36).substr(2, 9) + '_' + Date.now();
                localStorage.setItem('goalv_browser_id', browserId);
            }
            return browserId;
        },

        /**
         * Bind events
         */
        bindEvents: function () {
            // Voting buttons - both templates
            $(document).on('click', '.goalv-vote-btn, .goalv-grid-vote-btn', this.handleVote.bind(this));

            // Admin sync button
            $(document).on('click', '#sync-matches-btn', this.handleSync.bind(this));

            // Auto-refresh vote results every 30 seconds
            setInterval(this.updateVoteResults.bind(this), 30000);
        },

        /**
         * Handle vote submission
         */
        handleVote: function (e) {
            e.preventDefault();

            if (!this.isInitialized()) {
                console.error('GoalV: Plugin not properly initialized');
                return;
            }

            const $btn = $(e.currentTarget);
            const matchId = $btn.data('match-id');
            const optionId = $btn.data('option-id');
            const location = $btn.data('location');

            // Prevent double voting
            const votingKey = `${matchId}-${location}`;
            if (this.votingInProgress.has(votingKey)) {
                return;
            }

            // Check if user is trying to vote on details page without login
            if (location === 'details' && !goalv_ajax.is_user_logged_in) {
                this.showMessage($btn.closest('.goalv-voting-section'), 'Login required for detailed voting', 'error');
                return;
            }

            // Get current selection state
            const isCurrentlySelected = $btn.hasClass('selected') || $btn.data('selected') === true;

            // If already selected and trying to vote again, check if changes are allowed
            if (isCurrentlySelected) {
                const canChange = this.canChangeVote(location);
                if (!canChange) {
                    this.showMessage($btn.closest('.goalv-voting-section, .goalv-grid-voting'), 'Vote changes are not allowed', 'error');
                    return;
                }
            }

            // Start voting process
            this.votingInProgress.add(votingKey);
            this.setVotingState($btn, true);

            // Prepare vote data
            const voteData = {
                action: 'goalv_cast_vote',
                match_id: matchId,
                option_id: optionId,
                vote_location: location,
                nonce: goalv_ajax.nonce
            };

            // Add browser ID for guest users
            if (!goalv_ajax.is_user_logged_in && typeof window.goalvBrowserId !== 'undefined') {
                voteData.browser_id = window.goalvBrowserId;
            }

            // Submit vote
            $.post(goalv_ajax.ajax_url, voteData)
                .done((response) => {
                    this.handleVoteResponse(response, $btn, matchId, location, optionId);
                })
                .fail(() => {
                    this.handleVoteError($btn, matchId, location);
                })
                .always(() => {
                    this.votingInProgress.delete(votingKey);
                    this.setVotingState($btn, false);
                });
        },

        /**
         * Handle vote response
         */
        handleVoteResponse: function (response, $btn, matchId, location, optionId) {
            const $container = $btn.closest('.goalv-voting-section, .goalv-grid-voting');

            if (response.success) {
                // Update UI with new results
                this.updateVoteUI(matchId, location, response.data.results, optionId);

                // Store user vote
                this.userVotes.set(`${matchId}-${location}`, optionId);

                // Show success message (brief)
                this.showMessage($container, response.data.message, 'success', 2000);
            } else {
                this.showMessage($container, response.data || 'Vote failed', 'error');
            }
        },

        /**
         * Handle vote error
         */
        handleVoteError: function ($btn, matchId, location) {
            const $container = $btn.closest('.goalv-voting-section, .goalv-grid-voting');
            this.showMessage($container, 'Network error. Please try again.', 'error');
        },

        /**
         * Update vote UI with new results
         */
        updateVoteUI: function (matchId, location, results, selectedOptionId) {
            const $matchContainer = $(`.goalv-match-card[data-match-id="${matchId}"], .goalv-match-grid-item[data-match-id="${matchId}"], .goalv-detailed-voting[data-match-id="${matchId}"]`);

            if (location === 'homepage') {
                // Update card and grid templates
                this.updateHomepageVoting($matchContainer, results, selectedOptionId);
            } else {
                // Update single page detailed voting
                this.updateDetailedVoting($matchContainer, results, selectedOptionId);
            }
        },

        /**
         * Update homepage voting UI (card and grid templates) - FIXED for category-based voting
         */
        updateHomepageVoting: function ($container, results, selectedOptionIds) {
            // Ensure selectedOptionIds is an array and convert to integers
            if (!Array.isArray(selectedOptionIds)) {
                selectedOptionIds = selectedOptionIds ? [parseInt(selectedOptionIds)] : [];
            }
            selectedOptionIds = selectedOptionIds.map(id => parseInt(id));

            console.log('UpdateHomepageVoting - Selected IDs:', selectedOptionIds); // Debug

            // Update buttons and percentages
            $container.find('.goalv-vote-btn, .goalv-grid-vote-btn, .goalv-vote-btn-inline').each(function () {
                const $btn = $(this);
                const optionId = parseInt($btn.data('option-id'));
                const result = results.find(r => parseInt(r.option_id) === optionId);

                if (result) {
                    // Update percentage
                    $btn.find('.goalv-percentage, .goalv-grid-percentage, .goalv-percentage-inline').text(result.percentage + '%');

                    // Update vote count - check different possible selectors
                    const $voteCount = $btn.find('.goalv-vote-count, .goalv-grid-vote-count, .goalv-inline-vote-count');
                    if ($voteCount.length) {
                        $voteCount.text(`(${result.votes_count})`);
                    }

                    // Update selection state - FIXED for category-based voting
                    if (selectedOptionIds.includes(optionId)) {
                        $btn.addClass('selected').attr('data-selected', 'true');
                    } else {
                        $btn.removeClass('selected').removeAttr('data-selected');
                    }
                }
            });

            // Store votes for guest users
            if (!goalv_ajax.is_user_logged_in) {
                const matchId = $container.data('match-id');
                const location = 'homepage';
                this.storeVotes(matchId, location, selectedOptionIds);
            }
        },

        /**
         * Update detailed voting UI (single page)
         */
        updateDetailedVoting: function ($container, results, selectedOptionId) {
            // Update vote buttons
            $container.find('.goalv-vote-btn').each(function () {
                const $btn = $(this);
                const $option = $btn.closest('.goalv-vote-option');
                const optionId = $btn.data('option-id');
                const result = results.find(r => r.option_id == optionId);

                if (result) {
                    // Update stats
                    $btn.find('.goalv-percentage').text(result.percentage + '%');
                    $btn.find('.goalv-votes-count').text(`(${result.votes_count} votes)`);

                    // Update selection state
                    if (optionId == selectedOptionId) {
                        $btn.addClass('selected').attr('data-selected', 'true');
                        $option.addClass('selected');

                        // Remove selection from other options
                        $btn.closest('.goalv-voting-options').find('.goalv-vote-option').not($option).removeClass('selected');
                        $btn.closest('.goalv-voting-options').find('.goalv-vote-btn').not($btn).removeClass('selected').removeAttr('data-selected');
                    }
                }
            });

            // Update results summary if present
            this.updateResultsSummary($container.closest('.goalv-single-match-wrapper'), results);
        },

        /**
         * Update results summary section
         */
        updateResultsSummary: function ($wrapper, results) {
            const $resultsGrid = $wrapper.find('.goalv-results-grid');
            if (!$resultsGrid.length) return;

            let totalVotes = results.reduce((sum, result) => sum + result.votes_count, 0);

            results.forEach(result => {
                const $resultItem = $resultsGrid.find(`.goalv-result-item:contains("${result.option_text}")`);
                if ($resultItem.length) {
                    $resultItem.find('.goalv-result-percentage').text(result.percentage + '%');
                    $resultItem.find('.goalv-result-count').text(`(${result.votes_count})`);
                    $resultItem.find('.goalv-result-fill').css('width', result.percentage + '%');
                }
            });

            // Update total votes
            $wrapper.find('.goalv-total-votes').text(`Total Votes: ${totalVotes}`);
        },

        /**
         * Check if vote changes are allowed
         */
        canChangeVote: function (location) {
            // This is a client-side check, but the server will validate too
            // For now, we'll let the server handle all validation
            return true;
        },

        /**
         * Set voting state (loading/normal)
         */
        setVotingState: function ($btn, isLoading) {
            if (isLoading) {
                $btn.addClass('loading').prop('disabled', true);
            } else {
                $btn.removeClass('loading').prop('disabled', false);
            }
        },

        /**
         * Show message to user
         */
        showMessage: function ($container, message, type, duration = 5000) {
            const $statusDiv = $container.find('.goalv-vote-status, .goalv-grid-vote-status');

            if ($statusDiv.length) {
                $statusDiv
                    .removeClass('success error')
                    .addClass(type)
                    .text(message)
                    .show();

                // Auto-hide success messages
                if (type === 'success' && duration > 0) {
                    setTimeout(() => {
                        $statusDiv.fadeOut(300, function () {
                            $(this).text('').removeClass('success error');
                        });
                    }, duration);
                }
            }
        },

        /**
         * Update vote results periodically
         */
        updateVoteResults: function () {
            if (!this.isInitialized()) {
                return;
            }

            $('.goalv-match-card, .goalv-match-grid-item, .goalv-detailed-voting').each(function () {
                const $match = $(this);
                const matchId = $match.data('match-id');

                if (!matchId) return;

                // Determine location
                const location = $match.hasClass('goalv-detailed-voting') ? 'details' : 'homepage';

                // Skip if voting in progress for this match
                if (GoalV.votingInProgress.has(`${matchId}-${location}`)) {
                    return;
                }

                // Fetch updated results
                $.get(goalv_ajax.ajax_url, {
                    action: 'goalv_get_vote_results',
                    match_id: matchId,
                    vote_location: location
                })
                    .done(function (response) {
                        if (response.success && response.data) {
                            GoalV.updateVoteResultsOnly($match, response.data, location);
                        }
                    });
            });
        },

        /**
         * Update only vote results without changing selection
         */
        updateVoteResultsOnly: function ($container, results, location) {
            if (location === 'homepage') {
                // Update card/grid template percentages and counts
                $container.find('.goalv-vote-btn, .goalv-grid-vote-btn').each(function () {
                    const $btn = $(this);
                    const optionId = $btn.data('option-id');
                    const result = results.find(r => r.option_id == optionId);

                    if (result) {
                        // Update percentage
                        $btn.find('.goalv-percentage, .goalv-grid-percentage').text(result.percentage + '%');

                        // Update vote counts
                        $btn.find('.goalv-vote-count, .goalv-grid-vote-count').text(`(${result.votes_count} votes)`);
                        $btn.find('.goalv-inline-vote-count').text(`(${result.votes_count})`);
                    }
                });
            } else {
                // Update detailed voting (already handles vote counts)
                $container.find('.goalv-vote-btn').each(function () {
                    const $btn = $(this);
                    const optionId = $btn.data('option-id');
                    const result = results.find(r => r.option_id == optionId);

                    if (result) {
                        $btn.find('.goalv-percentage').text(result.percentage + '%');
                        $btn.find('.goalv-votes-count').text(`(${result.votes_count} votes)`);
                    }
                });

                // Update results summary
                this.updateResultsSummary($container.closest('.goalv-single-match-wrapper'), results);
            }
        },

        /**
         * Handle admin sync functionality - FIXED VERSION
         */
        handleSync: function (e) {
            e.preventDefault();

            const $btn = $(e.currentTarget);
            const $loader = $('#sync-loader');
            const $result = $('#sync-result');

            // Show loader
            $loader.addClass('is-active');

            // Disable button and show loading
            $btn.prop('disabled', true).text('Syncing...');
            $result.html('<div class="notice notice-info"><p>Syncing matches from API...</p></div>');

            // Use the correct AJAX object - check if we're in admin context
            let ajaxUrl, ajaxNonce;

            if (typeof goalv_ajax !== 'undefined') {
                ajaxUrl = goalv_ajax.ajax_url;
                ajaxNonce = goalv_ajax.nonce;
            } else if (typeof ajaxurl !== 'undefined') {
                // Fallback to WordPress global ajaxurl
                ajaxUrl = ajaxurl;
                ajaxNonce = $('#goalv-admin-nonce').val() || $('input[name="_wpnonce"]').val();
            } else {
                $result.html('<div class="notice notice-error"><p>AJAX configuration error. Please refresh the page.</p></div>');
                $btn.prop('disabled', false).text('Sync Matches');
                $loader.removeClass('is-active');
                return;
            }

            $.post(ajaxUrl, {
                action: 'goalv_sync_matches',
                nonce: ajaxNonce
            })
                .done(function (response) {
                    console.log('Sync response:', response); // Debug log

                    if (response && response.success) {
                        $result.html(`<div class="notice notice-success"><p>${response.data.message}</p></div>`);

                        // Update last sync display
                        const now = new Date();
                        const timeString = now.toLocaleString();
                        $('.goalv-last-sync-info').html(`<p class="description">Last sync: ${timeString}</p>`);

                        // Reload page after successful sync to show new matches
                        setTimeout(() => {
                            location.reload();
                        }, 2000);
                    } else {
                        const errorMsg = (response && response.data) ? response.data : 'Sync failed - unknown error';
                        $result.html(`<div class="notice notice-error"><p>${errorMsg}</p></div>`);
                    }
                })
                .fail(function (xhr, status, error) {
                    console.error('Sync request failed:', xhr.status, xhr.statusText, error);
                    let errorMessage = 'Network error during sync. Please try again.';

                    if (xhr.status === 403) {
                        errorMessage = 'Permission denied. Please refresh the page and try again.';
                    } else if (xhr.status === 500) {
                        errorMessage = 'Server error during sync. Please check the API configuration.';
                    } else if (xhr.responseJSON && xhr.responseJSON.data) {
                        errorMessage = xhr.responseJSON.data;
                    }

                    $result.html(`<div class="notice notice-error"><p>${errorMessage}</p></div>`);
                })
                .always(function () {
                    $btn.prop('disabled', false).text('Sync This Week\'s Matches');
                    $loader.removeClass('is-active');
                });
        },

        /**
         * Get stored vote for localStorage tracking
         */
        getStoredVote: function (matchId, location) {
            if (!this.isInitialized()) {
                return null;
            }

            if (goalv_ajax.is_user_logged_in) {
                return null; // Server handles logged-in user votes
            }

            const storageKey = `goalv_vote_${matchId}_${location}`;
            return localStorage.getItem(storageKey);
        },

        /**
         * Store vote in localStorage for guests
         */
        storeVote: function (matchId, location, optionId) {
            if (!this.isInitialized()) {
                return;
            }

            if (!goalv_ajax.is_user_logged_in) {
                const storageKey = `goalv_vote_${matchId}_${location}`;
                localStorage.setItem(storageKey, optionId);
            }
        },

        /**
         * Initialize existing votes from localStorage
         */
        initStoredVotes: function () {
            if (!this.isInitialized()) {
                return;
            }

            if (goalv_ajax.is_user_logged_in) {
                return; // Server-side data is authoritative for logged-in users
            }

            // Mark buttons as selected based on localStorage
            $('.goalv-vote-btn, .goalv-grid-vote-btn').each(function () {
                const $btn = $(this);
                const matchId = $btn.data('match-id');
                const location = $btn.data('location');
                const optionId = $btn.data('option-id');

                const storedVote = GoalV.getStoredVote(matchId, location);
                if (storedVote && storedVote == optionId) {
                    $btn.addClass('selected').attr('data-selected', 'true');

                    // For detailed voting, also mark the option container
                    $btn.closest('.goalv-vote-option').addClass('selected');
                }
            });
        },

        /**
         * Handle responsive behavior
         */
        handleResponsive: function () {
            const checkMobile = () => {
                const isMobile = window.innerWidth <= 768;
                $('body').toggleClass('goalv-mobile', isMobile);
            };

            checkMobile();
            $(window).on('resize', checkMobile);
        },

        /**
         * Add smooth animations
         */
        addAnimations: function () {
            // Fade in matches on page load
            $('.goalv-match-card, .goalv-match-grid-item').each(function (index) {
                $(this).css({
                    opacity: 0,
                    transform: 'translateY(20px)'
                }).delay(index * 100).animate({
                    opacity: 1
                }, 500).animate({
                    transform: 'translateY(0)'
                }, 300);
            });

            // Smooth hover effects for vote buttons
            $('.goalv-vote-btn, .goalv-grid-vote-btn').on('mouseenter', function () {
                if (!$(this).hasClass('loading')) {
                    $(this).css('transform', 'translateY(-1px)');
                }
            }).on('mouseleave', function () {
                $(this).css('transform', 'translateY(0)');
            });
        },

        /**
         * Handle vote button states
         */
        handleVoteStates: function () {
            // Disable voting for finished matches
            $('.goalv-match-card, .goalv-match-grid-item').each(function () {
                const $match = $(this);
                const isFinished = $match.find('.goalv-status-finished').length > 0;

                if (isFinished) {
                    $match.find('.goalv-vote-btn, .goalv-grid-vote-btn')
                        .addClass('disabled')
                        .prop('disabled', true)
                        .css('cursor', 'not-allowed');
                }
            });
        },

        /**
         * Enhanced error handling
         */
        handleErrors: function () {
            // Global AJAX error handler for GoalV requests
            $(document).ajaxError(function (event, xhr, settings) {
                if (settings.url && (settings.url.includes('goalv_') || (settings.data && settings.data.includes('goalv_')))) {
                    console.error('GoalV AJAX Error:', xhr.status, xhr.statusText);
                }
            });
        },

        /**
         * Auto-retry failed requests
         */
        retryFailedRequest: function (requestData, maxRetries = 2) {
            let retryCount = 0;

            const makeRequest = () => {
                return $.post(goalv_ajax.ajax_url, requestData)
                    .fail(() => {
                        retryCount++;
                        if (retryCount < maxRetries) {
                            setTimeout(makeRequest, 1000 * retryCount); // Exponential backoff
                        }
                    });
            };

            return makeRequest();
        },

        /**
         * Validate vote data before submission
         */
        validateVoteData: function (matchId, optionId, location) {
            if (!matchId || !optionId || !location) {
                return false;
            }

            if (!['homepage', 'details'].includes(location)) {
                return false;
            }

            return true;
        },

        /**
         * Handle keyboard navigation
         */
        initKeyboardNav: function () {
            $('.goalv-vote-btn, .goalv-grid-vote-btn').on('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    $(this).click();
                }
            });
        },

        updateVoteResultsThrottled: function () {
            if (this.updateThrottle) {
                clearTimeout(this.updateThrottle);
            }

            this.updateThrottle = setTimeout(() => {
                this.updateVoteResults();
            }, 2000); // Throttle updates to every 2 seconds max
        },

        /**
         * Performance optimization - debounce updates
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
        }
    };

    // Debounced update function
    GoalV.debouncedUpdateResults = GoalV.debounce(GoalV.updateVoteResults.bind(GoalV), 1000);

    /**
     * Initialize when DOM is ready
     */
    $(document).ready(function () {
        // Wait a bit for WordPress to load all scripts
        setTimeout(function () {
            if (typeof goalv_ajax !== 'undefined') {
                GoalV.init();
                GoalV.initStoredVotes();
                GoalV.handleResponsive();
                GoalV.addAnimations();
                GoalV.handleVoteStates();
                GoalV.handleErrors();
                GoalV.initKeyboardNav();
            } else {
                console.warn('GoalV: goalv_ajax not loaded. Plugin may not be properly enqueued on this page.');

                // Still initialize basic functionality without AJAX
                GoalV.handleResponsive();
                GoalV.addAnimations();
                GoalV.initKeyboardNav();
            }
        }, 100);
    });

    /**
     * Utility functions
     */

    // Smooth scroll to element
    function smoothScrollTo($element, offset = 0) {
        if ($element.length) {
            $('html, body').animate({
                scrollTop: $element.offset().top - offset
            }, 500);
        }
    }

    // Format numbers with commas
    function formatNumber(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }

    // Check if element is in viewport
    function isInViewport($element) {
        const elementTop = $element.offset().top;
        const elementBottom = elementTop + $element.outerHeight();
        const viewportTop = $(window).scrollTop();
        const viewportBottom = viewportTop + $(window).height();

        return elementBottom > viewportTop && elementTop < viewportBottom;
    }

    /**
     * Additional event handlers for enhanced UX
     */

    // Show tooltips for truncated team names
    $(document).on('mouseenter', '.goalv-team-name-short', function () {
        const $this = $(this);
        if (this.scrollWidth > this.clientWidth) {
            $this.attr('title', $this.text());
        }
    });

    // Handle voting button focus states
    $(document).on('focus', '.goalv-vote-btn, .goalv-grid-vote-btn', function () {
        $(this).closest('.goalv-voting-section, .goalv-grid-voting').addClass('focused');
    }).on('blur', '.goalv-vote-btn, .goalv-grid-vote-btn', function () {
        $(this).closest('.goalv-voting-section, .goalv-grid-voting').removeClass('focused');
    });

    // Lazy load team logos
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    if (img.dataset.src) {
                        img.src = img.dataset.src;
                        img.removeAttribute('data-src');
                        observer.unobserve(img);
                    }
                }
            });
        });

        $(document).ready(function () {
            $('.goalv-team-logo img, .goalv-team-logo-small').each(function () {
                imageObserver.observe(this);
            });
        });
    }

    // Expose GoalV object for debugging
    window.GoalV = GoalV;

})(jQuery);

/**
 * Vanilla JavaScript fallback for browsers without jQuery
 */
if (typeof jQuery === 'undefined') {
    console.warn('GoalV: jQuery not found. Some features may not work properly.');

    // Basic voting functionality without jQuery
    document.addEventListener('DOMContentLoaded', function () {
        const voteButtons = document.querySelectorAll('.goalv-vote-btn, .goalv-grid-vote-btn');

        voteButtons.forEach(button => {
            button.addEventListener('click', function (e) {
                e.preventDefault();

                const matchId = this.dataset.matchId;
                const optionId = this.dataset.optionId;
                const location = this.dataset.location;

                // Basic vote submission without full functionality
                fetch(goalv_ajax.ajax_url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'goalv_cast_vote',
                        match_id: matchId,
                        option_id: optionId,
                        vote_location: location,
                        nonce: goalv_ajax.nonce,
                        browser_id: window.goalvBrowserId || ''
                    })
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Basic UI update
                            this.classList.add('selected');

                            // Remove selection from siblings
                            const siblings = this.parentNode.querySelectorAll('.goalv-vote-btn, .goalv-grid-vote-btn');
                            siblings.forEach(sibling => {
                                if (sibling !== this) {
                                    sibling.classList.remove('selected');
                                }
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Vote submission failed:', error);
                    });
            });
        });
    });
}