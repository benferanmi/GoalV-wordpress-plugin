<?php
/**
 * GoalV Live Scores Sync Handler - FIXED VERSION
 * 
 * FIXES:
 * 1. Added time-based stale match finalization (matches older than 4 hours)
 * 2. Improved finalize logic to handle edge cases
 * 3. Added better error handling
 * 
 * @package GoalV
 * @version 8.1.1
 */

if (!defined('ABSPATH')) {
    exit;
}

class GoalV_Sync_Live_Scores
{
    private $api_client;

    public function __construct()
    {
        $this->api_client = new GoalV_API_Football_Client();
    }

    /**
     * Main method: Sync all currently live matches
     */
    public function sync_live_scores()
    {
        global $wpdb;
        $matches_table = $wpdb->prefix . 'goalv_matches';
        $live_scores_table = $wpdb->prefix . 'goalv_live_scores';

        $results = array(
            'live_matches' => 0,
            'updated' => 0,
            'finalized' => 0,
            'errors' => array(),
            'timestamp' => current_time('mysql')
        );

        // CRITICAL FIX: Finalize stale matches FIRST (before API call)
        $stale_finalized = $this->finalize_stale_live_matches();
        $results['finalized'] += $stale_finalized;

        // Fetch live matches from API
        $response = $this->api_client->request('fixtures', array('live' => 'all'), true);

        if (isset($response['error'])) {
            $results['errors'][] = $response['error'];
            return $results;
        }

        $live_fixtures = $response['response'] ?? array();
        $results['live_matches'] = count($live_fixtures);

        // If no live matches, ensure all are finalized
        if (empty($live_fixtures)) {
            // Already handled by finalize_stale_live_matches above
            return $results;
        }

        // Update each live match
        foreach ($live_fixtures as $fixture) {
            try {
                $updated = $this->update_live_match($fixture);
                if ($updated) {
                    $results['updated']++;
                }
            } catch (Exception $e) {
                $results['errors'][] = sprintf(
                    'Match %d: %s',
                    $fixture['fixture']['id'] ?? 'unknown',
                    $e->getMessage()
                );
            }
        }

        // CRITICAL FIX: Finalize matches that are no longer in live API response
        $completed = $this->finalize_completed_matches($live_fixtures);
        $results['finalized'] += $completed;

        return $results;
    }

