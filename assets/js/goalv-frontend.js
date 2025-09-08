/**
 * GoalV Football Predictions - Frontend JavaScript (Updated for Multiple Votes)
 * Handles voting functionality for homepage and single match pages
 */

(function ($) {
    'use strict';

    // Frontend voting system
    const GoalVFrontend = {
        // State management
        votingInProgress: new Set(),
        userVotes: new Map(), // Now stores arrays of vote IDs for multiple votes
        updateThrottle: null,

        init: function () {
            if (!this.isInitialized()) {
                return;
            }

            this.bindEvents();
            this.initBrowserId();
            this.initStoredVotes();
            this.handleResponsive();
            this.addAnimations();
            this.handleVoteStates();
            this.initKeyboardNav();
            this.initInfiniteScroll();

            // Start periodic updates
            this.startPeriodicUpdates();
        },

        isInitialized: function () {
            return typeof goalv_ajax !== 'undefined';
        },

        bindEvents: function () {
            // Voting buttons for all templates
            $(document).on('click', '.goalv-vote-btn, .goalv-grid-vote-btn', this.handleVote.bind(this));

            // Auto-refresh results
            $(window).on('focus', this.updateVoteResults.bind(this));
        },

        /**
         * Handle vote submission - UPDATED for multiple votes
         */
        handleVote: function (e) {
            e.preventDefault();

            const $btn = $(e.currentTarget);
            const matchId = $btn.data('match-id');
            const optionId = $btn.data('option-id');
            const location = $btn.data('location') || 'homepage';

            if (!this.validateVoteData(matchId, optionId, location)) {
                return;
            }

            // Check login requirement for details page
            if (location === 'details' && !goalv_ajax.is_user_logged_in) {
                this.showMessage($btn.closest('.goalv-voting-section'), 'Login required for detailed voting', 'error');
                return;
            }

            // If multiple votes are disabled and user already has a vote, prevent multiple selections
            if (!this.isMultipleVotesEnabled()) {
                const userVotes = this.userVotes.get(`${matchId}-${location}`) || [];
                const currentOptionId = parseInt(optionId);

                if (userVotes.length > 0 && !userVotes.includes(currentOptionId)) {
                    // User is trying to vote for a different option when multiple votes are disabled
                    // This should be handled as a vote change, not addition
                }
            }

            // Prevent double voting
            const votingKey = `${matchId}-${location}`;
            if (this.votingInProgress.has(votingKey)) {
                return;
            }

            this.submitVote($btn, matchId, optionId, location);
        },

        submitVote: function ($btn, matchId, optionId, location) {
            const votingKey = `${matchId}-${location}`;

            this.votingInProgress.add(votingKey);
            this.setVotingState($btn, true);

            const voteData = {
                action: 'goalv_cast_vote',
                match_id: matchId,
                option_id: optionId,
                vote_location: location,
                nonce: goalv_ajax.nonce
            };

            // Add browser ID for guest users
            if (!goalv_ajax.is_user_logged_in && window.goalvBrowserId) {
                voteData.browser_id = window.goalvBrowserId;
            }

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
         * Handle vote response - UPDATED for one-vote-per-category system
         */
        handleVoteResponse: function (response, $btn, matchId, location, optionId) {
            const $container = $btn.closest('.goalv-voting-section, .goalv-grid-voting');

            if (response.success) {
                // NEW: Handle one-vote-per-category system
                if (response.data.one_vote_per_category) {
                    this.updateVoteUIByCategory(matchId, location, response.data.results, response.data.user_votes_by_category, response.data.category);

                    // Store votes by category for guest users
                    if (!goalv_ajax.is_user_logged_in) {
                        this.storeVotesByCategory(matchId, location, response.data.user_votes_by_category);
                    }
                } else {
                    // Legacy multiple votes system
                    this.updateVoteUI(matchId, location, response.data.results, response.data.user_votes || [optionId]);
                    this.storeVotes(matchId, location, response.data.user_votes || [optionId]);
                }

                // Show appropriate message
                let message = response.data.message;
                this.showMessage($container, message, 'success', 2000);
            } else {
                this.showMessage($container, response.data || 'Vote failed', 'error');
            }
        },

        /**
 * Store votes by category for guest users
 */
        storeVotesByCategory: function (matchId, location, votesByCategory) {
            if (!goalv_ajax.is_user_logged_in) {
                const storageKey = `goalv_votes_by_category_${matchId}_${location}`;
                localStorage.setItem(storageKey, JSON.stringify(votesByCategory));
            }
        },

        /**
         * Get stored votes by category for guest users
         */
        getStoredVotesByCategory: function (matchId, location) {
            if (!goalv_ajax.is_user_logged_in) {
                const storageKey = `goalv_votes_by_category_${matchId}_${location}`;
                const stored = localStorage.getItem(storageKey);
                return stored ? JSON.parse(stored) : {};
            }
            return {};
        },

        /**
 * Update UI for one-vote-per-category system 
 */
        updateVoteUIByCategory: function (matchId, location, results, userVotesByCategory, changedCategory) {
            const $matchContainer = $(`.goalv-match-card[data-match-id="${matchId}"], .goalv-match-grid-item[data-match-id="${matchId}"], .goalv-detailed-voting[data-match-id="${matchId}"]`);

            // Update results first
            this.updateVoteResultsOnly($matchContainer, results, location);

            // Clear all selections in the changed category only
            $matchContainer.find(`.goalv-voting-group[data-category="${changedCategory}"] .goalv-vote-btn`).each(function () {
                $(this).removeClass('selected').removeAttr('data-selected');
                $(this).closest('.goalv-vote-option').removeClass('selected');
            });

            // Set new selections based on user votes by category
            Object.keys(userVotesByCategory).forEach(category => {
                const selectedOptionId = userVotesByCategory[category];
                const $categoryBtn = $matchContainer.find(`.goalv-voting-group[data-category="${category}"] .goalv-vote-btn[data-option-id="${selectedOptionId}"]`);

                if ($categoryBtn.length) {
                    $categoryBtn.addClass('selected').attr('data-selected', 'true');
                    $categoryBtn.closest('.goalv-vote-option').addClass('selected');
                }
            });
        },


        handleVoteError: function ($btn, matchId, location) {
            const $container = $btn.closest('.goalv-voting-section, .goalv-grid-voting');
            this.showMessage($container, 'Network error. Please try again.', 'error');
        },

        /**
         * Update vote UI with new results - UPDATED for multiple votes
         */
        updateVoteUI: function (matchId, location, results, selectedOptionIds) {
            const $matchContainer = $(`.goalv-match-card[data-match-id="${matchId}"], .goalv-match-grid-item[data-match-id="${matchId}"], .goalv-detailed-voting[data-match-id="${matchId}"]`);

            // CRITICAL FIX: Ensure selectedOptionIds is always an array and convert to integers
            if (!selectedOptionIds) {
                selectedOptionIds = [];
            }
            if (!Array.isArray(selectedOptionIds)) {
                selectedOptionIds = [selectedOptionIds];
            }
            // Convert all IDs to integers for proper comparison
            selectedOptionIds = selectedOptionIds.map(id => parseInt(id));

            console.log('UpdateVoteUI - Match:', matchId, 'Location:', location, 'Selected IDs:', selectedOptionIds); // Debug log

            if (location === 'homepage') {
                this.updateHomepageVoting($matchContainer, results, selectedOptionIds);
            } else {
                this.updateDetailedVoting($matchContainer, results, selectedOptionIds);
            }
        },


        updateHomepageVoting: function ($container, results, selectedOptionIds) {
            // CRITICAL FIX: Convert selectedOptionIds to integers if not already
            selectedOptionIds = selectedOptionIds.map(id => parseInt(id));

            $container.find('.goalv-vote-btn, .goalv-grid-vote-btn').each(function () {
                const $btn = $(this);
                const optionId = parseInt($btn.data('option-id')); // Convert to integer
                const result = results.find(r => r.option_id == optionId);

                if (result) {
                    // Update percentage and counts
                    $btn.find('.goalv-percentage, .goalv-grid-percentage').text(result.percentage + '%');
                    $btn.find('.goalv-vote-count, .goalv-grid-vote-count, .goalv-inline-vote-count').text(`(${result.votes_count})`);

                    // FIXED: Update selection state - handle multiple selections with proper integer comparison
                    if (selectedOptionIds.includes(optionId)) {
                        $btn.addClass('selected').attr('data-selected', 'true');
                        console.log('Adding selected to option:', optionId); // Debug log
                    } else {
                        $btn.removeClass('selected').removeAttr('data-selected');
                        console.log('Removing selected from option:', optionId); // Debug log
                    }
                }
            });

            // Add visual indicator for multiple votes mode
            if (selectedOptionIds.length > 1) {
                $container.addClass('goalv-multiple-votes-active');
            } else {
                $container.removeClass('goalv-multiple-votes-active');
            }
        },

        updateDetailedVoting: function ($container, results, selectedOptionIds) {
            // CRITICAL FIX: Convert selectedOptionIds to integers if not already
            selectedOptionIds = selectedOptionIds.map(id => parseInt(id));

            $container.find('.goalv-vote-btn').each(function () {
                const $btn = $(this);
                const $option = $btn.closest('.goalv-vote-option');
                const optionId = parseInt($btn.data('option-id')); // Convert to integer
                const result = results.find(r => r.option_id == optionId);

                if (result) {
                    $btn.find('.goalv-percentage').text(result.percentage + '%');
                    $btn.find('.goalv-votes-count').text(`(${result.votes_count} votes)`);

                    // FIXED: Update selection state for multiple votes with proper integer comparison
                    if (selectedOptionIds.includes(optionId)) {
                        $btn.addClass('selected').attr('data-selected', 'true');
                        $option.addClass('selected');
                        console.log('Adding selected to detailed option:', optionId); // Debug log
                    } else {
                        $btn.removeClass('selected').removeAttr('data-selected');
                        $option.removeClass('selected');
                        console.log('Removing selected from detailed option:', optionId); // Debug log
                    }
                }
            });

            // Add multiple votes indicator
            if (selectedOptionIds.length > 1) {
                $container.addClass('goalv-multiple-votes-active');
                this.showMultipleVotesIndicator($container, selectedOptionIds.length);
            } else {
                $container.removeClass('goalv-multiple-votes-active');
                this.hideMultipleVotesIndicator($container);
            }

            this.updateResultsSummary($container.closest('.goalv-single-match-wrapper'), results);
        },

        showMultipleVotesIndicator: function ($container, voteCount) {
            let $indicator = $container.find('.goalv-multiple-votes-indicator');
            if (!$indicator.length) {
                $indicator = $('<div class="goalv-multiple-votes-indicator"></div>');
                $container.prepend($indicator);
            }
            $indicator.html(`<span class="goalv-votes-badge">${voteCount} votes selected</span>`).show();
        },

        hideMultipleVotesIndicator: function ($container) {
            $container.find('.goalv-multiple-votes-indicator').hide();
        },

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

            $wrapper.find('.goalv-total-votes').text(`Total Votes: ${totalVotes}`);
        },

        /**
         * Browser ID management for guest users
         */
        initBrowserId: function () {
            if (!goalv_ajax.is_user_logged_in) {
                if (typeof window.goalvBrowserId === 'undefined') {
                    window.goalvBrowserId = this.generateBrowserId();
                }
            }
        },

        generateBrowserId: function () {
            let browserId = localStorage.getItem('goalv_browser_id');
            if (!browserId) {
                browserId = 'guest_' + Math.random().toString(36).substr(2, 9) + '_' + Date.now();
                localStorage.setItem('goalv_browser_id', browserId);
            }
            return browserId;
        },

        /**
         * Vote storage for guest users - UPDATED for multiple votes
         */
        getStoredVotes: function (matchId, location) {
            if (!goalv_ajax.is_user_logged_in) {
                const storageKey = `goalv_votes_${matchId}_${location}`;
                const stored = localStorage.getItem(storageKey);
                return stored ? JSON.parse(stored) : [];
            }
            return [];
        },

        storeVotes: function (matchId, location, optionIds) {
            if (!goalv_ajax.is_user_logged_in) {
                const storageKey = `goalv_votes_${matchId}_${location}`;
                localStorage.setItem(storageKey, JSON.stringify(optionIds));
            }
        },

        isMultipleVotesEnabled: function () {
            return typeof goalv_ajax.allow_multiple_votes !== 'undefined' && goalv_ajax.allow_multiple_votes === true;
        },

        // Legacy support for single vote storage
        getStoredVote: function (matchId, location) {
            const votes = this.getStoredVotes(matchId, location);
            return votes.length > 0 ? votes[0] : null;
        },

        storeVote: function (matchId, location, optionId) {
            this.storeVotes(matchId, location, [optionId]);
        },

        initStoredVotes: function () {
            if (goalv_ajax.is_user_logged_in) {
                return;
            }

            $('.goalv-vote-btn, .goalv-grid-vote-btn').each(function () {
                const $btn = $(this);
                const matchId = $btn.data('match-id');
                const location = $btn.data('location') || 'homepage';
                const optionId = parseInt($btn.data('option-id'));

                const storedVotes = GoalVFrontend.getStoredVotes(matchId, location);
                if (storedVotes.includes(optionId)) {
                    $btn.addClass('selected').attr('data-selected', 'true');
                    $btn.closest('.goalv-vote-option').addClass('selected');
                }
            });

            // Show multiple votes indicators for stored votes
            $('.goalv-match-card, .goalv-match-grid-item, .goalv-detailed-voting').each(function () {
                const $match = $(this);
                const matchId = $match.data('match-id');
                if (!matchId) return;

                const location = $match.hasClass('goalv-detailed-voting') ? 'details' : 'homepage';
                const storedVotes = GoalVFrontend.getStoredVotes(matchId, location);

                if (storedVotes.length > 1) {
                    $match.addClass('goalv-multiple-votes-active');
                    if (location === 'details') {
                        GoalVFrontend.showMultipleVotesIndicator($match, storedVotes.length);
                    }
                }
            });
        },

        /**
         * Periodic updates and real-time features
         */
        startPeriodicUpdates: function () {
            // Update vote results every 30 seconds
            setInterval(this.updateVoteResults.bind(this), 30000);
        },

        updateVoteResults: function () {
            $('.goalv-match-card, .goalv-match-grid-item, .goalv-detailed-voting').each(function () {
                const $match = $(this);
                const matchId = $match.data('match-id');

                if (!matchId) return;

                const location = $match.hasClass('goalv-detailed-voting') ? 'details' : 'homepage';

                if (GoalVFrontend.votingInProgress.has(`${matchId}-${location}`)) {
                    return;
                }

                $.get(goalv_ajax.ajax_url, {
                    action: 'goalv_get_vote_results',
                    match_id: matchId,
                    vote_location: location
                })
                    .done(function (response) {
                        if (response.success && response.data) {
                            GoalVFrontend.updateVoteResultsOnly($match, response.data, location);
                        }
                    });
            });
        },

        updateVoteResultsOnly: function ($container, results, location) {
            if (location === 'homepage') {
                $container.find('.goalv-vote-btn, .goalv-grid-vote-btn').each(function () {
                    const $btn = $(this);
                    const optionId = $btn.data('option-id');
                    const result = results.find(r => r.option_id == optionId);

                    if (result) {
                        $btn.find('.goalv-percentage, .goalv-grid-percentage').text(result.percentage + '%');
                        $btn.find('.goalv-vote-count, .goalv-grid-vote-count, .goalv-inline-vote-count').text(`(${result.votes_count})`);
                    }
                });
            } else {
                $container.find('.goalv-vote-btn').each(function () {
                    const $btn = $(this);
                    const optionId = $btn.data('option-id');
                    const result = results.find(r => r.option_id == optionId);

                    if (result) {
                        $btn.find('.goalv-percentage').text(result.percentage + '%');
                        $btn.find('.goalv-votes-count').text(`(${result.votes_count} votes)`);
                    }
                });

                this.updateResultsSummary($container.closest('.goalv-single-match-wrapper'), results);
            }
        },

        /**
         * UI State Management
         */
        setVotingState: function ($btn, isLoading) {
            if (isLoading) {
                $btn.addClass('loading').prop('disabled', true);
            } else {
                $btn.removeClass('loading').prop('disabled', false);
            }
        },

        showMessage: function ($container, message, type, duration = 5000) {
            const $statusDiv = $container.find('.goalv-vote-status, .goalv-grid-vote-status');

            if ($statusDiv.length) {
                $statusDiv
                    .removeClass('success error')
                    .addClass(type)
                    .text(message)
                    .show();

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
         * Responsive and UX enhancements
         */
        handleResponsive: function () {
            const checkMobile = () => {
                const isMobile = window.innerWidth <= 768;
                $('body').toggleClass('goalv-mobile', isMobile);
            };

            checkMobile();
            $(window).on('resize', checkMobile);
        },

        addAnimations: function () {
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

            $('.goalv-vote-btn, .goalv-grid-vote-btn').on('mouseenter', function () {
                if (!$(this).hasClass('loading')) {
                    $(this).css('transform', 'translateY(-1px)');
                }
            }).on('mouseleave', function () {
                $(this).css('transform', 'translateY(0)');
            });
        },

        handleVoteStates: function () {
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

        initKeyboardNav: function () {
            $('.goalv-vote-btn, .goalv-grid-vote-btn').on('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    $(this).click();
                }
            });
        },

        /**
         * Infinite scroll functionality
         */
        infiniteScroll: {
            isLoading: false,
            hasMoreMatches: true,
            currentPage: 1,
            perPage: 10,
            container: null
        },

        initInfiniteScroll: function () {
            const $container = $('.goalv-table-body');
            if ($container.length) {
                this.infiniteScroll.container = $container;
                this.bindScrollEvents();
                this.addLoadingIndicator();
            }
        },

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
         * Utility functions
         */
        validateVoteData: function (matchId, optionId, location) {
            return matchId && optionId && ['homepage', 'details'].includes(location);
        },

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

    /**
     * Initialize when DOM is ready
     */
    $(document).ready(function () {
        setTimeout(function () {
            if (typeof goalv_ajax !== 'undefined') {
                GoalVFrontend.init();
            } else {
                console.warn('GoalV: goalv_ajax not loaded on frontend.');
                GoalVFrontend.handleResponsive();
                GoalVFrontend.addAnimations();
                GoalVFrontend.initKeyboardNav();
            }
        }, 100);
    });

    // Expose for debugging
    window.GoalVFrontend = GoalVFrontend;

})(jQuery);