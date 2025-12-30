<?php
/**
 * API Matches Handler - FIXED DATE RANGE
 * 
 * CRITICAL FIX:
 * - Changed date_from from TODAY to -3 DAYS
 * - This allows syncing of recent finished matches
 * - Previously only synced "next 7 days" missing all past matches
 * 
 * @package GoalV
 * @version 8.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class GoalV_API_Matches
{
    private $client;
    private $competitions_api;

    public function __construct()
    {
        $this->client = new GoalV_API_Football_Client();
        $this->competitions_api = new GoalV_API_Competitions();
    }

    /**
     * Sync matches for all enabled competitions
     * 
     * FIX: Lines 48-52 - Changed date range from TODAY to -3 DAYS
     */
    public function sync_all_competitions_matches($date_from = null, $date_to = null)
    {
        $start_time = microtime(true);

        // Get enabled competitions
        $competitions = $this->competitions_api->get_sync_enabled_competitions();

        if (empty($competitions)) {
            return array(
                'success' => false,
                'message' => 'No competitions enabled for syncing'
            );
        }

        // FIXED: Default date range now includes past 3 days (for status updates) + next 7 days
        if (!$date_from) {
            $date_from = date('Y-m-d', strtotime('-3 days')); // LINE 48 - CHANGED FROM: date('Y-m-d')
        }
        if (!$date_to) {
            $date_to = date('Y-m-d', strtotime('+7 days')); // LINE 51 - UNCHANGED
        }

        $total_matches = 0;
        $total_created = 0;
        $total_updated = 0;
        $errors = array();

        foreach ($competitions as $competition) {
            $result = $this->sync_competition_matches(
                $competition->api_competition_id,
                $date_from,
                $date_to
            );

            if ($result['success']) {
                $total_matches += $result['processed'];
                $total_created += $result['created'];
                $total_updated += $result['updated'];
            } else {
                $errors[] = $competition->name . ': ' . $result['message'];
            }
        }

        $duration = round(microtime(true) - $start_time, 2);

        // Log sync operation
        $this->log_sync_operation(
            'matches',
            null,
            empty($errors) ? 'success' : 'partial',
            $total_matches,
            $total_created,
            $total_updated,
            count($errors),
            $duration,
            !empty($errors) ? implode('; ', $errors) : null
        );

        return array(
            'success' => empty($errors),
            'message' => sprintf(
                'Synced %d matches across %d competitions in %s seconds (%d new, %d updated)',
                $total_matches,
                count($competitions),
                $duration,
                $total_created,
                $total_updated
            ),
            'processed' => $total_matches,
            'created' => $total_created,
            'updated' => $total_updated,
            'errors' => $errors
        );
    }

    /**
     * Sync matches for specific competition
     * 
     * FIX: Lines 115-118 - Changed date range from TODAY to -3 DAYS
     */
    public function sync_competition_matches($api_competition_id, $date_from = null, $date_to = null, $season = null)
    {
        global $wpdb;

        $start_time = microtime(true);

        // Get competition from database
        $competition = $this->get_competition_by_api_id($api_competition_id);

        if (!$competition) {
            return array(
                'success' => false,
                'message' => 'Competition not found in database'
            );
        }

        // Default to current season
        if (!$season) {
            $season = $this->get_current_season();
        }

        // FIXED: Default date range now includes past 3 days (for status updates) + next 7 days
        if (!$date_from) {
            $date_from = date('Y-m-d', strtotime('-3 days')); // LINE 115 - CHANGED FROM: date('Y-m-d')
        }
        if (!$date_to) {
            $date_to = date('Y-m-d', strtotime('+7 days')); // LINE 118 - UNCHANGED
        }

        // Build API parameters
        $params = array(
            'league' => $api_competition_id,
            'season' => $season
        );

        if ($date_from) {
            $params['from'] = $date_from;
        }
        if ($date_to) {
            $params['to'] = $date_to;
        }

        // Fetch from API
        $response = $this->client->request('fixtures', $params);

        if (isset($response['error'])) {
            return array(
                'success' => false,
                'message' => $response['error']
            );
        }

        $matches = $response['response'] ?? array();

        if (empty($matches)) {
            return array(
                'success' => true,
                'message' => 'No matches found for specified date range',
                'processed' => 0,
                'created' => 0,
                'updated' => 0
            );
        }

        $created_count = 0;
        $updated_count = 0;
        $failed_count = 0;

        foreach ($matches as $match_data) {
            $result = $this->save_match($match_data, $competition->id);

            if ($result['created']) {
                $created_count++;
            } elseif ($result['updated']) {
                $updated_count++;
            } else {
                $failed_count++;
            }
        }

        $duration = round(microtime(true) - $start_time, 2);

        return array(
            'success' => true,
            'message' => sprintf(
                '%s: %d matches processed (%d new, %d updated)',
                $competition->name,
                count($matches),
                $created_count,
                $updated_count
            ),
            'processed' => count($matches),
            'created' => $created_count,
            'updated' => $updated_count,
            'failed' => $failed_count,
            'duration' => $duration
        );
    }

    /**
     * Sync single match by API ID
     * FIXED: Use request() instead of get()
     * 
     * @param string $api_match_id API Match ID
     * @return array Result
     */
    public function sync_single_match($api_match_id)
    {
        // Use request() method instead of non-existent get() method
        $response = $this->client->request('fixtures', array('id' => $api_match_id), true);

        if (isset($response['error'])) {
            return array(
                'success' => false,
                'message' => __('API Error: ' . $response['error'], 'goalv')
            );
        }

        if (empty($response['response'])) {
            return array(
                'success' => false,
                'message' => __('Match not found in API', 'goalv')
            );
        }

        $match_data = $response['response'][0];

        // Get competition_id from database - needed for save_match()
        global $wpdb;
        $matches_table = $wpdb->prefix . 'goalv_matches';
        $existing_match = $wpdb->get_row($wpdb->prepare(
            "SELECT competition_id FROM $matches_table WHERE api_match_id = %s",
            $api_match_id
        ));

        if (!$existing_match) {
            return array(
                'success' => false,
                'message' => __('Match not found in database', 'goalv')
            );
        }

        // Process and save match with correct competition_id
        $saved = $this->save_match($match_data, $existing_match->competition_id);

        if ($saved['updated'] || $saved['created']) {
            return array(
                'success' => true,
                'message' => __('Match synced successfully', 'goalv')
            );
        }

        return array(
            'success' => false,
            'message' => __('Failed to save match', 'goalv')
        );
    }

    /**
     * Save or update a single match
     * UPDATED: Now uses Vote Options Manager
     */
    private function save_match($match_data, $competition_id)
    {
        global $wpdb;

        $matches_table = $wpdb->prefix . 'goalv_matches';
        $api_match_id = $match_data['fixture']['id'];

        // Get or create team IDs
        $home_team_id = $this->get_or_create_team($match_data['teams']['home']);
        $away_team_id = $this->get_or_create_team($match_data['teams']['away']);

        if (!$home_team_id || !$away_team_id) {
            return array('created' => false, 'updated' => false);
        }

        // Convert API status to our format
        $status = $this->convert_match_status($match_data['fixture']['status']['short']);

        // Prepare match data
        $match_record = array(
            'competition_id' => $competition_id,
            'home_team_id' => $home_team_id,
            'away_team_id' => $away_team_id,
            'match_date' => date('Y-m-d H:i:s', $match_data['fixture']['timestamp']),
            'status' => $status,
            'home_score' => $match_data['goals']['home'],
            'away_score' => $match_data['goals']['away'],
            'home_halftime_score' => $match_data['score']['halftime']['home'] ?? null,
            'away_halftime_score' => $match_data['score']['halftime']['away'] ?? null,
            'home_fulltime_score' => $match_data['score']['fulltime']['home'] ?? null,
            'away_fulltime_score' => $match_data['score']['fulltime']['away'] ?? null,
            'venue' => $match_data['fixture']['venue']['name'] ?? null,
            'referee' => $match_data['fixture']['referee'] ?? null,
            'matchday' => $match_data['league']['round'] ? $this->extract_matchday($match_data['league']['round']) : null
        );

        // Check if match exists
        $existing_match = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $matches_table WHERE api_match_id = %s",
            $api_match_id
        ));

        if ($existing_match) {
            // Update existing match
            $updated = $wpdb->update(
                $matches_table,
                $match_record,
                array('api_match_id' => $api_match_id),
                array('%d', '%d', '%d', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%d'),
                array('%s')
            );

            // Ensure vote options exist for existing match
            if (class_exists('GoalV_Vote_Options_Manager')) {
                GoalV_Vote_Options_Manager::ensure_options_exist($existing_match->id);
            }

            return array('created' => false, 'updated' => $updated !== false);

        } else {
            // Insert new match
            $match_record['api_match_id'] = $api_match_id;

            $inserted = $wpdb->insert(
                $matches_table,
                $match_record,
                array('%s', '%d', '%d', '%d', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%d')
            );

            if ($inserted) {
                $match_id = $wpdb->insert_id;

                // Create vote options for new match
                if (class_exists('GoalV_Vote_Options_Manager')) {
                    try {
                        $vote_result = GoalV_Vote_Options_Manager::create_default_options(
                            $match_id,
                            $home_team_id,
                            $away_team_id
                        );

                        if (!$vote_result['success']) {
                            error_log('GoalV: Failed to create vote options for match ' . $match_id . ': ' . ($vote_result['error'] ?? 'Unknown error'));
                        }
                    } catch (Exception $e) {
                        error_log('GoalV: Exception creating vote options for match ' . $match_id . ': ' . $e->getMessage());
                    }
                } else {
                    error_log('GoalV WARNING: Vote Options Manager class not loaded for match ID: ' . $match_id);
                }

                return array('created' => true, 'updated' => false);
            }

            return array('created' => false, 'updated' => false);
        }
    }

    /**
     * Get or create team in database
     */
    private function get_or_create_team($team_data)
    {
        global $wpdb;

        $teams_table = $wpdb->prefix . 'goalv_teams';
        $api_team_id = $team_data['id'];

        // Check if team exists
        $existing_team = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $teams_table WHERE api_team_id = %s",
            $api_team_id
        ));

        if ($existing_team) {
            return $existing_team->id;
        }

        // Create new team
        $team_record = array(
            'api_team_id' => $api_team_id,
            'name' => $team_data['name'],
            'logo_url' => $team_data['logo'] ?? null
        );

        $inserted = $wpdb->insert(
            $teams_table,
            $team_record,
            array('%s', '%s', '%s')
        );

        return $inserted ? $wpdb->insert_id : null;
    }

    /**
     * Convert API match status to our format
     */
    private function convert_match_status($api_status)
    {
        $status_map = array(
            'TBD' => 'scheduled',
            'NS' => 'scheduled',
            'PST' => 'postponed',
            'CANC' => 'cancelled',
            '1H' => 'live',
            'HT' => 'paused',
            '2H' => 'live',
            'ET' => 'live',
            'P' => 'live',
            'FT' => 'finished',
            'AET' => 'finished',
            'PEN' => 'finished',
            'BT' => 'live',
            'SUSP' => 'paused',
            'INT' => 'paused',
            'AWD' => 'awarded',
            'WO' => 'awarded',
            'LIVE' => 'live'
        );

        return $status_map[$api_status] ?? 'scheduled';
    }

    /**
     * Extract matchday number from round string
     */
    private function extract_matchday($round_string)
    {
        // Examples: "Regular Season - 15", "Matchday 10"
        if (preg_match('/\d+/', $round_string, $matches)) {
            return (int) $matches[0];
        }
        return null;
    }

    /**
     * Get current football season
     */
    private function get_current_season()
    {
        $month = (int) date('n');
        $year = (int) date('Y');

        return ($month < 8) ? $year - 1 : $year;
    }

    /**
     * Get competition by API ID
     */
    private function get_competition_by_api_id($api_competition_id)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'goalv_competitions';

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE api_competition_id = %s",
            $api_competition_id
        ));
    }

    /**
     * Get single match with related data
     * Used after sync to verify changes
     * 
     * @param int $match_id Database match ID
     * @return object|null Match data with team info
     */
    public function get_match($match_id)
    {
        global $wpdb;

        $matches_table = $wpdb->prefix . 'goalv_matches';
        $teams_table = $wpdb->prefix . 'goalv_teams';
        $competitions_table = $wpdb->prefix . 'goalv_competitions';

        return $wpdb->get_row($wpdb->prepare(
            "SELECT m.*, 
                ht.name as home_team_name, ht.logo_url as home_team_logo,
                at.name as away_team_name, at.logo_url as away_team_logo,
                c.name as competition_name
         FROM {$matches_table} m
         LEFT JOIN {$teams_table} ht ON m.home_team_id = ht.id
         LEFT JOIN {$teams_table} at ON m.away_team_id = at.id
         LEFT JOIN {$competitions_table} c ON m.competition_id = c.id
         WHERE m.id = %d",
            $match_id
        ));
    }

    /**
     * Get matches by status
     */
    public function get_matches_by_status($status, $limit = 10)
    {
        global $wpdb;

        $matches_table = $wpdb->prefix . 'goalv_matches';
        $teams_table = $wpdb->prefix . 'goalv_teams';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT m.*, 
                    ht.name as home_team_name, ht.logo_url as home_team_logo,
                    at.name as away_team_name, at.logo_url as away_team_logo
             FROM $matches_table m
             LEFT JOIN $teams_table ht ON m.home_team_id = ht.id
             LEFT JOIN $teams_table at ON m.away_team_id = at.id
             WHERE m.status = %s
             ORDER BY m.match_date ASC
             LIMIT %d",
            $status,
            $limit
        ));
    }

    /**
     * Get upcoming matches
     */
    public function get_upcoming_matches($limit = 10, $competition_id = null)
    {
        global $wpdb;

        $matches_table = $wpdb->prefix . 'goalv_matches';
        $teams_table = $wpdb->prefix . 'goalv_teams';
        $competitions_table = $wpdb->prefix . 'goalv_competitions';

        $where = "m.status = 'scheduled' AND m.match_date >= NOW()";

        if ($competition_id) {
            $where .= $wpdb->prepare(" AND m.competition_id = %d", $competition_id);
        }

        return $wpdb->get_results(
            "SELECT m.*, 
                    ht.name as home_team_name, ht.logo_url as home_team_logo,
                    at.name as away_team_name, at.logo_url as away_team_logo,
                    c.name as competition_name
             FROM $matches_table m
             LEFT JOIN $teams_table ht ON m.home_team_id = ht.id
             LEFT JOIN $teams_table at ON m.away_team_id = at.id
             LEFT JOIN $competitions_table c ON m.competition_id = c.id
             WHERE $where
             ORDER BY m.match_date ASC
             LIMIT $limit"
        );
    }

    /**
     * Log sync operation
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