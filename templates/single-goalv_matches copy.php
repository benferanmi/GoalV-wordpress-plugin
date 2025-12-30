<?php
/**
 * Betfair Exchange - Single Match Detail Page
 * Mobile-first responsive: collapsed odds on mobile, Back/Lay on desktop
 * Same markets, same interaction, only layout density changes
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
    echo '<div class="betfair-error">' . __('Match ID not provided.', 'goalv') . '</div>';
    get_footer();
    return;
}

$frontend = new GoalV_Frontend();
$renderer = $frontend->get_renderer();
$match = $frontend->get_single_match_data($match_id);

if (!$match) {
    echo '<div class="betfair-error">' . __('Match not found.', 'goalv') . '</div>';
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

<div class="betfair-single-wrapper">
    
    <!-- EVENT HEADER -->
    <header class="event-header">
        <div class="header-top">
            <a href="<?php echo home_url(); ?>" class="header-back">← Back</a>
            <span class="header-comp"><?php echo esc_html($match->competition); ?></span>
        </div>
        
        <div class="header-matchup">
            <span class="team home-team"><?php echo esc_html($match->home_team); ?></span>
            <span class="vs">vs</span>
            <span class="team away-team"><?php echo esc_html($match->away_team); ?></span>
        </div>
        
        <div class="header-status">
            <?php if ($match->status === 'live'): ?>
                <span class="badge live-badge">LIVE</span>
                <span class="score"><?php echo esc_html($match->home_score . '-' . $match->away_score); ?></span>
                <span class="timer"><?php echo esc_html($match->match_minute ?? '0'); ?>'</span>
            <?php elseif ($match->status === 'finished'): ?>
                <span class="badge ft-badge">FT</span>
                <span class="score"><?php echo esc_html($match->home_score . '-' . $match->away_score); ?></span>
            <?php else: ?>
                <span class="kickoff"><?php echo esc_html(date('H:i', strtotime($match->match_date))); ?></span>
                <span class="date"><?php echo esc_html(date('d M', strtotime($match->match_date))); ?></span>
            <?php endif; ?>
        </div>
    </header>
    
    <!-- CONTENT AREA -->
    <div class="content-grid">
        
        <!-- MARKETS (Main) -->
        <main class="markets-area">
            
            <!-- MARKET TABS -->
            <div class="tabs-bar">
                <?php 
                $tab_order = array('match_result', 'match_score', 'goals_threshold', 'both_teams_score', 'first_to_score', 'other');
                $tab_index = 0;
                foreach ($tab_order as $category_key):
                    if (!isset($markets[$category_key])) continue;
                    $is_active = $tab_index === 0;
                    $tab_index++;
                ?>
                    <button class="tab-btn <?php echo $is_active ? 'active' : ''; ?>" 
                            data-market="<?php echo esc_attr($category_key); ?>">
                        <?php echo esc_html($markets[$category_key]['label']); ?>
                    </button>
                <?php endforeach; ?>
            </div>
            
            <!-- MARKET PANELS -->
            <div class="markets-list">
                <?php 
                $tab_index = 0;
                foreach ($tab_order as $category_key):
                    if (!isset($markets[$category_key])) continue;
                    $category_data = $markets[$category_key];
                    $is_active = $tab_index === 0;
                    $tab_index++;
                ?>
                    <div class="market-panel" id="market-<?php echo esc_attr($category_key); ?>" 
                         style="<?php echo $is_active ? '' : 'display:none;'; ?>">
                        
                        <div class="market-selections">
                            <?php foreach ($category_data['options'] as $option): ?>
                                <?php 
                                $is_selected = isset($match->user_votes_by_category[$category_key]) && 
                                              $match->user_votes_by_category[$category_key] == $option->id;
                                $result = isset($match->vote_results[$option->id]) ? $match->vote_results[$option->id] : array('percentage' => 0, 'votes_count' => 0);
                                ?>
                                <div class="selection-card <?php echo $is_selected ? 'selected' : ''; ?>" data-option-id="<?php echo esc_attr($option->id); ?>">
                                    
                                    <div class="selection-name">
                                        <span><?php echo esc_html($option->option_text); ?></span>
                                        <span class="selection-votes"><?php echo esc_html($result['votes_count']); ?> votes</span>
                                    </div>
                                    
                                    <!-- Mobile: Single odds button -->
                                    <button class="odds-btn mobile-odds betfair-vote-btn" 
                                            data-match-id="<?php echo esc_attr($match->id); ?>"
                                            data-option-id="<?php echo esc_attr($option->id); ?>"
                                            data-location="details"
                                            data-category="<?php echo esc_attr($category_key); ?>"
                                            <?php echo ($match->status === 'finished') ? 'disabled' : ''; ?>>
                                        <span class="odds-value">2.50</span>
                                    </button>
                                    
                                    <!-- Desktop: Back/Lay split -->
                                    <div class="odds-ladder desktop-odds">
                                        <button class="odds-btn back-btn betfair-vote-btn" 
                                                data-match-id="<?php echo esc_attr($match->id); ?>"
                                                data-option-id="<?php echo esc_attr($option->id); ?>"
                                                data-location="details"
                                                data-category="<?php echo esc_attr($category_key); ?>"
                                                data-side="back"
                                                title="Back"
                                                <?php echo ($match->status === 'finished') ? 'disabled' : ''; ?>>
                                            <span class="odds-price">2.50</span>
                                            <span class="odds-label">B</span>
                                        </button>
                                        <button class="odds-btn lay-btn betfair-vote-btn" 
                                                data-match-id="<?php echo esc_attr($match->id); ?>"
                                                data-option-id="<?php echo esc_attr($option->id); ?>"
                                                data-location="details"
                                                data-category="<?php echo esc_attr($category_key); ?>"
                                                data-side="lay"
                                                title="Lay"
                                                <?php echo ($match->status === 'finished') ? 'disabled' : ''; ?>>
                                            <span class="odds-price">2.52</span>
                                            <span class="odds-label">L</span>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </main>
        
        <!-- BET SLIP (Right side on desktop, slide-up on mobile) -->
        <aside class="betslip-panel">
            <div class="betslip-header">
                <h3>Bet Slip</h3>
                <button class="betslip-close">×</button>
            </div>
            
            <div class="betslip-content">
                <div class="betslip-empty">
                    <p>No selections</p>
                </div>
                
                <div class="betslip-selections" style="display:none;">
                    <!-- Selections added via JS -->
                </div>
                
                <div class="betslip-form" style="display:none;">
                    <div class="form-field">
                        <label>Stake</label>
                        <input type="number" class="form-input stake-input" placeholder="£10" min="1" />
                    </div>
                    
                    <div class="form-field">
                        <label>Est. Return</label>
                        <div class="returns-display">£0.00</div>
                    </div>
                    
                    <button class="btn-primary betslip-place">Place Bet</button>
                    <button class="btn-secondary betslip-clear">Clear All</button>
                </div>
            </div>
        </aside>
    </div>
    
</div>

<!-- STYLES -->
<style>
/* ========================================
   BETFAIR SINGLE MATCH - RESPONSIVE LAYOUT
   Mobile-first: vertical, Desktop: horizontal
======================================== */

