<?php
/**
 * GoalV Admin Sync Manager Module - FIXED VERSION
 * 
 * CRITICAL FIX:
 * - Fixed sync log display using correct 'started_at' column instead of 'created_at'
 * - Fixed "No details available" issue - now shows proper log information
 * 
 * @package GoalV
 * @subpackage Admin
 * @since 8.1.2
 */

if (!defined('ABSPATH')) {
    exit;
}

class GoalV_Admin_Sync
{
    /**
     * Constructor
     */
    public function __construct()
    {
        // Register AJAX handlers
        add_action('wp_ajax_goalv_manual_sync_competitions', array($this, 'ajax_manual_sync_competitions'));
        add_action('wp_ajax_goalv_manual_sync_matches', array($this, 'ajax_manual_sync_matches'));
        add_action('wp_ajax_goalv_force_full_sync', array($this, 'ajax_force_full_sync'));
        add_action('wp_ajax_goalv_get_sync_logs', array($this, 'ajax_get_sync_logs'));
        add_action('wp_ajax_goalv_toggle_live_sync', array($this, 'ajax_toggle_live_sync'));
        add_action('wp_ajax_goalv_get_live_matches', array($this, 'ajax_get_live_matches'));
    }

    /**
     * Render sync manager page
     */
    public function render()
    {
        $api_key = get_option('goalv_api_football_key', '');
        $live_sync_enabled = get_option('goalv_enable_live_sync', true);
        ?>

        <div class="goalv-admin-section">
            <h2><?php _e('Sync Manager', 'goalv'); ?></h2>
            <p class="description">
                <?php _e('Control manual syncing and monitor autonomous background sync system.', 'goalv'); ?>
            </p>

            <?php if (empty($api_key)): ?>
                <!-- API Key Required Notice -->
                <div class="notice notice-warning">
                    <p>
                        <strong><?php _e('API Key Required', 'goalv'); ?></strong><br>
                        <?php
                        printf(
                            __('Please configure your API-Football key in %sAPI Settings%s first.', 'goalv'),
                            '<a href="' . admin_url('admin.php?page=goalv-settings&tab=api-settings') . '">',
                            '</a>'
                        );
                        ?>
                    </p>
                </div>
            <?php else: ?>

                <!-- Manual Sync Controls -->
                <div class="goalv-sync-controls" style="margin-bottom: 30px;">
                    <h3><?php _e('Manual Sync Actions', 'goalv'); ?></h3>

                    <div class="goalv-sync-buttons" style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <button type="button" id="sync-competitions-btn" class="button button-primary">
                            <span class="dashicons dashicons-download"></span>
                            <?php _e('Fetch Competitions', 'goalv'); ?>
                        </button>

                        <button type="button" id="sync-matches-btn" class="button button-primary">
                            <span class="dashicons dashicons-update"></span>
                            <?php _e('Sync Matches (Next 7 Days)', 'goalv'); ?>
                        </button>

                        <button type="button" id="force-full-sync-btn" class="button button-secondary">
                            <span class="dashicons dashicons-admin-generic"></span>
                            <?php _e('Force Full Resync', 'goalv'); ?>
                        </button>

                        <span id="sync-loader" class="spinner"></span>
                    </div>

                    <div id="sync-result" style="margin-top: 15px;"></div>

                    <p class="description" style="margin-top: 10px;">
                        <?php _e('Manual sync actions trigger immediate data fetch. Normally, the system syncs automatically every hour.', 'goalv'); ?>
                    </p>
                </div>

                <!-- Live Sync Status -->
                <div class="goalv-live-sync-status"
                    style="margin-bottom: 30px; padding: 15px; background: #f9f9f9; border-radius: 4px;">
                    <h3><?php _e('Live Score Updates', 'goalv'); ?></h3>

                    <div style="display: flex; align-items: center; gap: 15px;">
                        <div class="goalv-live-sync-toggle">
                            <label class="goalv-toggle-switch">
                                <input type="checkbox" id="toggle-live-sync" <?php checked($live_sync_enabled, true); ?>>
                                <span class="goalv-toggle-slider"></span>
                            </label>
                        </div>
                        <div>
                            <strong><?php echo $live_sync_enabled ? __('Live Sync: ENABLED', 'goalv') : __('Live Sync: DISABLED', 'goalv'); ?></strong>
                            <p class="description" style="margin: 5px 0 0 0;">
                                <?php _e('Updates live matches every 30 seconds. Disable to reduce API usage.', 'goalv'); ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Sync Status Dashboard -->
                <div class="goalv-sync-status-dashboard" style="margin-bottom: 30px;">
                    <h3>
                        <?php _e('Sync Status', 'goalv'); ?>
                        <button type="button" id="refresh-sync-status" class="button button-small button-secondary"
                            style="margin-left: 10px;">
                            <span class="dashicons dashicons-update"></span>
                            <?php _e('Refresh', 'goalv'); ?>
                        </button>
                    </h3>

                    <?php $this->render_sync_status_cards(); ?>
                </div>

                <!-- Live Matches Monitor -->
                <?php if ($live_sync_enabled): ?>
                    <div class="goalv-live-matches-monitor" style="margin-bottom: 30px;">
                        <h3>
                            <?php _e('Live Matches Monitor', 'goalv'); ?>
                            <button type="button" id="refresh-live-matches" class="button button-small button-secondary"
                                style="margin-left: 10px;">
                                <span class="dashicons dashicons-update"></span>
                                <?php _e('Refresh', 'goalv'); ?>
                            </button>
                        </h3>

                        <div id="live-matches-container">
                            <?php $this->render_live_matches(); ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Sync Logs -->
                <div class="goalv-sync-logs" style="margin-bottom: 30px;">
                    <h3>
                        <?php _e('Recent Sync Logs', 'goalv'); ?>
                        <button type="button" id="refresh-sync-logs" class="button button-small button-secondary"
                            style="margin-left: 10px;">
                            <span class="dashicons dashicons-update"></span>
                            <?php _e('Refresh', 'goalv'); ?>
                        </button>
                        <button type="button" id="clear-sync-logs" class="button button-small button-link-delete"
                            style="margin-left: 10px;">
                            <?php _e('Clear Logs', 'goalv'); ?>
                        </button>
                    </h3>

                    <div id="sync-logs-container">
                        <?php $this->render_sync_logs(); ?>
                    </div>
                </div>

            <?php endif; ?>

            <!-- Cron Job Information -->
            <div class="goalv-cron-info" style="padding: 15px; background: #f9f9f9; border-left: 4px solid #0073aa;">
                <h4><?php _e('Autonomous Sync Schedule', 'goalv'); ?></h4>
                <ul>
                    <li><strong><?php _e('Every 30 seconds:', 'goalv'); ?></strong>
                        <?php _e('Live score updates (when enabled)', 'goalv'); ?></li>
                    <li><strong><?php _e('Every hour:', 'goalv'); ?></strong>
                        <?php _e('Full competition and match sync', 'goalv'); ?></li>
                    <li><strong><?php _e('Daily at 3 AM:', 'goalv'); ?></strong>
                        <?php _e('Cleanup old logs and archived matches', 'goalv'); ?></li>
                </ul>

                <?php $this->render_cron_status(); ?>
            </div>
        </div>

        <?php
    }

