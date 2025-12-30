
<?php
/**
 * Betfair Exchange UI - Daily Matches Template with Date Navigation
 * 
 * FIXED VERSION - Uses passed $matches array from shortcode
 * No direct database queries needed
 * 
 * Features:
 * - Horizontal date slider (Today + next 7 days)
 * - Simple URL-based date switching
 * - Grouped by competition
 * - Mobile responsive
 * 
 * Usage: [goalv_matches template="betfairdaily"] or include in page template
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get current date from query or default to today
$selected_date = isset($_GET['match_date']) ? sanitize_text_field($_GET['match_date']) : date('Y-m-d');

// Get current page URL for date links
$current_url = add_query_arg(array(), home_url($_SERVER['REQUEST_URI']));
$base_url = remove_query_arg('match_date', $current_url);

// Generate 8 days array (today + next 7 days)
$dates = array();
for ($i = 0; $i < 8; $i++) {
    $date = date('Y-m-d', strtotime("+{$i} days"));
    $dates[] = array(
        'date' => $date,
        'day_name' => date('D', strtotime($date)),
        'day_num' => date('j', strtotime($date)),
        'month' => date('M', strtotime($date)),
        'is_today' => ($i === 0),
        'is_selected' => ($date === $selected_date),
        'url' => add_query_arg('match_date', $date, $base_url)
    );
}

// Filter matches by selected date (matches already come from shortcode with all data)
$filtered_matches = array_filter($matches, function ($match) use ($selected_date) {
    return date('Y-m-d', strtotime($match->match_date)) === $selected_date 
           && !in_array($match->status, array('cancelled', 'postponed'));
});

// Instantiate frontend for helper methods
$frontend = new GoalV_Frontend();
$renderer = $frontend->get_renderer();
?>

<div class="betfair-daily-wrapper">

    <!-- Date Navigation Bar -->
    <div class="betfair-date-navigation">
        <button class="date-nav-arrow date-nav-prev" id="scrollDatesLeft" aria-label="Scroll left">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                <path
                    d="M12.707 14.707a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 1.414L9.414 10l3.293 3.293a1 1 0 010 1.414z" />
            </svg>
        </button>

        <div class="date-slider-container">
            <div class="date-slider" id="dateSlider">
                <?php foreach ($dates as $date_info): ?>
                    <a href="<?php echo esc_url($date_info['url']); ?>"
                        class="date-card <?php echo $date_info['is_today'] ? 'date-today' : ''; ?> <?php echo $date_info['is_selected'] ? 'date-selected' : ''; ?>"
                        data-date="<?php echo esc_attr($date_info['date']); ?>">

                        <?php if ($date_info['is_today']): ?>
                            <span class="date-label-today">TODAY</span>
                        <?php endif; ?>

                        <span class="date-day-name"><?php echo esc_html($date_info['day_name']); ?></span>
                        <span class="date-day-num"><?php echo esc_html($date_info['day_num']); ?></span>
                        <span class="date-month"><?php echo esc_html($date_info['month']); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <button class="date-nav-arrow date-nav-next" id="scrollDatesRight" aria-label="Scroll right">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                <path
                    d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" />
            </svg>
        </button>
    </div>

    <!-- Matches Container -->
    <div class="matches-container" id="matchesContainer">
        <?php
        // Display matches - already enhanced with vote data from shortcode
        if (!empty($filtered_matches)) {
            echo '<div class="betfair-exchange-wrapper">';

            // Group by competition
            $matches_by_comp = array();
            foreach ($filtered_matches as $match) {
                // Get competition name - it's already in $match object from the query
                $comp = !empty($match->competition) ? $match->competition : __('Other', 'goalv');
                if (!isset($matches_by_comp[$comp])) {
                    $matches_by_comp[$comp] = array();
                }
                $matches_by_comp[$comp][] = $match;
            }

            foreach ($matches_by_comp as $competition => $comp_matches):
                ?>
                <div class="betfair-competition-block">
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

                    <div class="matches-table">
                        <?php foreach ($comp_matches as $match):
                            // Find basic vote options
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

                            // Calculate results
                            $home_result = isset($match->vote_results[$home_win->id ?? 0]) ? $match->vote_results[$home_win->id] : null;
                            $draw_result = isset($match->vote_results[$draw->id ?? 0]) ? $match->vote_results[$draw->id] : null;
                            $away_result = isset($match->vote_results[$away_win->id ?? 0]) ? $match->vote_results[$away_win->id] : null;

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

                            $can_vote = $renderer->can_vote_on_match($match);
                            $is_finished = $match->status === 'finished';

                            $home_selected = $home_win && in_array($home_win->id, $match->user_votes ?? array());
                            $draw_selected = $draw && in_array($draw->id, $match->user_votes ?? array());
                            $away_selected = $away_win && in_array($away_win->id, $match->user_votes ?? array());

                            $home_team_short = strlen($match->home_team) > 18 ? substr($match->home_team, 0, 15) . '...' : $match->home_team;
                            $away_team_short = strlen($match->away_team) > 18 ? substr($match->away_team, 0, 15) . '...' : $match->away_team;

                            $match_url = $renderer->get_match_permalink($match->id);
                            ?>

                            <div class="match-row-exchange <?php echo $is_finished ? 'match-finished' : ''; ?>"
                                data-match-id="<?php echo esc_attr($match->id); ?>"
                                data-status="<?php echo esc_attr($match->status); ?>">

                                <div class="col-teams">
                                    <a href="<?php echo esc_url($match_url); ?>" class="teams-link" title="View full match details">
                                        <div class="team-stack">
                                            <div class="team-line">
                                                <img src="<?php echo esc_url($renderer->get_team_logo($match->home_team_logo, $match->home_team)); ?>"
                                                    alt="" class="team-logo-tiny" onerror="this.style.display='none'">
                                                <span class="team-name"
                                                    title="<?php echo esc_attr($match->home_team); ?>"><?php echo esc_html($home_team_short); ?></span>
                                            </div>
                                            <div class="team-line">
                                                <img src="<?php echo esc_url($renderer->get_team_logo($match->away_team_logo, $match->away_team)); ?>"
                                                    alt="" class="team-logo-tiny" onerror="this.style.display='none'">
                                                <span class="team-name"
                                                    title="<?php echo esc_attr($match->away_team); ?>"><?php echo esc_html($away_team_short); ?></span>
                                            </div>
                                        </div>
                                    </a>

                                    <div class="match-time-mobile">
                                        <?php if (in_array($match->status, array('live', 'finished', 'paused'))): ?>
                                            <span
                                                class="score-mobile"><?php echo esc_html($match->home_score . '-' . $match->away_score); ?></span>
                                        <?php endif; ?>

                                        <?php if ($match->status === 'live'): ?>
                                            <span class="live-indicator"><?php echo esc_html($match->match_minute ?? ''); ?>'</span>
                                        <?php elseif ($match->status === 'finished'): ?>
                                            <span class="ft-indicator">FT</span>
                                        <?php else: ?>
                                            <span
                                                class="time-indicator"><?php echo esc_html(date('g:i A', strtotime($match->match_date))); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="col-right-details">
                                    <div class="col-score">
                                        <?php if (in_array($match->status, array('live', 'finished', 'paused'))): ?>
                                            <span
                                                class="score-display"><?php echo esc_html($match->home_score . '-' . $match->away_score); ?></span>
                                        <?php else: ?>
                                            <span class="score-empty">–</span>
                                        <?php endif; ?>

                                        <?php if ($match->status === 'live'): ?>
                                            <span class="live-badge-score"><?php echo esc_html($match->match_minute ?? ''); ?>'</span>
                                        <?php elseif ($match->status === 'finished'): ?>
                                            <span class="ft-badge-score">FT</span>
                                        <?php else: ?>
                                            <span
                                                class="time-badge-score"><?php echo esc_html(date('g:i A', strtotime($match->match_date))); ?></span>
                                        <?php endif; ?>
                                    </div>

                                    <!-- HOME (1) -->
                                    <div class="col-outcome">
                                        <button
                                            class="odds-btn-mobile goalv-vote-btn <?php echo $home_selected ? 'selected' : ''; ?>"
                                            data-match-id="<?php echo esc_attr($match->id); ?>"
                                            data-option-id="<?php echo esc_attr($home_win->id ?? ''); ?>" data-location="homepage"
                                            <?php echo !$can_vote || $is_finished ? 'disabled' : ''; ?>>
                                            <span class="odds-value"><?php echo esc_html($home_odds); ?></span>
                                        </button>

                                        <div class="odds-desktop-split">
                                            <button class="odds-back goalv-vote-btn <?php echo $home_selected ? 'selected' : ''; ?>"
                                                data-match-id="<?php echo esc_attr($match->id); ?>"
                                                data-option-id="<?php echo esc_attr($home_win->id ?? ''); ?>"
                                                data-location="homepage" title="Back Home" <?php echo !$can_vote || $is_finished ? 'disabled' : ''; ?>>
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
                                        <button
                                            class="odds-btn-mobile goalv-vote-btn <?php echo $draw_selected ? 'selected' : ''; ?>"
                                            data-match-id="<?php echo esc_attr($match->id); ?>"
                                            data-option-id="<?php echo esc_attr($draw->id ?? ''); ?>" data-location="homepage" <?php echo !$can_vote || $is_finished ? 'disabled' : ''; ?>>
                                            <span class="odds-value"><?php echo esc_html($draw_odds); ?></span>
                                        </button>

                                        <div class="odds-desktop-split">
                                            <button class="odds-back goalv-vote-btn <?php echo $draw_selected ? 'selected' : ''; ?>"
                                                data-match-id="<?php echo esc_attr($match->id); ?>"
                                                data-option-id="<?php echo esc_attr($draw->id ?? ''); ?>" data-location="homepage"
                                                title="Back Draw" <?php echo !$can_vote || $is_finished ? 'disabled' : ''; ?>>
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
                                        <button
                                            class="odds-btn-mobile goalv-vote-btn <?php echo $away_selected ? 'selected' : ''; ?>"
                                            data-match-id="<?php echo esc_attr($match->id); ?>"
                                            data-option-id="<?php echo esc_attr($away_win->id ?? ''); ?>" data-location="homepage"
                                            <?php echo !$can_vote || $is_finished ? 'disabled' : ''; ?>>
                                            <span class="odds-value"><?php echo esc_html($away_odds); ?></span>
                                        </button>

                                        <div class="odds-desktop-split">
                                            <button class="odds-back goalv-vote-btn <?php echo $away_selected ? 'selected' : ''; ?>"
                                                data-match-id="<?php echo esc_attr($match->id); ?>"
                                                data-option-id="<?php echo esc_attr($away_win->id ?? ''); ?>"
                                                data-location="homepage" title="Back Away" <?php echo !$can_vote || $is_finished ? 'disabled' : ''; ?>>
                                                <span class="odds-price"><?php echo esc_html($away_odds); ?></span>
                                                <span class="odds-vol"><?php echo esc_html($away_votes); ?></span>
                                            </button>
                                            <button class="odds-lay" title="Lay Away" <?php echo !$can_vote || $is_finished ? 'disabled' : ''; ?>>
                                                <span class="odds-price"><?php echo esc_html($away_percentage); ?>%</span>
                                                <span class="odds-vol"><?php echo esc_html($away_votes); ?></span>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="col-volume">
                                        <span class="total-votes"><?php echo esc_html(number_format($total_votes)); ?></span>
                                        <span class="volume-label">votes</span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php
            endforeach;

            echo '</div>';

        } else {
            ?>
            <div class="betfair-no-matches">
                <svg width="64" height="64" viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="32" cy="32" r="30" />
                    <path d="M32 16v16M32 40h.01" />
                </svg>
                <p class="no-matches-title">No matches scheduled</p>
                <p class="no-matches-subtitle">for <?php echo date('l, F j, Y', strtotime($selected_date)); ?></p>
            </div>
            <?php
        }
        ?>
    </div>

</div>

<style>
    /* ======================================== ALL STYLES FROM ORIGINAL ======================================== */
    .betfair-daily-wrapper {
        background: #f7f7f7;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        margin: 0;
        padding: 0;
    }

    .betfair-date-navigation {
        display: flex;
        align-items: center;
        background: #1a1a1a;
        padding: 12px 8px;
        gap: 8px;
        position: sticky;
        top: 0;
        z-index: 100;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .date-nav-arrow {
        background: rgba(255, 255, 255, 0.1);
        border: none;
        color: #fff;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s ease;
        flex-shrink: 0;
    }

    .date-nav-arrow:hover {
        background: rgba(255, 255, 255, 0.2);
        transform: scale(1.05);
    }

    .date-nav-arrow:active {
        transform: scale(0.95);
    }

    .date-slider-container {
        flex: 1;
        overflow: hidden;
        position: relative;
    }

    .date-slider {
        display: flex;
        gap: 8px;
        overflow-x: auto;
        scroll-behavior: smooth;
        scrollbar-width: none;
        -ms-overflow-style: none;
        padding: 2px 0;
    }

    .date-slider::-webkit-scrollbar {
        display: none;
    }

    .date-card {
        background: rgba(255, 255, 255, 0.1);
        border: 2px solid transparent;
        border-radius: 8px;
        padding: 8px 12px;
        min-width: 70px;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 2px;
        cursor: pointer;
        transition: all 0.2s ease;
        color: #fff;
        font-weight: 600;
        position: relative;
        text-decoration: none;
    }

    .date-card:hover {
        background: rgba(255, 255, 255, 0.15);
        transform: translateY(-2px);
    }

    .date-card.date-selected {
        background: #FFB80C;
        color: #1a1a1a;
        border-color: #FFB80C;
        box-shadow: 0 4px 12px rgba(255, 184, 12, 0.4);
    }

    .date-card.date-today:not(.date-selected) {
        border-color: #FFB80C;
    }

    .date-label-today {
        font-size: 8px;
        font-weight: 800;
        letter-spacing: 0.5px;
        color: #FFB80C;
        text-transform: uppercase;
    }

    .date-card.date-selected .date-label-today {
        color: #1a1a1a;
    }

    .date-day-name {
        font-size: 11px;
        text-transform: uppercase;
        opacity: 0.8;
    }

    .date-day-num {
        font-size: 20px;
        font-weight: 700;
        line-height: 1;
    }

    .date-month {
        font-size: 10px;
        opacity: 0.7;
        text-transform: uppercase;
    }

    .betfair-no-matches {
        padding: 80px 20px;
        text-align: center;
        background: #fff;
        margin: 16px;
        border-radius: 8px;
        color: #999;
    }

    .betfair-no-matches svg {
        margin-bottom: 16px;
        opacity: 0.3;
    }

    .no-matches-title {
        font-size: 18px;
        font-weight: 600;
        color: #666;
        margin: 8px 0 4px 0;
    }

    .no-matches-subtitle {
        font-size: 14px;
        color: #999;
        margin: 0;
    }

    .matches-container {
        padding: 16px 0;
        min-height: 400px;
    }

    @media (max-width: 480px) {
        .betfair-date-navigation {
            padding: 8px 4px;
        }

        .date-nav-arrow {
            width: 36px;
            height: 36px;
        }

        .date-card {
            min-width: 60px;
            padding: 6px 10px;
        }

        .date-day-num {
            font-size: 18px;
        }
    }

    @media (min-width: 768px) {
        .betfair-date-navigation {
            padding: 16px 12px;
        }

        .date-card {
            min-width: 80px;
            padding: 12px 16px;
        }

        .date-day-num {
            font-size: 24px;
        }
    }

    /* ======================================== BETFAIR EXCHANGE STYLES (FROM MATCHES-BETFAIR.PHP) ======================================== */
    .betfair-exchange-wrapper {
        background: #f7f7f7;
        color: #1a1a1a;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        font-size: 12px;
        margin: 0;
        padding: 0;
        max-width: 100%;
    }

    .betfair-competition-block {
        background: #fff;
        margin-bottom: 16px;
        border: 1px solid #e0e0e0;
    }

    .comp-header-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #FFB80C;
        padding: 10px 12px;
        border-bottom: 2px solid #e0a000;
    }

    .comp-title {
        font-weight: 700;
        font-size: 13px;
        color: #1a1a1a;
        flex: 1;
    }

    .comp-columns-desktop {
        display: none;
        gap: 8px;
        font-size: 10px;
        font-weight: 600;
        color: #1a1a1a;
        text-transform: uppercase;
    }

    .col-header {
        text-align: center;
    }

    .col-header.col-score {
        width: 60px;
    }

    .col-header.col-outcome {
        width: 100px;
    }

    .col-header.col-volume {
        width: 80px;
    }

    .matches-table {
        display: flex;
        flex-direction: column;
    }

    .match-row-exchange {
        display: flex;
        align-items: stretch;
        border-bottom: 1px solid #e8e8e8;
        background: #fff;
        transition: background 0.12s ease;
        max-height: 60px;
        justify-content: space-between;
    }

    .match-row-exchange:hover {
        background: #fafafa;
    }

    .match-row-exchange.match-finished {
        opacity: 0.5;
        pointer-events: none;
    }

    .col-right-details {
        display: flex;
    }

    .col-teams {
        flex: 1;
        min-width: 140px;
        max-width: 200px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        padding: 8px 12px;
        gap: 4px;
    }

    .team-stack {
        display: flex;
        flex-direction: column;
        gap: 3px;
    }

    .team-line {
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .team-logo-tiny {
        width: 16px;
        height: 16px;
        object-fit: contain;
        flex-shrink: 0;
    }

    .team-name {
        font-weight: 500;
        font-size: 11px;
        color: #1a1a1a;
        line-height: 1.2;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .match-time-mobile {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 10px;
        font-weight: 600;
        margin-top: 2px;
    }

    .score-mobile {
        font-weight: 700;
        font-size: 13px;
        color: #1a1a1a;
        margin-right: 4px;
    }

    .live-indicator {
        background: #2D8659;
        color: white;
        padding: 2px 5px;
        border-radius: 2px;
        display: inline-block;
    }

    .ft-indicator {
        background: #666;
        color: white;
        padding: 2px 5px;
        border-radius: 2px;
        display: inline-block;
    }

    .time-indicator {
        color: #666;
    }

    .col-score {
        display: none;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        width: 60px;
        padding: 4px;
        border-left: 1px solid #e8e8e8;
        gap: 2px;
        flex-shrink: 0;
    }

    .score-display {
        font-weight: 700;
        font-size: 14px;
        color: #1a1a1a;
        line-height: 1;
    }

    .score-empty {
        font-weight: 600;
        font-size: 14px;
        color: #ccc;
    }

    .live-badge-score {
        background: #2D8659;
        color: white;
        padding: 1px 2px;
        border-radius: 2px;
        font-size: 9px;
        font-weight: 600;
    }

    .ft-badge-score {
        background: #666;
        color: white;
        padding: 1px 4px;
        border-radius: 2px;
        font-size: 9px;
        font-weight: 600;
    }

    .time-badge-score {
        color: #666;
        font-size: 9px;
        font-weight: 600;
    }

    .col-outcome {
        display: flex;
        align-items: stretch;
        justify-content: center;
        width: 70px;
        padding: 0;
        border-left: 1px solid #e8e8e8;
        flex-shrink: 0;
    }

    .odds-btn-mobile {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        height: 100%;
        background: #0D7C5C;
        color: white;
        border: none;
        cursor: pointer;
        font-weight: 700;
        font-size: 13px;
        transition: background 0.12s ease;
    }

    .odds-btn-mobile:hover:not(:disabled) {
        background: #0a5f47;
    }

    .odds-btn-mobile:active:not(:disabled) {
        background: #084a38;
    }

    .odds-btn-mobile.selected {
        background: #FF6B35;
        box-shadow: inset 0 0 0 2px #fff;
    }

    .odds-btn-mobile:disabled {
        opacity: 0.4;
        cursor: not-allowed;
    }

    .odds-value {
        font-weight: 700;
        font-size: 14px;
    }

    .odds-desktop-split {
        display: none;
        width: 100%;
        height: 100%;
    }

    .col-volume {
        display: none;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        width: 80px;
        padding: 4px;
        border-left: 1px solid #e8e8e8;
        gap: 2px;
        flex-shrink: 0;
    }

    .total-votes {
        font-weight: 700;
        font-size: 13px;
        color: #1a1a1a;
        line-height: 1;
    }

    .volume-label {
        font-size: 9px;
        color: #666;
        text-transform: uppercase;
    }

    .betfair-no-matches {
        padding: 40px 20px;
        text-align: center;
        background: #fff;
        color: #666;
        font-size: 13px;
    }

    .teams-link {
        text-decoration: none;
        color: inherit;
        display: block;
        transition: all 0.2s ease;
    }

    .teams-link:hover {
        opacity: 0.85;
    }

    .teams-link:hover .team-name {
        color: #FFB80C;
        text-decoration: underline;
    }

    .teams-link:focus {
        outline: 2px solid #FFB80C;
        outline-offset: 2px;
    }

    .match-row-exchange[data-status="live"] {
        border-left: 10px solid #FFB80C !important;
        background: linear-gradient(90deg, rgba(255, 184, 12, 0.05) 0%, transparent 50%) !important;
        animation: liveMatchGlow 2s ease-in-out infinite;
    }

    @keyframes liveMatchGlow {

        0%,
        100% {
            box-shadow: 0 0 0 0 rgba(255, 184, 12, 0.4);
        }

        50% {
            box-shadow: 0 0 15px 0 rgba(255, 184, 12, 0.6);
        }
    }

    .live-badge-score,
    .live-indicator {
        background: #FFB80C !important;
        color: #000 !important;
        font-weight: 700 !important;
        animation: liveBadgePulse 1.5s ease-in-out infinite;
        box-shadow: 0 2px 5px rgba(255, 184, 12, 0.4);
    }

    @keyframes liveBadgePulse {

        0%,
        100% {
            opacity: 1;
            transform: scale(1);
        }

        50% {
            opacity: 0.85;
            transform: scale(1.05);
        }
    }

    .live-badge-score::before,
    .live-indicator::before {
        content: "⚽ LIVE ";
        font-weight: 800;
        letter-spacing: 0.5px;
    }

    .match-row-exchange[data-status="live"] .score-display,
    .match-row-exchange[data-status="live"] .score-mobile {
        color: #FFB80C !important;
        font-weight: 800 !important;
        font-size: 15px !important;
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
    }

    @media (min-width: 768px) {
        .comp-columns-desktop {
            display: flex;
        }

        .col-score {
            display: flex;
        }

        .match-time-mobile {
            display: none;
        }

        .col-volume {
            display: flex;
        }

        .odds-btn-mobile {
            display: none;
        }

        .odds-desktop-split {
            display: flex;
            flex-direction: row;
            width: 100%;
            height: 100%;
        }

        .col-outcome {
            width: 100px;
        }

        .odds-back,
        .odds-lay {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 50%;
            height: 100%;
            border: none;
            cursor: pointer;
            font-weight: 600;
            font-size: 11px;
            transition: all 0.12s ease;
            padding: 4px 2px;
            gap: 2px;
            border-right: 1px solid rgba(255, 255, 255, 0.15);
        }

        .odds-back {
            background: #0066CC;
            color: white;
        }

        .odds-back:hover:not(:disabled) {
            background: #0052a3;
            transform: scale(1.02);
        }

        .odds-back.selected {
            background: #FF6B35;
            box-shadow: inset 0 0 0 2px #fff;
        }

        .odds-lay {
            background: #CC0066;
            color: white;
        }

        .odds-lay:hover:not(:disabled) {
            background: #a00052;
            transform: scale(1.02);
        }

        .odds-back:disabled,
        .odds-lay:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }

        .odds-price {
            font-weight: 700;
            font-size: 12px;
            line-height: 1;
        }

        .odds-vol {
            font-size: 9px;
            opacity: 0.8;
            line-height: 1;
        }

        .col-teams {
            min-width: 180px;
            max-width: 220px;
        }

        .team-name {
            font-size: 12px;
        }
    }

    @media (min-width: 1024px) {
        .col-teams {
            min-width: 200px;
            max-width: 250px;
        }

        .col-outcome {
            width: 128px;
        }

        .col-volume {
            width: 92px;
        }

        .col-score {
            width: 63px;
        }
    }

    @media (max-width: 480px) {
        .col-teams {
            min-width: 110px;
            padding: 6px 8px;
        }

        .team-name {
            font-size: 10px;
        }

        .team-logo-tiny {
            width: 14px;
            height: 14px;
        }

        .col-outcome {
            width: 60px;
        }

        .odds-value {
            font-size: 12px;
        }

        .comp-title {
            font-size: 11px;
        }
    }
</style>

<script>
    (function ($) {
        'use strict';

        const BetfairDaily = {
            init: function () {
                console.log('📅 Betfair Daily Template Initialized (Simple Mode - No AJAX)');
                this.bindScrollArrows();
                this.scrollToSelected();
                this.initVoting();
                this.initLivePolling();
            },

            bindScrollArrows: function () {
                $('#scrollDatesLeft').on('click', function () {
                    const slider = document.getElementById('dateSlider');
                    slider.scrollBy({ left: -200, behavior: 'smooth' });
                });

                $('#scrollDatesRight').on('click', function () {
                    const slider = document.getElementById('dateSlider');
                    slider.scrollBy({ left: 200, behavior: 'smooth' });
                });
            },

            scrollToSelected: function () {
                setTimeout(() => {
                    const $selected = $('.date-card.date-selected');
                    if ($selected.length) {
                        const slider = document.getElementById('dateSlider');
                        const card = $selected[0];
                        const scrollLeft = card.offsetLeft - (slider.offsetWidth / 2) + (card.offsetWidth / 2);
                        slider.scrollTo({ left: scrollLeft, behavior: 'smooth' });
                    }
                }, 100);
            },

            initVoting: function () {
                // Restore saved votes for guests
                this.restoreVotes();

                // Bind vote button clicks
                $(document).on('click', '.goalv-vote-btn:not(:disabled)', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    this.handleVote($(e.currentTarget));
                });
            },

            handleVote: function ($btn) {
                const matchId = $btn.data('match-id');
                const optionId = $btn.data('option-id');
                const location = $btn.data('location') || 'homepage';

                if (!matchId || !optionId || $btn.hasClass('voting')) {
                    return;
                }

                $btn.addClass('voting');

                const voteData = {
                    action: 'goalv_cast_vote',
                    match_id: matchId,
                    option_id: optionId,
                    vote_location: location,
                    nonce: goalv_ajax.nonce
                };

                if (!goalv_ajax.is_user_logged_in) {
                    voteData.browser_id = this.getBrowserId();
                }

                $.ajax({
                    url: goalv_ajax.ajax_url,
                    type: 'POST',
                    data: voteData,
                    success: (response) => {
                        if (response.success) {
                            const results = response.data.results;
                            const userVotesByCategory = response.data.user_votes_by_category || {};
                            this.updateMatchUI(matchId, results, userVotesByCategory);

                            if (!goalv_ajax.is_user_logged_in) {
                                this.storeVote(matchId, location, userVotesByCategory);
                            }
                        }
                    },
                    complete: () => {
                        $btn.removeClass('voting');
                    }
                });
            },

            updateMatchUI: function (matchId, results, userVotesByCategory) {
                const $row = $(`.match-row-exchange[data-match-id="${matchId}"]`);
                if (!$row.length) return;

                $row.find('.goalv-vote-btn').removeClass('selected');
                const selectedOptionId = userVotesByCategory['match_result'];
                if (selectedOptionId) {
                    $row.find(`.goalv-vote-btn[data-option-id="${selectedOptionId}"]`).addClass('selected');
                }

                this.updateOddsDisplay($row, results);
            },

            updateOddsDisplay: function ($row, results) {
                let totalVotes = 0;
                const resultsById = {};

                results.forEach(result => {
                    resultsById[result.option_id] = result;
                    totalVotes += parseInt(result.votes_count) || 0;
                });

                $row.find('.goalv-vote-btn').each(function () {
                    const $btn = $(this);
                    const optionId = parseInt($btn.data('option-id'));
                    const result = resultsById[optionId];
                    if (!result) return;

                    const percentage = parseFloat(result.percentage) || 0;
                    const votes = parseInt(result.votes_count) || 0;
                    const odds = percentage > 0 ? (100 / percentage).toFixed(2) : '-';

                    if ($btn.hasClass('odds-btn-mobile')) {
                        $btn.find('.odds-value').text(odds);
                    }

                    if ($btn.hasClass('odds-back')) {
                        $btn.find('.odds-price').text(odds);
                        $btn.find('.odds-vol').text(votes);
                        const $layBtn = $btn.siblings('.odds-lay');
                        $layBtn.find('.odds-price').text(percentage.toFixed(1) + '%');
                        $layBtn.find('.odds-vol').text(votes);
                    }
                });

                $row.find('.total-votes').text(totalVotes.toLocaleString());
            },

            getBrowserId: function () {
                let browserId = localStorage.getItem('goalv_browser_id');
                if (!browserId) {
                    browserId = 'guest_' + Math.random().toString(36).substr(2, 9) + '_' + Date.now();
                    localStorage.setItem('goalv_browser_id', browserId);
                }
                return browserId;
            },

            storeVote: function (matchId, location, votesByCategory) {
                const storageKey = `goalv_votes_${matchId}_${location}`;
                localStorage.setItem(storageKey, JSON.stringify(votesByCategory));
            },

            getStoredVote: function (matchId, location) {
                const storageKey = `goalv_votes_${matchId}_${location}`;
                const stored = localStorage.getItem(storageKey);
                return stored ? JSON.parse(stored) : {};
            },

            restoreVotes: function () {
                if (goalv_ajax.is_user_logged_in) return;

                $('.match-row-exchange').each((index, element) => {
                    const $row = $(element);
                    const matchId = $row.data('match-id');
                    if (!matchId) return;

                    const storedVotes = this.getStoredVote(matchId, 'homepage');
                    const selectedOptionId = storedVotes['match_result'];
                    if (selectedOptionId) {
                        $row.find(`.goalv-vote-btn[data-option-id="${selectedOptionId}"]`).addClass('selected');
                    }
                });
            },

            initLivePolling: function () {
                const $liveMatches = $('.match-row-exchange[data-status="live"]');
                if ($liveMatches.length === 0) return;

                setInterval(() => this.pollLiveScores(), 30000);
                setTimeout(() => this.pollLiveScores(), 5000);
            },

            pollLiveScores: function () {
                const matchIds = $('.match-row-exchange[data-status="live"]').map(function () {
                    return $(this).data('match-id');
                }).get();

                if (matchIds.length === 0) return;

                $.ajax({
                    url: goalv_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'goalv_get_live_scores',
                        match_ids: matchIds,
                        nonce: goalv_ajax.nonce
                    },
                    success: (response) => {
                        if (response.success && response.data) {
                            this.updateLiveScores(response.data);
                        }
                    }
                });
            },

            updateLiveScores: function (liveData) {
                Object.keys(liveData).forEach(matchId => {
                    const data = liveData[matchId];
                    const $row = $(`.match-row-exchange[data-match-id="${matchId}"]`);
                    if (!$row.length) return;

                    if (data.home_score !== undefined && data.away_score !== undefined) {
                        const scoreText = `${data.home_score}-${data.away_score}`;
                        $row.find('.score-display').text(scoreText);
                        $row.find('.score-mobile').text(scoreText);
                    }

                    if (data.match_minute) {
                        $row.find('.live-badge-score').text(data.match_minute + "'");
                        $row.find('.live-indicator').text(data.match_minute + "'");
                    }

                    if (data.status === 'finished') {
                        $row.attr('data-status', 'finished').addClass('match-finished');
                        $row.find('.goalv-vote-btn').prop('disabled', true);
                        $row.find('.live-badge-score').replaceWith('<span class="ft-badge-score">FT</span>');
                        $row.find('.live-indicator').replaceWith('<span class="ft-indicator">FT</span>');
                    }
                });
            }
        };

        $(document).ready(function () {
            if (typeof goalv_ajax === 'undefined') {
                console.error('❌ goalv_ajax not defined');
                return;
            }
            BetfairDaily.init();
        });

    })(jQuery);
</script>