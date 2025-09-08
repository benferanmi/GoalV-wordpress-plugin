<?php
/**
 * Table Template for Matches - UPDATED WITH DYNAMIC LABELS
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

echo '<div class="goalv-matches-container goalv-table-template">';

if (!empty($matches)) {
    foreach ($matches as $match) {
        // Show week header if enabled and week changes
        if ($show_headers && isset($match->display_week) && $match->display_week !== $current_week) {
            if ($current_week !== '') {
                echo '</div></div>'; // Close previous week table body and section
            }
            
            $current_week = $match->display_week;
            echo '<div class="goalv-week-section">';
            echo '<h3 class="goalv-week-header">' . esc_html($current_week) . ' Matches</h3>';
            
            // Table Header for this week
            echo '<div class="goalv-table-header">';
            echo '<div class="goalv-table-row goalv-header-row">';
            echo '<div class="goalv-col-teams">' . esc_html($labels['teams']) . '</div>';
            echo '<div class="goalv-col-score">' . esc_html($labels['score']) . '</div>';
            echo '<div class="goalv-col-status">' . esc_html($labels['status']) . '</div>';
            echo '<div class="goalv-col-date">' . esc_html($labels['date']) . '</div>';
            echo '<div class="goalv-col-predictions">' . esc_html($labels['predictions']) . '</div>';
            echo '<div class="goalv-col-actions">' . esc_html($labels['details']) . '</div>';
            echo '</div>';
            echo '</div>';
            
            echo '<div class="goalv-table-body">';
        } elseif (!$show_headers && $current_week === '') {
            // Start table for non-header mode
            echo '<div class="goalv-table-header">';
            echo '<div class="goalv-table-row goalv-header-row">';
            echo '<div class="goalv-col-teams">' . esc_html($labels['teams']) . '</div>';
            echo '<div class="goalv-col-score">' . esc_html($labels['score']) . '</div>';
            echo '<div class="goalv-col-status">' . esc_html($labels['status']) . '</div>';
            echo '<div class="goalv-col-date">' . esc_html($labels['date']) . '</div>';
            echo '<div class="goalv-col-predictions">' . esc_html($labels['predictions']) . '</div>';
            echo '<div class="goalv-col-actions">' . esc_html($labels['details']) . '</div>';
            echo '</div>';
            echo '</div>';
            echo '<div class="goalv-table-body">';
            $current_week = 'started';
        }
        
        // Individual match table row
        ?>
        <div class="goalv-table-row goalv-match-row goalv-match-card" data-match-id="<?php echo esc_attr($match->ID); ?>">
            
            <!-- Teams Column (Stacked) -->
            <div class="goalv-col-teams">
                <div class="goalv-teams-stacked">
                    <!-- Home Team (Top) -->
                    <div class="goalv-team-stacked goalv-home-stacked">
                        <img src="<?php echo esc_url($frontend->get_team_logo($match->home_team_logo, $match->home_team)); ?>" 
                             alt="<?php echo esc_attr($match->home_team); ?>" 
                             class="goalv-team-logo-tiny"
                             onerror="this.src='<?php echo esc_url(GOALV_PLUGIN_URL . 'assets/images/default-team-logo.png'); ?>'">
                        <span class="goalv-team-name-stacked"><?php echo esc_html($match->home_team); ?></span>
                    </div>
                    <div class="goalv-team-vs">VS</div>
                    <!-- Away Team (Bottom) -->
                    <div class="goalv-team-stacked goalv-away-stacked">
                        <img src="<?php echo esc_url($frontend->get_team_logo($match->away_team_logo, $match->away_team)); ?>" 
                             alt="<?php echo esc_attr($match->away_team); ?>" 
                             class="goalv-team-logo-tiny"
                             onerror="this.src='<?php echo esc_url(GOALV_PLUGIN_URL . 'assets/images/default-team-logo.png'); ?>'">
                        <span class="goalv-team-name-stacked"><?php echo esc_html($match->away_team); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Score Column -->
            <div class="goalv-col-score">
                <?php if ($match->match_status === 'finished' || $match->match_status === 'live'): ?>
                    <span class="goalv-score-display"><?php echo esc_html($match->home_score . ' - ' . $match->away_score); ?></span>
                <?php else: ?>
                    <span class="goalv-no-score">-</span>
                <?php endif; ?>
            </div>
            
            <!-- Status Column -->
            <div class="goalv-col-status">
                <span class="goalv-status-inline"><?php echo $frontend->get_status_display($match->match_status, $match->home_score, $match->away_score); ?></span>
            </div>
            
            <!-- Date Column -->
            <div class="goalv-col-date">
                <span class="goalv-date-inline"><?php echo esc_html($frontend->format_match_date($match->match_date)); ?></span>
            </div>
            
            <!-- Predictions Column -->
            <div class="goalv-col-predictions">
                <?php if ($match->match_status !== 'finished'): ?>
                    <div class="goalv-voting-inline goalv-voting-section" data-match-id="<?php echo esc_attr($match->ID); ?>">
                        <?php foreach ($match->vote_options as $option): ?>
                            <?php 
                            $is_selected = in_array($option->id, $match->user_votes);
                            $result = isset($match->vote_results[$option->id]) ? $match->vote_results[$option->id] : array('percentage' => 0, 'votes_count' => 0);
                            $percentage = $result['percentage'];
                            $vote_count = $result['votes_count'];
                            ?>
                            <button type="button" 
                                    class="goalv-vote-btn-inline goalv-vote-btn <?php echo $is_selected ? 'selected' : ''; ?>" 
                                    data-option-id="<?php echo esc_attr($option->id); ?>"
                                    data-match-id="<?php echo esc_attr($match->ID); ?>"
                                    data-location="homepage"
                                    title="<?php echo esc_attr($option->option_text); ?>"
                                    <?php echo $is_selected ? 'data-selected="true"' : ''; ?>>
                                <span class="goalv-option-text-inline"><?php echo esc_html($option->option_text); ?></span>
                                <span class="goalv-inline-vote-stats">
                                    <span class="goalv-percentage goalv-percentage-inline"><?php echo esc_html($percentage); ?>%</span>
                                    <span class="goalv-inline-vote-count">(<?php echo esc_html($vote_count); ?>)</span>
                                </span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <!-- Final Results Inline -->
                    <div class="goalv-results-inline">
                        <?php foreach ($match->vote_results as $result): ?>
                            <span class="goalv-result-inline">
                                <span class="goalv-result-text-inline"><?php echo esc_html($result['option_text']); ?></span>
                                <span class="goalv-result-stats-inline">
                                    <span class="goalv-result-percentage-inline"><?php echo esc_html($result['percentage']); ?>%</span>
                                    <span class="goalv-result-count-inline">(<?php echo esc_html($result['votes_count']); ?>)</span>
                                </span>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Actions Column -->
            <div class="goalv-col-actions">
                <a href="<?php echo get_permalink($match->ID); ?>" class="goalv-details-btn-inline">
                    More Match Voting
                </a>
            </div>
            
        </div>
        <?php
    }
    
    // Close final section
    if ($current_week !== '') {
        echo '</div>'; // Close table body
        if ($show_headers) {
            echo '</div>'; // Close week section
        }
    }
} else {
    // No matches message
    ?>
    <div class="goalv-no-matches goalv-table-no-matches">
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


<style>
    
/* //table */
/* Table Template Styles - Won't conflict with existing grid styles */
.goalv-table-template {
    width: 100%;
    margin: 20px 0;
}