.betfair-single-wrapper {
    background: #f5f5f5;
    color: #333;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    font-size: 13px;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}

/* ========================================
   EVENT HEADER
======================================== */

.event-header {
    background: #fff;
    border-bottom: 1px solid #ddd;
    padding: 12px 16px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.header-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 11px;
}

.header-back {
    color: #0066cc;
    text-decoration: none;
    font-weight: 600;
    font-size: 12px;
}

.header-back:hover {
    color: #0052a3;
}

.header-comp {
    color: #666;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.header-matchup {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    font-size: 18px;
    font-weight: 700;
}

.team {
    color: #333;
}

.vs {
    color: #999;
    font-size: 12px;
    text-transform: uppercase;
}

.header-status {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 12px;
    font-size: 12px;
    font-weight: 600;
}

.badge {
    padding: 4px 8px;
    border-radius: 2px;
    font-size: 10px;
    text-transform: uppercase;
    font-weight: bold;
}

.live-badge {
    background: #2d8659;
    color: white;
    animation: pulse-live 1s infinite;
}

@keyframes pulse-live {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.6; }
}

.ft-badge {
    background: #999;
    color: white;
}

.score,
.timer,
.kickoff,
.date {
    color: #666;
}

/* ========================================
   CONTENT GRID (Mobile vertical, Desktop horizontal)
======================================== */

