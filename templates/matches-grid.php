<?php
/**
 * Grid Template for Matches - UPDATED FOR DATABASE
 */

if (!defined('ABSPATH')) {
    exit;
}

$frontend = new GoalV_Frontend();
$renderer = $frontend->get_renderer();

// Set default labels
$default_labels = array(
    'teams' => __('Teams', 'goalv'),
    'score' => __('Score', 'goalv'),
    'status' => __('Status', 'goalv'),
    'date' => __('Date', 'goalv'),
    'predictions' => __('Predictions', 'goalv'),
    'details' => __('Details', 'goalv')
);

$labels = isset($labels) ? array_merge($default_labels, $labels) : $default_labels;
$show_headers = isset($show_week_headers) ? $show_week_headers : false;

echo '<div class="goalv-matches-container goalv-grid-template">';

if (!empty($matches)) {
    $current_week = '';
    
    foreach ($matches as $match) {
        if ($show_headers && isset($match->display_week) && $match->display_week !== $current_week) {
            if ($current_week !== '') {
                echo '</div></div>';
            }
            $current_week = $match->display_week;
            echo '<div class="goalv-week-section">';
            echo '<h3 class="goalv-week-header">' . esc_html($current_week) . ' Matches</h3>';
            echo '<div class="goalv-matches-grid">';
        } elseif (!$show_headers && $current_week === '') {
            echo '<div class="goalv-matches-grid">';
            $current_week = 'started';
        }
        ?>
        <div class="goalv-match-grid-item" data-match-id="<?php echo esc_attr($match->id); ?>">

            <!-- Competition Badge -->
            <div class="goalv-grid-competition">
                <?php echo esc_html($match->competition); ?>
            </div>

            <!-- Teams Row -->
            <div class="goalv-teams-row">
                <div class="goalv-team-compact goalv-home">
                    <img src="<?php echo esc_url($renderer->get_team_logo($match->home_team_logo, $match->home_team)); ?>"
                        alt="<?php echo esc_attr($match->home_team); ?>" class="goalv-team-logo-small"
                        onerror="this.src='<?php echo esc_url(GOALV_PLUGIN_URL . 'assets/images/default-team-logo.png'); ?>'">
                    <span class="goalv-team-name-short"><?php echo esc_html($match->home_team); ?></span>
                </div>

                <div class="goalv-match-vs">
                    <?php if (in_array($match->status, array('finished', 'live', 'paused'))): ?>
                        <span class="goalv-score-compact">
                            <?php echo esc_html($match->home_score . '-' . $match->away_score); ?>
                        </span>
                    <?php else: ?>
                        <span class="goalv-vs-compact">vs</span>
                    <?php endif; ?>
                </div>

                <div class="goalv-team-compact goalv-away">
                    <img src="<?php echo esc_url($renderer->get_team_logo($match->away_team_logo, $match->away_team)); ?>"
                        alt="<?php echo esc_attr($match->away_team); ?>" class="goalv-team-logo-small"
                        onerror="this.src='<?php echo esc_url(GOALV_PLUGIN_URL . 'assets/images/default-team-logo.png'); ?>'">
                    <span class="goalv-team-name-short"><?php echo esc_html($match->away_team); ?></span>
                </div>
            </div>

            <!-- Match Info -->
            <div class="goalv-grid-match-info">
                <div class="goalv-grid-status">
                    <?php echo $renderer->get_status_display($match->status, $match->home_score, $match->away_score); ?>
                </div>
                <div class="goalv-grid-date">
                    <?php echo esc_html($renderer->format_match_date($match->match_date)); ?>
                </div>
            </div>

            <!-- Voting Buttons -->
            <?php if ($renderer->can_vote_on_match($match)): ?>
                <div class="goalv-grid-voting goalv-voting-section" data-match-id="<?php echo esc_attr($match->id); ?>">
                    <?php foreach ($match->vote_options as $option): ?>
                        <?php
                        $is_selected = in_array($option->id, $match->user_votes);
                        $result = isset($match->vote_results[$option->id]) ? $match->vote_results[$option->id] : array('percentage' => 0, 'votes_count' => 0);
                        ?>
                        <button type="button" 
                                class="goalv-grid-vote-btn goalv-vote-btn <?php echo $is_selected ? 'selected' : ''; ?>"
                                data-option-id="<?php echo esc_attr($option->id); ?>" 
                                data-match-id="<?php echo esc_attr($match->id); ?>"
                                data-location="homepage" 
                                <?php echo $is_selected ? 'data-selected="true"' : ''; ?>>
                            <span class="goalv-grid-option-text"><?php echo esc_html($option->option_text); ?></span>
                            <span class="goalv-grid-vote-stats">
                                <span class="goalv-grid-percentage goalv-percentage"><?php echo esc_html($result['percentage']); ?>%</span>
                                <span class="goalv-grid-vote-count">(<?php echo esc_html($result['votes_count']); ?>)</span>
                            </span>
                        </button>
                    <?php endforeach; ?>
                    <div class="goalv-grid-vote-status goalv-vote-status"></div>
                </div>
            <?php else: ?>
                <!-- Final Results -->
                <div class="goalv-grid-final-results">
                    <?php foreach ($match->vote_results as $result): ?>
                        <div class="goalv-grid-result-item">
                            <span class="goalv-grid-result-text"><?php echo esc_html($result['option_text']); ?></span>
                            <span class="goalv-grid-result-percentage"><?php echo esc_html($result['percentage']); ?>%</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Details Link -->
            <div class="goalv-grid-footer">
                <a href="<?php echo esc_url($renderer->get_match_permalink($match->id)); ?>" class="goalv-grid-details-link">
                    <?php _e('More Match Voting', 'goalv'); ?>
                </a>
            </div>

        </div>
        <?php
    }

    if ($current_week !== '') {
        echo '</div>';
        if ($show_headers) {
            echo '</div>';
        }
    }
} else {
    ?>
    <div class="goalv-no-matches goalv-grid-no-matches">
        <div class="goalv-no-matches-content">
            <div class="goalv-no-matches-icon">⚽</div>
            <h3><?php _e('No Matches Available', 'goalv'); ?></h3>
            <p><?php _e('Check back soon for upcoming matches!', 'goalv'); ?></p>
        </div>
    </div>
    <?php
}

echo '</div>';