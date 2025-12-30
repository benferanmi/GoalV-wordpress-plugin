<?php
/**
 * GoalV Match Query Handler - FIXED FOR LIVE SCORES
 * Pure database queries for matches - NO CPT DEPENDENCIES
 * 
 * @package GoalV
 * @subpackage Frontend
 * @version 8.1.1 - LIVE SCORE FIX
 */

if (!defined('ABSPATH')) {
    exit;
}

class GoalV_Match_Query
{
    /**
     * Get upcoming matches from database
     * 
     * @param int $limit Number of matches to retrieve
     * @param int|null $competition_id Filter by competition
     * @return array Match objects with enhanced data
     */
    public function get_upcoming_matches($limit = 10, $competition_id = null)
    {
        global $wpdb;

        $matches_table = $wpdb->prefix . 'goalv_matches';
        $teams_table = $wpdb->prefix . 'goalv_teams';
        $competitions_table = $wpdb->prefix . 'goalv_competitions';
        $live_scores_table = $wpdb->prefix . 'goalv_live_scores';

        // Show all matches from today + future matches (scheduled/live)
        $where = "(DATE(m.match_date) = CURDATE()) OR (m.status IN ('scheduled', 'live') AND m.match_date > CURDATE())";

        if ($competition_id) {
            $where .= $wpdb->prepare(" AND m.competition_id = %d", $competition_id);
        }

        // FIX: Use COALESCE to prefer live_scores data when available
        $sql = "SELECT m.*, 
                       ht.name as home_team, 
                       ht.logo_url as home_team_logo,
                       at.name as away_team, 
                       at.logo_url as away_team_logo,
                       c.name as competition,
                       COALESCE(ls.home_score, m.home_score) as home_score,
                       COALESCE(ls.away_score, m.away_score) as away_score,
                       COALESCE(ls.match_minute, m.match_minute) as match_minute
                FROM $matches_table m
                LEFT JOIN $teams_table ht ON m.home_team_id = ht.id
                LEFT JOIN $teams_table at ON m.away_team_id = at.id
                LEFT JOIN $competitions_table c ON m.competition_id = c.id
                LEFT JOIN $live_scores_table ls ON m.id = ls.match_id
                WHERE $where
                ORDER BY m.match_date ASC
                LIMIT $limit";

        $matches = $wpdb->get_results($sql);

        // Enhance matches with vote data
        return $this->enhance_matches_with_votes($matches, 'basic');
    }

    /**
     * Get matches by competition
     * 
     * @param int $competition_id Competition ID
     * @param int $limit Number of matches
     * @return array Match objects
     */
    public function get_matches_by_competition($competition_id, $limit = 10)
    {
        global $wpdb;

        $matches_table = $wpdb->prefix . 'goalv_matches';
        $teams_table = $wpdb->prefix . 'goalv_teams';
        $competitions_table = $wpdb->prefix . 'goalv_competitions';
        $live_scores_table = $wpdb->prefix . 'goalv_live_scores';

        $sql = $wpdb->prepare(
            "SELECT m.*, 
                    ht.name as home_team, 
                    ht.logo_url as home_team_logo,
                    at.name as away_team, 
                    at.logo_url as away_team_logo,
                    c.name as competition,
                    COALESCE(ls.home_score, m.home_score) as home_score,
                    COALESCE(ls.away_score, m.away_score) as away_score,
                    COALESCE(ls.match_minute, m.match_minute) as match_minute
             FROM $matches_table m
             LEFT JOIN $teams_table ht ON m.home_team_id = ht.id
             LEFT JOIN $teams_table at ON m.away_team_id = at.id
             LEFT JOIN $competitions_table c ON m.competition_id = c.id
             LEFT JOIN $live_scores_table ls ON m.id = ls.match_id
             WHERE m.competition_id = %d
             ORDER BY m.match_date ASC
             LIMIT %d",
            $competition_id,
            $limit
        );

        $matches = $wpdb->get_results($sql);

        return $this->enhance_matches_with_votes($matches, 'basic');
    }

    /**
     * Get matches by status
     * 
     * @param string $status Match status (scheduled, live, finished)
     * @param int $limit Number of matches
     * @return array Match objects
     */
    public function get_matches_by_status($status, $limit = 10)
    {
        global $wpdb;

        $matches_table = $wpdb->prefix . 'goalv_matches';
        $teams_table = $wpdb->prefix . 'goalv_teams';
        $competitions_table = $wpdb->prefix . 'goalv_competitions';
        $live_scores_table = $wpdb->prefix . 'goalv_live_scores';

        $sql = $wpdb->prepare(
            "SELECT m.*, 
                    ht.name as home_team, 
                    ht.logo_url as home_team_logo,
                    at.name as away_team, 
                    at.logo_url as away_team_logo,
                    c.name as competition,
                    COALESCE(ls.home_score, m.home_score) as home_score,
                    COALESCE(ls.away_score, m.away_score) as away_score,
                    COALESCE(ls.match_minute, m.match_minute) as match_minute
             FROM $matches_table m
             LEFT JOIN $teams_table ht ON m.home_team_id = ht.id
             LEFT JOIN $teams_table at ON m.away_team_id = at.id
             LEFT JOIN $competitions_table c ON m.competition_id = c.id
             LEFT JOIN $live_scores_table ls ON m.id = ls.match_id
             WHERE m.status = %s
             ORDER BY m.match_date ASC
             LIMIT %d",
            $status,
            $limit
        );

        $matches = $wpdb->get_results($sql);

        return $this->enhance_matches_with_votes($matches, 'basic');
    }