.content-grid {
    display: flex;
    flex-direction: column;
    flex: 1;
    gap: 0;
}

/* ========================================
   MARKETS AREA
======================================== */

.markets-area {
    display: flex;
    flex-direction: column;
    flex: 1;
    overflow: hidden;
}

.tabs-bar {
    display: flex;
    background: #fff;
    border-bottom: 1px solid #ddd;
    overflow-x: auto;
    gap: 0;
    padding: 0;
}

.tab-btn {
    background: none;
    border: none;
    color: #666;
    padding: 10px 16px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    cursor: pointer;
    transition: all 0.2s;
    white-space: nowrap;
    border-bottom: 3px solid transparent;
}

.tab-btn:hover {
    color: #333;
}

.tab-btn.active {
    color: #0066cc;
    border-bottom-color: #0066cc;
}

.markets-list {
    flex: 1;
    overflow-y: auto;
    background: #fff;
}

.market-panel {
    padding: 12px 0;
}

.market-selections {
    display: flex;
    flex-direction: column;
    gap: 0;
}

/* ========================================
   SELECTION CARD (Mobile & Desktop)
======================================== */

.selection-card {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 12px;
    align-items: center;
    padding: 10px 16px;
    border-bottom: 1px solid #eee;
    transition: background 0.15s ease;
}

.selection-card:active {
    background: #f9f9f9;
}

.selection-card.selected {
    background: #f0f0f0;
}

.selection-name {
    display: flex;
    flex-direction: column;
    gap: 2px;
    font-size: 12px;
    font-weight: 500;
    color: #333;
    flex: 1;
}

.selection-votes {
    font-size: 10px;
    color: #999;
    font-weight: normal;
}

/* ========================================
   ODDS: Mobile (single button) vs Desktop (Back/Lay)
======================================== */

/* Mobile: Show single odds button */
.mobile-odds {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 70px;
    height: 50px;
    background: #0d7c5c;
    color: white;
    border: none;
    border-radius: 0;
    cursor: pointer;
    font-weight: 700;
    font-size: 13px;
    transition: background 0.15s;
    padding: 0;
}

.mobile-odds:hover:not(:disabled) {
    background: #0a5f47;
}

.mobile-odds:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.odds-value {
    font-size: 14px;
    font-weight: 700;
}

/* Desktop: Hide single, show Back/Lay */
.desktop-odds {
    display: none;
}

/* ========================================
   RESPONSIVE: TABLET & UP (768px+)
======================================== */

