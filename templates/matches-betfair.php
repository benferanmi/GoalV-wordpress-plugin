<?php
/**
 * Betfair Exchange UI - Matches Template (FIXED)
 * 
 * FIXES:
 * - Added force_enqueue_assets() to load AJAX variables
 * - Added clickable links to single match detail page
 * - Added hover styles for clickable teams
 * 
 * Variables:
 * - $matches (array) - Match objects from database
 * - $labels (array) - UI labels
 */

if (!defined('ABSPATH')) {
    exit;
}

// CRITICAL FIX 1: Force enqueue assets and AJAX variables
$frontend_instance = new GoalV_Frontend();
$frontend_instance->force_enqueue_assets();

$frontend = new GoalV_Frontend();
$renderer = $frontend->get_renderer();

// Default labels
$default_labels = array(
    'teams' => __('Teams', 'goalv'),
    'score' => __('Score', 'goalv'),
    'status' => __('Status', 'goalv'),
    'date' => __('Date', 'goalv'),
    'predictions' => __('Predictions', 'goalv'),
    'details' => __('Details', 'goalv')
);

$labels = isset($labels) ? array_merge($default_labels, $labels) : $default_labels;

echo '<div class="betfair-exchange-wrapper">';

if (!empty($matches)) {
    // Group by competition
    $matches_by_comp = array();
    foreach ($matches as $match) {
        $comp = $match->competition ?: 'Other';
        if (!isset($matches_by_comp[$comp])) {
            $matches_by_comp[$comp] = array();
        }
        $matches_by_comp[$comp][] = $match;
    }

    foreach ($matches_by_comp as $competition => $comp_matches):
        ?>
        <div class="betfair-competition-block">

            <!-- Competition Header (Yellow Brand) -->
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

            <!-- Match Rows -->
            <div class="matches-table">
                <?php foreach ($comp_matches as $match):
                    // Find the 3 basic vote options (Home/Draw/Away)
                    $home_win = null;
                    $draw = null;
                    $away_win = null;
                    $total_votes = 0;

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

                    // Check if user can vote
                    $can_vote = $renderer->can_vote_on_match($match);
                    $is_finished = $match->status === 'finished';

                    // User selections
                    $home_selected = $home_win && in_array($home_win->id, $match->user_votes);
                    $draw_selected = $draw && in_array($draw->id, $match->user_votes);
                    $away_selected = $away_win && in_array($away_win->id, $match->user_votes);

                    // Truncate team names (max 18 chars)
                    $home_team_short = strlen($match->home_team) > 18 ? substr($match->home_team, 0, 15) . '...' : $match->home_team;
                    $away_team_short = strlen($match->away_team) > 18 ? substr($match->away_team, 0, 15) . '...' : $match->away_team;

                    // CRITICAL FIX 2: Get match permalink
                    $match_url = $renderer->get_match_permalink($match->id);
                    ?>

                    <div class="match-row-exchange <?php echo $is_finished ? 'match-finished' : ''; ?>"
                        data-match-id="<?php echo esc_attr($match->id); ?>" data-status="<?php echo esc_attr($match->status); ?>">

                        <!-- COLUMN 1: TEAMS (NOW CLICKABLE) -->
                        <div class="col-teams">
                            <a href="<?php echo esc_url($match_url); ?>" class="teams-link"
                                title="View full match details for <?php echo esc_attr($match->home_team . ' vs ' . $match->away_team); ?>">
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
                            <!-- COLUMN 2: SCORE -->
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

                            <!-- COLUMN 3: HOME (1) -->
                            <div class="col-outcome">
                                <!-- Mobile: Single odds button -->
                                <button class="odds-btn-mobile goalv-vote-btn <?php echo $home_selected ? 'selected' : ''; ?>"
                                    data-match-id="<?php echo esc_attr($match->id); ?>"
                                    data-option-id="<?php echo esc_attr($home_win->id ?? ''); ?>" data-location="homepage" <?php echo !$can_vote || $is_finished ? 'disabled' : ''; ?>>
                                    <span class="odds-value"><?php echo esc_html($home_odds); ?></span>
                                </button>

                                <!-- Desktop: Back/Lay -->
                                <div class="odds-desktop-split">
                                    <button class="odds-back goalv-vote-btn <?php echo $home_selected ? 'selected' : ''; ?>"
                                        data-match-id="<?php echo esc_attr($match->id); ?>"
                                        data-option-id="<?php echo esc_attr($home_win->id ?? ''); ?>" data-location="homepage"
                                        title="Back Home" <?php echo !$can_vote || $is_finished ? 'disabled' : ''; ?>>
                                        <span class="odds-price"><?php echo esc_html($home_odds); ?></span>
                                        <span class="odds-vol"><?php echo esc_html($home_votes); ?></span>
                                    </button>
                                    <button class="odds-lay" title="Lay Home" <?php echo !$can_vote || $is_finished ? 'disabled' : ''; ?>>
                                        <span class="odds-price"><?php echo esc_html($home_percentage); ?>%</span>
                                        <span class="odds-vol"><?php echo esc_html($home_votes); ?></span>
                                    </button>
                                </div>
                            </div>

                            <!-- COLUMN 4: DRAW (X) -->
                            <div class="col-outcome">
                                <!-- Mobile: Single odds button -->
                                <button class="odds-btn-mobile goalv-vote-btn <?php echo $draw_selected ? 'selected' : ''; ?>"
                                    data-match-id="<?php echo esc_attr($match->id); ?>"
                                    data-option-id="<?php echo esc_attr($draw->id ?? ''); ?>" data-location="homepage" <?php echo !$can_vote || $is_finished ? 'disabled' : ''; ?>>
                                    <span class="odds-value"><?php echo esc_html($draw_odds); ?></span>
                                </button>

                                <!-- Desktop: Back/Lay -->
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

                            <!-- COLUMN 5: AWAY (2) -->
                            <div class="col-outcome">
                                <!-- Mobile: Single odds button -->
                                <button class="odds-btn-mobile goalv-vote-btn <?php echo $away_selected ? 'selected' : ''; ?>"
                                    data-match-id="<?php echo esc_attr($match->id); ?>"
                                    data-option-id="<?php echo esc_attr($away_win->id ?? ''); ?>" data-location="homepage" <?php echo !$can_vote || $is_finished ? 'disabled' : ''; ?>>
                                    <span class="odds-value"><?php echo esc_html($away_odds); ?></span>
                                </button>

                                <!-- Desktop: Back/Lay -->
                                <div class="odds-desktop-split">
                                    <button class="odds-back goalv-vote-btn <?php echo $away_selected ? 'selected' : ''; ?>"
                                        data-match-id="<?php echo esc_attr($match->id); ?>"
                                        data-option-id="<?php echo esc_attr($away_win->id ?? ''); ?>" data-location="homepage"
                                        title="Back Away" <?php echo !$can_vote || $is_finished ? 'disabled' : ''; ?>>
                                        <span class="odds-price"><?php echo esc_html($away_odds); ?></span>
                                        <span class="odds-vol"><?php echo esc_html($away_votes); ?></span>
                                    </button>
                                    <button class="odds-lay" title="Lay Away" <?php echo !$can_vote || $is_finished ? 'disabled' : ''; ?>>
                                        <span class="odds-price"><?php echo esc_html($away_percentage); ?>%</span>
                                        <span class="odds-vol"><?php echo esc_html($away_votes); ?></span>
                                    </button>
                                </div>
                            </div>

                            <!-- COLUMN 6: VOLUME (Desktop Only) -->
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

} else {
    ?>
    <div class="betfair-no-matches">
        <p><?php _e('No matches available', 'goalv'); ?></p>
    </div>
    <?php
}