    /**
     * Render sync status cards
     */
    private function render_sync_status_cards()
    {
        // Get last sync times from options
        $last_competition_sync = get_option('goalv_last_competition_sync', '');
        $last_match_sync = get_option('goalv_last_match_sync', '');
        $last_live_sync = get_option('goalv_last_live_sync', '');

        // Get competition and match counts
        $competition_model = new GoalV_Competition();
        $match_model = new GoalV_Match();

        $active_competitions = count($competition_model->get_active());
        $total_matches = $match_model->count_all();
        $upcoming_matches = $match_model->count_by_status('upcoming');

        ?>
        <div class="goalv-status-cards"
            style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">

            <!-- Last Competition Sync -->
            <div class="goalv-status-card">
                <div class="goalv-status-card-icon">
                    <span class="dashicons dashicons-awards"></span>
                </div>
                <div class="goalv-status-card-content">
                    <h4><?php _e('Competitions', 'goalv'); ?></h4>
                    <p class="goalv-status-value"><?php echo esc_html($active_competitions); ?>         <?php _e('Active', 'goalv'); ?>
                    </p>
                    <p class="goalv-status-meta">
                        <?php
                        if ($last_competition_sync) {
                            echo sprintf(__('Last sync: %s', 'goalv'), human_time_diff(strtotime($last_competition_sync), current_time('timestamp')) . ' ' . __('ago', 'goalv'));
                        } else {
                            _e('Never synced', 'goalv');
                        }
                        ?>
                    </p>
                </div>
            </div>

            <!-- Matches -->
            <div class="goalv-status-card">
                <div class="goalv-status-card-icon">
                    <span class="dashicons dashicons-calendar-alt"></span>
                </div>
                <div class="goalv-status-card-content">
                    <h4><?php _e('Matches', 'goalv'); ?></h4>
                    <p class="goalv-status-value"><?php echo esc_html($total_matches); ?>         <?php _e('Total', 'goalv'); ?></p>
                    <p class="goalv-status-meta">
                        <?php echo esc_html($upcoming_matches); ?>         <?php _e('upcoming', 'goalv'); ?>
                    </p>
                </div>
            </div>

            <!-- Last Match Sync -->
            <div class="goalv-status-card">
                <div class="goalv-status-card-icon">
                    <span class="dashicons dashicons-update"></span>
                </div>
                <div class="goalv-status-card-content">
                    <h4><?php _e('Last Match Sync', 'goalv'); ?></h4>
                    <p class="goalv-status-value">
                        <?php
                        if ($last_match_sync) {
                            echo human_time_diff(strtotime($last_match_sync), current_time('timestamp')) . ' ' . __('ago', 'goalv');
                        } else {
                            _e('Never', 'goalv');
                        }
                        ?>
                    </p>
                    <p class="goalv-status-meta">
                        <?php
                        if ($last_match_sync) {
                            echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($last_match_sync));
                        }
                        ?>
                    </p>
                </div>
            </div>

