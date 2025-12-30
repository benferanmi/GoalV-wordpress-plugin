<?php
/**
 * GoalV Sync Manager - FIXED VERSION
 * 
 * CRITICAL FIXES:
 * 1. Fixed hourly sync date parameter issue - now passes proper date range to match sync
 * 2. Changed 'sync_time' to 'started_at' to match database schema
 * 3. Added proper column mapping for sync logs
 * 
 * @package GoalV
 * @version 8.1.2
 */

if (!defined('ABSPATH')) {
    exit;
}

class GoalV_Sync_Manager
{
    private $api_client;
    private $api_competitions;
    private $api_matches;
    private $live_scores_sync;

    public function __construct()
    {
        $this->init_api_handlers();
    }

    private function init_api_handlers()
    {
        $this->api_client = new GoalV_API_Football_Client();
        $this->api_competitions = new GoalV_API_Competitions();
        $this->api_matches = new GoalV_API_Matches();
        $this->live_scores_sync = new GoalV_Sync_Live_Scores();
    }

    /**
     * MAIN SYNC: Full system sync with extended date range
     * FIXED: Now fetches past 14 days to catch unfinished matches
     * 
     * @return array Results with competitions, matches, and errors
     */
    public function sync_all()
    {
        $start_time = microtime(true);
        $results = array(
            'competitions' => array(),
            'matches' => array(),
            'errors' => array(),
            'timestamp' => current_time('mysql')
        );

        $this->log_sync('Starting full system sync', 'info', 'full_sync');

        // Sync competitions
        $comp_result = $this->sync_competitions();
        $results['competitions'] = $comp_result;

        if (isset($comp_result['error'])) {
            $results['errors'][] = 'Competition sync failed: ' . $comp_result['error'];
            $this->log_sync('Competition sync failed', 'error', 'full_sync');
            return $results;
        }

        // ENHANCED: Fetch from past 14 days + next 7 days to catch status updates
        // This ensures unfinished matches get their final statuses
        $date_from = date('Y-m-d', strtotime('-14 days'));
        $date_to = date('Y-m-d', strtotime('+7 days'));

        error_log("GoalV: Sync date range: {$date_from} to {$date_to}");

        // Sync matches for all active competitions
        $match_sync_result = $this->api_matches->sync_all_competitions_matches($date_from, $date_to);

        if ($match_sync_result['success']) {
            $this->log_sync(sprintf(
                'Matches synced: %d processed (%d new, %d updated)',
                $match_sync_result['processed'],
                $match_sync_result['created'],
                $match_sync_result['updated']
            ), 'success', 'matches');

            $results['matches'] = $match_sync_result;
        } else {
            $results['errors'][] = 'Match sync failed: ' . $match_sync_result['message'];
            $this->log_sync('Match sync failed: ' . $match_sync_result['message'], 'error', 'matches');
        }

        $duration = round(microtime(true) - $start_time, 2);
        $results['duration'] = $duration . 's';
        $results['success'] = empty($results['errors']);
        $results['message'] = sprintf(
            'Full sync completed in %ss - Comps: %d, Matches processed: %d (created: %d, updated: %d)',
            $duration,
            count($results['competitions'] ?? array()),
            $match_sync_result['processed'] ?? 0,
            $match_sync_result['created'] ?? 0,
            $match_sync_result['updated'] ?? 0
        );

        $this->log_sync($results['message'], 'success', 'full_sync');

        return $results;
    }

    /**
     * Sync all competitions/leagues
     */
    public function sync_competitions()
    {
        $result = $this->api_competitions->sync_all_competitions();

        if (isset($result['error'])) {
            $this->log_sync('Competition sync error: ' . $result['error'], 'error', 'competitions');
            return $result;
        }

        $count = isset($result['synced']) ? $result['synced'] : 0;
        $this->log_sync("Synced {$count} competitions", 'success', 'competitions');

        return $result;
    }

