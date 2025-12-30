<?php
/**
 * API Live Scores Handler
 * Handles real-time match updates and event tracking
 * 
 * @package GoalV
 * @version 8.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class GoalV_API_Live_Scores
{
    private $client;

    public function __construct()
    {
        $this->client = new GoalV_API_Football_Client();
    }

    /**
     * Get all currently live matches across all enabled competitions
     */
    public function get_all_live_matches()
    {
        $start_time = microtime(true);

        // Fetch all live fixtures
        $response = $this->client->request('fixtures', array('live' => 'all'), true); // Force fresh

        if (isset($response['error'])) {
            return array(
                'success' => false,
                'message' => $response['error'],
                'matches' => array()
            );
        }

        $live_fixtures = $response['response'] ?? array();

        if (empty($live_fixtures)) {
            return array(
                'success' => true,
                'message' => 'No live matches currently',
                'matches' => array()
            );
        }

        $updated_count = 0;
        $matches_data = array();

        foreach ($live_fixtures as $fixture_data) {
            $result = $this->update_live_match($fixture_data);
            
            if ($result['success']) {
                $updated_count++;
                $matches_data[] = $result['match_data'];
            }
        }

        $duration = round(microtime(true) - $start_time, 2);

        return array(
            'success' => true,
            'message' => sprintf('Updated %d live matches in %s seconds', $updated_count, $duration),
            'matches' => $matches_data,
            'count' => $updated_count
        );
    }

    /**
     * Get live matches for specific competition
     */
    public function get_competition_live_matches($competition_id)
    {
        $response = $this->client->request('fixtures', array('live' => $competition_id), true);

        if (isset($response['error'])) {
            return array(
                'success' => false,
                'message' => $response['error']
            );
        }

        $live_fixtures = $response['response'] ?? array();
        $updated_count = 0;

        foreach ($live_fixtures as $fixture_data) {
            $result = $this->update_live_match($fixture_data);
            if ($result['success']) {
                $updated_count++;
            }
        }

        return array(
            'success' => true,
            'count' => $updated_count
        );
    }

    /**
     * Update a single live match
     */
    private function update_live_match($fixture_data)
    {
        global $wpdb;

        $matches_table = $wpdb->prefix . 'goalv_matches';
        $live_scores_table = $wpdb->prefix . 'goalv_live_scores';
        
        $api_match_id = $fixture_data['fixture']['id'];

        // Get match from database
        $match = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $matches_table WHERE api_match_id = %s",
            $api_match_id
        ));

        if (!$match) {
            // Match not in database, skip
            return array('success' => false);
        }

        $match_id = $match->id;

        // Extract live data
        $status_short = $fixture_data['fixture']['status']['short'];
        $elapsed = $fixture_data['fixture']['status']['elapsed'];
        $home_score = $fixture_data['goals']['home'] ?? 0;
        $away_score = $fixture_data['goals']['away'] ?? 0;
        $halftime_home = $fixture_data['score']['halftime']['home'];
        $halftime_away = $fixture_data['score']['halftime']['away'];

        // Update main match record
        $wpdb->update(
            $matches_table,
            array(
                'status' => $this->convert_status($status_short),
                'home_score' => $home_score,
                'away_score' => $away_score,
                'home_halftime_score' => $halftime_home,
                'away_halftime_score' => $halftime_away,
                'match_minute' => $elapsed
            ),
            array('id' => $match_id),
            array('%s', '%d', '%d', '%d', '%d', '%d'),
            array('%d')
        );

        // Update or create live scores record
        $period = $this->get_period_from_status($status_short);
        $live_status = in_array($status_short, array('1H', '2H', 'ET', 'P')) ? 'live' : 'paused';

        $existing_live = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $live_scores_table WHERE match_id = %d",
            $match_id
        ));

        if ($existing_live) {
            // Update existing
            $wpdb->update(
                $live_scores_table,
                array(
                    'home_score' => $home_score,
                    'away_score' => $away_score,
                    'match_minute' => $elapsed,
                    'period' => $period,
                    'status' => $live_status,
                    'updated_at' => current_time('mysql')
                ),
                array('match_id' => $match_id),
                array('%d', '%d', '%d', '%s', '%s', '%s'),
                array('%d')
            );
        } else {
            // Create new
            $wpdb->insert(
                $live_scores_table,
                array(
                    'match_id' => $match_id,
                    'home_score' => $home_score,
                    'away_score' => $away_score,
                    'match_minute' => $elapsed,
                    'period' => $period,
                    'status' => $live_status
                ),
                array('%d', '%d', '%d', '%d', '%s', '%s')
            );
        }

        // Fetch and update match events
        $this->update_match_events($api_match_id, $match_id);

        return array(
            'success' => true,
            'match_data' => array(
                'match_id' => $match_id,
                'api_match_id' => $api_match_id,
                'home_score' => $home_score,
                'away_score' => $away_score,
                'minute' => $elapsed,
                'status' => $status_short
            )
        );
    }

    /**
     * Update match events (goals, cards, substitutions)
     */
    private function update_match_events($api_match_id, $match_id)
    {
        global $wpdb;

        // Fetch fixture with events
        $response = $this->client->request("fixtures", array('id' => $api_match_id), true);

        if (isset($response['error']) || empty($response['response'])) {
            return false;
        }

        $fixture = $response['response'][0];
        $events = $fixture['events'] ?? array();

        if (empty($events)) {
            return true;
        }

        $events_table = $wpdb->prefix . 'goalv_match_events';
        $teams_table = $wpdb->prefix . 'goalv_teams';

        foreach ($events as $event) {
            // Get team database ID
            $api_team_id = $event['team']['id'];
            $team_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $teams_table WHERE api_team_id = %s",
                $api_team_id
            ));

            if (!$team_id) {
                continue;
            }

            // Map event type
            $event_type = $this->map_event_type($event['type'], $event['detail']);
            
            if (!$event_type) {
                continue;
            }

            // Check if event already exists
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $events_table 
                 WHERE match_id = %d 
                 AND team_id = %d 
                 AND event_type = %s 
                 AND player_name = %s 
                 AND minute = %d",
                $match_id,
                $team_id,
                $event_type,
                $event['player']['name'] ?? '',
                $event['time']['elapsed']
            ));

            if ($existing) {
                continue; // Event already recorded
            }

            // Insert new event
            $wpdb->insert(
                $events_table,
                array(
                    'match_id' => $match_id,
                    'team_id' => $team_id,
                    'event_type' => $event_type,
                    'player_name' => $event['player']['name'] ?? null,
                    'assist_player_name' => $event['assist']['name'] ?? null,
                    'minute' => $event['time']['elapsed'],
                    'added_time' => $event['time']['extra'],
                    'detail' => $event['detail'] ?? null
                ),
                array('%d', '%d', '%s', '%s', '%s', '%d', '%d', '%s')
            );

            // Update last_goal_time in live_scores if it's a goal
            if (in_array($event_type, array('goal', 'penalty_goal', 'own_goal'))) {
                $live_scores_table = $wpdb->prefix . 'goalv_live_scores';
                $wpdb->update(
                    $live_scores_table,
                    array('last_goal_time' => current_time('mysql')),
                    array('match_id' => $match_id),
                    array('%s'),
                    array('%d')
                );
            }
        }

        return true;
    }

    /**
     * Mark matches as finished if they're no longer live
     */
    public function check_and_finalize_matches()
    {
        global $wpdb;

        $matches_table = $wpdb->prefix . 'goalv_matches';
        $live_scores_table = $wpdb->prefix . 'goalv_live_scores';

        // Get matches that are currently marked as live
        $live_matches = $wpdb->get_results(
            "SELECT id, api_match_id FROM $matches_table WHERE status = 'live'"
        );

        if (empty($live_matches)) {
            return array('success' => true, 'finalized' => 0);
        }

        $finalized_count = 0;

        foreach ($live_matches as $match) {
            // Check API for current status
            $response = $this->client->request('fixtures', array('id' => $match->api_match_id), true);

            if (isset($response['error']) || empty($response['response'])) {
                continue;
            }

            $fixture = $response['response'][0];
            $status_short = $fixture['fixture']['status']['short'];

            // If match is finished
            if (in_array($status_short, array('FT', 'AET', 'PEN', 'AWD', 'WO'))) {
                // Update match status
                $wpdb->update(
                    $matches_table,
                    array(
                        'status' => 'finished',
                        'home_fulltime_score' => $fixture['score']['fulltime']['home'],
                        'away_fulltime_score' => $fixture['score']['fulltime']['away']
                    ),
                    array('id' => $match->id),
                    array('%s', '%d', '%d'),
                    array('%d')
                );

                // Update live scores status
                $wpdb->update(
                    $live_scores_table,
                    array('status' => 'finished'),
                    array('match_id' => $match->id),
                    array('%s'),
                    array('%d')
                );

                $finalized_count++;
            }
        }

        return array(
            'success' => true,
            'finalized' => $finalized_count
        );
    }

    /**
     * Get live score data for a match
     */
    public function get_match_live_data($match_id)
    {
        global $wpdb;

        $live_scores_table = $wpdb->prefix . 'goalv_live_scores';
        $events_table = $wpdb->prefix . 'goalv_match_events';
        $teams_table = $wpdb->prefix . 'goalv_teams';

        // Get live scores
        $live_score = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $live_scores_table WHERE match_id = %d",
            $match_id
        ));

        if (!$live_score) {
            return null;
        }

        // Get events
        $events = $wpdb->get_results($wpdb->prepare(
            "SELECT e.*, t.name as team_name 
             FROM $events_table e
             LEFT JOIN $teams_table t ON e.team_id = t.id
             WHERE e.match_id = %d
             ORDER BY e.minute ASC",
            $match_id
        ));

        return array(
            'live_score' => $live_score,
            'events' => $events
        );
    }

    /**
     * Convert API status to our format
     */
    private function convert_status($status_short)
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
            'AWD' => 'awarded',
            'WO' => 'awarded'
        );

        return $status_map[$status_short] ?? 'scheduled';
    }

    /**
     * Get period from status
     */
    private function get_period_from_status($status_short)
    {
        $period_map = array(
            '1H' => 'first_half',
            'HT' => 'halftime',
            '2H' => 'second_half',
            'ET' => 'extra_time',
            'P' => 'penalties',
            'FT' => 'finished',
            'AET' => 'finished',
            'PEN' => 'finished'
        );

        return $period_map[$status_short] ?? 'first_half';
    }

    /**
     * Map event type from API
     */
    private function map_event_type($type, $detail)
    {
        if ($type === 'Goal') {
            if (strpos($detail, 'Penalty') !== false) {
                return 'penalty_goal';
            }
            if (strpos($detail, 'Own Goal') !== false) {
                return 'own_goal';
            }
            return 'goal';
        }

        if ($type === 'Card') {
            if (strpos($detail, 'Yellow') !== false) {
                return 'yellow_card';
            }
            if (strpos($detail, 'Red') !== false) {
                return 'red_card';
            }
        }

        if ($type === 'subst') {
            return 'substitution';
        }

        return null;
    }

    /**
     * Get count of currently live matches
     */
    public function get_live_matches_count()
    {
        global $wpdb;
        
        $table = $wpdb->prefix . 'goalv_matches';
        
        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM $table WHERE status = 'live'"
        );
    }
}