echo '</div>';
?>

<style>
    /* ========================================
   BETFAIR EXCHANGE - PERFECT LAYOUT
   Dense table, proper columns, responsive
======================================== */

    .betfair-exchange-wrapper {
        background: #f7f7f7;
        color: #1a1a1a;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        font-size: 12px;
        margin: 0;
        padding: 0;
        max-width: 100%;
    }

    /* ========================================
   COMPETITION BLOCK
======================================== */

    .betfair-competition-block {
        background: #fff;
        margin-bottom: 16px;
        border: 1px solid #e0e0e0;
    }

    /* Competition Header (YELLOW BRAND) */
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
        /* Hidden on mobile */
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

    /* ========================================
   MATCH ROWS (TABLE STRUCTURE)
======================================== */

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



    /* ========================================
   COLUMN 1: TEAMS
======================================== */

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

    /* ========================================
   COLUMN 2: SCORE (HIDDEN ON MOBILE)
======================================== */

    .col-score {
        display: none;
        /* Hidden on mobile */
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

    /* ========================================
   COLUMNS 3-5: OUTCOMES (1/X/2)
======================================== */

    .col-outcome {
        display: flex;
        align-items: stretch;
        justify-content: center;
        width: 70px;
        padding: 0;
        border-left: 1px solid #e8e8e8;
        flex-shrink: 0;
    }

    /* MOBILE: Single odds button */
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

    /* DESKTOP: Back/Lay split (hidden on mobile) */
    .odds-desktop-split {
        display: none;
        width: 100%;
        height: 100%;
    }

    /* ========================================
   COLUMN 6: VOLUME (HIDDEN ON MOBILE)
======================================== */

    .col-volume {
        display: none;
        /* Hidden on mobile */
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

    /* ========================================
   NO MATCHES
======================================== */

    .betfair-no-matches {
        padding: 40px 20px;
        text-align: center;
        background: #fff;
        color: #666;
        font-size: 13px;
    }

    /* ========================================
   RESPONSIVE: TABLET & DESKTOP (768px+)
======================================== */

    @media (min-width: 768px) {

        /* Show desktop column headers */
        .comp-columns-desktop {
            display: flex;
        }

        /* Show score column */
        .col-score {
            display: flex;
        }

        /* Hide mobile time in teams area */
        .match-time-mobile {
            display: none;
        }

        /* Show volume column */
        .col-volume {
            display: flex;
        }

        /* Hide mobile odds button */
        .odds-btn-mobile {
            display: none;
        }

        /* Show desktop Back/Lay split */
        .odds-desktop-split {
            display: flex;
            flex-direction: row;
            width: 100%;
            height: 100%;
        }

        /* Expand outcome columns */
        .col-outcome {
            width: 100px;
        }

        /* Back/Lay buttons */
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

        .odds-back:last-child,
        .odds-lay:last-child {
            border-right: none;
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

        /* Adjust teams column */
        .col-teams {
            min-width: 180px;
            max-width: 220px;
        }

        .team-name {
            font-size: 12px;
        }
    }


    /* ============================== live css ===================== */

    .match-row-exchange[data-status="live"] {
        border-left: 10px solid #FFB80C !important;
        background: linear-gradient(90deg, rgba(255, 184, 12, 0.05) 0%, transparent 50%) !important;
        position: relative;
        animation: liveMatchGlow 2s ease-in-out infinite;
    }

    /* Pulsing glow effect */
    @keyframes liveMatchGlow {

        0%,
        100% {
            box-shadow: 0 0 0 0 rgba(255, 184, 12, 0.4);
        }

        50% {
            box-shadow: 0 0 15px 0 rgba(255, 184, 12, 0.6);
        }
    }

    /* Live badge styling - make it pop */
    .live-badge-score,
    .live-indicator {
        background: #FFB80C !important;
        color: #000 !important;
        font-weight: 700 !important;
        padding: 1px 3px !important;
        border-radius: 2px !important;
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

    /* Optional: Add "LIVE" text before the minute */
    .live-badge-score::before,
    .live-indicator::before {
        content: "⚽ LIVE ";
        font-weight: 800;
        letter-spacing: 0.5px;
    }

    /* Score display for live matches - highlight in yellow */
    .match-row-exchange[data-status="live"] .score-display,
    .match-row-exchange[data-status="live"] .score-mobile {
        color: #FFB80C !important;
        font-weight: 800 !important;
        font-size: 15px !important;
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
    }

    /* Optional: Add a "LIVE" ribbon in the top-right corner */
    .match-row-exchange[data-status="live"]::after {
        content: "LIVE";
        position: absolute;
        top: 5px;
        right: 5px;
        background: #FFB80C;
        color: #000;
        font-size: 9px;
        font-weight: 800;
        padding: 3px 8px;
        border-radius: 3px;
        z-index: 10;
        letter-spacing: 1px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        animation: liveRibbonBounce 2s ease-in-out infinite;
    }

    @keyframes liveRibbonBounce {

        0%,
        100% {
            transform: translateY(0);
        }

        50% {
            transform: translateY(-3px);
        }
    }

    /* Half-time matches - Orange border */
    .match-row-exchange[data-status="paused"] {
        border-left: 10px solid #FF6B35 !important;
        background: linear-gradient(90deg, rgba(255, 107, 53, 0.05) 0%, transparent 50%) !important;
    }

    /* Finished matches - keep subtle (current styling is fine) */
    .match-row-exchange[data-status="finished"] {
        border-left: 3px solid #e0e0e0 !important;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .match-row-exchange[data-status="live"] {
            border-left-width: 6px !important;
        }

        .match-row-exchange[data-status="live"]::after {
            font-size: 8px;
            padding: 2px 6px;
            top: 3px;
            right: 3px;
        }
    }

    /* ========================================
   DESKTOP LARGE (1024px+)
======================================== */

    @media (min-width: 1024px) {

        .col-teams {
            min-width: 200px;
            max-width: 250px;
        }

        .col-outcome {
            width: 128px;
        }

        .col-header.col-outcome {
            width: 120px;
        }

        .col-volume {
            width: 92px;
        }

        .col-score {
            width: 63px;
        }

        .odds-price {
            font-size: 13px;
        }

        .total-votes {
            font-size: 14px;
        }
    }

    /* ========================================
   SMALL MOBILE (max 480px)
======================================== */

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


    /* ====== new */

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
        /* Betfair yellow */
        text-decoration: underline;
    }

    .col-teams {
        cursor: pointer;
        position: relative;
    }

    .col-teams::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        width: 0;
        height: 2px;
        background: #FFB80C;
        transition: width 0.3s ease;
    }

    .col-teams:hover::after {
        width: 100%;
    }

    /* Accessibility - Focus state */
    .teams-link:focus {
        outline: 2px solid #FFB80C;
        outline-offset: 2px;
    }

    .teams-link:focus .team-name {
        color: #FFB80C;
    }
</style>
<script>

    (function ($) {
        'use strict';

        // ============================================
        // BETFAIR TEMPLATE VOTING - COMPLETE FIX
        // ============================================

        const BetfairVoting = {

            init: function () {
                console.log('🎯 Betfair Voting Initialized');

                // Restore saved votes on page load
                this.restoreVotes();

                // Bind vote button clicks
                this.bindEvents();

                // Start live polling if enabled
                this.initLivePolling();
            },

            bindEvents: function () {
                // Vote button click handler
                $(document).on('click', '.goalv-vote-btn:not(:disabled)', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    this.handleVote($(e.currentTarget));
                });

                // Prevent navigation when clicking disabled buttons
                $(document).on('click', '.goalv-vote-btn:disabled', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                });
            },

            handleVote: function ($btn) {
                const matchId = $btn.data('match-id');
                const optionId = $btn.data('option-id');
                const location = $btn.data('location') || 'homepage';

                if (!matchId || !optionId) {
                    console.error('❌ Missing match ID or option ID');
                    return;
                }

                // Check if already voting
                if ($btn.hasClass('voting')) {
                    return;
                }

                // Visual feedback - mark as loading
                $btn.addClass('voting');

                // Prepare vote data
                const voteData = {
                    action: 'goalv_cast_vote',
                    match_id: matchId,
                    option_id: optionId,
                    vote_location: location,
                    nonce: goalv_ajax.nonce
                };

                // Add browser ID for guest users
                if (!goalv_ajax.is_user_logged_in) {
                    voteData.browser_id = this.getBrowserId();
                }

                // Submit vote
                $.ajax({
                    url: goalv_ajax.ajax_url,
                    type: 'POST',
                    data: voteData,
                    success: (response) => {
                        this.handleVoteSuccess(response, $btn, matchId, optionId, location);
                    },
                    error: (xhr, status, error) => {
                        this.handleVoteError($btn, error);
                    },
                    complete: () => {
                        $btn.removeClass('voting');
                    }
                });
            },

            handleVoteSuccess: function (response, $btn, matchId, optionId, location) {
                if (!response.success) {
                    this.showMessage(response.data || 'Vote failed', 'error');
                    return;
                }

                console.log('✅ Vote Success:', response.data);

                // CRITICAL FIX: Backend returns 'results', not 'vote_counts'
                const results = response.data.results;
                const userVotesByCategory = response.data.user_votes_by_category || {};
                const category = response.data.category;
                const action = response.data.action; // 'added', 'changed', or 'removed'

                // Update UI
                this.updateMatchUI(matchId, results, userVotesByCategory, category, action);

                // Store vote for guests
                if (!goalv_ajax.is_user_logged_in) {
                    this.storeVote(matchId, location, userVotesByCategory);
                }

                // Show message
                this.showMessage(response.data.message, 'success');
            },

            handleVoteError: function ($btn, error) {
                console.error('❌ Vote Error:', error);
                this.showMessage('Failed to submit vote. Please try again.', 'error');
            },

            updateMatchUI: function (matchId, results, userVotesByCategory, changedCategory, action) {
                const $row = $(`.match-row-exchange[data-match-id="${matchId}"]`);

                if (!$row.length) {
                    console.warn('Match row not found:', matchId);
                    return;
                }

                // For homepage voting, we only have match_result category
                // Clear all selections in this row first
                $row.find('.goalv-vote-btn').removeClass('selected');

                // Set the selected button based on user votes by category
                const selectedOptionId = userVotesByCategory['match_result'];

                if (selectedOptionId) {
                    // Find and select the button with this option ID
                    $row.find(`.goalv-vote-btn[data-option-id="${selectedOptionId}"]`)
                        .addClass('selected');
                }

                // Update odds and vote counts
                this.updateOddsDisplay($row, results);
            },

            updateOddsDisplay: function ($row, results) {
                // Calculate total votes
                let totalVotes = 0;
                const resultsById = {};

                results.forEach(result => {
                    resultsById[result.option_id] = result;
                    totalVotes += parseInt(result.votes_count) || 0;
                });

                // Update each vote button
                $row.find('.goalv-vote-btn').each(function () {
                    const $btn = $(this);
                    const optionId = parseInt($btn.data('option-id'));
                    const result = resultsById[optionId];

                    if (!result) return;

                    const percentage = parseFloat(result.percentage) || 0;
                    const votes = parseInt(result.votes_count) || 0;

                    // Calculate odds (same formula as PHP)
                    const odds = percentage > 0 ? (100 / percentage).toFixed(2) : '-';

                    // Update mobile button
                    if ($btn.hasClass('odds-btn-mobile')) {
                        $btn.find('.odds-value').text(odds);
                    }

                    // Update desktop Back button
                    if ($btn.hasClass('odds-back')) {
                        $btn.find('.odds-price').text(odds);
                        $btn.find('.odds-vol').text(votes);

                        // Update corresponding Lay button
                        const $layBtn = $btn.siblings('.odds-lay');
                        $layBtn.find('.odds-price').text(percentage.toFixed(1) + '%');
                        $layBtn.find('.odds-vol').text(votes);
                    }
                });

                // Update total volume
                $row.find('.total-votes').text(totalVotes.toLocaleString());

                console.log('📊 Updated odds display:', totalVotes, 'total votes');
            },

            // ============================================
            // VOTE PERSISTENCE (GUEST USERS)
            // ============================================

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
                console.log('💾 Stored vote:', storageKey, votesByCategory);
            },

            getStoredVote: function (matchId, location) {
                const storageKey = `goalv_votes_${matchId}_${location}`;
                const stored = localStorage.getItem(storageKey);
                return stored ? JSON.parse(stored) : {};
            },

            restoreVotes: function () {
                if (goalv_ajax.is_user_logged_in) {
                    console.log('👤 User logged in - votes from server');
                    return;
                }

                console.log('👻 Guest user - restoring votes from localStorage');

                $('.match-row-exchange').each((index, element) => {
                    const $row = $(element);
                    const matchId = $row.data('match-id');
                    const location = 'homepage';

                    if (!matchId) return;

                    const storedVotes = this.getStoredVote(matchId, location);

                    // Get the selected option for match_result category
                    const selectedOptionId = storedVotes['match_result'];

                    if (selectedOptionId) {
                        $row.find(`.goalv-vote-btn[data-option-id="${selectedOptionId}"]`)
                            .addClass('selected');

                        console.log(`✓ Restored vote for match ${matchId}: option ${selectedOptionId}`);
                    }
                });
            },

            // ============================================
            // LIVE SCORE POLLING
            // ============================================

            initLivePolling: function () {
                const $liveMatches = $('.match-row-exchange[data-status="live"]');

                if ($liveMatches.length === 0) {
                    return;
                }

                console.log('📡 Starting live score polling for', $liveMatches.length, 'matches');

                // Poll every 30 seconds
                setInterval(() => {
                    this.pollLiveScores();
                }, 30000);

                // Initial poll after 5 seconds
                setTimeout(() => {
                    this.pollLiveScores();
                }, 5000);
            },

            pollLiveScores: function () {
                const matchIds = $('.match-row-exchange[data-status="live"]')
                    .map(function () {
                        return $(this).data('match-id');
                    })
                    .get();

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

                    // Update score displays
                    if (data.home_score !== undefined && data.away_score !== undefined) {
                        const scoreText = `${data.home_score}-${data.away_score}`;
                        $row.find('.score-display').text(scoreText);
                        $row.find('.score-mobile').text(scoreText);
                    }

                    // Update match minute
                    if (data.match_minute) {
                        $row.find('.live-badge-score').text(data.match_minute + "'");
                        $row.find('.live-indicator').text(data.match_minute + "'");
                    }

                    // Update status if finished
                    if (data.status === 'finished') {
                        $row.attr('data-status', 'finished');
                        $row.addClass('match-finished');
                        $row.find('.goalv-vote-btn').prop('disabled', true);

                        $row.find('.live-badge-score').replaceWith('<span class="ft-badge-score">FT</span>');
                        $row.find('.live-indicator').replaceWith('<span class="ft-indicator">FT</span>');
                    }
                });
            },

            // ============================================
            // UI HELPERS
            // ============================================

            showMessage: function (message, type = 'info') {
                console.log(`[${type.toUpperCase()}] ${message}`);

                // You can add a toast notification here if you have one
                // For now, just console log
            }
        };

        // ============================================
        // AUTO-INITIALIZE
        // ============================================

        $(document).ready(function () {
            // Check if AJAX is available
            if (typeof goalv_ajax === 'undefined') {
                console.error('❌ goalv_ajax not defined');
                return;
            }

            console.log('✅ goalv_ajax available:', goalv_ajax);

            // Initialize voting
            BetfairVoting.init();
        });

    })(jQuery);
</script>