@media (min-width: 768px) {
    
    /* Hide mobile single odds */
    .mobile-odds {
        display: none;
    }
    
    /* Show desktop Back/Lay */
    .desktop-odds {
        display: flex;
        gap: 4px;
    }
    
    .odds-ladder {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 4px;
        width: auto;
    }
    
    .odds-btn {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        min-width: 70px;
        height: 50px;
        border: none;
        border-radius: 0;
        cursor: pointer;
        font-weight: 600;
        font-size: 11px;
        transition: background 0.15s;
        padding: 4px;
        gap: 2px;
    }
    
    .odds-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    
    /* BACK (Blue) */
    .back-btn {
        background: #0066cc;
        color: white;
    }
    
    .back-btn:hover:not(:disabled) {
        background: #0052a3;
    }
    
    /* LAY (Pink) */
    .lay-btn {
        background: #c13584;
        color: white;
    }
    
    .lay-btn:hover:not(:disabled) {
        background: #a02e6f;
    }
    
    .odds-price {
        font-size: 12px;
        font-weight: 700;
        line-height: 1;
    }
    
    .odds-label {
        font-size: 9px;
        opacity: 0.8;
        line-height: 1;
    }
    
    /* LAYOUT: Side-by-side on desktop */
    .content-grid {
        flex-direction: row;
        gap: 0;
    }
    
    .markets-area {
        flex: 1;
        border-right: 1px solid #ddd;
    }
    
    .betslip-panel {
        width: 280px;
        border-left: 1px solid #ddd;
    }
}

/* ========================================
   BET SLIP (Mobile: slide-up, Desktop: right panel)
======================================== */

.betslip-panel {
    background: #fff;
    border-top: 1px solid #ddd;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.betslip-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 16px;
    border-bottom: 1px solid #ddd;
    background: #f9f9f9;
}

