<?php
/**
 * API Competitions Handler
 * Manages fetching and syncing competition/league data from API-Football
 * 
 * @package GoalV
 * @version 8.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class GoalV_API_Competitions
{
    private $client;

    public function __construct()
    {
        $this->client = new GoalV_API_Football_Client();
    }

    /**
     * Sync all enabled competitions from database
     */
    public function sync_all_competitions()
    {
        global $wpdb;
        
        $start_time = microtime(true);
        $table = $wpdb->prefix . 'goalv_competitions';
        
        // Get all active competitions that need syncing
        $competitions = $wpdb->get_results(
            "SELECT * FROM $table WHERE is_active = 1 AND sync_enabled = 1"
        );

        if (empty($competitions)) {
            return array(
                'success' => false,
                'message' => 'No active competitions found to sync'
            );
        }

        $total_updated = 0;
        $errors = array();

        foreach ($competitions as $competition) {
            $result = $this->sync_single_competition($competition->api_competition_id);
            
            if ($result['success']) {
                $total_updated++;
            } else {
                $errors[] = $competition->name . ': ' . $result['message'];
            }
        }

        $duration = round(microtime(true) - $start_time, 2);

        // Log sync operation
        $this->log_sync_operation(
            'competitions',
            null,
            empty($errors) ? 'success' : 'partial',
            count($competitions),
            0,
            $total_updated,
            count($errors),
            $duration,
            !empty($errors) ? implode('; ', $errors) : null
        );

        return array(
            'success' => empty($errors),
            'message' => sprintf(
                'Synced %d of %d competitions in %s seconds',
                $total_updated,
                count($competitions),
                $duration
            ),
            'total' => count($competitions),
            'updated' => $total_updated,
            'errors' => $errors
        );
    }

    /**
     * Sync single competition data
     */
    public function sync_single_competition($api_competition_id, $season = null)
    {
        global $wpdb;

        // Get current season if not provided
        if (!$season) {
            $season = $this->get_current_season();
        }

        // Fetch competition data from API
        $response = $this->client->request('leagues', array(
            'id' => $api_competition_id,
            'season' => $season
        ));

        if (isset($response['error'])) {
            return array(
                'success' => false,
                'message' => $response['error']
            );
        }

        if (empty($response['response'])) {
            return array(
                'success' => false,
                'message' => 'No data returned for competition ID: ' . $api_competition_id
            );
        }

        $competition_data = $response['response'][0];
        $league_data = $competition_data['league'];
        $season_data = $competition_data['seasons'][0] ?? array();

        // Prepare database update
        $table = $wpdb->prefix . 'goalv_competitions';
        
        $data = array(
            'name' => $league_data['name'],
            'code' => $league_data['type'] ?? null,           // This is the short code like "PL"
            'type' => $league_data['type'] ?? 'League',       // FIXED: Properly map type
            'country' => $competition_data['country']['name'] ?? null,
            'current_season' => $season ?? null,              // ADDED: Save current season
            'logo_url' => $league_data['logo'] ?? null,
            'season_start' => !empty($season_data['start']) ? $season_data['start'] : null,
            'season_end' => !empty($season_data['end']) ? $season_data['end'] : null,
            'current_matchday' => $season_data['current'] ?? 1,
            'last_synced' => current_time('mysql')
        );

        // Check if competition exists
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE api_competition_id = %s",
            $api_competition_id
        ));

        if ($exists) {
            // Update existing
            $wpdb->update(
                $table,
                $data,
                array('api_competition_id' => $api_competition_id),
                array('%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s'),
                array('%s')
            );
        } else {
            // Insert new
            $data['api_competition_id'] = $api_competition_id;
            $data['is_active'] = true;
            $data['sync_enabled'] = false; // Require manual activation
            
            $wpdb->insert($table, $data);
        }

        return array(
            'success' => true,
            'message' => 'Competition synced: ' . $league_data['name'],
            'competition_id' => $api_competition_id
        );
    }

    /**
     * Get competition by API ID
     */
    public function get_competition($api_competition_id)
    {
        global $wpdb;
        
        $table = $wpdb->prefix . 'goalv_competitions';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE api_competition_id = %s",
            $api_competition_id
        ));
    }

    /**
     * Get all active competitions
     */
    public function get_active_competitions()
    {
        global $wpdb;
        
        $table = $wpdb->prefix . 'goalv_competitions';
        
        return $wpdb->get_results(
            "SELECT * FROM $table WHERE is_active = 1 ORDER BY name ASC"
        );
    }

    /**
     * Get competitions enabled for sync
     */
    public function get_sync_enabled_competitions()
    {
        global $wpdb;
        
        $table = $wpdb->prefix . 'goalv_competitions';
        
        return $wpdb->get_results(
            "SELECT * FROM $table WHERE is_active = 1 AND sync_enabled = 1 ORDER BY name ASC"
        );
    }

    /**
     * Enable/disable competition sync
     */
    public function toggle_competition_sync($competition_id, $enabled)
    {
        global $wpdb;
        
        $table = $wpdb->prefix . 'goalv_competitions';
        
        $result = $wpdb->update(
            $table,
            array('sync_enabled' => $enabled ? 1 : 0),
            array('id' => $competition_id),
            array('%d'),
            array('%d')
        );

        return $result !== false;
    }

    /**
     * Get current football season (handles season transitions)
     */
    private function get_current_season()
    {
        $month = (int) date('n');
        $year = (int) date('Y');

        // European football season runs August-May
        // If we're in Jan-July, season is previous year
        if ($month < 8) {
            return $year - 1;
        }

        return $year;
    }

    /**
     * Calculate current matchday for a competition
     */
    public function calculate_current_matchday($competition_id)
    {
        $competition = $this->get_competition_by_db_id($competition_id);
        
        if (!$competition || !$competition->season_start) {
            return 1;
        }

        $season_start = strtotime($competition->season_start);
        $today = current_time('timestamp');
        
        $weeks_elapsed = floor(($today - $season_start) / (7 * 24 * 60 * 60));
        $matchday = max(1, $weeks_elapsed + 1);
        
        // Cap at total matchdays
        return min($matchday, $competition->total_matchdays);
    }

    /**
     * Get competition by database ID
     */
    private function get_competition_by_db_id($competition_id)
    {
        global $wpdb;
        
        $table = $wpdb->prefix . 'goalv_competitions';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $competition_id
        ));
    }

    /**
     * Search available competitions from API
     */
    public function search_competitions($search_term, $country = null)
    {
        $params = array('search' => $search_term);
        
        if ($country) {
            $params['country'] = $country;
        }

        $response = $this->client->request('leagues', $params);

        if (isset($response['error'])) {
            return array('error' => $response['error']);
        }

        return $response['response'] ?? array();
    }

    /**
     * Add new competition to database
     */
    public function add_competition($api_competition_id, $auto_sync = false)
    {
        global $wpdb;
        
        $table = $wpdb->prefix . 'goalv_competitions';
        
        // Check if already exists
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE api_competition_id = %s",
            $api_competition_id
        ));

        if ($exists) {
            return array(
                'success' => false,
                'message' => 'Competition already exists in database'
            );
        }

        // Sync from API to get data
        return $this->sync_single_competition($api_competition_id);
    }


    /**
 * Fetch ALL available competitions from API and save to database
 * This is different from sync_all_competitions which only updates existing ones
 */
