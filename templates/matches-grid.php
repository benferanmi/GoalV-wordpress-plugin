<?php
/**
 * Grid Template for Matches - UPDATED WITH DYNAMIC LABELS
 */

if (!defined('ABSPATH')) {
    exit;
}

$frontend = new GoalV_Frontend();

// Set default labels if not provided
$default_labels = array(
    'teams' => __('Teams', 'goalv'),
    'score' => __('Score', 'goalv'),
    'status' => __('Status', 'goalv'),
    'date' => __('Date', 'goalv'),
    'predictions' => __('Predictions', 'goalv'),
    'details' => __('Details', 'goalv')
);

// Merge with provided labels
$labels = isset($labels) ? array_merge($default_labels, $labels) : $default_labels;

// Week header logic
$current_week = '';
$show_headers = isset($show_week_headers) ? $show_week_headers : false;

echo '<div class="goalv-matches-container goalv-grid-template">';

if (!empty($matches)) {
    foreach ($matches as $match) {
        // Show week header if enabled and week changes
        if ($show_headers && isset($match->display_week) && $match->display_week !== $current_week) {
            if ($current_week !== '') {
                echo '</div></div>'; // Close previous week grid and section
            }

            $current_week = $match->display_week;
            echo '<div class="goalv-week-section">';
            echo '<h3 class="goalv-week-header">' . esc_html($current_week) . ' Matches</h3>';
            echo '<div class="goalv-matches-grid">';
        } elseif (!$show_headers && $current_week === '') {
            // Start matches grid for non-header mode
            echo '<div class="goalv-matches-grid">';
            $current_week = 'started';
        }

        // Individual match grid item
        ?>
        <div class="goalv-match-grid-item" data-match-id="<?php echo esc_attr($match->ID); ?>">

            <!-- Competition Badge -->
            <div class="goalv-grid-competition">
                <?php echo esc_html($match->competition); ?>
            </div>

            <!-- Teams Row -->
            <div class="goalv-teams-row">
                <div class="goalv-team-compact goalv-home">
                    <img src="<?php echo esc_url($frontend->get_team_logo($match->home_team_logo, $match->home_team)); ?>"
                        alt="<?php echo esc_attr($match->home_team); ?>" class="goalv-team-logo-small"
                        onerror="this.src='<?php echo esc_url(GOALV_PLUGIN_URL . 'assets/images/default-team-logo.png'); ?>'">
                    <span class="goalv-team-name-short">
                        <?php echo esc_html($match->home_team); ?>
                    </span>
                </div>

                <div class="goalv-match-vs">
                    <?php if ($match->match_status === 'finished' || $match->match_status === 'live'): ?>
                        <span class="goalv-score-compact">
                            <?php echo esc_html($match->home_score . '-' . $match->away_score); ?>
                        </span>
                    <?php else: ?>
                        <span class="goalv-vs-compact">vs</span>
                    <?php endif; ?>
                </div>

                <div class="goalv-team-compact goalv-away">
                    <img src="<?php echo esc_url($frontend->get_team_logo($match->away_team_logo, $match->away_team)); ?>"
                        alt="<?php echo esc_attr($match->away_team); ?>" class="goalv-team-logo-small"
                        onerror="this.src='<?php echo esc_url(GOALV_PLUGIN_URL . 'assets/images/default-team-logo.png'); ?>'">
                    <span class="goalv-team-name-short">
                        <?php echo esc_html($match->away_team); ?>
                    </span>
                </div>
            </div>

            <!-- Match Info -->
            <div class="goalv-grid-match-info">
                <div class="goalv-grid-status">
                    <?php echo $frontend->get_status_display($match->match_status, $match->home_score, $match->away_score); ?>
                </div>
                <div class="goalv-grid-date">
                    <?php echo esc_html($frontend->format_match_date($match->match_date)); ?>
                </div>
            </div>

            <!-- Voting Buttons -->
            <?php if ($match->match_status !== 'finished'): ?>
                <div class="goalv-grid-voting goalv-voting-section" data-match-id="<?php echo esc_attr($match->ID); ?>">
                    <?php foreach ($match->vote_options as $option): ?>
                        <?php
                        $is_selected = in_array($option->id, $match->user_votes);
                        $result = isset($match->vote_results[$option->id]) ? $match->vote_results[$option->id] : array('percentage' => 0, 'votes_count' => 0);
                        $percentage = $result['percentage'];
                        $vote_count = $result['votes_count'];
                        ?>
                        <button type="button" class="goalv-grid-vote-btn goalv-vote-btn <?php echo $is_selected ? 'selected' : ''; ?>"
                            data-option-id="<?php echo esc_attr($option->id); ?>" data-match-id="<?php echo esc_attr($match->ID); ?>"
                            data-location="homepage" title="<?php echo esc_attr($option->option_text); ?>" <?php echo $is_selected ? 'data-selected="true"' : ''; ?>>
                            <span class="goalv-grid-option-text">
                                <?php echo esc_html($option->option_text); ?>
                            </span>
                            <span class="goalv-grid-vote-stats">
                                <span class="goalv-grid-percentage goalv-percentage">
                                    <?php echo esc_html($percentage); ?>%
                                </span>
                                <span class="goalv-grid-vote-count">(
                                    <?php echo esc_html($vote_count); ?>)
                                </span>
                            </span>
                        </button>
                    <?php endforeach; ?>
                    <div class="goalv-grid-vote-status goalv-vote-status"></div>
                </div>
            <?php else: ?>
                <!-- Final Results for Finished Matches -->
                <div class="goalv-grid-final-results">
                    <?php foreach ($match->vote_results as $result): ?>
                        <div class="goalv-grid-result-item">
                            <span class="goalv-grid-result-text">
                                <?php echo esc_html($result['option_text']); ?>
                            </span>
                            <span class="goalv-grid-result-percentage">
                                <?php echo esc_html($result['percentage']); ?>%
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Details Link -->
            <div class="goalv-grid-footer">
                <a href="<?php echo get_permalink($match->ID); ?>" class="goalv-grid-details-link">
                    More Match Voting
                </a>
            </div>

        </div>
        <?php
    }

    // Close final section
    if ($current_week !== '') {
        echo '</div>'; // Close matches grid
        if ($show_headers) {
            echo '</div>'; // Close week section
        }
    }
} else {
    // No matches message
    ?>
    <div class="goalv-no-matches goalv-grid-no-matches">
        <div class="goalv-no-matches-content">
            <div class="goalv-no-matches-icon">⚽</div>
            <h3>
                <?php _e('No Matches This Week', 'goalv'); ?>
            </h3>
            <p>
                <?php _e('Check back soon for upcoming matches!', 'goalv'); ?>
            </p>
        </div>
    </div>
    <?php
}

echo '</div>'; // Close main container
?>