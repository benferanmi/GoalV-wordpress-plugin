<?php
/**
 * Betfair Exchange - Single Match Voting Page
 * Version: 1.0.0
 * 
 * Displays single match with:
 * - Event header (match info + live timer)
 * - Vote markets (tabbed, category-based)
 * - Simple vote buttons per option
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

// Get match ID
$match_id = get_query_var('goalv_match_id');
if (!$match_id && isset($_GET['id'])) {
    $match_id = intval($_GET['id']);
}

if (!$match_id) {
    echo '<div class="voting-error">' . __('Match ID not provided.', 'goalv') . '</div>';
    get_footer();
    return;
}

$frontend = new GoalV_Frontend();
$renderer = $frontend->get_renderer();
$match = $frontend->get_single_match_data($match_id);

if (!$match) {
    echo '<div class="voting-error">' . __('Match not found.', 'goalv') . '</div>';
    get_footer();
    return;
}

// Group vote options by category
$markets = array();
if (isset($match->vote_options_grouped)) {
    foreach ($match->vote_options_grouped as $category_key => $category_data) {
        $markets[$category_key] = $category_data;
    }
}
?>

<div class="voting-wrapper">
    
    <!-- EVENT HEADER -->
    <header class="voting-header">
        <div class="header-top">
            <a href="<?php echo home_url(); ?>" class="header-back">← Back</a>
            <span class="header-competition"><?php echo esc_html($match->competition); ?></span>
        </div>
        
        <div class="header-main">
            <div class="matchup">
                <span class="team home"><?php echo esc_html($match->home_team); ?></span>
                <span class="vs">vs</span>
                <span class="team away"><?php echo esc_html($match->away_team); ?></span>
            </div>
            
            <div class="match-status">
                <?php if ($match->status === 'live'): ?>
                    <span class="badge live">LIVE</span>
                    <span class="score"><?php echo esc_html($match->home_score . '-' . $match->away_score); ?></span>
                    <span class="timer"><?php echo esc_html($match->match_minute ?? '0') . "'"; ?></span>
                <?php elseif ($match->status === 'finished'): ?>
                    <span class="badge finished">FT</span>
                    <span class="score"><?php echo esc_html($match->home_score . '-' . $match->away_score); ?></span>
                <?php else: ?>
                    <span class="kickoff"><?php echo esc_html(date('H:i, d M', strtotime($match->match_date))); ?></span>
                <?php endif; ?>
            </div>
        </div>
    </header>
    
    <!-- MARKETS CONTENT -->
    <main class="voting-markets">
        
        <!-- MARKET TABS -->
        <div class="market-tabs">
            <?php 
            $tab_order = array('match_result', 'match_score', 'goals_threshold', 'both_teams_score', 'first_to_score', 'other');
            $tab_index = 0;
            foreach ($tab_order as $category_key):
                if (!isset($markets[$category_key])) continue;
                $is_active = $tab_index === 0;
                $tab_index++;
            ?>
                <button class="tab <?php echo $is_active ? 'active' : ''; ?>" data-market="<?php echo esc_attr($category_key); ?>">
                    <?php echo esc_html($markets[$category_key]['label']); ?>
                </button>
            <?php endforeach; ?>
        </div>
        
        <!-- MARKET CONTENT -->
        <div class="market-content">
            <?php 
            $tab_index = 0;
            foreach ($tab_order as $category_key):
                if (!isset($markets[$category_key])) continue;
                $category_data = $markets[$category_key];
                $is_active = $tab_index === 0;
                $tab_index++;
            ?>
                <div class="market-panel" id="market-<?php echo esc_attr($category_key); ?>" style="<?php echo !$is_active ? 'display:none;' : ''; ?>">
                    
                    <table class="options-table">
                        <thead>
                            <tr>
                                <th>Selection</th>
                                <th>Votes</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($category_data['options'] as $option): ?>
                                <?php 
                                $result = isset($match->vote_results[$option->id]) ? $match->vote_results[$option->id] : array('votes_count' => 0, 'percentage' => 0);
                                $is_user_vote = isset($match->user_votes_by_category[$category_key]) && $match->user_votes_by_category[$category_key] == $option->id;
                                ?>
                                <tr class="option-row <?php echo $is_user_vote ? 'user-voted' : ''; ?>" data-option-id="<?php echo esc_attr($option->id); ?>">
                                    
                                    <td class="option-name">
                                        <span><?php echo esc_html($option->option_text); ?></span>
                                    </td>
                                    
                                    <td class="option-votes">
                                        <span class="vote-count"><?php echo esc_html($result['votes_count']); ?></span>
                                    </td>
                                    
                                    <td class="option-action">
                                        <button class="vote-btn goalv-vote-btn" 
                                                data-match-id="<?php echo esc_attr($match->id); ?>"
                                                data-option-id="<?php echo esc_attr($option->id); ?>"
                                                data-category="<?php echo esc_attr($category_key); ?>"
                                                <?php echo $match->status === 'finished' ? 'disabled' : ''; ?>>
                                            <?php echo $is_user_vote ? '✓ Voted' : 'Vote'; ?>
                                        </button>
                                    </td>
                                    
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                </div>
            <?php endforeach; ?>
        </div>
        
    </main>
    
</div>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    padding: 0;
}

.voting-wrapper {
    background: #1a1a1a;
    color: #fff;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 13px;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}

/* ========================================
   HEADER
======================================== */

