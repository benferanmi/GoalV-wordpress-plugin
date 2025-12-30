<?php
/**
 * Competition Block - Reusable Partial
 * Renders a single competition with its matches
 * 
 * Required variables:
 * - $competition (string) - Competition name
 * - $comp_matches (array) - Array of match objects
 * - $renderer (GoalV_Match_Renderer) - Renderer instance
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="betfair-competition-block">
    
    <!-- Competition Header -->
    <div class="comp-header-row">
        <div class="comp-title"><?php echo esc_html($competition); ?></div>
        <div class="comp-columns-desktop">
            <span class="col-header col-score">Score</span>
            <span class="col-header col-outcome">1</span>
            <span class="col-header col-outcome">X</span>
            <span class="col-header col-outcome">2</span>
            <span class="col-header col-volume">Volume</span>
        </div>
    </div>

    <!-- Matches Table -->
    <div class="matches-table">
        <?php foreach ($comp_matches as $match):
            // Find basic vote options (Home/Draw/Away)
            $home_win = null;
            $draw = null;
            $away_win = null;

            if (!empty($match->vote_options)) {
                foreach ($match->vote_options as $option) {
                    $text = strtolower($option->option_text);
                    if (stripos($text, 'home') !== false || stripos($text, '1') === 0) {
                        $home_win = $option;
                    } elseif (stripos($text, 'draw') !== false || stripos($text, 'x') === 0) {
                        $draw = $option;
                    } elseif (stripos($text, 'away') !== false || stripos($text, '2') === 0) {
                        $away_win = $option;
                    }
                }
            }

            // Calculate vote results
            $home_result = isset($match->vote_results[$home_win->id]) ? $match->vote_results[$home_win->id] : null;
            $draw_result = isset($match->vote_results[$draw->id]) ? $match->vote_results[$draw->id] : null;
            $away_result = isset($match->vote_results[$away_win->id]) ? $match->vote_results[$away_win->id] : null;

            $home_percentage = $home_result ? $home_result['percentage'] : 0;
            $draw_percentage = $draw_result ? $draw_result['percentage'] : 0;
            $away_percentage = $away_result ? $away_result['percentage'] : 0;

            $home_odds = $home_percentage > 0 ? number_format(100 / $home_percentage, 2) : '-';
            $draw_odds = $draw_percentage > 0 ? number_format(100 / $draw_percentage, 2) : '-';
            $away_odds = $away_percentage > 0 ? number_format(100 / $away_percentage, 2) : '-';

            $home_votes = $home_result ? $home_result['votes_count'] : 0;
            $draw_votes = $draw_result ? $draw_result['votes_count'] : 0;
            $away_votes = $away_result ? $away_result['votes_count'] : 0;
            $total_votes = $home_votes + $draw_votes + $away_votes;

            // Match state
            $can_vote = $renderer->can_vote_on_match($match);
            $is_finished = $match->status === 'finished';

            // User selections
            $home_selected = $home_win && in_array($home_win->id, $match->user_votes);
            $draw_selected = $draw && in_array($draw->id, $match->user_votes);
            $away_selected = $away_win && in_array($away_win->id, $match->user_votes);

            // Truncate team names
            $home_team_short = strlen($match->home_team) > 18 ? substr($match->home_team, 0, 15) . '...' : $match->home_team;
            $away_team_short = strlen($match->away_team) > 18 ? substr($match->away_team, 0, 15) . '...' : $match->away_team;

            // Match URL
            $match_url = $renderer->get_match_permalink($match->id);
            ?>

            <div class="match-row-exchange <?php echo $is_finished ? 'match-finished' : ''; ?>" 
                 data-match-id="<?php echo esc_attr($match->id); ?>" 
                 data-status="<?php echo esc_attr($match->status); ?>">
                
                <!-- TEAMS COLUMN -->
                <div class="col-teams">
                    <a href="<?php echo esc_url($match_url); ?>" class="teams-link" title="View match details">
                        <div class="team-stack">
                            <div class="team-line">
                                <img src="<?php echo esc_url($renderer->get_team_logo($match->home_team_logo, $match->home_team)); ?>" 
                                     alt="" class="team-logo-tiny" onerror="this.style.display='none'">
                                <span class="team-name" title="<?php echo esc_attr($match->home_team); ?>">
                                    <?php echo esc_html($home_team_short); ?>
                                </span>
                            </div>
                            <div class="team-line">
                                <img src="<?php echo esc_url($renderer->get_team_logo($match->away_team_logo, $match->away_team)); ?>" 
                                     alt="" class="team-logo-tiny" onerror="this.style.display='none'">
                                <span class="team-name" title="<?php echo esc_attr($match->away_team); ?>">
                                    <?php echo esc_html($away_team_short); ?>
                                </span>
                            </div>
                        </div>
                    </a>

                    <div class="match-time-mobile">
                        <?php if (in_array($match->status, array('live', 'finished', 'paused'))): ?>
                            <span class="score-mobile"><?php echo esc_html($match->home_score . '-' . $match->away_score); ?></span>
                        <?php endif; ?>

                        <?php if ($match->status === 'live'): ?>
                            <span class="live-indicator"><?php echo esc_html($match->match_minute ?? ''); ?>'</span>
                        <?php elseif ($match->status === 'finished'): ?>
                            <span class="ft-indicator">FT</span>
                        <?php else: ?>
                            <span class="time-indicator"><?php echo esc_html(date('g:i A', strtotime($match->match_date))); ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-right-details">
                    
                    <!-- SCORE COLUMN -->
                    <div class="col-score">
                        <?php if (in_array($match->status, array('live', 'finished', 'paused'))): ?>
                            <span class="score-display"><?php echo esc_html($match->home_score . '-' . $match->away_score); ?></span>
                        <?php else: ?>
                            <span class="score-empty">–</span>
                        <?php endif; ?>

                        <?php if ($match->status === 'live'): ?>
                            <span class="live-badge-score"><?php echo esc_html($match->match_minute ?? ''); ?>'</span>
                        <?php elseif ($match->status === 'finished'): ?>
                            <span class="ft-badge-score">FT</span>
                        <?php else: ?>
                            <span class="time-badge-score"><?php echo esc_html(date('g:i A', strtotime($match->match_date))); ?></span>
                        <?php endif; ?>
                    </div>

                    <!-- HOME (1) -->
                    <div class="col-outcome">
                        <button class="odds-btn-mobile goalv-vote-btn <?php echo $home_selected ? 'selected' : ''; ?>" 
                                data-match-id="<?php echo esc_attr($match->id); ?>" 
                                data-option-id="<?php echo esc_attr($home_win->id ?? ''); ?>" 
                                data-location="homepage" 
                                <?php echo !$can_vote || $is_finished ? 'disabled' : ''; ?>>
                            <span class="odds-value"><?php echo esc_html($home_odds); ?></span>
                        </button>

                        <div class="odds-desktop-split">
                            <button class="odds-back goalv-vote-btn <?php echo $home_selected ? 'selected' : ''; ?>" 
                                    data-match-id="<?php echo esc_attr($match->id); ?>" 
                                    data-option-id="<?php echo esc_attr($home_win->id ?? ''); ?>" 
                                    data-location="homepage" 
                                    title="Back Home" 
                                    <?php echo !$can_vote || $is_finished ? 'disabled' : ''; ?>>
                                <span class="odds-price"><?php echo esc_html($home_odds); ?></span>
                                <span class="odds-vol"><?php echo esc_html($home_votes); ?></span>
                            </button>
                            <button class="odds-lay" title="Lay Home" <?php echo !$can_vote || $is_finished ? 'disabled' : ''; ?>>
                                <span class="odds-price"><?php echo esc_html($home_percentage); ?>%</span>
                                <span class="odds-vol"><?php echo esc_html($home_votes); ?></span>
                            </button>
                        </div>
                    </div>

                    <!-- DRAW (X) -->
                    <div class="col-outcome">
                        <button class="odds-btn-mobile goalv-vote-btn <?php echo $draw_selected ? 'selected' : ''; ?>" 
                                data-match-id="<?php echo esc_attr($match->id); ?>" 
                                data-option-id="<?php echo esc_attr($draw->id ?? ''); ?>" 
                                data-location="homepage" 
                                <?php echo !$can_vote || $is_finished ? 'disabled' : ''; ?>>
                            <span class="odds-value"><?php echo esc_html($draw_odds); ?></span>
                        </button>

                        <div class="odds-desktop-split">
                            <button class="odds-back goalv-vote-btn <?php echo $draw_selected ? 'selected' : ''; ?>" 
                                    data-match-id="<?php echo esc_attr($match->id); ?>" 
                                    data-option-id="<?php echo esc_attr($draw->id ?? ''); ?>" 
                                    data-location="homepage" 
                                    title="Back Draw" 
                                    <?php echo !$can_vote || $is_finished ? 'disabled' : ''; ?>>
                                <span class="odds-price"><?php echo esc_html($draw_odds); ?></span>
                                <span class="odds-vol"><?php echo esc_html($draw_votes); ?></span>
                            </button>
                            <button class="odds-lay" title="Lay Draw" <?php echo !$can_vote || $is_finished ? 'disabled' : ''; ?>>
                                <span class="odds-price"><?php echo esc_html($draw_percentage); ?>%</span>
                                <span class="odds-vol"><?php echo esc_html($draw_votes); ?></span>
                            </button>
                        </div>
                    </div>

                    <!-- AWAY (2) -->
                    <div class="col-outcome">
                        <button class="odds-btn-mobile goalv-vote-btn <?php echo $away_selected ? 'selected' : ''; ?>" 
                                data-match-id="<?php echo esc_attr($match->id); ?>" 
                                data-option-id="<?php echo esc_attr($away_win->id ?? ''); ?>" 
                                data-location="homepage" 
                                <?php echo !$can_vote || $is_finished ? 'disabled' : ''; ?>>
                            <span class="odds-value"><?php echo esc_html($away_odds); ?></span>
                        </button>

                        <div class="odds-desktop-split">
                            <button class="odds-back goalv-vote-btn <?php echo $away_selected ? 'selected' : ''; ?>" 
                                    data-match-id="<?php echo esc_attr($match->id); ?>" 
                                    data-option-id="<?php echo esc_attr($away_win->id ?? ''); ?>" 
                                    data-location="homepage" 
                                    title="Back Away" 
                                    <?php echo !$can_vote || $is_finished ? 'disabled' : ''; ?>>
                                <span class="odds-price"><?php echo esc_html($away_odds); ?></span>
                                <span class="odds-vol"><?php echo esc_html($away_votes); ?></span>
                            </button>
                            <button class="odds-lay" title="Lay Away" <?php echo !$can_vote || $is_finished ? 'disabled' : ''; ?>>
                                <span class="odds-price"><?php echo esc_html($away_percentage); ?>%</span>
                                <span class="odds-vol"><?php echo esc_html($away_votes); ?></span>
                            </button>
                        </div>
                    </div>

                    <!-- VOLUME -->
                    <div class="col-volume">
                        <span class="total-votes"><?php echo esc_html(number_format($total_votes)); ?></span>
                        <span class="volume-label">votes</span>
                    </div>

                </div>
            </div>

        <?php endforeach; ?>
    </div>

</div>