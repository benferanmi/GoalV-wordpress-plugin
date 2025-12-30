<?php

// class goalv admin system<?php
/**
 * GoalV Admin System Info Module
 * Handles system health and diagnostics
 * 
 * @package GoalV
 * @subpackage Admin
 * @since 8.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class GoalV_Admin_System
{
    /**
     * Constructor
     */
    public function __construct()
    {
        // Register AJAX handlers
        add_action('wp_ajax_goalv_check_system_health', array($this, 'ajax_check_system_health'));
        add_action('wp_ajax_goalv_test_cron_jobs', array($this, 'ajax_test_cron_jobs'));
        add_action('wp_ajax_goalv_clear_cache', array($this, 'ajax_clear_cache'));
    }

    /**
     * Render system info page
     */
    public function render()
    {
        ?>
        <div class="goalv-admin-section">
            <h2><?php _e('System Information', 'goalv'); ?></h2>
            <p class="description">
                <?php _e('Monitor plugin health, database status, and system diagnostics.', 'goalv'); ?>
            </p>

            <!-- System Health Check -->
            <div class="goalv-system-health" style="margin-bottom: 30px;">
                <h3>
                    <?php _e('System Health', 'goalv'); ?>
                    <button type="button" id="run-health-check" class="button button-secondary" style="margin-left: 10px;">
                        <span class="dashicons dashicons-admin-tools"></span>
                        <?php _e('Run Health Check', 'goalv'); ?>
                    </button>
                    <span id="health-check-loader" class="spinner"></span>
                </h3>

                <div id="health-check-results">
                    <?php $this->render_health_status(); ?>
                </div>
            </div>

            <!-- Database Status -->
            <div class="goalv-database-status" style="margin-bottom: 30px;">
                <h3><?php _e('Database Status', 'goalv'); ?></h3>
                <?php $this->render_database_status(); ?>
            </div>

            <!-- Cron Jobs Status -->
            <div class="goalv-cron-status" style="margin-bottom: 30px;">
                <h3>
                    <?php _e('Scheduled Tasks (WP-Cron)', 'goalv'); ?>
                    <button type="button" id="test-cron-jobs" class="button button-secondary" style="margin-left: 10px;">
                        <span class="dashicons dashicons-clock"></span>
                        <?php _e('Test Cron', 'goalv'); ?>
                    </button>
                </h3>
                <?php $this->render_cron_status(); ?>
            </div>

            <!-- Plugin Information -->
            <div class="goalv-plugin-info" style="margin-bottom: 30px;">
                <h3><?php _e('Plugin Information', 'goalv'); ?></h3>
                <?php $this->render_plugin_info(); ?>
            </div>

            <!-- Cache Management -->
            <div class="goalv-cache-management">
                <h3><?php _e('Cache Management', 'goalv'); ?></h3>
                <p class="description"><?php _e('Clear cached API responses and transients.', 'goalv'); ?></p>

                <button type="button" id="clear-cache-btn" class="button button-secondary">
                    <span class="dashicons dashicons-trash"></span>
                    <?php _e('Clear All Cache', 'goalv'); ?>
                </button>
                <span id="cache-clear-loader" class="spinner"></span>
                <div id="cache-clear-result" style="margin-top: 10px;"></div>
            </div>
        </div>
        <?php
    }

    /**
     * Render health status
     */
    private function render_health_status()
    {
        $health_checks = $this->run_health_checks();
        $all_passed = true;

        ?>
        <div class="goalv-health-checks">
            <?php foreach ($health_checks as $check): ?>
                <?php
                $status_class = $check['passed'] ? 'goalv-health-pass' : 'goalv-health-fail';
                $icon = $check['passed'] ? 'dashicons-yes-alt' : 'dashicons-dismiss';
                if (!$check['passed'])
                    $all_passed = false;
                ?>
                <div class="goalv-health-check-item <?php echo esc_attr($status_class); ?>">
                    <span class="dashicons <?php echo esc_attr($icon); ?>"></span>
                    <div class="goalv-health-check-content">
                        <strong><?php echo esc_html($check['label']); ?></strong>
                        <p><?php echo esc_html($check['message']); ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($all_passed): ?>
            <div class="notice notice-success inline" style="margin-top: 15px;">
                <p><strong><?php _e('All system checks passed!', 'goalv'); ?></strong></p>
            </div>
        <?php else: ?>
            <div class="notice notice-warning inline" style="margin-top: 15px;">
                <p><strong><?php _e('Some system checks failed. Please review and fix the issues above.', 'goalv'); ?></strong></p>
            </div>
        <?php endif; ?>
    <?php
    }

    /**
     * Run health checks
     */
    private function run_health_checks()
    {
        $checks = array();

        // Check 1: API Key configured
        $api_key = get_option('goalv_api_football_key', '');
        $checks[] = array(
            'label' => __('API Key Configuration', 'goalv'),
            'passed' => !empty($api_key),
            'message' => !empty($api_key) ? __('API key is configured', 'goalv') : __('API key is missing', 'goalv')
        );

        // Check 2: Database tables exist
        global $wpdb;
        $required_tables = array(
            'goalv_competitions',
            'goalv_teams',
            'goalv_matches',
            'goalv_sync_logs',
            'goalv_votes',
            'goalv_vote_options'
        );

        $tables_exist = true;
        $missing_tables = array();

        foreach ($required_tables as $table) {
            $table_name = $wpdb->prefix . $table;
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
                $tables_exist = false;
                $missing_tables[] = $table;
            }
        }

        $checks[] = array(
            'label' => __('Database Tables', 'goalv'),
            'passed' => $tables_exist,
            'message' => $tables_exist ?
                sprintf(__('All %d required tables exist', 'goalv'), count($required_tables)) :
                sprintf(__('Missing tables: %s', 'goalv'), implode(', ', $missing_tables))
        );

        // Check 3: Cron jobs scheduled
        $crons = _get_cron_array();
        $goalv_crons = array('goalv_live_score_sync', 'goalv_hourly_sync', 'goalv_daily_cleanup');
        $crons_scheduled = 0;

        foreach ($crons as $timestamp => $cron) {
            foreach ($goalv_crons as $hook) {
                if (isset($cron[$hook])) {
                    $crons_scheduled++;
                    break;
                }
            }
        }

        $checks[] = array(
            'label' => __('Scheduled Tasks', 'goalv'),
            'passed' => $crons_scheduled >= 2,
            'message' => sprintf(__('%d out of 3 cron jobs scheduled', 'goalv'), $crons_scheduled)
        );

        // Check 4: Active competitions
        $competition_model = new GoalV_Competition();
        $active_count = count($competition_model->get_active());

        $checks[] = array(
            'label' => __('Active Competitions', 'goalv'),
            'passed' => $active_count > 0,
            'message' => sprintf(__('%d competition(s) enabled', 'goalv'), $active_count)
        );

        return $checks;
    }

    /**
     * Render database status
     */
    private function render_database_status()
    {
        global $wpdb;

        $tables = array(
            'goalv_competitions' => __('Competitions', 'goalv'),
            'goalv_teams' => __('Teams', 'goalv'),
            'goalv_matches' => __('Matches', 'goalv'),
            'goalv_sync_logs' => __('Sync Logs', 'goalv'),
            'goalv_votes' => __('Votes', 'goalv'),
            'goalv_vote_options' => __('Vote Options', 'goalv'),
            'goalv_vote_categories' => __('Vote Categories', 'goalv'),
            'goalv_vote_summary' => __('Vote Summary', 'goalv')
        );

        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Table', 'goalv'); ?></th>
                    <th><?php _e('Status', 'goalv'); ?></th>
                    <th><?php _e('Rows', 'goalv'); ?></th>
                    <th><?php _e('Size', 'goalv'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tables as $table => $label): ?>
                    <?php
                    $table_name = $wpdb->prefix . $table;
                    $exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;

                    if ($exists) {
                        $row_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
                        $table_status = $wpdb->get_row("SHOW TABLE STATUS LIKE '$table_name'");
                        $size = size_format($table_status->Data_length + $table_status->Index_length, 2);
                    }
                    ?>
                    <tr>
                        <td><strong><?php echo esc_html($label); ?></strong></td>
                        <td>
                            <?php if ($exists): ?>
                                <span class="goalv-status-badge goalv-status-success">
                                    <span class="dashicons dashicons-yes-alt"></span>
                                    <?php _e('Exists', 'goalv'); ?>
                                </span>
                            <?php else: ?>
                                <span class="goalv-status-badge goalv-status-error">
                                    <span class="dashicons dashicons-dismiss"></span>
                                    <?php _e('Missing', 'goalv'); ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $exists ? number_format($row_count) : '—'; ?></td>
                        <td><?php echo $exists ? esc_html($size) : '—'; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Render cron status
     * FIXED: Updated hook name to match scheduler registration
     */
    private function render_cron_status()
    {
        $crons = _get_cron_array();

        // FIX: Changed 'goalv_live_score_sync' to 'goalv_live_sync'
        $goalv_crons = array(
            'goalv_live_sync' => __('Live Score Updates (30 sec)', 'goalv'),      // ← FIXED
            'goalv_hourly_sync' => __('Hourly Match Sync', 'goalv'),
            'goalv_daily_cleanup' => __('Daily Cleanup', 'goalv')
        );

        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Task', 'goalv'); ?></th>
                    <th><?php _e('Status', 'goalv'); ?></th>
                    <th><?php _e('Next Run', 'goalv'); ?></th>
                    <th><?php _e('Interval', 'goalv'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($goalv_crons as $hook => $label): ?>
                    <?php
                    $scheduled = false;
                    $next_run = null;
                    $interval = null;

                    foreach ($crons as $timestamp => $cron) {
                        if (isset($cron[$hook])) {
                            $scheduled = true;
                            $next_run = $timestamp;

                            // Get interval info
                            $cron_data = reset($cron[$hook]);
                            if (isset($cron_data['schedule'])) {
                                $schedules = wp_get_schedules();
                                $interval = isset($schedules[$cron_data['schedule']])
                                    ? $schedules[$cron_data['schedule']]['display']
                                    : $cron_data['schedule'];
                            }
                            break;
                        }
                    }
                    ?>
                    <tr>
                        <td><strong><?php echo esc_html($label); ?></strong></td>
                        <td>
                            <?php if ($scheduled): ?>
                                <span class="goalv-status-badge goalv-status-success">
                                    <span class="dashicons dashicons-yes-alt"></span>
                                    <?php _e('Scheduled', 'goalv'); ?>
                                </span>
                            <?php else: ?>
                                <span class="goalv-status-badge goalv-status-error">
                                    <span class="dashicons dashicons-dismiss"></span>
                                    <?php _e('Not Scheduled', 'goalv'); ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            if ($scheduled && $next_run) {
                                $time_diff = $next_run - current_time('timestamp');
                                if ($time_diff > 0) {
                                    echo human_time_diff(current_time('timestamp'), $next_run) . ' ' . __('from now', 'goalv');
                                } else {
                                    echo '<span style="color: #d63638;">' . __('Overdue', 'goalv') . '</span>';
                                }
                            } else {
                                echo '—';
                            }
                            ?>
                        </td>
                        <td>
                            <?php echo $interval ? esc_html($interval) : '—'; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if (!$scheduled): ?>
            <div class="notice notice-warning inline" style="margin-top: 15px;">
                <p>
                    <strong><?php _e('Cron jobs not scheduled!', 'goalv'); ?></strong><br>
                    <?php _e('Try deactivating and reactivating the plugin to reschedule tasks.', 'goalv'); ?>
                </p>
            </div>
        <?php endif; ?>
    <?php
    }

    /**
     * Render plugin info
     */
    private function render_plugin_info()
    {
        ?>
        <table class="form-table">
            <tr>
                <th><?php _e('Plugin Version', 'goalv'); ?></th>
                <td><strong><?php echo GOALV_VERSION; ?></strong></td>
            </tr>
            <tr>
                <th><?php _e('WordPress Version', 'goalv'); ?></th>
                <td><?php echo get_bloginfo('version'); ?></td>
            </tr>
            <tr>
                <th><?php _e('PHP Version', 'goalv'); ?></th>
                <td><?php echo PHP_VERSION; ?></td>
            </tr>
            <tr>
                <th><?php _e('Database Version', 'goalv'); ?></th>
                <td><?php global $wpdb;
                echo $wpdb->db_version(); ?></td>
            </tr>
            <tr>
                <th><?php _e('Plugin Path', 'goalv'); ?></th>
                <td><code><?php echo GOALV_PLUGIN_PATH; ?></code></td>
            </tr>
            <tr>
                <th><?php _e('Plugin URL', 'goalv'); ?></th>
                <td><code><?php echo GOALV_PLUGIN_URL; ?></code></td>
            </tr>
        </table>
        <?php
    }

    /**
     * AJAX: Check system health
     */
    public function ajax_check_system_health()
    {
        check_ajax_referer('goalv_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'goalv'));
        }

        ob_start();
        $this->render_health_status();
        $html = ob_get_clean();

        wp_send_json_success(array('html' => $html));
    }

    /**
     * AJAX: Test cron jobs
     */
    public function ajax_test_cron_jobs()
    {
        check_ajax_referer('goalv_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'goalv'));
        }

        // Test by spawning a cron event
        spawn_cron();

        wp_send_json_success(array(
            'message' => __('Cron test triggered. Check scheduled tasks table for results.', 'goalv')
        ));
    }

    /**
     * AJAX: Clear cache
     */
    public function ajax_clear_cache()
    {
        check_ajax_referer('goalv_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'goalv'));
        }

        global $wpdb;

        // Delete all GoalV transients
        $wpdb->query(
            "DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_goalv_%' OR option_name LIKE '_transient_timeout_goalv_%'"
        );

        wp_send_json_success(array(
            'message' => __('All cache cleared successfully', 'goalv')
        ));
    }
}