    /**
     * Get live matches
     * 
     * @return array Live match objects
     */
    public function get_live_matches()
    {
        return $this->get_matches_by_status('live', 50);
    }

    /**
     * Get single match by ID with full details
     * 
     * @param int $match_id Match database ID
     * @return object|null Match object or null
     */
    public function get_single_match($match_id)
    {
        global $wpdb;

        $matches_table = $wpdb->prefix . 'goalv_matches';
        $teams_table = $wpdb->prefix . 'goalv_teams';
        $competitions_table = $wpdb->prefix . 'goalv_competitions';
        $live_scores_table = $wpdb->prefix . 'goalv_live_scores';

        $sql = $wpdb->prepare(
            "SELECT m.*, 
                    ht.name as home_team, 
                    ht.logo_url as home_team_logo,
                    at.name as away_team, 
                    at.logo_url as away_team_logo,
                    c.name as competition,
                    COALESCE(ls.home_score, m.home_score) as home_score,
                    COALESCE(ls.away_score, m.away_score) as away_score,
                    COALESCE(ls.match_minute, m.match_minute) as match_minute
             FROM $matches_table m
             LEFT JOIN $teams_table ht ON m.home_team_id = ht.id
             LEFT JOIN $teams_table at ON m.away_team_id = at.id
             LEFT JOIN $competitions_table c ON m.competition_id = c.id
             LEFT JOIN $live_scores_table ls ON m.id = ls.match_id
             WHERE m.id = %d",
            $match_id
        );

        $match = $wpdb->get_row($sql);

        if (!$match) {
            return null;
        }

        // Enhance with detailed vote options and results
        return $this->enhance_single_match($match);
    }

    /**
     * Get matches by date range
     * 
     * @param string $date_from Start date (Y-m-d)
     * @param string $date_to End date (Y-m-d)
     * @param int $limit Number of matches
     * @return array Match objects
     */
    public function get_matches_by_date_range($date_from, $date_to, $limit = 50)
    {
        global $wpdb;

        $matches_table = $wpdb->prefix . 'goalv_matches';
        $teams_table = $wpdb->prefix . 'goalv_teams';
        $competitions_table = $wpdb->prefix . 'goalv_competitions';
        $live_scores_table = $wpdb->prefix . 'goalv_live_scores';

        $sql = $wpdb->prepare(
            "SELECT m.*, 
                    ht.name as home_team, 
                    ht.logo_url as home_team_logo,
                    at.name as away_team, 
                    at.logo_url as away_team_logo,
                    c.name as competition,
                    COALESCE(ls.home_score, m.home_score) as home_score,
                    COALESCE(ls.away_score, m.away_score) as away_score,
                    COALESCE(ls.match_minute, m.match_minute) as match_minute
             FROM $matches_table m
             LEFT JOIN $teams_table ht ON m.home_team_id = ht.id
             LEFT JOIN $teams_table at ON m.away_team_id = at.id
             LEFT JOIN $competitions_table c ON m.competition_id = c.id
             LEFT JOIN $live_scores_table ls ON m.id = ls.match_id
             WHERE DATE(m.match_date) BETWEEN %s AND %s
             ORDER BY m.match_date ASC
             LIMIT %d",
            $date_from,
            $date_to,
            $limit
        );

        $matches = $wpdb->get_results($sql);

        return $this->enhance_matches_with_votes($matches, 'basic');
    }

    /**
     * Enhance matches with vote options and user votes - OPTIMIZED FOR ELEMENTOR
     * 
     * @param array $matches Match objects
     * @param string $option_type Vote option type (basic/detailed)
     * @return array Enhanced matches
     */
    private function enhance_matches_with_votes($matches, $option_type = 'basic')
    {
        if (empty($matches)) {
            return $matches;
        }

        // CRITICAL FIX: Disable voting enhancement during Elementor preview
        if (defined('ELEMENTOR_VERSION') && isset($_REQUEST['action']) && $_REQUEST['action'] === 'elementor_ajax') {
            // Return minimal data to Elementor without enhanced voting
            foreach ($matches as &$match) {
                $match->vote_options = array();
                $match->user_votes = array();
                $match->user_vote = null;
                $match->vote_results = array();
                $match->custom_options_count = 0;
            }
            return $matches;
        }

        // For normal frontend, initialize voting system
        $voting = new GoalV_Voting();

        foreach ($matches as &$match) {
            try {
                // Add vote options
                $match->vote_options = $voting->get_vote_options($match->id, $option_type);

                // Add user's current votes
                $match->user_votes = $voting->get_user_votes($match->id, 'homepage');
                $match->user_vote = $voting->get_user_vote($match->id, 'homepage');

                // Add vote results (percentages)
                $match->vote_results = $this->get_vote_percentages($match->id, 'homepage');

                // Add custom options count
                $match->custom_options_count = $voting->get_custom_options_count($match->id, 'basic');
            } catch (Exception $e) {
                // Silent fail - don't break Elementor preview
                error_log('GoalV Voting Error: ' . $e->getMessage());
                $match->vote_options = array();
                $match->vote_results = array();
            }
        }

        return $matches;
    }