.voting-header {
    background: #151515;
    border-bottom: 1px solid #2a2a2a;
    padding: 16px;
}

.header-top {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 16px;
}

.header-back {
    color: #60a5fa;
    text-decoration: none;
    font-size: 12px;
    font-weight: 600;
    padding: 4px 8px;
    transition: color 0.2s;
}

.header-back:hover {
    color: #93c5fd;
}

.header-competition {
    font-size: 11px;
    color: #999;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
}

.header-main {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 32px;
    align-items: center;
}

.matchup {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 20px;
    font-weight: 700;
}

.team {
    color: #fff;
}

.vs {
    color: #666;
    font-size: 12px;
    text-transform: uppercase;
    font-weight: 600;
}

.match-status {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 13px;
}

.badge {
    padding: 4px 8px;
    border-radius: 2px;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
}

.badge.live {
    background: #d32f2f;
    color: white;
    animation: pulse 1s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.6; }
}

.badge.finished {
    background: #1b5e20;
    color: white;
}

.score {
    color: #999;
    font-weight: 600;
}

.timer {
    color: #999;
    font-weight: 600;
}

.kickoff {
    color: #aaa;
    font-weight: 600;
}

/* ========================================
   MARKETS
======================================== */

.voting-markets {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.market-tabs {
    display: flex;
    background: #151515;
    border-bottom: 1px solid #2a2a2a;
    overflow-x: auto;
    gap: 0;
}

.tab {
    background: none;
    border: none;
    color: #999;
    padding: 12px 16px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    cursor: pointer;
    transition: all 0.2s;
    white-space: nowrap;
    border-bottom: 2px solid transparent;
}

.tab:hover {
    color: #ccc;
}

.tab.active {
    color: #60a5fa;
    border-bottom-color: #60a5fa;
}

.market-content {
    flex: 1;
    overflow-y: auto;
    padding: 0;
}

.market-panel {
    padding: 0;
}

/* ========================================
   OPTIONS TABLE
======================================== */

.options-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
}

.options-table thead {
    background: #151515;
    position: sticky;
    top: 0;
    z-index: 10;
}

.options-table th {
    padding: 10px 16px;
    text-align: left;
    font-weight: 600;
    color: #999;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    border-bottom: 1px solid #2a2a2a;
}

.options-table th:first-child {
    width: 50%;
}

.options-table th:nth-child(2) {
    width: 20%;
    text-align: center;
}

