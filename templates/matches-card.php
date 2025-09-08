<?php
/**
 * Card Template for Matches - UPDATED WITH DYNAMIC LABELS
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

echo '<div class="goalv-matches-container goalv-card-template">';

if (!empty($matches)) {
    foreach ($matches as $match) {
        // Show week header if enabled and week changes
        if ($show_headers && isset($match->display_week) && $match->display_week !== $current_week) {
            if ($current_week !== '') {
                echo '</div></div>'; // Close previous week matches and section
            }

            $current_week = $match->display_week;
            echo '<div class="goalv-week-section">';
            echo '<h3 class="goalv-week-header">' . esc_html($current_week) . ' Matches</h3>';
            echo '<div class="goalv-week-matches">';
        } elseif (!$show_headers && $current_week === '') {
            // Start matches container for non-header mode
            echo '<div class="goalv-week-matches">';
            $current_week = 'started';
        }

        // Individual match card
        ?>
        <div class="goalv-match-card" data-match-id="<?php echo esc_attr($match->ID); ?>">

            <!-- Match Header -->
            <div class="goalv-card-header">
                <div class="goalv-competition-badge">
                    <?php echo esc_html($match->competition); ?>
                </div>
                <div class="goalv-match-date">
                    <?php echo esc_html($frontend->format_match_date($match->match_date)); ?>
                </div>
            </div>

            <!-- Teams Display -->
            <div class="goalv-teams-section">
                <div class="goalv-team goalv-home">
                    <div class="goalv-team-logo">
                        <img src="<?php echo esc_url($frontend->get_team_logo($match->home_team_logo, $match->home_team)); ?>"
                            alt="<?php echo esc_attr($match->home_team); ?>"
                            onerror="this.src='<?php echo esc_url(GOALV_PLUGIN_URL . 'assets/images/default-team-logo.png'); ?>'">
                    </div>
                    <div class="goalv-team-name"><?php echo esc_html($match->home_team); ?></div>
                </div>

                <div class="goalv-match-center">
                    <div class="goalv-match-status">
                        <?php echo $frontend->get_status_display($match->match_status, $match->home_score, $match->away_score); ?>
                    </div>

                    <?php if ($match->match_status === 'finished' || $match->match_status === 'live'): ?>
                        <div class="goalv-score-display">
                            <?php echo esc_html($match->home_score . ' - ' . $match->away_score); ?>
                        </div>
                    <?php else: ?>
                        <div class="goalv-vs-text">VS</div>
                    <?php endif; ?>
                </div>

                <div class="goalv-team goalv-away">
                    <div class="goalv-team-logo">
                        <img src="<?php echo esc_url($frontend->get_team_logo($match->away_team_logo, $match->away_team)); ?>"
                            alt="<?php echo esc_attr($match->away_team); ?>"
                            onerror="this.src='<?php echo esc_url(GOALV_PLUGIN_URL . 'assets/images/default-team-logo.png'); ?>'">
                    </div>
                    <div class="goalv-team-name"><?php echo esc_html($match->away_team); ?></div>
                </div>
            </div>

            <!-- Voting Section -->
            <?php if ($match->match_status !== 'finished'): ?>
                <div class="goalv-voting-section" data-match-id="<?php echo esc_attr($match->ID); ?>">
                    <div class="goalv-voting-options">
                        <?php foreach ($match->vote_options as $option): ?>
                            <?php
                            $is_selected = in_array($option->id, $match->user_votes);
                            $result = isset($match->vote_results[$option->id]) ? $match->vote_results[$option->id] : array('percentage' => 0, 'votes_count' => 0);
                            $percentage = $result['percentage'];
                            $vote_count = $result['votes_count'];
                            ?>
                            <button type="button" class="goalv-vote-btn <?php echo $is_selected ? 'selected' : ''; ?>"
                                data-option-id="<?php echo esc_attr($option->id); ?>"
                                data-match-id="<?php echo esc_attr($match->ID); ?>" 
                                data-location="homepage" 
                                title="<?php echo esc_attr($option->option_text); ?>"
                                <?php echo $is_selected ? 'data-selected="true"' : ''; ?>>
                                <span class="goalv-option-text"><?php echo esc_html($option->option_text); ?></span>
                                <span class="goalv-vote-stats">
                                    <span class="goalv-percentage"><?php echo esc_html($percentage); ?>%</span>
                                    <span class="goalv-vote-count">(<?php echo esc_html($vote_count); ?>)</span>
                                </span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <div class="goalv-vote-status"></div>
                </div>
            <?php else: ?>
                <!-- Final Results for Finished Matches -->
                <div class="goalv-final-results">
                    <h4><?php echo esc_html($labels['predictions']); ?></h4>
                    <div class="goalv-results-list">
                        <?php foreach ($match->vote_results as $result): ?>
                            <div class="goalv-result-item">
                                <span class="goalv-result-text"><?php echo esc_html($result['option_text']); ?></span>
                                <span class="goalv-result-stats">
                                    <span class="goalv-result-percentage"><?php echo esc_html($result['percentage']); ?>%</span>
                                    <span class="goalv-result-count">(<?php echo esc_html($result['votes_count']); ?>)</span>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- View Details Link -->
            <div class="goalv-card-footer">
                <a href="<?php echo get_permalink($match->ID); ?>" class="goalv-details-link">
                    More Match Voting
                </a>
            </div>

        </div>
        <?php
    }

    // Close final section
    if ($current_week !== '') {
        echo '</div>'; // Close matches container
        if ($show_headers) {
            echo '</div>'; // Close week section
        }
    }
} else {
    // No matches message
    ?>
    <div class="goalv-no-matches goalv-card-no-matches">
        <div class="goalv-no-matches-content">
            <div class="goalv-no-matches-icon">⚽</div>
            <h3><?php _e('No Matches This Week', 'goalv'); ?></h3>
            <p><?php _e('Check back soon for upcoming matches!', 'goalv'); ?></p>
        </div>
    </div>
    <?php
}

echo '</div>'; // Close main container
?>