.goalv-table-header {
    background: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
    margin-bottom: 10px;
}

.goalv-table-row {
    display: flex;
    align-items: center;
    padding: 8px 0px;
    border-bottom: 1px solid #e9ecef;
    transition: background-color 0.2s ease;
    column-gap: 20px;
}

.goalv-table-row:hover {
    background-color: #484848;
}

.goalv-header-row {
    font-weight: bold;
    background: #e9ecef;
    border-bottom: none;
}

.goalv-header-row:hover {
    background: #e9ecef;
}
.goalv-table-body > :nth-child(even) {
  background-color: #e9ecef;
}
.goalv-table-body > :nth-child(odd) {
  background-color: white ;
}


/* Column Widths */
.goalv-col-teams { flex: 0 0 220px;  }
.goalv-col-score { flex: 0 0 100px; text-align: center; }
.goalv-col-status { flex: 0 0 150px; }
.goalv-col-date { flex: 0 0 150px; }
.goalv-col-predictions { flex: 1; min-width: 200px; }
.goalv-col-actions { flex: 0 0 180px; }

/* Teams Stacked Layout */
.goalv-teams-stacked {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.goalv-team-vs {
    display: none;
}
@media screen and (max-width: 768px) {
    .goalv-teams-stacked {
        flex-direction: row;
        flex-wrap: nowrap;
        gap: 1px;
        justify-content: center;
    }
    .goalv-home-stacked {
        border-bottom: 0 !important;
        padding-bottom: 0 !important;
    }
.goalv-team-vs {
display: block;
    padding: 0 5px;
     white-space: nowrap;
}
.goalv-no-score {
    display: none;
}
.goalv-team-stacked {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 1px;
}
.goalv-team-name-stacked {
    text-align: center;
    font-size: 12px;
}
}

.goalv-team-stacked {
    display: flex;
    align-items: center;
    gap: 8px;
}

.goalv-team-logo-tiny {
    width: 24px;
    height: 24px;
    object-fit: cover;
    border-radius: 2px;
}

.goalv-team-name-stacked {
    font-weight: 500;
    font-size: 14px;
}

.goalv-home-stacked {
    border-bottom: 1px solid #e9ecef;
    padding-bottom: 2px;
}

.goalv-away-stacked {
    padding-top: 2px;
}

/* Score Display */
.goalv-score-display {
    font-weight: bold;
    color: #28a745;
    background: #d4edda;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 14px;
}

.goalv-no-score {
    color: #6c757d;
    font-style: italic;
}

/* Status */
.goalv-status-inline {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: bold;
}

/* Date */
.goalv-date-inline {
    font-size: 14px;
    color: #495057;
}

/* Predictions Inline */
.goalv-voting-inline {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
    padding: 0 !important;
}

.goalv-vote-btn-inline {
    background: #dcdee0;
    border: 1px solid #dee2e677;
    color: black;
    color: black;
    padding: 4px 8px;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 12px;
}

.goalv-vote-btn-inline:hover {
    background: #e9ecef;
    color:black;
    border-color: #adb5bd;
}

.goalv-vote-btn-inline.selected {
    background: #007cba;
    color: white;
    border-color: #007cba;
}

.goalv-option-text-inline {
    margin-right: 4px;
}

.goalv-percentage-inline {
    font-weight: bold;
}

/* Results Inline */
.goalv-results-inline {
    display: flex;
    gap: 4px;
    justify-content: space-between;
    flex-wrap: wrap;
    flex: 1;
}

.goalv-result-inline {
    background: #e9ecef;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    flex-grow: 1;
}

.goalv-result-percentage-inline {
    font-weight: bold;
    margin-left: 4px;
}

/* Details Button */
.goalv-details-btn-inline {
    background: #28a745;
    color: white;
    padding: 6px 12px;
    text-decoration: none;
    border-radius: 4px;
    font-size: 12px;
    transition: background-color 0.2s ease;
}

.goalv-details-btn-inline:hover {
    background: #218838;
    color: white;
    text-decoration: none;
}

/* No Matches */
.goalv-table-no-matches {
    text-align: center;
    padding: 60px 20px;
}

.goalv-no-matches-icon {
    font-size: 48px;
    margin-bottom: 20px;
}

/* Responsive Design */
@media (max-width: 768px) {
    .goalv-table-row {
        flex-direction: column;
        align-items: stretch;
        gap: 10px;
    }
    
    .goalv-col-teams,
    .goalv-col-score,
    .goalv-col-status,
    .goalv-col-date,
    .goalv-col-predictions,
    .goalv-col-actions {
        flex: none;
        width: 100%;
    }
    
    .goalv-header-row {
        display: none;
    }
    
    .goalv-table-row > div:before {
        content: attr(data-label);
        font-weight: bold;
        display: block;
        margin-bottom: 5px;
    }
    
    .goalv-col-teams:before { content: "Teams: "; }
    .goalv-col-score:before { content: "Score: "; }
    .goalv-col-status:before { content: "Status: "; }
    .goalv-col-date:before { content: "Date: "; }
    .goalv-col-predictions:before { content: "Predictions: "; }
    .goalv-col-actions:before { content: "Actions: "; }
}
.goalv-inline-vote-stats {
    display: flex;
    flex-direction: row;
    gap: 2px;
    align-items: center;
    gap: 1px;
}

.goalv-inline-vote-count {
    font-size: 10px;
    color: #666;
    font-weight: normal;
}

.goalv-vote-btn-inline.selected .goalv-inline-vote-count {
    color: #fff;
}
</style>