            <!-- Last Live Sync -->
            <div class="goalv-status-card">
                <div class="goalv-status-card-icon">
                    <span class="dashicons dashicons-video-alt3"></span>
                </div>
                <div class="goalv-status-card-content">
                    <h4><?php _e('Last Live Update', 'goalv'); ?></h4>
                    <p class="goalv-status-value">
                        <?php
                        if ($last_live_sync) {
                            echo human_time_diff(strtotime($last_live_sync), current_time('timestamp')) . ' ' . __('ago', 'goalv');
                        } else {
                            _e('Never', 'goalv');
                        }
                        ?>
                    </p>
                    <p class="goalv-status-meta">
                        <?php echo get_option('goalv_enable_live_sync', true) ? __('Active', 'goalv') : __('Disabled', 'goalv'); ?>
                    </p>
                </div>
            </div>

        </div>
        <?php
    }

    /**
     * Render live matches
     */
    private function render_live_matches()
    {
        // Use GoalV_Match model to get live matches
        $match_model = new GoalV_Match();
        $live_matches = $match_model->get_by_status('live');

        if (empty($live_matches)) {
            echo '<p class="description">' . __('No live matches at the moment.', 'goalv') . '</p>';
            return;
        }

        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Match', 'goalv'); ?></th>
                    <th><?php _e('Competition', 'goalv'); ?></th>
                    <th><?php _e('Score', 'goalv'); ?></th>
                    <th><?php _e('Status', 'goalv'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($live_matches as $match): ?>
                    <?php
                    // Get team objects
                    $home_team = $match->get_home_team();
                    $away_team = $match->get_away_team();

                    // Extract team names safely
                    $home_name = is_object($home_team) ? $home_team->name : 'Unknown';
                    $away_name = is_object($away_team) ? $away_team->name : 'Unknown';

                    // Get competition
                    $competition = $match->get_competition();
                    $comp_name = is_object($competition) ? $competition->name : 'Unknown';
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($home_name . ' vs ' . $away_name); ?></strong>
                        </td>
                        <td><?php echo esc_html($comp_name); ?></td>
                        <td>
                            <span class="goalv-live-score" style="font-weight: bold; color: #d63638;">
                                <?php echo esc_html($match->home_score . ' - ' . $match->away_score); ?>
                            </span>
                        </td>
                        <td>
                            <span class="goalv-status-badge goalv-status-live"
                                style="background: #d63638; color: white; padding: 3px 8px; border-radius: 3px;">
                                <span class="dashicons dashicons-video-alt3" style="font-size: 14px; line-height: 1.2;"></span>
                                <?php _e('LIVE', 'goalv'); ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Render sync logs - FIXED VERSION
     * CRITICAL FIX: Now uses 'started_at' instead of 'created_at'
     * CRITICAL FIX: Improved "No details available" logic
     */
    private function render_sync_logs()
    {
        global $wpdb;
        $logs_table = $wpdb->prefix . 'goalv_sync_logs';

        // FIXED: Use 'started_at' column (correct column name)
        $logs = $wpdb->get_results(
            "SELECT * FROM $logs_table ORDER BY started_at DESC LIMIT 20"
        );

        if (empty($logs)) {
            echo '<p class="description">' . __('No sync logs found.', 'goalv') . '</p>';
            return;
        }

        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 150px;"><?php _e('Time', 'goalv'); ?></th>
                    <th style="width: 120px;"><?php _e('Type', 'goalv'); ?></th>
                    <th style="width: 100px;"><?php _e('Status', 'goalv'); ?></th>
                    <th><?php _e('Details', 'goalv'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td>
                            <?php
                            // FIXED: Use 'started_at' column
                            echo esc_html(human_time_diff(strtotime($log->started_at), current_time('timestamp')) . ' ago');
                            ?>
                        </td>
                        <td>
                            <span class="goalv-log-type">
                                <?php echo esc_html(ucfirst($log->sync_type)); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($log->status === 'success'): ?>
                                <span class="goalv-status-badge goalv-status-success">
                                    <span class="dashicons dashicons-yes-alt"></span>
                                    <?php _e('Success', 'goalv'); ?>
                                </span>
                            <?php elseif ($log->status === 'failed' || $log->status === 'error'): ?>
                                <span class="goalv-status-badge goalv-status-error">
                                    <span class="dashicons dashicons-dismiss"></span>
                                    <?php _e('Error', 'goalv'); ?>
                                </span>
                            <?php else: ?>
                                <span class="goalv-status-badge goalv-status-info">
                                    <span class="dashicons dashicons-info"></span>
                                    <?php echo esc_html(ucfirst($log->status)); ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            // FIXED: Improved details display logic
                            // Priority: error_message > numeric details > fallback message
                            if (!empty($log->error_message)): ?>
                                <span style="color: #d63638;">
                                    <?php echo esc_html($log->error_message); ?>
                                </span>
                            <?php elseif (isset($log->items_processed) && $log->items_processed > 0): ?>
                                <?php
                                echo sprintf(
                                    '%d processed (%d created, %d updated)',
                                    $log->items_processed,
                                    $log->items_created ?? 0,
                                    $log->items_updated ?? 0
                                );
                                ?>
                            <?php else: ?>
                                <span class="description">
                                    <?php
                                    // Show sync type specific message
                                    if ($log->sync_type === 'live_scores') {
                                        _e('No live matches at sync time', 'goalv');
                                    } elseif ($log->sync_type === 'info') {
                                        _e('Information log', 'goalv');
                                    } else {
                                        _e('Sync completed', 'goalv');
                                    }
                                    ?>
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Render cron status
     */
    private function render_cron_status()
    {
        $crons = _get_cron_array();
        $goalv_crons = array(
            'goalv_live_sync' => __('Live Score Updates (30 sec)', 'goalv'),
            'goalv_hourly_sync' => __('Hourly Full Sync', 'goalv'),
            'goalv_daily_cleanup' => __('Daily Cleanup', 'goalv')
        );

        echo '<h4>' . __('Scheduled Tasks Status', 'goalv') . '</h4>';
        echo '<ul>';

        foreach ($goalv_crons as $hook => $label) {
            $scheduled = false;
            foreach ($crons as $timestamp => $cron) {
                if (isset($cron[$hook])) {
                    $scheduled = true;
                    break;
                }
            }

            if ($scheduled) {
                echo '<li><span class="dashicons dashicons-yes-alt" style="color: green;"></span> ' . esc_html($label) . ': <strong>' . __('Scheduled', 'goalv') . '</strong></li>';
            } else {
                echo '<li><span class="dashicons dashicons-dismiss" style="color: red;"></span> ' . esc_html($label) . ': <strong>' . __('Not Scheduled', 'goalv') . '</strong></li>';
            }
        }

        echo '</ul>';
    }

    /**
     * AJAX: Manual sync competitions
     */
    public function ajax_manual_sync_competitions()
    {
        check_ajax_referer('goalv_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'goalv'));
        }

        try {
            $sync_manager = new GoalV_Sync_Manager();
            $result = $sync_manager->sync_competitions();

            if ($result['success']) {
                update_option('goalv_last_competition_sync', current_time('mysql'));
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result['message']);
            }
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * AJAX: Manual sync matches
     */
    public function ajax_manual_sync_matches()
    {
        check_ajax_referer('goalv_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'goalv'));
        }

        try {
            $api_matches = new GoalV_API_Matches();
            $result = $api_matches->sync_all_competitions_matches(
                date('Y-m-d'),
                date('Y-m-d', strtotime('+7 days'))
            );

            if ($result['success']) {
                update_option('goalv_last_match_sync', current_time('mysql'));
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result['message']);
            }
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * AJAX: Force full sync with comprehensive error handling
     */
    public function ajax_force_full_sync()
    {
        check_ajax_referer('goalv_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'goalv'));
        }

        try {
            error_log('GoalV: ADMIN - Force full sync triggered');

            $sync_manager = new GoalV_Sync_Manager();

            // Clear cache before sync
            $api_client = new GoalV_API_Football_Client();
            $api_client->clear_cache();
            error_log('GoalV: Cache cleared before sync');

            // Run full sync
            $result = $sync_manager->sync_all();

            error_log('GoalV: Full sync result: ' . wp_json_encode($result));

            if ($result['success']) {
                wp_send_json_success(array(
                    'message' => $result['message'] ?? 'Full sync completed successfully',
                    'duration' => $result['duration'] ?? 'unknown',
                    'matches_processed' => $result['matches']['processed'] ?? 0,
                    'created' => $result['matches']['created'] ?? 0,
                    'updated' => $result['matches']['updated'] ?? 0
                ));
            } else {
                $error_msg = isset($result['errors']) && is_array($result['errors'])
                    ? implode('; ', $result['errors'])
                    : ($result['message'] ?? 'Sync failed');

                wp_send_json_error($error_msg);
            }

        } catch (Exception $e) {
            error_log('GoalV: Force full sync exception - ' . $e->getMessage());
            error_log('GoalV: Stack trace: ' . $e->getTraceAsString());
            wp_send_json_error('Exception: ' . $e->getMessage());
        }
    }

    /**
     * AJAX: Get sync logs
     */
    public function ajax_get_sync_logs()
    {
        check_ajax_referer('goalv_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'goalv'));
        }

        ob_start();
        $this->render_sync_logs();
        $html = ob_get_clean();

        wp_send_json_success(array('html' => $html));
    }

    /**
     * AJAX: Toggle live sync
     */
    public function ajax_toggle_live_sync()
    {
        check_ajax_referer('goalv_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'goalv'));
        }

        $enabled = isset($_POST['enabled']) ? (bool) $_POST['enabled'] : false;

        update_option('goalv_enable_live_sync', $enabled);

        // Enable/disable the cron job
        $scheduler = new GoalV_Sync_Scheduler();
        $scheduler->toggle_live_sync($enabled);

        wp_send_json_success(array(
            'message' => $enabled ? __('Live sync enabled', 'goalv') : __('Live sync disabled', 'goalv'),
            'enabled' => $enabled
        ));
    }

    /**
     * AJAX: Get live matches
     */
    public function ajax_get_live_matches()
    {
        check_ajax_referer('goalv_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'goalv'));
        }

        ob_start();
        $this->render_live_matches();
        $html = ob_get_clean();

        wp_send_json_success(array('html' => $html));
    }
}