    /**
     * Enhance single match with full voting data - ELEMENTOR SAFE
     * 
     * @param object $match Match object
     * @return object Enhanced match
     */
    private function enhance_single_match($match)
    {
        // CRITICAL FIX: Disable detailed voting during Elementor preview
        if (defined('ELEMENTOR_VERSION') && isset($_REQUEST['action']) && $_REQUEST['action'] === 'elementor_ajax') {
            $match->vote_options = array();
            $match->vote_options_grouped = array();
            $match->user_votes_by_category = array();
            $match->user_votes = array();
            $match->user_vote = null;
            $match->vote_results = array();
            return $match;
        }

        try {
            $voting = new GoalV_Voting();

            // Add detailed vote options
            $match->vote_options = $voting->get_vote_options($match->id, 'detailed');

            // Add grouped vote options
            $match->vote_options_grouped = $voting->get_vote_options_grouped($match->id, 'detailed');

            // Add user's votes by category
            $match->user_votes_by_category = $voting->get_user_votes_by_category($match->id, 'details');

            // Legacy support
            $match->user_votes = $voting->get_user_votes($match->id, 'details');
            $match->user_vote = $voting->get_user_vote($match->id, 'details');

            // Add vote results
            $match->vote_results = $this->get_vote_percentages($match->id, 'details');

            // Add voting statistics (for admin view)
            if (current_user_can('manage_options')) {
                $match->voting_stats = $voting->get_voting_statistics($match->id);
            }
        } catch (Exception $e) {
            // Graceful fallback
            error_log('GoalV Single Match Enhancement Error: ' . $e->getMessage());
            $match->vote_options = array();
            $match->vote_results = array();
        }

        return $match;
    }

    /**
     * Calculate vote percentages for a match
     * 
     * @param int $match_id Match database ID
     * @param string $vote_location Vote location (homepage/details)
     * @return array Vote results with percentages
     */
    private function get_vote_percentages($match_id, $vote_location)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'goalv_vote_options';
        $option_type = ($vote_location === 'homepage') ? 'basic' : 'detailed';

        $options = $wpdb->get_results($wpdb->prepare(
            "SELECT id, option_text, votes_count, is_custom, display_order 
             FROM $table_name 
             WHERE match_id = %d AND option_type = %s 
             ORDER BY is_custom ASC, display_order ASC, id ASC",
            $match_id,
            $option_type
        ));

        $total_votes = array_sum(array_column($options, 'votes_count'));
        $results = array();

        foreach ($options as $option) {
            $percentage = $total_votes > 0 ? round(($option->votes_count / $total_votes) * 100, 1) : 0;
            $results[$option->id] = array(
                'option_id' => $option->id,
                'option_text' => $option->option_text,
                'votes_count' => $option->votes_count,
                'percentage' => $percentage,
                'total_votes' => $total_votes,
                'is_custom' => (bool) $option->is_custom,
                'display_order' => $option->display_order
            );
        }

        return $results;
    }

    /**
     * Search matches by team name
     * 
     * @param string $team_name Team name to search
     * @param int $limit Number of matches
     * @return array Match objects
     */
    public function search_matches_by_team($team_name, $limit = 20)
    {
        global $wpdb;

        $matches_table = $wpdb->prefix . 'goalv_matches';
        $teams_table = $wpdb->prefix . 'goalv_teams';
        $competitions_table = $wpdb->prefix . 'goalv_competitions';

        $search_term = '%' . $wpdb->esc_like($team_name) . '%';

        $sql = $wpdb->prepare(
            "SELECT m.*, 
                    ht.name as home_team, 
                    ht.logo_url as home_team_logo,
                    at.name as away_team, 
                    at.logo_url as away_team_logo,
                    c.name as competition
             FROM $matches_table m
             LEFT JOIN $teams_table ht ON m.home_team_id = ht.id
             LEFT JOIN $teams_table at ON m.away_team_id = at.id
             LEFT JOIN $competitions_table c ON m.competition_id = c.id
             WHERE ht.name LIKE %s OR at.name LIKE %s
             ORDER BY m.match_date DESC
             LIMIT %d",
            $search_term,
            $search_term,
            $limit
        );

        $matches = $wpdb->get_results($sql);

        return $this->enhance_matches_with_votes($matches, 'basic');
    }

    /**
     * Get match count by status
     * 
     * @param string $status Match status
     * @return int Count
     */
    public function get_match_count($status = null)
    {
        global $wpdb;

        $matches_table = $wpdb->prefix . 'goalv_matches';

        if ($status) {
            return (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $matches_table WHERE status = %s",
                $status
            ));
        }

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM $matches_table");
    }
}