    /**
     * Update a single live match
     */
    private function update_live_match($fixture)
    {
        global $wpdb;
        $matches_table = $wpdb->prefix . 'goalv_matches';
        $live_scores_table = $wpdb->prefix . 'goalv_live_scores';
        $events_table = $wpdb->prefix . 'goalv_match_events';

        $api_match_id = $fixture['fixture']['id'];
        $status = $fixture['fixture']['status']['short'];
        $elapsed = $fixture['fixture']['status']['elapsed'] ?? 0;

        // Get internal match ID
        $match_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$matches_table} WHERE api_match_id = %d",
            $api_match_id
        ));

        if (!$match_id) {
            return false;
        }

        // Convert API status to our format
        $match_status = $this->convert_status($status);

        // Update main match record
        $wpdb->update(
            $matches_table,
            array(
                'status' => $match_status,
                'home_score' => $fixture['goals']['home'] ?? 0,
                'away_score' => $fixture['goals']['away'] ?? 0,
                'last_updated' => current_time('mysql')
            ),
            array('id' => $match_id),
            array('%s', '%d', '%d', '%s'),
            array('%d')
        );

        // Update/insert live scores table
        $existing_live = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$live_scores_table} WHERE match_id = %d",
            $match_id
        ));

        $live_data = array(
            'match_id' => $match_id,
            'home_score' => $fixture['goals']['home'] ?? 0,
            'away_score' => $fixture['goals']['away'] ?? 0,
            'status' => $status,
            'elapsed_time' => $elapsed,
            'halftime_home' => $fixture['score']['halftime']['home'] ?? null,
            'halftime_away' => $fixture['score']['halftime']['away'] ?? null,
            'last_updated' => current_time('mysql')
        );

        if ($existing_live) {
            $wpdb->update(
                $live_scores_table,
                $live_data,
                array('id' => $existing_live),
                array('%d', '%d', '%d', '%s', '%d', '%d', '%d', '%s'),
                array('%d')
            );
        } else {
            $wpdb->insert(
                $live_scores_table,
                $live_data,
                array('%d', '%d', '%d', '%s', '%d', '%d', '%d', '%s')
            );
        }

        // Process match events
        if (!empty($fixture['events'])) {
            $this->process_match_events($match_id, $fixture['events']);
        }

        return true;
    }

    /**
     * Process match events (goals, cards, subs)
     */
    private function process_match_events($match_id, $events)
    {
        global $wpdb;
        $events_table = $wpdb->prefix . 'goalv_match_events';

        foreach ($events as $event) {
            $event_type = strtolower($event['type'] ?? '');
            $event_detail = $event['detail'] ?? '';
            $time_elapsed = $event['time']['elapsed'] ?? 0;
            $team_id = $event['team']['id'] ?? 0;
            $player_name = $event['player']['name'] ?? 'Unknown';

            $description = $this->build_event_description($event);

            // Check if event exists
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$events_table} 
                 WHERE match_id = %d AND event_type = %s 
                 AND time_elapsed = %d AND player_name = %s",
                $match_id,
                $event_type,
                $time_elapsed,
                $player_name
            ));

            if (!$exists) {
                $wpdb->insert(
                    $events_table,
                    array(
                        'match_id' => $match_id,
                        'event_type' => $event_type,
                        'event_detail' => $event_detail,
                        'time_elapsed' => $time_elapsed,
                        'team_id' => $team_id,
                        'player_name' => $player_name,
                        'event_description' => $description,
                        'created_at' => current_time('mysql')
                    ),
                    array('%d', '%s', '%s', '%d', '%d', '%s', '%s', '%s')
                );
            }
        }
    }

    /**
     * Build human-readable event description
     */
    private function build_event_description($event)
    {
        $type = strtolower($event['type'] ?? '');
        $detail = $event['detail'] ?? '';
        $player = $event['player']['name'] ?? 'Unknown';
        $time = $event['time']['elapsed'] ?? 0;

        switch ($type) {
            case 'goal':
                $goal_type = ($detail === 'Penalty') ? ' (Penalty)' : '';
                return "{$time}' GOAL{$goal_type} - {$player}";

            case 'card':
                $card_color = ucfirst($detail);
                return "{$time}' {$card_color} Card - {$player}";

            case 'subst':
                $assist = $event['assist']['name'] ?? 'Unknown';
                return "{$time}' Substitution - {$player} replaces {$assist}";

            case 'var':
                return "{$time}' VAR - {$detail}";

            default:
                return "{$time}' {$type} - {$player}";
        }
    }

    /**
     * Convert API status to our internal status
     */
    private function convert_status($api_status)
    {
        $status_map = array(
            'TBD' => 'scheduled',
            'NS' => 'scheduled',
            '1H' => 'live',
            'HT' => 'live',
            '2H' => 'live',
            'ET' => 'live',
            'P' => 'live',
            'BT' => 'live',
            'SUSP' => 'live',
            'INT' => 'live',
            'FT' => 'finished',
            'AET' => 'finished',
            'PEN' => 'finished',
            'PST' => 'postponed',
            'CANC' => 'cancelled',
            'ABD' => 'abandoned',
            'AWD' => 'finished',
            'WO' => 'finished'
        );

        return $status_map[$api_status] ?? 'scheduled';
    }

    /**
     * CRITICAL FIX: Finalize matches that were live but are no longer
     * 
     * @param array $current_live_fixtures Currently live fixtures from API
     * @return int Number of matches finalized
     */
    private function finalize_completed_matches($current_live_fixtures)
    {
        global $wpdb;
        $matches_table = $wpdb->prefix . 'goalv_matches';

        // Get API IDs of currently live matches
        $live_api_ids = array_map(function ($f) {
            return $f['fixture']['id'];
        }, $current_live_fixtures);

        if (empty($live_api_ids)) {
            return 0; // Already handled by finalize_stale_live_matches
        }

        // Finalize matches marked as live but NOT in current API response
        $placeholders = implode(',', array_fill(0, count($live_api_ids), '%d'));

        $result = $wpdb->query($wpdb->prepare(
            "UPDATE {$matches_table} 
             SET status = 'finished', last_updated = NOW()
             WHERE status = 'live' 
             AND api_match_id NOT IN ({$placeholders})",
            $live_api_ids
        ));

        return $result !== false ? $result : 0;
    }

    /**
     * CRITICAL FIX: Finalize stale live matches (time-based check)
     * Fixes matches stuck in LIVE status from days ago
     * NOW HANDLES INVALID DATES (0000-00-00)
     * 
     * @return int Number of matches finalized
     */
    private function finalize_stale_live_matches()
    {
        global $wpdb;
        $matches_table = $wpdb->prefix . 'goalv_matches';

        $fixed_count = 0;

        // FIX 1: Handle matches with invalid/null dates (0000-00-00, NULL, '')
        $invalid_dates = $wpdb->query(
            "UPDATE {$matches_table} 
         SET status = 'finished', 
             match_date = NOW(),
             last_updated = NOW()
         WHERE status = 'live' 
         AND (match_date = '0000-00-00 00:00:00' 
              OR match_date IS NULL 
              OR match_date = '' 
              OR YEAR(match_date) < 2000)"
        );

        if ($invalid_dates > 0) {
            error_log("GoalV: Fixed {$invalid_dates} matches with invalid dates (0000-00-00)");
            $fixed_count += $invalid_dates;
        }

        // FIX 2: Finalize matches with valid dates that started more than 4 hours ago
        $stale_matches = $wpdb->query(
            "UPDATE {$matches_table} 
         SET status = 'finished', last_updated = NOW()
         WHERE status = 'live' 
         AND match_date < DATE_SUB(NOW(), INTERVAL 4 HOUR)
         AND match_date != '0000-00-00 00:00:00'
         AND match_date IS NOT NULL"
        );

        if ($stale_matches > 0) {
            error_log("GoalV: Finalized {$stale_matches} stale live matches (older than 4 hours)");
            $fixed_count += $stale_matches;
        }

        return $fixed_count;
    }

    /**
     * Get live match data for frontend display
     */
    public function get_live_match_data($match_id)
    {
        global $wpdb;
        $live_scores_table = $wpdb->prefix . 'goalv_live_scores';
        $events_table = $wpdb->prefix . 'goalv_match_events';

        $live_data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$live_scores_table} WHERE match_id = %d",
            $match_id
        ), ARRAY_A);

        if (!$live_data) {
            return null;
        }

        $events = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$events_table} 
             WHERE match_id = %d 
             ORDER BY time_elapsed DESC",
            $match_id
        ), ARRAY_A);

        $live_data['events'] = $events;

        return $live_data;
    }

    /**
     * Get all currently live matches
     */
    public function get_all_live_matches()
    {
        global $wpdb;
        $matches_table = $wpdb->prefix . 'goalv_matches';
        $teams_table = $wpdb->prefix . 'goalv_teams';
        $live_scores_table = $wpdb->prefix . 'goalv_live_scores';

        $query = "
            SELECT 
                m.*,
                ht.name as home_team_name,
                ht.logo_url as home_team_logo,
                at.name as away_team_name,
                at.logo_url as away_team_logo,
                ls.elapsed_time,
                ls.status as live_status
            FROM {$matches_table} m
            LEFT JOIN {$teams_table} ht ON m.home_team_id = ht.id
            LEFT JOIN {$teams_table} at ON m.away_team_id = at.id
            LEFT JOIN {$live_scores_table} ls ON m.id = ls.match_id
            WHERE m.status = 'live'
            ORDER BY m.match_date ASC
        ";

        return $wpdb->get_results($query, ARRAY_A);
    }

    /**
     * Get live matches count (for admin dashboard)
     */
    public function get_live_matches_count()
    {
        global $wpdb;
        $matches_table = $wpdb->prefix . 'goalv_matches';

        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$matches_table} WHERE status = 'live'"
        );
    }
}