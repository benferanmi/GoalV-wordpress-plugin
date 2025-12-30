<?php
/**
 * GoalV Admin AJAX Handlers
 * Connects admin pages to Phase 1-4 backend
 * 
 * @package GoalV
 * @since 8.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class GoalV_Admin_AJAX
{
    /**
     * Constructor - Register all AJAX actions
     */
    public function __construct()
    {
        // DEBUG: Log that AJAX class is initialized
        error_log('GoalV: Admin AJAX Handler Initialized');
        // API Settings
        add_action('wp_ajax_goalv_test_api_connection', array($this, 'test_api_connection'));

        // Competitions
        add_action('wp_ajax_goalv_fetch_competitions', array($this, 'fetch_competitions'));
        add_action('wp_ajax_goalv_toggle_competition', array($this, 'toggle_competition'));
        add_action('wp_ajax_goalv_sync_single_competition', array($this, 'sync_single_competition'));
        add_action('wp_ajax_goalv_bulk_toggle_competitions', array($this, 'bulk_toggle_competitions'));

        // Sync Manager
        add_action('wp_ajax_goalv_manual_sync_competitions', array($this, 'manual_sync_competitions'));
        add_action('wp_ajax_goalv_manual_sync_matches', array($this, 'manual_sync_matches'));
        add_action('wp_ajax_goalv_manual_sync_live', array($this, 'manual_sync_live'));
        add_action('wp_ajax_goalv_manual_sync_full', array($this, 'manual_sync_full'));
        add_action('wp_ajax_goalv_toggle_live_sync', array($this, 'toggle_live_sync'));
        add_action('wp_ajax_goalv_clear_sync_logs', array($this, 'clear_sync_logs'));
        add_action('wp_ajax_goalv_trigger_hourly_sync', array($this, 'trigger_hourly_sync'));
        add_action('wp_ajax_goalv_trigger_live_sync', array($this, 'trigger_live_sync'));
        add_action('wp_ajax_goalv_trigger_cleanup', array($this, 'trigger_cleanup'));

        // System Info
        add_action('wp_ajax_goalv_run_health_check', array($this, 'run_health_check'));
        add_action('wp_ajax_goalv_clear_cache', array($this, 'clear_cache'));
        add_action('wp_ajax_goalv_optimize_table', array($this, 'optimize_table'));

        // Matches Management
        add_action('wp_ajax_goalv_get_match_details', array($this, 'get_match_details'));
        add_action('wp_ajax_goalv_delete_match', array($this, 'delete_match'));
        add_action('wp_ajax_goalv_update_match_status', array($this, 'update_match_status'));
        add_action('wp_ajax_goalv_resync_match', array($this, 'resync_match'));
        add_action('wp_ajax_goalv_fix_orphaned_matches', array($this, 'fix_orphaned_matches'));
        add_action('wp_ajax_goalv_export_matches_csv', array($this, 'export_matches_csv'));
    }

    /**
     * Verify nonce and permissions
     */
    private function verify_request()
    {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'goalv_admin_nonce')) {
            wp_send_json_error(__('Security check failed', 'goalv'));
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'goalv'));
        }
    }

    /**
     * API Settings: Test connection
     */
    public function test_api_connection()
    {
        $this->verify_request();

        $client = new GoalV_API_Football_Client();
        $result = $client->test_connection();

        if ($result['success']) {
            wp_send_json_success(array(
                'message' => $result['message']
            ));
        } else {
            wp_send_json_error($result['message']);
        }
    }

    /**
     * Competitions: Fetch from API
     */
    public function fetch_competitions()
    {
        $this->verify_request();

        $api_competitions = new GoalV_API_Competitions();

        // FIX: Call the correct method
        $result = $api_competitions->fetch_all_available_competitions();

        if ($result['success']) {
            wp_send_json_success(array(
                'message' => $result['message'],
                'total' => $result['total'],
                'inserted' => $result['inserted'],
                'updated' => $result['updated']
            ));
        } else {
            wp_send_json_error($result['message']);
        }
    }

    /**
     * Bulk toggle competitions
     */
    public function bulk_toggle_competitions()
    {
        $this->verify_request();

        $competition_ids = isset($_POST['competition_ids']) ? array_map('intval', $_POST['competition_ids']) : array();
        $bulk_action = isset($_POST['bulk_action']) ? sanitize_text_field($_POST['bulk_action']) : '';

        if (empty($competition_ids) || !in_array($bulk_action, array('enable', 'disable'))) {
            wp_send_json_error(__('Invalid parameters', 'goalv'));
        }

        $new_status = ($bulk_action === 'enable') ? 1 : 0;
        $updated_count = 0;

        global $wpdb;
        $table = $wpdb->prefix . 'goalv_competitions';

        foreach ($competition_ids as $comp_id) {
            $result = $wpdb->update(
                $table,
                array('is_active' => $new_status),
                array('id' => $comp_id),
                array('%d'),
                array('%d')
            );

            if ($result !== false) {
                $updated_count++;
            }
        }

        wp_send_json_success(array(
            'message' => sprintf(
                __('%d competitions %s', 'goalv'),
                $updated_count,
                $new_status ? __('enabled', 'goalv') : __('disabled', 'goalv')
            ),
            'updated_count' => $updated_count
        ));
    }

    /**
     * Competitions: Toggle active/sync status
     */
    public function toggle_competition()
    {
        $this->verify_request();

        $competition_id = intval($_POST['competition_id']);
        $type = sanitize_text_field($_POST['type']);
        $status = intval($_POST['status']);

        if (!$competition_id || !in_array($type, array('active', 'sync'))) {
            wp_send_json_error(__('Invalid parameters', 'goalv'));
        }

        global $wpdb;
        $table = $wpdb->prefix . 'goalv_competitions';
        $field = ($type === 'active') ? 'is_active' : 'sync_enabled';

        $result = $wpdb->update(
            $table,
            array($field => $status),
            array('id' => $competition_id),
            array('%d'),
            array('%d')
        );

        if ($result !== false) {
            wp_send_json_success(array(
                'message' => __('Competition updated', 'goalv')
            ));
        } else {
            wp_send_json_error(__('Update failed', 'goalv'));
        }
    }

    /**
     * Competitions: Sync single competition
     */
    public function sync_single_competition()
    {
        $this->verify_request();

        $competition_id = intval($_POST['competition_id']);

        global $wpdb;
        $table = $wpdb->prefix . 'goalv_competitions';
        $comp = $wpdb->get_row($wpdb->prepare(
            "SELECT api_competition_id FROM $table WHERE id = %d",
            $competition_id
        ));

        if (!$comp) {
            wp_send_json_error(__('Competition not found', 'goalv'));
        }

        // Sync MATCHES for this competition
        $api_matches = new GoalV_API_Matches();
        $result = $api_matches->sync_competition_matches($comp->api_competition_id);

        if ($result['success']) {
            wp_send_json_success(array(
                'message' => $result['message']
            ));
        } else {
            wp_send_json_error($result['message']);
        }
    }

    /**
     * Sync: Manual competitions sync
     */
    public function manual_sync_competitions()
    {
        $this->verify_request();

        $sync_manager = new GoalV_Sync_Manager();
        $result = $sync_manager->sync_competitions();

        if (isset($result['error'])) {
            wp_send_json_error($result['error']);
        }

        wp_send_json_success(array(
            'message' => __('Competitions synced successfully', 'goalv')
        ));
    }

    /**
     * Sync: Manual matches sync
     */
    public function manual_sync_matches()
    {
        $this->verify_request();

        $api_matches = new GoalV_API_Matches();
        $result = $api_matches->sync_all_competitions_matches();

        if ($result['success']) {
            wp_send_json_success(array(
                'message' => $result['message']
            ));
        } else {
            wp_send_json_error($result['message']);
        }
    }

    /**
     * Sync: Manual live scores update
     */
    public function manual_sync_live()
    {
        $this->verify_request();

        $sync_manager = new GoalV_Sync_Manager();
        $result = $sync_manager->sync_live_matches();

        if (isset($result['error'])) {
            wp_send_json_error($result['error']);
        }

        wp_send_json_success(array(
            'message' => sprintf(
                __('Updated %d live matches', 'goalv'),
                $result['count'] ?? 0
            )
        ));
    }

    /**
     * Sync: Full system sync
     */
    public function manual_sync_full()
    {
        $this->verify_request();

        $sync_manager = new GoalV_Sync_Manager();
        $result = $sync_manager->manual_sync_all();

        wp_send_json_success(array(
            'message' => $result['message'] ?? __('Full sync completed', 'goalv')
        ));
    }

    /**
     * Sync: Toggle live sync on/off
     */
    public function toggle_live_sync()
    {
        $this->verify_request();

        $enabled = intval($_POST['enabled']);
        $scheduler = new GoalV_Sync_Scheduler();
        $scheduler->toggle_live_sync($enabled);

        wp_send_json_success(array(
            'message' => $enabled
                ? __('Live sync enabled', 'goalv')
                : __('Live sync disabled', 'goalv')
        ));
    }

    /**
     * Sync: Clear old logs
     */
    public function clear_sync_logs()
    {
        $this->verify_request();

        $sync_manager = new GoalV_Sync_Manager();
        $sync_manager->cleanup_old_logs();

        wp_send_json_success(array(
            'message' => __('Logs cleared', 'goalv')
        ));
    }

    /**
     * Sync: Trigger hourly sync manually
     */
    public function trigger_hourly_sync()
    {
        $this->verify_request();

        $scheduler = new GoalV_Sync_Scheduler();
        $scheduler->trigger_hourly_sync_now();

        wp_send_json_success(array(
            'message' => __('Hourly sync triggered', 'goalv')
        ));
    }

    /**
     * Sync: Trigger live sync manually
     */
    public function trigger_live_sync()
    {
        $this->verify_request();

        $scheduler = new GoalV_Sync_Scheduler();
        $scheduler->trigger_live_sync_now();

        wp_send_json_success(array(
            'message' => __('Live sync triggered', 'goalv')
        ));
    }

    /**
     * Sync: Trigger cleanup manually
     */
    public function trigger_cleanup()
    {
        $this->verify_request();

        $scheduler = new GoalV_Sync_Scheduler();
        $scheduler->run_daily_cleanup();

        wp_send_json_success(array(
            'message' => __('Cleanup completed', 'goalv')
        ));
    }

    /**
     * System: Run health check
     */
    public function run_health_check()
    {
        $this->verify_request();

        $sync_manager = new GoalV_Sync_Manager();
        $health = $sync_manager->health_check();

        wp_send_json_success(array(
            'message' => __('Health check completed', 'goalv'),
            'status' => $health['status'],
            'issues' => $health['issues']
        ));
    }

    /**
     * System: Clear API cache
     */
    public function clear_cache()
    {
        $this->verify_request();

        $client = new GoalV_API_Football_Client();
        $client->clear_cache();

        wp_send_json_success(array(
            'message' => __('Cache cleared', 'goalv')
        ));
    }

    /**
     * System: Optimize database table
     */
    public function optimize_table()
    {
        $this->verify_request();

        $table = sanitize_text_field($_POST['table']);

        global $wpdb;
        $wpdb->query("OPTIMIZE TABLE $table");

        wp_send_json_success(array(
            'message' => __('Table optimized', 'goalv')
        ));
    }
    /**
     * Get match details for modal
     */
    public function get_match_details()
    {
        $this->verify_request();

        $match_id = intval($_POST['match_id']);

        if (!$match_id) {
            wp_send_json_error(__('Invalid match ID', 'goalv'));
        }

        global $wpdb;

        // Get match with related data
        $matches_table = $wpdb->prefix . 'goalv_matches';
        $competitions_table = $wpdb->prefix . 'goalv_competitions';
        $teams_table = $wpdb->prefix . 'goalv_teams';

        $match = $wpdb->get_row($wpdb->prepare(
            "SELECT m.*, 
                c.name as competition_name,
                c.logo_url as competition_logo,
                ht.name as home_team_name,
                ht.logo_url as home_team_logo,
                at.name as away_team_name,
                at.logo_url as away_team_logo
         FROM $matches_table m
         LEFT JOIN $competitions_table c ON m.competition_id = c.id
         LEFT JOIN $teams_table ht ON m.home_team_id = ht.id
         LEFT JOIN $teams_table at ON m.away_team_id = at.id
         WHERE m.id = %d",
            $match_id
        ));

        if (!$match) {
            wp_send_json_error(__('Match not found', 'goalv'));
        }

        // Get statistics
        $matches_admin = new GoalV_Admin_Matches();
        $stats = $matches_admin->get_match_statistics($match_id);

        // Get vote options
        $options_table = $wpdb->prefix . 'goalv_vote_options';
        $vote_options = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $options_table 
         WHERE match_id = %d 
         ORDER BY category, display_order ASC",
            $match_id
        ));

        wp_send_json_success(array(
            'match' => $match,
            'stats' => $stats,
            'vote_options' => $vote_options
        ));
    }

    /**
     * Delete single match
     */
    public function delete_match()
    {
        $this->verify_request();

        $match_id = intval($_POST['match_id']);

        if (!$match_id) {
            wp_send_json_error(__('Invalid match ID', 'goalv'));
        }

        $matches_admin = new GoalV_Admin_Matches();
        $result = $matches_admin->delete_match($match_id);

        if ($result) {
            wp_send_json_success(array(
                'message' => __('Match deleted successfully', 'goalv')
            ));
        } else {
            wp_send_json_error(__('Failed to delete match', 'goalv'));
        }
    }

    /**
     * Update match status
     */
    public function update_match_status()
    {
        $this->verify_request();

        $match_id = intval($_POST['match_id']);
        $status = sanitize_text_field($_POST['status']);

        $allowed_statuses = array('scheduled', 'postponed', 'cancelled', 'live', 'paused', 'finished', 'awarded');

        if (!$match_id || !in_array($status, $allowed_statuses)) {
            wp_send_json_error(__('Invalid parameters', 'goalv'));
        }

        $matches_admin = new GoalV_Admin_Matches();
        $result = $matches_admin->update_match($match_id, array('status' => $status));

        if ($result !== false) {
            wp_send_json_success(array(
                'message' => __('Match status updated', 'goalv')
            ));
        } else {
            wp_send_json_error(__('Failed to update status', 'goalv'));
        }
    }

    /**
     * Resync match from API
     */
    public function resync_match()
    {
        $this->verify_request();

        $match_id = intval($_POST['match_id']);

        if (!$match_id) {
            wp_send_json_error(__('Invalid match ID', 'goalv'));
        }

        $matches_admin = new GoalV_Admin_Matches();
        $result = $matches_admin->resync_match($match_id);

        if ($result['success']) {
            wp_send_json_success(array(
                'message' => $result['message']
            ));
        } else {
            wp_send_json_error($result['message']);
        }
    }

    /**
     * Fix orphaned matches
     */
    public function fix_orphaned_matches()
    {
        $this->verify_request();

        $matches_admin = new GoalV_Admin_Matches();

        // Get orphaned matches
        $orphaned_ids = $matches_admin->get_orphaned_matches();

        if (empty($orphaned_ids)) {
            wp_send_json_success(array(
                'message' => __('No orphaned matches found', 'goalv')
            ));
        }

        // Fix them
        $fixed = $matches_admin->fix_orphaned_matches($orphaned_ids);

        wp_send_json_success(array(
            'message' => sprintf(
                __('Created vote options for %d match(es)', 'goalv'),
                $fixed
            )
        ));
    }

    /**
     * Export matches to CSV
     */
    public function export_matches_csv()
    {
        $this->verify_request();

        $filters = array(
            'competition_id' => isset($_GET['competition']) ? intval($_GET['competition']) : 0,
            'status' => isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '',
            'date_from' => isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '',
            'date_to' => isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '',
            'search' => isset($_GET['search']) ? sanitize_text_field($_GET['search']) : ''
        );

        $matches_admin = new GoalV_Admin_Matches();
        $csv_content = $matches_admin->export_to_csv($filters);

        // Set headers for download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=goalv-matches-' . date('Y-m-d') . '.csv');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo "\xEF\xBB\xBF"; // UTF-8 BOM
        echo $csv_content;
        exit;
    }
}