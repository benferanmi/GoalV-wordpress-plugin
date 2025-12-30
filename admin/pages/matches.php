<?php
/**
 * Matches Management Admin Page - FIXED
 * Full match management interface with filtering and actions
 * 
 * @package GoalV
 * @since 8.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Initialize matches admin
$matches_admin = new GoalV_Admin_Matches();

// Get filter parameters
$competition_id = isset($_GET['filter_competition']) ? intval($_GET['filter_competition']) : 0;
$status = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : '';
$date_from = isset($_GET['filter_date_from']) ? sanitize_text_field($_GET['filter_date_from']) : '';
$date_to = isset($_GET['filter_date_to']) ? sanitize_text_field($_GET['filter_date_to']) : '';
$search = isset($_GET['filter_search']) ? sanitize_text_field($_GET['filter_search']) : '';
$page = isset($_GET['paged']) ? intval($_GET['paged']) : 1;

// Get matches data
$matches_data = $matches_admin->get_matches(array(
    'competition_id' => $competition_id,
    'status' => $status,
    'date_from' => $date_from,
    'date_to' => $date_to,
    'search' => $search,
    'page' => $page,
    'per_page' => 100
));

// Get dropdown data
$competitions = $matches_admin->get_competitions_list();
$status_counts = $matches_admin->get_status_counts();
$orphaned_count = count($matches_admin->get_orphaned_matches());

// Create nonce for AJAX
$nonce = wp_create_nonce('goalv_admin_nonce');
?>

<div class="goalv-admin-section">
    <div class="goalv-section-header">
        <h2><?php _e('Matches Management', 'goalv'); ?></h2>
        <p class="description">
            <?php _e('View, edit, and manage all football matches. Use filters to find specific matches.', 'goalv'); ?>
        </p>
    </div>

    <!-- Summary Stats -->
    <div class="goalv-stats-row"
        style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px;">
        <div class="goalv-status-card">
            <div class="goalv-status-card-icon">
                <span class="dashicons dashicons-calendar-alt"></span>
            </div>
            <div class="goalv-status-card-content">
                <h4><?php _e('Total Matches', 'goalv'); ?></h4>
                <div class="goalv-status-value"><?php echo number_format($matches_data['total']); ?></div>
            </div>
        </div>

        <div class="goalv-status-card" style="border-left: 4px solid #0073aa;">
            <div class="goalv-status-card-icon" style="color: #0073aa;">
                <span class="dashicons dashicons-clock"></span>
            </div>
            <div class="goalv-status-card-content">
                <h4><?php _e('Scheduled', 'goalv'); ?></h4>
                <div class="goalv-status-value"><?php echo number_format($status_counts['scheduled']); ?></div>
            </div>
        </div>

        <div class="goalv-status-card" style="border-left: 4px solid #dc3545;">
            <div class="goalv-status-card-icon" style="color: #dc3545;">
                <span class="dashicons dashicons-controls-play"></span>
            </div>
            <div class="goalv-status-card-content">
                <h4><?php _e('Live', 'goalv'); ?></h4>
                <div class="goalv-status-value"><?php echo number_format($status_counts['live']); ?></div>
            </div>
        </div>

        <div class="goalv-status-card" style="border-left: 4px solid #28a745;">
            <div class="goalv-status-card-icon" style="color: #28a745;">
                <span class="dashicons dashicons-yes-alt"></span>
            </div>
            <div class="goalv-status-card-content">
                <h4><?php _e('Finished', 'goalv'); ?></h4>
                <div class="goalv-status-value"><?php echo number_format($status_counts['finished']); ?></div>
            </div>
        </div>

        <?php if ($orphaned_count > 0): ?>
            <div class="goalv-status-card" style="border-left: 4px solid #ffc107;">
                <div class="goalv-status-card-icon" style="color: #ffc107;">
                    <span class="dashicons dashicons-warning"></span>
                </div>
                <div class="goalv-status-card-content">
                    <h4><?php _e('Orphaned', 'goalv'); ?></h4>
                    <div class="goalv-status-value"><?php echo number_format($orphaned_count); ?></div>
                    <div class="goalv-status-meta"><?php _e('No vote options', 'goalv'); ?></div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Filters Bar -->
    <div class="goalv-card" style="margin-bottom: 20px;">
        <form method="get" id="matches-filter-form"
            style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; align-items: end;">
            <input type="hidden" name="page" value="goalv-settings" />
            <input type="hidden" name="tab" value="matches" />

            <div>
                <label for="filter_competition"><?php _e('Competition', 'goalv'); ?></label>
                <select name="filter_competition" id="filter_competition" class="widefat">
                    <option value=""><?php _e('All Competitions', 'goalv'); ?></option>
                    <?php foreach ($competitions as $comp): ?>
                        <option value="<?php echo esc_attr($comp->id); ?>" <?php selected($competition_id, $comp->id); ?>>
                            <?php echo esc_html($comp->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="filter_status"><?php _e('Status', 'goalv'); ?></label>
                <select name="filter_status" id="filter_status" class="widefat">
                    <option value=""><?php _e('All Statuses', 'goalv'); ?></option>
                    <option value="scheduled" <?php selected($status, 'scheduled'); ?>>
                        <?php _e('Scheduled', 'goalv'); ?></option>
                    <option value="live" <?php selected($status, 'live'); ?>><?php _e('Live', 'goalv'); ?></option>
                    <option value="finished" <?php selected($status, 'finished'); ?>><?php _e('Finished', 'goalv'); ?>
                    </option>
                    <option value="postponed" <?php selected($status, 'postponed'); ?>>
                        <?php _e('Postponed', 'goalv'); ?></option>
                    <option value="cancelled" <?php selected($status, 'cancelled'); ?>>
                        <?php _e('Cancelled', 'goalv'); ?></option>
                </select>
            </div>

            <div>
                <label for="filter_date_from"><?php _e('Date From', 'goalv'); ?></label>
                <input type="date" name="filter_date_from" id="filter_date_from"
                    value="<?php echo esc_attr($date_from); ?>" class="widefat" />
            </div>

            <div>
                <label for="filter_date_to"><?php _e('Date To', 'goalv'); ?></label>
                <input type="date" name="filter_date_to" id="filter_date_to" value="<?php echo esc_attr($date_to); ?>"
                    class="widefat" />
            </div>

            <div>
                <label for="filter_search"><?php _e('Search Teams', 'goalv'); ?></label>
                <input type="text" name="filter_search" id="filter_search" value="<?php echo esc_attr($search); ?>"
                    placeholder="<?php _e('Team name...', 'goalv'); ?>" class="widefat" />
            </div>

            <div style="display: flex; gap: 10px;">
                <button type="submit" class="button button-primary">
                    <span class="dashicons dashicons-filter"></span>
                    <?php _e('Filter', 'goalv'); ?>
                </button>
                <a href="<?php echo admin_url('admin.php?page=goalv-settings&tab=matches'); ?>" class="button">
                    <?php _e('Reset', 'goalv'); ?>
                </a>
            </div>
        </form>
    </div>

    <!-- Bulk Actions Bar -->
    <div class="goalv-card" style="margin-bottom: 20px;">
        <div class="goalv-actions-bar" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <select id="bulk-action-select" class="widefat" style="width: auto; min-width: 180px;">
                <option value=""><?php _e('Bulk Actions', 'goalv'); ?></option>
                <option value="delete"><?php _e('Delete Selected', 'goalv'); ?></option>
                <option value="status_scheduled"><?php _e('Set Status: Scheduled', 'goalv'); ?></option>
                <option value="status_live"><?php _e('Set Status: Live', 'goalv'); ?></option>
                <option value="status_finished"><?php _e('Set Status: Finished', 'goalv'); ?></option>
                <option value="status_postponed"><?php _e('Set Status: Postponed', 'goalv'); ?></option>
                <option value="status_cancelled"><?php _e('Set Status: Cancelled', 'goalv'); ?></option>
                <option value="resync"><?php _e('Resync from API', 'goalv'); ?></option>
            </select>

            <button type="button" id="apply-bulk-action" class="button" disabled>
                <?php _e('Apply', 'goalv'); ?>
            </button>

            <span style="margin-left: auto; display: flex; gap: 10px;">
                <?php if ($orphaned_count > 0): ?>
                    <button type="button" id="fix-orphaned-btn" class="button button-secondary">
                        <span class="dashicons dashicons-admin-tools"></span>
                        <?php printf(__('Fix %d Orphaned', 'goalv'), $orphaned_count); ?>
                    </button>
                <?php endif; ?>

                <button type="button" id="export-csv-btn" class="button">
                    <span class="dashicons dashicons-download"></span>
                    <?php _e('Export CSV', 'goalv'); ?>
                </button>
            </span>

            <span class="spinner" id="matches-spinner"></span>
        </div>

        <div id="matches-message" style="margin-top: 15px;"></div>
    </div>

    <!-- Matches Table -->
    <div class="goalv-card">
        <table class="wp-list-table widefat fixed striped" id="matches-table">
            <thead>
                <tr>
                    <td class="check-column">
                        <input type="checkbox" id="select-all-matches" />
                    </td>
                    <th style="width: 60px;"><?php _e('ID', 'goalv'); ?></th>
                    <th style="width: 80px;"><?php _e('Comp', 'goalv'); ?></th>
                    <th><?php _e('Match', 'goalv'); ?></th>
                    <th style="width: 100px; text-align: center;"><?php _e('Score', 'goalv'); ?></th>
                    <th style="width: 150px;"><?php _e('Date', 'goalv'); ?></th>
                    <th style="width: 100px;"><?php _e('Status', 'goalv'); ?></th>
                    <th style="width: 80px; text-align: center;"><?php _e('Votes', 'goalv'); ?></th>
                    <th style="width: 200px;"><?php _e('Actions', 'goalv'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($matches_data['matches'])): ?>
                    <tr>
                        <td colspan="9" class="no-items">
                            <?php _e('No matches found. Try adjusting your filters.', 'goalv'); ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($matches_data['matches'] as $match):
                        $stats = $matches_admin->get_match_statistics($match->id);
                        ?>
                        <tr data-match-id="<?php echo esc_attr($match->id); ?>">
                            <th class="check-column">
                                <input type="checkbox" class="match-checkbox" value="<?php echo esc_attr($match->id); ?>" />
                            </th>

                            <td><strong>#<?php echo esc_html($match->id); ?></strong></td>

                            <td>
                                <?php if ($match->competition_logo): ?>
                                    <img src="<?php echo esc_url($match->competition_logo); ?>"
                                        alt="<?php echo esc_attr($match->competition_name); ?>"
                                        title="<?php echo esc_attr($match->competition_name); ?>"
                                        style="width: 24px; height: 24px; object-fit: contain;" />
                                <?php else: ?>
                                    <span class="dashicons dashicons-awards"></span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <?php if ($match->home_team_logo): ?>
                                        <img src="<?php echo esc_url($match->home_team_logo); ?>"
                                            style="width: 20px; height: 20px; object-fit: contain;" />
                                    <?php endif; ?>
                                    <strong><?php echo esc_html($match->home_team_name); ?></strong>
                                </div>
                                <div style="margin: 5px 0; color: #666;">vs</div>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <?php if ($match->away_team_logo): ?>
                                        <img src="<?php echo esc_url($match->away_team_logo); ?>"
                                            style="width: 20px; height: 20px; object-fit: contain;" />
                                    <?php endif; ?>
                                    <strong><?php echo esc_html($match->away_team_name); ?></strong>
                                </div>
                            </td>

                            <td style="text-align: center;">
                                <?php if ($match->home_score !== null && $match->away_score !== null): ?>
                                    <strong style="font-size: 18px;">
                                        <?php echo esc_html($match->home_score); ?> - <?php echo esc_html($match->away_score); ?>
                                    </strong>
                                    <?php if ($match->status === 'live' && $match->match_minute): ?>
                                        <div class="goalv-match-minute"><?php echo esc_html($match->match_minute); ?>'</div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="description">-</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <div style="font-size: 13px;">
                                    <?php echo date('M j, Y', strtotime($match->match_date)); ?><br />
                                    <span class="description"><?php echo date('g:i A', strtotime($match->match_date)); ?></span>
                                </div>
                            </td>

                            <td>
                                <span class="goalv-status-badge goalv-status-<?php echo esc_attr($match->status); ?>">
                                    <?php echo esc_html(ucfirst($match->status)); ?>
                                </span>
                            </td>

                            <td style="text-align: center;">
                                <?php if ($stats['has_votes']): ?>
                                    <strong style="font-size: 16px;"><?php echo number_format($stats['total_votes']); ?></strong>
                                <?php else: ?>
                                    <span class="description">0</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <button type="button" class="button button-small view-details-btn"
                                    data-match-id="<?php echo esc_attr($match->id); ?>"
                                    title="<?php _e('View Details', 'goalv'); ?>">
                                    <span class="dashicons dashicons-visibility"></span>
                                </button>

                                <button type="button" class="button button-small resync-match-btn"
                                    data-match-id="<?php echo esc_attr($match->id); ?>"
                                    title="<?php _e('Refresh from API', 'goalv'); ?>">
                                    <span class="dashicons dashicons-update"></span>
                                </button>

                                <button type="button" class="button button-small button-link-delete delete-match-btn"
                                    data-match-id="<?php echo esc_attr($match->id); ?>"
                                    title="<?php _e('Delete Match', 'goalv'); ?>">
                                    <span class="dashicons dashicons-trash"></span>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <?php if ($matches_data['pages'] > 1): ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <span class="displaying-num">
                        <?php printf(__('%s matches', 'goalv'), number_format($matches_data['total'])); ?>
                    </span>
                    <?php
                    $base_url = admin_url('admin.php?page=goalv-settings&tab=matches');
                    if ($competition_id)
                        $base_url .= '&filter_competition=' . $competition_id;
                    if ($status)
                        $base_url .= '&filter_status=' . $status;
                    if ($date_from)
                        $base_url .= '&filter_date_from=' . $date_from;
                    if ($date_to)
                        $base_url .= '&filter_date_to=' . $date_to;
                    if ($search)
                        $base_url .= '&filter_search=' . urlencode($search);

                    echo paginate_links(array(
                        'base' => $base_url . '&paged=%#%',
                        'format' => '',
                        'current' => $page,
                        'total' => $matches_data['pages'],
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;'
                    ));
                    ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Match Details Modal -->
<div id="match-details-modal" class="goalv-modal" style="display: none;">
    <div class="goalv-modal-content">
        <span class="goalv-modal-close">&times;</span>
        <div id="match-details-content">
            <div style="text-align: center; padding: 40px;">
                <span class="spinner is-active"></span>
            </div>
        </div>
    </div>
</div>

<style>
    .goalv-modal {
        display: none;
        position: fixed;
        z-index: 999999;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.7);
    }

    .goalv-modal-content {
        background-color: #fff;
        margin: 5% auto;
        padding: 0;
        border-radius: 8px;
        width: 90%;
        max-width: 800px;
        max-height: 80vh;
        overflow-y: auto;
        position: relative;
    }

    .goalv-modal-close {
        position: absolute;
        right: 20px;
        top: 20px;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
        z-index: 1;
    }

    .goalv-modal-close:hover {
        color: #dc3545;
    }

    #match-details-content {
        padding: 30px;
    }
</style>

<!-- MATCHES PAGE INITIALIZATION -->
<script type="text/javascript">
    (function ($) {
        'use strict';

        $(document).ready(function () {
            console.log('Matches page ready, initializing...');

            // Verify dependencies
            if (!window.GoalV || !window.GoalV.Matches) {
                console.error('ERROR: GoalV.Matches not loaded!');
                return;
            }

            console.log('✓ GoalV.Matches available, initializing...');

            // Initialize
            window.GoalV.Matches.init();
            console.log('✓ Matches page initialized');
        });
    })(jQuery);
</script>