.betslip-header h3 {
    margin: 0;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.betslip-close {
    background: none;
    border: none;
    color: #999;
    font-size: 20px;
    cursor: pointer;
    padding: 0;
    transition: color 0.2s;
}

.betslip-close:hover {
    color: #333;
}

.betslip-content {
    flex: 1;
    padding: 12px 16px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.betslip-empty {
    display: flex;
    align-items: center;
    justify-content: center;
    flex: 1;
    text-align: center;
    color: #999;
    font-size: 12px;
}

.betslip-selections {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.slip-selection {
    background: #f9f9f9;
    border: 1px solid #eee;
    padding: 8px;
    border-radius: 2px;
    font-size: 11px;
}

.slip-selection-name {
    font-weight: 600;
    color: #333;
    margin-bottom: 4px;
}

.slip-selection-side {
    display: inline-block;
    padding: 2px 6px;
    border-radius: 2px;
    font-size: 9px;
    text-transform: uppercase;
    font-weight: 600;
    margin-right: 8px;
}

.slip-selection-side.back {
    background: #0066cc;
    color: white;
}

.slip-selection-side.lay {
    background: #c13584;
    color: white;
}

.slip-remove {
    text-align: right;
    margin-top: 4px;
}

.slip-remove button {
    background: none;
    border: none;
    color: #999;
    font-size: 11px;
    cursor: pointer;
    padding: 0;
    transition: color 0.2s;
}

.slip-remove button:hover {
    color: #c13584;
}

.betslip-form {
    display: flex;
    flex-direction: column;
    gap: 8px;
    border-top: 1px solid #eee;
    padding-top: 8px;
}

.form-field {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.form-field label {
    font-size: 10px;
    color: #999;
    text-transform: uppercase;
    font-weight: 600;
}

.form-input {
    background: #f9f9f9;
    border: 1px solid #eee;
    padding: 6px 8px;
    border-radius: 2px;
    font-size: 11px;
    color: #333;
}

.returns-display {
    font-size: 13px;
    font-weight: 700;
    color: #0066cc;
}

.btn-primary,
.btn-secondary {
    padding: 8px;
    border-radius: 2px;
    border: none;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-primary {
    background: #2d8659;
    color: white;
    margin-top: auto;
}

.btn-primary:hover {
    background: #1f5f41;
}

.btn-secondary {
    background: none;
    border: 1px solid #eee;
    color: #999;
}

.btn-secondary:hover {
    border-color: #c13584;
    color: #c13584;
}

/* ========================================
   SMALL PHONES (max 480px)
======================================== */

@media (max-width: 480px) {
    .header-matchup {
        font-size: 16px;
        gap: 8px;
    }
    
    .tab-btn {
        padding: 8px 12px;
        font-size: 10px;
    }
    
    .selection-card {
        padding: 8px 12px;
        gap: 8px;
    }
    
    .selection-name {
        font-size: 11px;
    }
}

/* ========================================
   DESKTOP (1024px+)
======================================== */

@media (min-width: 1024px) {
    .selection-card {
        padding: 12px 16px;
    }
    
    .odds-btn {
        min-height: 55px;
    }
}
</style>

<!-- JAVASCRIPT -->
<script>
(function($) {
    'use strict';
    
    window.BetfairSingleMatch = {
        
        init: function() {
            if (typeof jQuery === 'undefined') return;
            
            console.log('✓ Initializing Betfair Single Match');
            
            this.bindTabs();
            this.bindVoteButtons();
            this.bindBetslip();
        },
        
        bindTabs: function() {
            $(document).on('click', '.tab-btn', function() {
                const market = $(this).data('market');
                
                $('.tab-btn').removeClass('active');
                $(this).addClass('active');
                
                $('.market-panel').hide();
                $('#market-' + market).show();
            });
        },
        
        bindVoteButtons: function() {
            const self = this;
            
            $(document).on('click', '.betfair-vote-btn', function(e) {
                e.preventDefault();
                
                const $btn = $(this);
                const matchId = $btn.data('match-id');
                const optionId = $btn.data('option-id');
                const category = $btn.data('category');
                const side = $btn.data('side') || 'back';
                const selectionName = $btn.closest('.selection-card').find('.selection-name span:first').text();
                
                if (!matchId || !optionId) return;
                
                // Add to bet slip
                self.addToBetslip({
                    matchId: matchId,
                    optionId: optionId,
                    category: category,
                    side: side,
                    name: selectionName
                });
                
                // Highlight card
                $btn.closest('.selection-card').addClass('selected');
                
                // Submit vote
                self.submitVote(matchId, optionId);
            });
        },
        
        submitVote: function(matchId, optionId) {
            if (typeof goalv_ajax === 'undefined') return;
            
            $.ajax({
                url: goalv_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'goalv_cast_vote',
                    match_id: matchId,
                    option_id: optionId,
                    vote_location: 'details',
                    nonce: goalv_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        console.log('✓ Vote recorded');
                    }
                }
            });
        },
        
        addToBetslip: function(data) {
            const $empty = $('.betslip-empty');
            const $selections = $('.betslip-selections');
            const $form = $('.betslip-form');
            
            const selectionHtml = `
                <div class="slip-selection" data-selection-id="${data.matchId}-${data.optionId}-${data.side}">
                    <div class="slip-selection-name">
                        <span class="slip-selection-side ${data.side}">${data.side.toUpperCase()}</span>
                        ${data.name}
                    </div>
                    <div class="slip-remove">
                        <button type="button" class="remove-selection">Remove</button>
                    </div>
                </div>
            `;
            
            $selections.append(selectionHtml);
            
            if ($('.slip-selection').length > 0) {
                $empty.hide();
                $selections.show();
                $form.show();
            }
        },
        
        bindBetslip: function() {
            const self = this;
            
            $(document).on('click', '.remove-selection', function() {
                $(this).closest('.slip-selection').fadeOut(200, function() {
                    $(this).remove();
                    
                    if ($('.slip-selection').length === 0) {
                        self.clearBetslip();
                    }
                });
            });
            
            $(document).on('click', '.betslip-clear', function() {
                self.clearBetslip();
            });
            
            $(document).on('input', '.stake-input', function() {
                const stake = parseFloat($(this).val()) || 0;
                const profit = (stake * 2.50) - stake;
                $('.returns-display').text('£' + profit.toFixed(2));
            });
        },
        
        clearBetslip: function() {
            $('.betslip-selections').empty().hide();
            $('.betslip-form').hide();
            $('.betslip-empty').show();
            $('.selection-card').removeClass('selected');
        }
    };
    
    $(document).ready(function() {
        window.BetfairSingleMatch.init();
    });
    
})(jQuery);
</script>

<?php get_footer(); ?>