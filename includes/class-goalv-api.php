<?php
/**
 * Football-Data.org API Integration - UPDATED WITH PROPER WEEK DETECTION
 */

if (!defined('ABSPATH')) {
    exit;
}

class GoalV_API
{
    private $api_url = 'https://api.football-data.org/v4/';
    private $api_key;

    public function __construct()
    {
        $this->api_key = get_option('goalv_api_key', '');
    }

    /**
     * Make API request
     */
    private function make_request($endpoint)
    {
        if (empty($this->api_key)) {
            return array('error' => __('API key not configured', 'goalv'));
        }

        // Check cache first
        $cache_key = 'goalv_api_' . md5($endpoint);
        $cached_data = get_transient($cache_key);
        if ($cached_data !== false) {
            return $cached_data;
        }

        $args = array(
            'headers' => array(
                'X-Auth-Token' => $this->api_key
            ),
            'timeout' => 30
        );

        $response = wp_remote_get($this->api_url . $endpoint, $args);

        if (is_wp_error($response)) {
            return array('error' => $response->get_error_message());
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            return array('error' => 'API returned status code: ' . $status_code);
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return array('error' => 'Invalid JSON response');
        }

        // Cache for 5 minutes
        set_transient($cache_key, $data, 300);

        return $data;
    }

    /**
     * Get current football week - NEW METHOD
     */
    public function get_current_football_week($competition_id = null)
    {
        if (!$competition_id) {
            $competition_id = get_option('goalv_competition_id', '2021');
        }

        // Get season info to determine start date
        $season_data = $this->make_request("competitions/{$competition_id}");

        if (isset($season_data['error'])) {
            error_log('GoalV API: Error getting season data - ' . $season_data['error']);
            return 4; // Default fallback to week 4 as you mentioned
        }

        $current_season = $season_data['currentSeason'] ?? null;

        if (!$current_season) {
            return 4; // Fallback
        }

        $season_start = strtotime($current_season['startDate']);
        $today = time();

        // Calculate weeks since season started
        $weeks_elapsed = floor(($today - $season_start) / (7 * 24 * 60 * 60));

        // Football seasons typically start around week 1, adjust accordingly
        $current_week = max(1, $weeks_elapsed + 1);

        // Cap at reasonable maximum (Premier League has ~38 gameweeks)
        $current_week = min($current_week, 38);

        error_log("GoalV API: Calculated current football week: GW{$current_week}");

        return $current_week;
    }

    /**
     * Get available weeks for dropdown - NEW METHOD
     */
    public function get_available_weeks($competition_id = null)
    {
        if (!$competition_id) {
            $competition_id = get_option('goalv_competition_id', '2021');
        }

        $current_week = $this->get_current_football_week($competition_id);
        $weeks = array();

        // Generate weeks: previous 2, current, next 5
        for ($i = $current_week - 2; $i <= $current_week + 5; $i++) {
            if ($i > 0 && $i <= 38) {
                $label = "GW{$i}";
                if ($i == $current_week) {
                    $label .= " (Current)";
                } elseif ($i == $current_week + 1) {
                    $label .= " (Next)";
                }
                $weeks[$i] = $label;
            }
        }

        return $weeks;
    }

    /**
     * Get next week with upcoming matches - NEW METHOD
     */
    public function get_next_week_with_matches($competition_id = null)
    {
        if (!$competition_id) {
            $competition_id = get_option('goalv_competition_id', '2021');
        }

        $current_week = $this->get_current_football_week($competition_id);

        // Start from current week and look ahead
        for ($week = $current_week; $week <= $current_week + 3; $week++) {
            $matches = $this->get_matches_by_week($competition_id, $week);

            if (isset($matches['matches']) && !empty($matches['matches'])) {
                // Check if any matches are scheduled (not finished)
                foreach ($matches['matches'] as $match) {
                    $status = strtoupper($match['status']);
                    if (in_array($status, ['SCHEDULED', 'TIMED', 'POSTPONED'])) {
                        error_log("GoalV API: Found upcoming matches in GW{$week}");
                        return $week;
                    }
                }
            }
        }

        // Fallback to next week
        return $current_week + 1;
    }

