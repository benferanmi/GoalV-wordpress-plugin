<?php
/**
 * Single Match Template with Grouped Voting
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

$frontend = new GoalV_Frontend();
$match = $frontend->get_single_match_data(get_the_ID());

// //comment out start 
// echo '<pre style="background: #f9f9f9; padding: 10px; margin: 10px 0;">';
// echo 'Debug Match Data:' . "\n";
// echo 'Vote Options: ' . (isset($match->vote_options) ? count($match->vote_options) : 'NONE') . "\n";
// echo 'Vote Options Grouped: ' . (isset($match->vote_options_grouped) ? 'YES' : 'NO') . "\n";
// if (isset($match->vote_options)) {
//     echo 'First 3 Options:' . "\n";
//     foreach (array_slice($match->vote_options, 0, 3) as $option) {
//         echo "- ID: {$option->id}, Text: {$option->option_text}, Category: " . (isset($option->category) ? $option->category : 'NULL') . "\n";
//     }
// }
// echo '</pre>';

// //end

if (!$match) {
    echo '<div class="goalv-error">' . __('Match not found.', 'goalv') . '</div>';
    get_footer();
    return;
}
?>

<div class="goalv-single-match-container">
    <div class="goalv-single-match-wrapper">
        
        <!-- Match Header -->
        <div class="goalv-match-header">
            <div class="goalv-competition">
                <span class="goalv-competition-name"><?php echo esc_html($match->competition); ?></span>
            </div>
            
            <div class="goalv-teams-display">
                <div class="goalv-team goalv-home-team">
                    <div class="goalv-team-logo">
                        <img src="<?php echo esc_url($frontend->get_team_logo($match->home_team_logo, $match->home_team)); ?>" 
                             alt="<?php echo esc_attr($match->home_team); ?>" 
                             onerror="this.src='<?php echo esc_url(GOALV_PLUGIN_URL . 'assets/images/default-team-logo.png'); ?>'">
                    </div>
                    <div class="goalv-team-name"><?php echo esc_html($match->home_team); ?></div>
                </div>
                
                <div class="goalv-match-info">
                    <div class="goalv-match-score">
                        <?php if ($match->match_status === 'finished' || $match->match_status === 'live'): ?>
                            <span class="goalv-score"><?php echo esc_html($match->home_score . ' - ' . $match->away_score); ?></span>
                        <?php else: ?>
                            <span class="goalv-vs">VS</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="goalv-match-status">
                        <?php echo $frontend->get_status_display($match->match_status, $match->home_score, $match->away_score); ?>
                    </div>
                    
                    <div class="goalv-match-date">
                        <?php echo esc_html($frontend->format_match_date($match->match_date)); ?>
                    </div>
                </div>
                
                <div class="goalv-team goalv-away-team">
                    <div class="goalv-team-logo">
                        <img src="<?php echo esc_url($frontend->get_team_logo($match->away_team_logo, $match->away_team)); ?>" 
                             alt="<?php echo esc_attr($match->away_team); ?>"
                             onerror="this.src='<?php echo esc_url(GOALV_PLUGIN_URL . 'assets/images/default-team-logo.png'); ?>'">
                    </div>
                    <div class="goalv-team-name"><?php echo esc_html($match->away_team); ?></div>
                </div>
            </div>
        </div>
        
        <!-- Voting Section -->
        <div class="goalv-voting-section">
            <h2><?php _e('Make Your Predictions', 'goalv'); ?></h2>
            
            <?php if (!is_user_logged_in()): ?>
                <div class="goalv-login-notice">
                    <p><?php _e('Please log in to access detailed predictions and save your votes.', 'goalv'); ?></p>
                    <a href="<?php echo wp_login_url(get_permalink()); ?>" class="goalv-login-button">
                        <?php _e('Login to Vote', 'goalv'); ?>
                    </a>
                </div>
            <?php else: ?>
                
                <?php if ($match->match_status === 'finished'): ?>
                    <div class="goalv-voting-closed">
                        <p><?php _e('Voting is closed. This match has finished.', 'goalv'); ?></p>
                    </div>
                <?php else: ?>
                    
                    <div class="goalv-detailed-voting" data-match-id="<?php echo esc_attr($match->ID); ?>">
                        
                        <?php if (isset($match->vote_options_grouped) && !empty($match->vote_options_grouped)): ?>
                            
                            <?php foreach ($match->vote_options_grouped as $category_key => $category_data): ?>
                                <div class="goalv-voting-group" data-category="<?php echo esc_attr($category_key); ?>">
                                    <h3 class="goalv-group-title"><?php echo esc_html($category_data['label']); ?></h3>
                                    
                                    <div class="goalv-voting-options goalv-category-<?php echo esc_attr($category_key); ?>">
                                        <?php foreach ($category_data['options'] as $option): ?>
                                            <?php 
                                            $is_selected = ($match->user_vote == $option->id);
                                            $percentage = isset($match->vote_results[$option->id]) ? $match->vote_results[$option->id]['percentage'] : 0;
                                            $votes_count = isset($match->vote_results[$option->id]) ? $match->vote_results[$option->id]['votes_count'] : 0;
                                            ?>
                                            <div class="goalv-vote-option <?php echo $is_selected ? 'selected' : ''; ?>">
                                                <button type="button" 
                                                        class="goalv-vote-btn" 
                                                        data-option-id="<?php echo esc_attr($option->id); ?>"
                                                        data-match-id="<?php echo esc_attr($match->ID); ?>"
                                                        data-location="details"
                                                        data-category="<?php echo esc_attr($category_key); ?>"
                                                        <?php echo $is_selected ? 'data-selected="true"' : ''; ?>>
                                                    <span class="goalv-option-text"><?php echo esc_html($option->option_text); ?></span>
                                                    <span class="goalv-vote-stats">
                                                        <span class="goalv-percentage"><?php echo esc_html($percentage); ?>%</span>
                                                        <span class="goalv-votes-count">(<?php echo esc_html($votes_count); ?> votes)</span>
                                                    </span>
                                                </button>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            
                        <?php else: ?>
                            <!-- Fallback: Show ungrouped options if grouping fails -->
                            <div class="goalv-voting-options">
                                <?php foreach ($match->vote_options as $option): ?>
                                    <?php 
                                    $is_selected = ($match->user_vote == $option->id);
                                    $percentage = isset($match->vote_results[$option->id]) ? $match->vote_results[$option->id]['percentage'] : 0;
                                    $votes_count = isset($match->vote_results[$option->id]) ? $match->vote_results[$option->id]['votes_count'] : 0;
                                    ?>
                                    <div class="goalv-vote-option <?php echo $is_selected ? 'selected' : ''; ?>">
                                        <button type="button" 
                                                class="goalv-vote-btn" 
                                                data-option-id="<?php echo esc_attr($option->id); ?>"
                                                data-match-id="<?php echo esc_attr($match->ID); ?>"
                                                data-location="details"
                                                <?php echo $is_selected ? 'data-selected="true"' : ''; ?>>
                                            <span class="goalv-option-text"><?php echo esc_html($option->option_text); ?></span>
                                            <span class="goalv-vote-stats">
                                                <span class="goalv-percentage"><?php echo esc_html($percentage); ?>%</span>
                                                <span class="goalv-votes-count">(<?php echo esc_html($votes_count); ?> votes)</span>
                                            </span>
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="goalv-voting-info">
                            <p class="goalv-vote-status"></p>
                        </div>
                    </div>
                    
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <!-- Vote Results Summary (Updated for Grouped Display) -->
        <?php if (!empty($match->vote_results)): ?>
            <div class="goalv-results-summary">
                <h3><?php _e('Current Predictions', 'goalv'); ?></h3>
                
                <?php if (isset($match->vote_options_grouped) && !empty($match->vote_options_grouped)): ?>
                    
                    <?php foreach ($match->vote_options_grouped as $category_key => $category_data): ?>
                        <div class="goalv-results-group">
                            <h4 class="goalv-results-group-title"><?php echo esc_html($category_data['label']); ?></h4>
                            <div class="goalv-results-grid">
                                <?php foreach ($category_data['options'] as $option): ?>
                                    <?php if (isset($match->vote_results[$option->id])): ?>
                                        <?php $result = $match->vote_results[$option->id]; ?>
                                        <div class="goalv-result-item">
                                            <div class="goalv-result-text"><?php echo esc_html($result['option_text']); ?></div>
                                            <div class="goalv-result-bar">
                                                <div class="goalv-result-fill" style="width: <?php echo esc_attr($result['percentage']); ?>%"></div>
                                            </div>
                                            <div class="goalv-result-stats">
                                                <span class="goalv-result-percentage"><?php echo esc_html($result['percentage']); ?>%</span>
                                                <span class="goalv-result-count">(<?php echo esc_html($result['votes_count']); ?>)</span>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                <?php else: ?>
                    <!-- Fallback: Show ungrouped results -->
                    <div class="goalv-results-grid">
                        <?php 
                        $total_votes = array_sum(array_column($match->vote_results, 'votes_count'));
                        foreach ($match->vote_results as $result): 
                        ?>
                            <div class="goalv-result-item">
                                <div class="goalv-result-text"><?php echo esc_html($result['option_text']); ?></div>
                                <div class="goalv-result-bar">
                                    <div class="goalv-result-fill" style="width: <?php echo esc_attr($result['percentage']); ?>%"></div>
                                </div>
                                <div class="goalv-result-stats">
                                    <span class="goalv-result-percentage"><?php echo esc_html($result['percentage']); ?>%</span>
                                    <span class="goalv-result-count">(<?php echo esc_html($result['votes_count']); ?>)</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <div class="goalv-total-votes">
                    <?php 
                    $total_votes = array_sum(array_column($match->vote_results, 'votes_count'));
                    printf(__('Total Votes: %d', 'goalv'), $total_votes); 
                    ?>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Back to matches -->
        <div class="goalv-navigation">
            <a href="<?php echo home_url(); ?>" class="goalv-back-button">
                <?php _e('← Back to All Matches', 'goalv'); ?>
            </a>
        </div>
        
    </div>
</div>

<?php get_footer(); ?>
<style>
    /* Grouped Voting Styles */