.options-table th:nth-child(3) {
    width: 30%;
    text-align: center;
}

.option-row {
    border-bottom: 1px solid #2a2a2a;
    transition: background 0.2s;
}

.option-row:hover {
    background: #202020;
}

.option-row.user-voted {
    background: #1f2937;
}

.option-row td {
    padding: 12px 16px;
    vertical-align: middle;
}

.option-name {
    font-weight: 600;
    color: #fff;
}

.option-votes {
    text-align: center;
    color: #999;
    font-size: 12px;
}

.vote-count {
    font-weight: 600;
}

.option-action {
    text-align: center;
}

.vote-btn {
    background: #0066cc;
    color: white;
    border: 1px solid #0052a3;
    padding: 8px 16px;
    border-radius: 2px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    cursor: pointer;
    transition: all 0.2s;
}

.vote-btn:hover:not(:disabled) {
    background: #0052a3;
    box-shadow: 0 0 8px rgba(0, 102, 204, 0.3);
}

.vote-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.option-row.user-voted .vote-btn {
    background: #2d8659;
    border-color: #1b5e20;
    color: #fff;
}

/* ========================================
   RESPONSIVE
======================================== */

@media (max-width: 768px) {
    .header-main {
        grid-template-columns: 1fr;
        gap: 12px;
    }
    
    .matchup {
        font-size: 16px;
    }
    
    .tab {
        padding: 10px 12px;
        font-size: 11px;
    }
    
    .option-row td {
        padding: 10px 12px;
    }
    
    .vote-btn {
        padding: 6px 12px;
        font-size: 10px;
    }
}

@media (max-width: 480px) {
    .header-top {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }
    
    .matchup {
        flex-direction: column;
        gap: 4px;
        font-size: 14px;
    }
    
    .vs {
        order: -1;
    }
    
    .options-table th {
        padding: 8px 10px;
        font-size: 10px;
    }
    
    .option-row td {
        padding: 8px 10px;
        font-size: 11px;
    }
    
    .vote-btn {
        padding: 4px 8px;
        font-size: 9px;
    }
    
    .option-name {
        font-size: 11px;
    }
}
</style>

<script>
(function($) {
    'use strict';
    
    window.VotingMatch = {
        
        init: function() {
            if (typeof jQuery === 'undefined') return;
            console.log('✓ Voting Match initialized');
            this.bindTabs();
            this.bindVoteButtons();
        },
        
        bindTabs: function() {
            $(document).on('click', '.tab', function() {
                const $btn = $(this);
                const market = $btn.data('market');
                
                $('.tab').removeClass('active');
                $btn.addClass('active');
                
                $('.market-panel').hide();
                $('#market-' + market).show();
            });
        },
        
        bindVoteButtons: function() {
            $(document).on('click', '.vote-btn', function(e) {
                e.preventDefault();
                
                const $btn = $(this);
                const matchId = $btn.data('match-id');
                const optionId = $btn.data('option-id');
                const category = $btn.data('category');
                
                if (!matchId || !optionId) return;
                
                $btn.disabled = true;
                $btn.text('Voting...');
                
                $.ajax({
                    url: goalv_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'goalv_cast_vote',
                        match_id: matchId,
                        option_id: optionId,
                        vote_location: 'details',
                        nonce: goalv_ajax.nonce,
                        category: category
                    },
                    success: function(response) {
                        if (response.success) {
                            $btn.addClass('voted').text('✓ Voted').prop('disabled', true);
                            $btn.closest('.option-row').addClass('user-voted');
                            
                            // Update vote count
                            const votes = $btn.closest('.option-row').find('.vote-count');
                            votes.text(parseInt(votes.text()) + 1);
                        }
                    },
                    error: function() {
                        $btn.prop('disabled', false).text('Vote');
                    }
                });
            });
        }
    };
    
    $(document).ready(function() {
        window.VotingMatch.init();
    });
    
})(jQuery);
</script>

<?php get_footer(); ?>