    /**
     * Get matches by specific game week - NEW METHOD
     */
    public function get_matches_by_week($competition_id, $week_number)
    {
        // For now, we'll use date ranges since the API doesn't have direct week endpoints
        // This is an approximation - each week is roughly 7 days apart

        $season_data = $this->make_request("competitions/{$competition_id}");

        if (isset($season_data['error'])) {
            return array('error' => $season_data['error']);
        }

        $season_start = strtotime($season_data['currentSeason']['startDate']);

        // Calculate approximate dates for this game week
        $week_start_offset = ($week_number - 1) * 7; // Days from season start
        $week_start = date('Y-m-d', strtotime("+{$week_start_offset} days", $season_start));
        $week_end = date('Y-m-d', strtotime("+6 days", strtotime($week_start)));

        error_log("GoalV API: Fetching GW{$week_number} matches from {$week_start} to {$week_end}");

        $endpoint = "competitions/{$competition_id}/matches?dateFrom={$week_start}&dateTo={$week_end}";

        return $this->make_request($endpoint);
    }

    /**
     * Sync specific week matches - NEW METHOD
     */
    public function sync_week_matches($specific_week = null)
    {
        error_log('GoalV API: Starting sync_week_matches');

        $competition_id = get_option('goalv_competition_id', '2021');

        if ($specific_week) {
            $target_week = $specific_week;
            error_log("GoalV API: Syncing manually selected week: GW{$target_week}");
        } else {
            $target_week = $this->get_next_week_with_matches($competition_id);
            error_log("GoalV API: Auto-detected next week with matches: GW{$target_week}");
        }

        // Get matches for target week
        $matches_data = $this->get_matches_by_week($competition_id, $target_week);

        if (isset($matches_data['error'])) {
            error_log('GoalV API: Error getting matches - ' . $matches_data['error']);
            return array('success' => false, 'message' => $matches_data['error']);
        }

        if (!isset($matches_data['matches']) || empty($matches_data['matches'])) {
            error_log("GoalV API: No matches found for GW{$target_week}");
            return array('success' => false, 'message' => "No matches found for Game Week {$target_week}");
        }

        $synced_count = 0;
        $updated_count = 0;
        $gameweek_label = "GW{$target_week}";

        error_log('GoalV API: Processing ' . count($matches_data['matches']) . ' matches for ' . $gameweek_label);

        foreach ($matches_data['matches'] as $match) {
            $existing_match = $this->get_match_by_api_id($match['id']);

            if ($existing_match) {
                // Update existing match
                $was_updated = $this->update_match($existing_match->ID, $match, $gameweek_label);
                if ($was_updated) {
                    $updated_count++;
                }
                error_log('GoalV API: Updated match ID ' . $existing_match->ID);
            } else {
                // Create new match
                $match_id = $this->create_match($match, $gameweek_label);
                if ($match_id) {
                    $this->create_vote_options($match_id);
                    $synced_count++;
                    error_log('GoalV API: Created new match ID ' . $match_id);
                }
            }
        }

        $total_processed = $synced_count + $updated_count;

        if ($total_processed > 0) {
            $message = sprintf(
                __('%s: %d matches processed (%d new, %d updated)', 'goalv'),
                $gameweek_label,
                $total_processed,
                $synced_count,
                $updated_count
            );
        } else {
            $message = __("No changes made for {$gameweek_label} - all matches are up to date", 'goalv');
        }

        error_log('GoalV API: Sync completed - ' . $message);

        return array(
            'success' => true,
            'message' => $message,
            'count' => $total_processed,
            'new' => $synced_count,
            'updated' => $updated_count,
            'week' => $gameweek_label
        );
    }

    /**
     * Test API connection - NEW METHOD
     */
    public function test_api_connection()
    {
        $competition_id = get_option('goalv_competition_id', '2021');

        // Test with competition info (lightweight request)
        $result = $this->make_request("competitions/{$competition_id}");

        if (isset($result['error'])) {
            return array(
                'success' => false,
                'message' => __('API connection failed: ', 'goalv') . $result['error']
            );
        }

        if (isset($result['name'])) {
            return array(
                'success' => true,
                'message' => __('Connection successful', 'goalv'),
                'competition' => $result['name']
            );
        }

        return array(
            'success' => false,
            'message' => __('Unexpected API response format', 'goalv')
        );
    }