.goalv-voting-group {
    margin-bottom: 2rem;
    background: #f8f9fa;
    border-radius: 8px;
    border: 1px solid #e9ecef;
}

.goalv-group-title {
    font-size: 1.2rem;
    font-weight: 600;
    margin: 0 0 0.5rem 0;
    color: #2c3e50;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid #3498db;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Category-specific styling */
.goalv-category-match_result .goalv-group-title {
    border-bottom-color: #e74c3c;
}

.goalv-category-match_score .goalv-group-title {
    border-bottom-color: #f39c12;
}

.goalv-category-goals_threshold .goalv-group-title {
    border-bottom-color: #27ae60;
}

.goalv-category-both_teams_score .goalv-group-title {
    border-bottom-color: #9b59b6;
}

.goalv-category-first_to_score .goalv-group-title {
    border-bottom-color: #1abc9c;
}

.goalv-category-other .goalv-group-title {
    border-bottom-color: #95a5a6;
}

/* Grouped voting options layout */
.goalv-voting-group .goalv-voting-options {
    display: grid;
    gap: 0.75rem;
}

/* Match Result - 3 columns */
.goalv-category-match_result .goalv-voting-options {
    grid-template-columns: repeat(3, 1fr);
}

/* Match Score - 2 columns for score predictions */
.goalv-category-match_score .goalv-voting-options {
    grid-template-columns: repeat(2, 1fr);
}

/* Goals threshold - 2 columns */
.goalv-category-goals_threshold .goalv-voting-options {
    grid-template-columns: repeat(2, 1fr);
}

/* Both teams score - 2 columns */
.goalv-category-both_teams_score .goalv-voting-options {
    grid-template-columns: repeat(2, 1fr);
}

/* First to score - 2 columns */
.goalv-category-first_to_score .goalv-voting-options {
    grid-template-columns: repeat(2, 1fr);
}

/* Other/Custom - flexible layout */
.goalv-category-other .goalv-voting-options {
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
}

/* Vote option buttons in groups */
.goalv-voting-group .goalv-vote-option {
    margin: 0;
}

.goalv-voting-group .goalv-vote-btn {
    width: 100%;
    min-height: 60px;
    padding: 0.75rem;
    font-size: 0.9rem;
    transition: all 0.2s ease;
}

/* Hover effects by category */
.goalv-category-match_result .goalv-vote-btn:hover {
    border-color: #e74c3c;
    box-shadow: 0 2px 8px rgba(231, 76, 60, 0.2);
}

.goalv-category-match_score .goalv-vote-btn:hover {
    border-color: #f39c12;
    box-shadow: 0 2px 8px rgba(243, 156, 18, 0.2);
}

.goalv-category-goals_threshold .goalv-vote-btn:hover {
    border-color: #27ae60;
    box-shadow: 0 2px 8px rgba(39, 174, 96, 0.2);
}

.goalv-category-both_teams_score .goalv-vote-btn:hover {
    border-color: #9b59b6;
    box-shadow: 0 2px 8px rgba(155, 89, 182, 0.2);
}

.goalv-category-first_to_score .goalv-vote-btn:hover {
    border-color: #1abc9c;
    box-shadow: 0 2px 8px rgba(26, 188, 156, 0.2);
}

/* Results Summary Grouping */
.goalv-results-group {
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #e9ecef;
}

.goalv-results-group:last-child {
    border-bottom: none;
    margin-bottom: 0;
}

.goalv-results-group-title {
    font-size: 1rem;
    font-weight: 600;
    margin: 0 0 0.75rem 0;
    color: #495057;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.goalv-results-group .goalv-results-grid {
    margin-bottom: 0;
}

/* Mobile Responsiveness */
@media (max-width: 768px) {
    .goalv-voting-group {
        padding: 1rem;
        margin-bottom: 1.5rem;
    }
    
    .goalv-group-title {
        font-size: 1.1rem;
    }
    
    /* All categories stack on mobile */
    .goalv-voting-group .goalv-voting-options {
        grid-template-columns: 1fr;
        gap: 0.5rem;
    }
    
    .goalv-voting-group .goalv-vote-btn {
        min-height: 50px;
        font-size: 0.85rem;
    }
}

@media (max-width: 480px) {
    .goalv-voting-group {
        margin-left: -10px;
        margin-right: -10px;
        border-radius: 0;
    }
    
    .goalv-group-title {
        font-size: 1rem;
    }
}

/* Loading states for grouped voting */
.goalv-voting-group.loading {
    opacity: 0.7;
    pointer-events: none;
}

.goalv-voting-group.loading .goalv-vote-btn {
    background: #f8f9fa;
    color: #6c757d;
}

/* Success/Error states */
.goalv-voting-group.success .goalv-group-title {
    color: #28a745;
}

.goalv-voting-group.error .goalv-group-title {
    color: #dc3545;
}
</style>