    /**
     * Sync matches for specific competition
     * FIXED: Now accepts and passes date parameters correctly
     */
    public function sync_competition_matches($competition_id, $date_from = null, $date_to = null, $season = null)
    {
        // Set default date range if not provided
        if (!$date_from) {
            $date_from = date('Y-m-d');
        }
        if (!$date_to) {
            $date_to = date('Y-m-d', strtotime('+7 days'));
        }

        if (!$season) {
            $season = $this->get_current_season();
        }

        // Get competition data
        global $wpdb;
        $table = $wpdb->prefix . 'goalv_competitions';
        $competition = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $competition_id
        ));

        if (!$competition) {
            return array('error' => 'Competition not found');
        }

        // Call API with proper parameters
        $result = $this->api_matches->sync_competition_matches(
            $competition->api_competition_id,
            $date_from,
            $date_to,
            $season
        );

        if (isset($result['error'])) {
            $this->log_sync(sprintf(
                'Match sync error for competition %d: %s',
                $competition_id,
                $result['error']
            ), 'error', 'matches');
            return $result;
        }

        return $result;
    }

    /**
     * LIVE SYNC: Update all live matches
     */
    public function sync_live_matches()
    {
        $result = $this->live_scores_sync->sync_live_scores();

        if (isset($result['error'])) {
            $this->log_sync('Live sync error: ' . $result['error'], 'error', 'live_scores');
            return $result;
        }

        $live_count = isset($result['live_matches']) ? $result['live_matches'] : 0;

        // Only log if there are live matches
        if ($live_count > 0) {
            $this->log_sync("Live sync: {$live_count} active matches updated", 'success', 'live_scores');
        }

        return $result;
    }

    /**
     * Sync specific match by ID
     */
    public function sync_single_match($match_id)
    {
        global $wpdb;
        $matches_table = $wpdb->prefix . 'goalv_matches';

        $match = $wpdb->get_row($wpdb->prepare(
            "SELECT api_match_id, competition_id FROM {$matches_table} WHERE id = %d",
            $match_id
        ));

        if (!$match) {
            return array('error' => 'Match not found');
        }

        $result = $this->api_matches->sync_single_match($match->api_match_id);

        if (isset($result['error'])) {
            $this->log_sync("Single match sync error (ID: {$match_id}): " . $result['error'], 'error', 'matches');
            return $result;
        }

        $this->log_sync("Single match synced: ID {$match_id}", 'success', 'matches');
        return $result;
    }

    private function get_active_competitions()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'goalv_competitions';

        return $wpdb->get_results(
            "SELECT * FROM {$table} WHERE is_active = 1 ORDER BY name ASC"
        );
    }

    private function get_current_season()
    {
        $current_date = new DateTime('now', new DateTimeZone('UTC'));
        $year = (int) $current_date->format('Y');
        $month = (int) $current_date->format('m');

        if ($month < 8) {
            $year--;
        }

        return (string) $year;
    }

    private function count_total_matches($match_results)
    {
        $total = 0;
        foreach ($match_results as $result) {
            if (isset($result['synced'])) {
                $total += $result['synced'];
            }
        }
        return $total;
    }

    /**
     * Smart sync - pulls unfinished matches from past + present
     * Designed to catch status updates on matches that started but haven't finished
     * 
     * @return array Results with updated count and details
     */
    public function sync_unfinished_matches()
    {
        global $wpdb;
        $matches_table = $wpdb->prefix . 'goalv_matches';
        $teams_table = $wpdb->prefix . 'goalv_teams';

        // Get all unfinished matches (including from past)
        $unfinished = $wpdb->get_results(
            "SELECT m.id, m.api_match_id, m.match_date, m.home_team_id, m.away_team_id, 
                m.status, m.home_score, m.away_score, m.last_updated,
                ht.name as home_team_name, at.name as away_team_name
         FROM {$matches_table} m
         LEFT JOIN {$teams_table} ht ON m.home_team_id = ht.id
         LEFT JOIN {$teams_table} at ON m.away_team_id = at.id
         WHERE m.status NOT IN ('finished', 'cancelled', 'postponed', 'awarded')
         AND m.match_date <= NOW()
         ORDER BY m.last_updated ASC
         LIMIT 100"
        );

        if (empty($unfinished)) {
            $this->log_sync('No unfinished matches to sync', 'info', 'unfinished_match_sync');
            return array(
                'success' => true,
                'message' => 'No unfinished matches found',
                'count' => 0,
                'details' => array()
            );
        }

        $updated_count = 0;
        $details = array();
        $start_time = microtime(true);

        foreach ($unfinished as $match) {
            // Fetch fresh data from API
            $result = $this->api_matches->sync_single_match($match->api_match_id);

            if ($result['success']) {
                // Get updated match data
                $updated_match = $this->api_matches->get_match($match->id);

                // Check what changed
                $changes = array();

                if ($updated_match->home_score != $match->home_score) {
                    $changes[] = "Home: {$match->home_score} → {$updated_match->home_score}";
                }
                if ($updated_match->away_score != $match->away_score) {
                    $changes[] = "Away: {$match->away_score} → {$updated_match->away_score}";
                }
                if ($updated_match->status != $match->status) {
                    $changes[] = "Status: {$match->status} → {$updated_match->status}";
                }

                // Log with team names if changes detected
                if (!empty($changes)) {
                    $updated_count++;
                    $match_display = $match->home_team_name . ' vs ' . $match->away_team_name;
                    $changes_str = implode(' | ', $changes);

                    $details[] = array(
                        'match_id' => $match->id,
                        'match' => $match_display,
                        'changes' => $changes_str,
                        'new_status' => $updated_match->status,
                        'score' => $updated_match->home_score . '-' . $updated_match->away_score,
                        'timestamp' => current_time('mysql')
                    );

                    // Log each update
                    error_log("GoalV [unfinished_match]: {$match_display} - {$changes_str}");
                }
            }
        }

        $duration = round(microtime(true) - $start_time, 2);

        // Log overall result
        $summary = sprintf(
            'Unfinished matches sync: %d matches checked, %d updated in %ss',
            count($unfinished),
            $updated_count,
            $duration
        );

        $this->log_sync_detailed($summary, 'success', 'unfinished_match_sync', $details);

        return array(
            'success' => true,
            'message' => sprintf('%d unfinished matches updated', $updated_count),
            'count' => $updated_count,
            'details' => $details,
            'duration' => $duration
        );
    }

    /**
     * Enhanced logging with detailed change tracking
     * Stores metadata about what changed in each sync
     * 
     * @param string $message Main message
     * @param string $status Status (success/error/info/failed)
     * @param string $sync_type Type of sync operation
     * @param array $details Detailed change information
     */
    private function log_sync_detailed($message, $status = 'success', $sync_type = 'auto', $details = array())
    {
        global $wpdb;
        $logs_table = $wpdb->prefix . 'goalv_sync_logs';

        $log_data = array(
            'sync_type' => $sync_type,
            'status' => $status,
            'error_message' => ($status === 'error' || $status === 'failed') ? $message : null,
            'started_at' => current_time('mysql'),
            'completed_at' => current_time('mysql'),
            'metadata' => !empty($details) ? wp_json_encode($details) : null
        );

        $formats = array('%s', '%s', '%s', '%s', '%s', '%s');

        $inserted = $wpdb->insert($logs_table, $log_data, $formats);

        if ($inserted === false) {
            error_log("GoalV: Failed to insert detailed sync log - DB Error: " . $wpdb->last_error);
        }

        // Also log to error_log for monitoring
        $log_message = "GoalV Sync [{$status}] ({$sync_type}): {$message}";
        if (!empty($details)) {
            $log_message .= " | Details: " . wp_json_encode($details);
        }
        error_log($log_message);
    }

    /**
     * Get sync statistics
     * FIXED: Uses 'started_at' instead of 'sync_time'
     */
    public function get_sync_stats()
    {
        global $wpdb;
        $logs_table = $wpdb->prefix . 'goalv_sync_logs';

        $last_full_sync = $wpdb->get_var(
            "SELECT started_at FROM {$logs_table} 
             WHERE sync_type = 'full_sync' AND status = 'success'
             ORDER BY started_at DESC LIMIT 1"
        );

        $last_live_sync = $wpdb->get_var(
            "SELECT started_at FROM {$logs_table} 
             WHERE sync_type = 'live_scores' AND status = 'success'
             ORDER BY started_at DESC LIMIT 1"
        );

        $syncs_today = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$logs_table} 
             WHERE DATE(started_at) = CURDATE()"
        );

        $recent_errors = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$logs_table} 
             WHERE status IN ('error', 'failed') AND started_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)"
        );

        return array(
            'last_full_sync' => $last_full_sync,
            'last_live_sync' => $last_live_sync,
            'syncs_today' => (int) $syncs_today,
            'errors_last_hour' => (int) $recent_errors,
            'api_stats' => $this->api_client->get_request_stats()
        );
    }

    /**
     * Get recent sync logs
     */
    public function get_recent_logs($limit = 20)
    {
        global $wpdb;
        $logs_table = $wpdb->prefix . 'goalv_sync_logs';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$logs_table} 
             ORDER BY started_at DESC 
             LIMIT %d",
            $limit
        ));
    }

    /**
     * Manual trigger: Force sync everything now
     */
    public function manual_sync_all()
    {
        $this->log_sync('Manual full sync triggered by admin', 'info', 'full_sync');

        $this->api_client->clear_cache();

        return $this->sync_all();
    }

    /**
     * Manual trigger: Force live score update now
     */
    public function manual_sync_live()
    {
        $this->log_sync('Manual live sync triggered by admin', 'info', 'live_scores');

        $this->api_client->clear_cache();

        return $this->sync_live_matches();
    }

    /**
     * FIXED: Log sync operation to database with structured data
     * Now captures counts separately for better analysis
     * 
     * @param string $message Log message
     * @param string $status Status (success/error/info/failed)
     * @param string $sync_type Type of sync
     * @param array $processed_data Optional breakdown of counts
     */
    private function log_sync($message, $status = 'success', $sync_type = 'auto', $processed_data = array())
    {
        global $wpdb;
        $logs_table = $wpdb->prefix . 'goalv_sync_logs';

        // Parse message for structured logging
        $items_processed = 0;
        $items_created = 0;
        $items_updated = 0;

        // Extract counts from message if provided in processed_data
        if (!empty($processed_data)) {
            $items_processed = isset($processed_data['processed']) ? (int) $processed_data['processed'] : 0;
            $items_created = isset($processed_data['created']) ? (int) $processed_data['created'] : 0;
            $items_updated = isset($processed_data['updated']) ? (int) $processed_data['updated'] : 0;
        } else {
            // Try to extract from message format
            if (preg_match('/(\d+)\s+processed\s*\((\d+)\s+new,\s*(\d+)\s+updated\)/', $message, $matches)) {
                $items_processed = (int) $matches[1];
                $items_created = (int) $matches[2];
                $items_updated = (int) $matches[3];
            }
        }

        // Use correct column names from schema
        $log_data = array(
            'sync_type' => $sync_type,
            'status' => $status,
            'items_processed' => $items_processed,
            'items_created' => $items_created,
            'items_updated' => $items_updated,
            'error_message' => ($status === 'error' || $status === 'failed') ? $message : null,
            'started_at' => current_time('mysql'),
            'completed_at' => current_time('mysql')
        );

        $formats = array('%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s');

        $inserted = $wpdb->insert($logs_table, $log_data, $formats);

        if ($inserted === false) {
            error_log("GoalV: Failed to insert sync log - DB Error: " . $wpdb->last_error);
        }

        // Also log to error_log with full context
        error_log(sprintf(
            "GoalV Sync [%s] (%s): %s | Processed: %d, Created: %d, Updated: %d",
            $status,
            $sync_type,
            $message,
            $items_processed,
            $items_created,
            $items_updated
        ));
    }

    /**
     * Clean old logs (keep last 1000 entries)
     */
    public function cleanup_old_logs()
    {
        global $wpdb;
        $logs_table = $wpdb->prefix . 'goalv_sync_logs';

        // Keep last 1000, delete older
        $keep_count = 1000;

        $oldest_to_keep_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$logs_table} 
             ORDER BY started_at DESC 
             LIMIT 1 OFFSET %d",
            $keep_count - 1
        ));

        if ($oldest_to_keep_id) {
            $deleted = $wpdb->query($wpdb->prepare(
                "DELETE FROM {$logs_table} WHERE id < %d",
                $oldest_to_keep_id
            ));

            if ($deleted > 0) {
                error_log("GoalV: Cleaned {$deleted} old sync logs");
            }
        }
    }

    /**
     * Health check: Verify system is syncing properly
     */
    public function health_check()
    {
        $stats = $this->get_sync_stats();
        $health = array(
            'status' => 'healthy',
            'issues' => array()
        );

        // Check if full sync is stale (over 2 hours old)
        if ($stats['last_full_sync']) {
            $last_sync_time = strtotime($stats['last_full_sync']);
            $hours_since = (time() - $last_sync_time) / 3600;

            if ($hours_since > 2) {
                $health['status'] = 'warning';
                $health['issues'][] = 'No full sync in over 2 hours';
            }
        } else {
            $health['status'] = 'error';
            $health['issues'][] = 'No successful sync recorded';
        }

        // Check for recent errors
        if ($stats['errors_last_hour'] > 5) {
            $health['status'] = 'error';
            $health['issues'][] = "High error rate: {$stats['errors_last_hour']} errors in last hour";
        }

        // Check API limit
        if (isset($stats['api_stats']['percentage_used'])) {
            if ($stats['api_stats']['percentage_used'] > 90) {
                $health['status'] = 'warning';
                $health['issues'][] = 'API limit nearly exhausted: ' . $stats['api_stats']['percentage_used'] . '% used';
            }
        }

        return $health;
    }
}