    /**
     * Get current week matches - UPDATED VERSION
     */
    private function get_current_week_matches($limit)
    {
        // Get matches for current/next football week, not calendar week
        $competition_id = get_option('goalv_competition_id', '2021');
        $current_football_week = $this->get_current_football_week($competition_id);

        $args = array(
            'post_type' => 'goalv_matches',
            'posts_per_page' => $limit,
            'post_status' => 'publish',
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => 'goalv_week_synced',
                    'value' => "GW{$current_football_week}",
                    'compare' => '='
                ),
                array(
                    'key' => 'goalv_week_synced',
                    'value' => "GW" . ($current_football_week + 1),
                    'compare' => '='
                ),
                // Also include matches with upcoming status regardless of week
                array(
                    'key' => 'goalv_match_status',
                    'value' => 'scheduled',
                    'compare' => '='
                )
            ),
            'meta_key' => 'goalv_match_date',
            'orderby' => 'meta_value',
            'order' => 'ASC'
        );

        $matches = get_posts($args);

        // If no matches found, fall back to date-based query for upcoming matches
        if (empty($matches)) {
            $today = date('Y-m-d');
            $next_week = date('Y-m-d', strtotime('+7 days'));

            $args = array(
                'post_type' => 'goalv_matches',
                'posts_per_page' => $limit,
                'post_status' => 'publish',
                'meta_query' => array(
                    array(
                        'key' => 'goalv_match_date',
                        'value' => array($today, $next_week),
                        'compare' => 'BETWEEN',
                        'type' => 'DATE'
                    )
                ),
                'meta_key' => 'goalv_match_date',
                'orderby' => 'meta_value',
                'order' => 'ASC'
            );

            $matches = get_posts($args);
        }

        // Enhance matches with additional data
        foreach ($matches as &$match) {
            $match->home_team = get_post_meta($match->ID, 'goalv_home_team', true);
            $match->away_team = get_post_meta($match->ID, 'goalv_away_team', true);
            $match->home_team_logo = get_post_meta($match->ID, 'goalv_home_team_logo', true);
            $match->away_team_logo = get_post_meta($match->ID, 'goalv_away_team_logo', true);
            $match->match_date = get_post_meta($match->ID, 'goalv_match_date', true);
            $match->match_status = get_post_meta($match->ID, 'goalv_match_status', true);
            $match->home_score = get_post_meta($match->ID, 'goalv_home_score', true);
            $match->away_score = get_post_meta($match->ID, 'goalv_away_score', true);
            $match->competition = get_post_meta($match->ID, 'goalv_competition', true);

            // Get vote options and user's current vote
            $voting = new GoalV_Voting();
            $match->vote_options = $voting->get_vote_options($match->ID, 'basic');
            $match->user_vote = $voting->get_user_vote($match->ID, 'homepage');
            $match->vote_results = $this->get_vote_percentages($match->ID, 'homepage');
        }

        return $matches;
    }

    /**
     * Sync current week matches - KEPT FOR BACKWARDS COMPATIBILITY
     */
    public function sync_current_week_matches()
    {
        // Redirect to new week-based sync method
        return $this->sync_week_matches();
    }

    /**
     * Get match by API ID
     */
    private function get_match_by_api_id($api_id)
    {
        $matches = get_posts(array(
            'post_type' => 'goalv_matches',
            'meta_key' => 'goalv_api_match_id',
            'meta_value' => $api_id,
            'posts_per_page' => 1
        ));

        return !empty($matches) ? $matches[0] : null;
    }

    /**
     * Create new match - UPDATED with proper week labeling
     */
    private function create_match($match_data, $week_label)
    {
        $home_team = $match_data['homeTeam']['name'];
        $away_team = $match_data['awayTeam']['name'];

        $post_data = array(
            'post_title' => $home_team . ' vs ' . $away_team,
            'post_type' => 'goalv_matches',
            'post_status' => 'publish',
            'meta_input' => array(
                'goalv_api_match_id' => $match_data['id'],
                'goalv_home_team' => $home_team,
                'goalv_away_team' => $away_team,
                'goalv_home_team_logo' => $match_data['homeTeam']['crest'] ?? '',
                'goalv_away_team_logo' => $match_data['awayTeam']['crest'] ?? '',
                'goalv_match_date' => date('Y-m-d\TH:i', strtotime($match_data['utcDate'])),
                'goalv_match_status' => $this->convert_status($match_data['status']),
                'goalv_home_score' => $match_data['score']['fullTime']['home'] ?? 0,
                'goalv_away_score' => $match_data['score']['fullTime']['away'] ?? 0,
                'goalv_competition' => $match_data['competition']['name'],
                'goalv_week_synced' => $week_label
            )
        );

        return wp_insert_post($post_data);
    }

    /**
     * Update existing match - UPDATED with proper week labeling
     */
    private function update_match($post_id, $match_data, $week_label)
    {
        $changes_made = false;

        // Get current values
        $current_status = get_post_meta($post_id, 'goalv_match_status', true);
        $current_home_score = (int) get_post_meta($post_id, 'goalv_home_score', true);
        $current_away_score = (int) get_post_meta($post_id, 'goalv_away_score', true);

        // New values from API
        $new_status = $this->convert_status($match_data['status']);
        $new_home_score = (int) ($match_data['score']['fullTime']['home'] ?? 0);
        $new_away_score = (int) ($match_data['score']['fullTime']['away'] ?? 0);

        // Update status if changed
        if ($current_status !== $new_status) {
            update_post_meta($post_id, 'goalv_match_status', $new_status);
            $changes_made = true;
            error_log("GoalV API: Match $post_id status changed from $current_status to $new_status");
        }

        // Update scores if changed
        if ($current_home_score !== $new_home_score) {
            update_post_meta($post_id, 'goalv_home_score', $new_home_score);
            $changes_made = true;
            error_log("GoalV API: Match $post_id home score changed from $current_home_score to $new_home_score");
        }

        if ($current_away_score !== $new_away_score) {
            update_post_meta($post_id, 'goalv_away_score', $new_away_score);
            $changes_made = true;
            error_log("GoalV API: Match $post_id away score changed from $current_away_score to $new_away_score");
        }

        // Always update week synced
        update_post_meta($post_id, 'goalv_week_synced', $week_label);

        // Update logos if they've changed or are missing
        $current_home_logo = get_post_meta($post_id, 'goalv_home_team_logo', true);
        $current_away_logo = get_post_meta($post_id, 'goalv_away_team_logo', true);

        $new_home_logo = $match_data['homeTeam']['crest'] ?? '';
        $new_away_logo = $match_data['awayTeam']['crest'] ?? '';

        if (empty($current_home_logo) && !empty($new_home_logo)) {
            update_post_meta($post_id, 'goalv_home_team_logo', $new_home_logo);
            $changes_made = true;
        }

        if (empty($current_away_logo) && !empty($new_away_logo)) {
            update_post_meta($post_id, 'goalv_away_team_logo', $new_away_logo);
            $changes_made = true;
        }

        // Update match date if it has changed
        $current_date = get_post_meta($post_id, 'goalv_match_date', true);
        $new_date = date('Y-m-d\TH:i', strtotime($match_data['utcDate']));

        if ($current_date !== $new_date) {
            update_post_meta($post_id, 'goalv_match_date', $new_date);
            $changes_made = true;
            error_log("GoalV API: Match $post_id date changed from $current_date to $new_date");
        }

        return $changes_made;
    }

    /**
     * Convert API status to our format
     */
    private function convert_status($api_status)
    {
        error_log('GoalV API: Converting status: ' . $api_status);

        switch (strtoupper($api_status)) {
            case 'SCHEDULED':
            case 'TIMED':
            case 'POSTPONED':
            case 'SUSPENDED':
            case 'CANCELED':
                return 'scheduled';

            case 'IN_PLAY':
            case 'PAUSED':
            case 'LIVE':
                return 'live';

            case 'FINISHED':
            case 'FULL_TIME':
            case 'AWARDED':
                return 'finished';

            default:
                error_log('GoalV API: Unhandled match status: ' . $api_status . ' - defaulting to scheduled');
                return 'scheduled';
        }
    }

    /**
     * Create vote options for a match
     */
    private function create_vote_options($match_id)
    {
        global $wpdb;

        $home_team = get_post_meta($match_id, 'goalv_home_team', true);
        $away_team = get_post_meta($match_id, 'goalv_away_team', true);

        // Basic options (for homepage) - GENERIC with proper categories
        $basic_options = array(
            array('text' => 'Home Win', 'category' => 'match_result', 'order' => 1),
            array('text' => 'Draw', 'category' => 'match_result', 'order' => 2),
            array('text' => 'Away Win', 'category' => 'match_result', 'order' => 3)
        );

        // Detailed options (for single page) - TEAM SPECIFIC with proper categories
        $detailed_options = array(
            // Match Result
            array('text' => $home_team . ' Win', 'category' => 'match_result', 'order' => 1),
            array('text' => 'Draw', 'category' => 'match_result', 'order' => 2),
            array('text' => $away_team . ' Win', 'category' => 'match_result', 'order' => 3),

            // Goals Threshold
            array('text' => 'Over 2.5 Goals', 'category' => 'goals_threshold', 'order' => 4),
            array('text' => 'Under 2.5 Goals', 'category' => 'goals_threshold', 'order' => 5),

            // Both Teams Score
            array('text' => 'Both Teams Score - Yes', 'category' => 'both_teams_score', 'order' => 6),
            array('text' => 'Both Teams Score - No', 'category' => 'both_teams_score', 'order' => 7),

            // Score Predictions
            array('text' => $home_team . ' Wins 2-1', 'category' => 'match_score', 'order' => 8),
            array('text' => $away_team . ' Wins 1-2', 'category' => 'match_score', 'order' => 9),
            array('text' => 'Match Ends 1-1', 'category' => 'match_score', 'order' => 10),

            // First to Score
            array('text' => $home_team . ' Scores First', 'category' => 'first_to_score', 'order' => 11),
            array('text' => $away_team . ' Scores First', 'category' => 'first_to_score', 'order' => 12)
        );

        $table_name = $wpdb->prefix . 'goalv_vote_options';

        // Check if options already exist
        $existing_options = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE match_id = %d",
            $match_id
        ));

        if ($existing_options > 0) {
            error_log("GoalV API: Vote options already exist for match $match_id, skipping creation");
            return;
        }

        // Insert basic options with proper categories
        foreach ($basic_options as $option) {
            $wpdb->insert(
                $table_name,
                array(
                    'match_id' => $match_id,
                    'option_text' => $option['text'],
                    'option_type' => 'basic',
                    'category' => $option['category'], // THIS IS CRUCIAL!
                    'display_order' => $option['order'],
                    'is_custom' => 0,
                    'votes_count' => 0
                ),
                array('%d', '%s', '%s', '%s', '%d', '%d', '%d')
            );
        }

        // Insert detailed options with proper categories
        foreach ($detailed_options as $option) {
            $wpdb->insert(
                $table_name,
                array(
                    'match_id' => $match_id,
                    'option_text' => $option['text'],
                    'option_type' => 'detailed',
                    'category' => $option['category'],
                    'display_order' => $option['order'],
                    'is_custom' => 0,
                    'votes_count' => 0
                ),
                array('%d', '%s', '%s', '%s', '%d', '%d', '%d')
            );
        }

        error_log("GoalV API: Created vote options with proper categories for match $match_id");
    }

    /**
     * Get team logo with fallback
     */
    public function get_team_logo($logo_url, $team_name)
    {
        if (!empty($logo_url) && filter_var($logo_url, FILTER_VALIDATE_URL)) {
            return $logo_url;
        }

        return GOALV_PLUGIN_URL . 'assets/images/default-team-logo.png';
    }

    /**
     * Get vote percentages - HELPER METHOD
     */
    private function get_vote_percentages($match_id, $location)
    {
        $voting = new GoalV_Voting();
        return $voting->get_vote_percentages($match_id, $location);
    }

    private function log_error($message, $context = array())
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('GoalV Plugin: ' . $message . ' Context: ' . print_r($context, true));
        }
    }
}