public function fetch_all_available_competitions()
{
    global $wpdb;
    
    $start_time = microtime(true);
    $current_season = $this->get_current_season();
    
    // Fetch ALL leagues from API (no ID filter)
    $response = $this->client->request('leagues', array(
        'season' => $current_season
    ));

    if (isset($response['error'])) {
        return array(
            'success' => false,
            'message' => $response['error']
        );
    }

    if (empty($response['response'])) {
        return array(
            'success' => false,
            'message' => 'No competitions returned from API'
        );
    }

    $table = $wpdb->prefix . 'goalv_competitions';
    $competitions_data = $response['response'];
    $total = count($competitions_data);
    $inserted = 0;
    $updated = 0;
    $errors = array();

    foreach ($competitions_data as $competition_data) {
        $league_data = $competition_data['league'];
        $season_data = $competition_data['seasons'][0] ?? array();
        $country_data = $competition_data['country'] ?? array();
        
        $api_competition_id = $league_data['id'];
        
        // Prepare data
        $data = array(
            'name' => $league_data['name'],
            'code' => $league_data['type'] ?? null,
            'type' => $league_data['type'] ?? 'League',
            'country' => $country_data['name'] ?? null,
            'current_season' => $current_season,
            'logo_url' => $league_data['logo'] ?? null,
            'season_start' => !empty($season_data['start']) ? $season_data['start'] : null,
            'season_end' => !empty($season_data['end']) ? $season_data['end'] : null,
            'current_matchday' => $season_data['current'] ?? 1,
            'last_synced' => current_time('mysql')
        );

        // Check if exists
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE api_competition_id = %s",
            $api_competition_id
        ));

        if ($exists) {
            // Update existing
            $result = $wpdb->update(
                $table,
                $data,
                array('api_competition_id' => $api_competition_id)
            );
            
            if ($result !== false) {
                $updated++;
            }
        } else {
            // Insert new - default to INACTIVE so admin must manually enable
            $data['api_competition_id'] = $api_competition_id;
            $data['is_active'] = 0;  // Start disabled
            $data['sync_enabled'] = 0;  // Start disabled
            
            $result = $wpdb->insert($table, $data);
            
            if ($result) {
                $inserted++;
            } else {
                $errors[] = $league_data['name'];
            }
        }
    }

    $duration = round(microtime(true) - $start_time, 2);

    // Log the operation
    $this->log_sync_operation(
        'fetch_competitions',
        null,
        empty($errors) ? 'success' : 'partial',
        $total,
        $inserted,
        $updated,
        count($errors),
        $duration,
        !empty($errors) ? 'Failed: ' . implode(', ', $errors) : null
    );

    return array(
        'success' => true,
        'message' => sprintf(
            'Fetched %d competitions: %d new, %d updated in %s seconds',
            $total,
            $inserted,
            $updated,
            $duration
        ),
        'total' => $total,
        'inserted' => $inserted,
        'updated' => $updated,
        'errors' => $errors
    );
}

    /**
     * Log sync operation to database
     */
    private function log_sync_operation($sync_type, $competition_id, $status, $processed, $created, $updated, $failed, $duration, $error_message = null)
    {
        global $wpdb;
        
        $table = $wpdb->prefix . 'goalv_sync_logs';
        
        $wpdb->insert($table, array(
            'sync_type' => $sync_type,
            'competition_id' => $competition_id,
            'status' => $status,
            'items_processed' => $processed,
            'items_created' => $created,
            'items_updated' => $updated,
            'items_failed' => $failed,
            'duration_seconds' => $duration,
            'error_message' => $error_message,
            'completed_at' => current_time('mysql')
        ));
    }
}