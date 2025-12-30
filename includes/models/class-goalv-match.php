<?php
/**
 * Match Model - UPDATED WITH exists() METHOD
 * Object-oriented interface for match data with status management
 * 
 * @package GoalV
 * @version 8.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class GoalV_Match
{
    public $id;
    public $api_match_id;
    public $competition_id;
    public $home_team_id;
    public $away_team_id;
    public $matchday;
    public $match_date;
    public $status;
    public $home_score;
    public $away_score;
    public $home_halftime_score;
    public $away_halftime_score;
    public $home_fulltime_score;
    public $away_fulltime_score;
    public $match_minute;
    public $venue;
    public $referee;
    public $attendance;

    // Related objects (lazy loaded)
    private $competition;
    private $home_team;
    private $away_team;
    private $live_data;
    private $events;
    private $vote_options;

    /**
     * Constructor
     */
    public function __construct($id_or_data = null)
    {
        if (is_numeric($id_or_data)) {
            $this->load($id_or_data);
        } elseif (is_object($id_or_data) || is_array($id_or_data)) {
            $this->populate($id_or_data);
        }
    }

    /**
     * Load match from database
     */
    public function load($match_id)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'goalv_matches';
        $data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $match_id
        ));

        if ($data) {
            $this->populate($data);
            return true;
        }

        return false;
    }

    /**
     * Populate properties from data
     */
    private function populate($data)
    {
        $data = (object) $data;

        $this->id = $data->id ?? null;
        $this->api_match_id = $data->api_match_id ?? null;
        $this->competition_id = $data->competition_id ?? null;
        $this->home_team_id = $data->home_team_id ?? null;
        $this->away_team_id = $data->away_team_id ?? null;
        $this->matchday = $data->matchday ?? null;
        $this->match_date = $data->match_date ?? null;
        $this->status = $data->status ?? 'scheduled';
        $this->home_score = $data->home_score ?? null;
        $this->away_score = $data->away_score ?? null;
        $this->home_halftime_score = $data->home_halftime_score ?? null;
        $this->away_halftime_score = $data->away_halftime_score ?? null;
        $this->home_fulltime_score = $data->home_fulltime_score ?? null;
        $this->away_fulltime_score = $data->away_fulltime_score ?? null;
        $this->match_minute = $data->match_minute ?? null;
        $this->venue = $data->venue ?? null;
        $this->referee = $data->referee ?? null;
        $this->attendance = $data->attendance ?? null;
    }

    /**
     * Save match to database
     */
    public function save()
    {
        global $wpdb;

        $table = $wpdb->prefix . 'goalv_matches';

        $data = array(
            'api_match_id' => $this->api_match_id,
            'competition_id' => $this->competition_id,
            'home_team_id' => $this->home_team_id,
            'away_team_id' => $this->away_team_id,
            'matchday' => $this->matchday,
            'match_date' => $this->match_date,
            'status' => $this->status,
            'home_score' => $this->home_score,
            'away_score' => $this->away_score,
            'home_halftime_score' => $this->home_halftime_score,
            'away_halftime_score' => $this->away_halftime_score,
            'home_fulltime_score' => $this->home_fulltime_score,
            'away_fulltime_score' => $this->away_fulltime_score,
            'match_minute' => $this->match_minute,
            'venue' => $this->venue,
            'referee' => $this->referee,
            'attendance' => $this->attendance
        );

        if ($this->id) {
            // Update existing
            $wpdb->update(
                $table,
                $data,
                array('id' => $this->id),
                array('%s', '%d', '%d', '%d', '%d', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%d'),
                array('%d')
            );
            return $this->id;
        } else {
            // Insert new
            $wpdb->insert($table, $data);
            $this->id = $wpdb->insert_id;
            return $this->id;
        }
    }

    /**
     * Delete match
     */
    public function delete()
    {
        if (!$this->id) {
            return false;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'goalv_matches';

        return $wpdb->delete($table, array('id' => $this->id), array('%d'));
    }

    /**
     * NEW: Static method to check if match exists
     * Used by voting system to validate match_id
     * 
     * @param int $match_id Match database ID
     * @return bool True if match exists
     */
    public static function exists($match_id)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'goalv_matches';

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE id = %d",
            $match_id
        ));

        return $count > 0;
    }

    /**
     * Get competition object (lazy load)
     */
    public function get_competition()
    {
        if (!$this->competition && $this->competition_id) {
            $this->competition = new GoalV_Competition($this->competition_id);
        }
        return $this->competition;
    }

    /**
     * Get home team object (lazy load)
     */
    public function get_home_team()
    {
        if (!$this->home_team && $this->home_team_id) {
            $this->home_team = new GoalV_Team($this->home_team_id);
        }
        return $this->home_team;
    }

    /**
     * Get away team object (lazy load)
     */
    public function get_away_team()
    {
        if (!$this->away_team && $this->away_team_id) {
            $this->away_team = new GoalV_Team($this->away_team_id);
        }
        return $this->away_team;
    }

    /**
     * Get live score data
     */
    public function get_live_data()
    {
        if ($this->is_live() && !$this->live_data) {
            global $wpdb;

            $table = $wpdb->prefix . 'goalv_live_scores';
            $this->live_data = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table WHERE match_id = %d",
                $this->id
            ));
        }
        return $this->live_data;
    }

    /**
     * Get match events
     */
    public function get_events()
    {
        if (!$this->events) {
            global $wpdb;

            $events_table = $wpdb->prefix . 'goalv_match_events';
            $teams_table = $wpdb->prefix . 'goalv_teams';

            $this->events = $wpdb->get_results($wpdb->prepare(
                "SELECT e.*, t.name as team_name, t.logo_url as team_logo
                 FROM $events_table e
                 LEFT JOIN $teams_table t ON e.team_id = t.id
                 WHERE e.match_id = %d
                 ORDER BY e.minute ASC, e.id ASC",
                $this->id
            ));
        }
        return $this->events;
    }

    /**
     * Get vote options
     */
    public function get_vote_options($option_type = 'detailed')
    {
        if (!isset($this->vote_options[$option_type])) {
            global $wpdb;

            $table = $wpdb->prefix . 'goalv_vote_options';

            $this->vote_options[$option_type] = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table 
                 WHERE match_id = %d AND option_type = %s
                 ORDER BY category, display_order ASC",
                $this->id,
                $option_type
            ));
        }
        return $this->vote_options[$option_type];
    }

    /**
     * Get grouped vote options by category
     */
    public function get_grouped_vote_options($option_type = 'detailed')
    {
        $options = $this->get_vote_options($option_type);
        $grouped = array();

        foreach ($options as $option) {
            $category = $option->category ?? 'other';

            if (!isset($grouped[$category])) {
                $grouped[$category] = array(
                    'label' => $this->get_category_label($category),
                    'options' => array()
                );
            }

            $grouped[$category]['options'][] = $option;
        }

        return $grouped;
    }

    /**
     * Get category label
     */
    private function get_category_label($category_key)
    {
        $labels = array(
            'match_result' => __('Match Result', 'goalv'),
            'match_score' => __('Exact Score', 'goalv'),
            'goals_threshold' => __('Total Goals', 'goalv'),
            'both_teams_score' => __('Both Teams to Score', 'goalv'),
            'first_to_score' => __('First Team to Score', 'goalv'),
            'other' => __('Other Predictions', 'goalv')
        );

        return $labels[$category_key] ?? ucwords(str_replace('_', ' ', $category_key));
    }

    /**
     * Status checks
     */
    public function is_scheduled()
    {
        return $this->status === 'scheduled';
    }

    public function is_live()
    {
        return $this->status === 'live';
    }

    public function is_finished()
    {
        return $this->status === 'finished';
    }

    public function is_postponed()
    {
        return $this->status === 'postponed';
    }

    public function is_cancelled()
    {
        return $this->status === 'cancelled';
    }

    /**
     * Check if match is in the past
     */
    public function is_past()
    {
        return strtotime($this->match_date) < time();
    }

    /**
     * Check if match is upcoming
     */
    public function is_upcoming()
    {
        return $this->is_scheduled() && !$this->is_past();
    }

    /**
     * Get match result (home win, away win, draw)
     */
    public function get_result()
    {
        if (!$this->is_finished()) {
            return null;
        }

        if ($this->home_score > $this->away_score) {
            return 'home_win';
        } elseif ($this->away_score > $this->home_score) {
            return 'away_win';
        } else {
            return 'draw';
        }
    }

    /**
     * Get formatted match date
     */
    public function get_formatted_date($format = 'F j, Y g:i A')
    {
        return date($format, strtotime($this->match_date));
    }

    /**
     * Get time until match starts
     */
    public function get_time_until()
    {
        if ($this->is_past()) {
            return null;
        }

        $diff = strtotime($this->match_date) - time();

        $days = floor($diff / 86400);
        $hours = floor(($diff % 86400) / 3600);
        $minutes = floor(($diff % 3600) / 60);

        if ($days > 0) {
            return sprintf('%d days, %d hours', $days, $hours);
        } elseif ($hours > 0) {
            return sprintf('%d hours, %d minutes', $hours, $minutes);
        } else {
            return sprintf('%d minutes', $minutes);
        }
    }

    /**
     * Get status badge HTML
     */
    public function get_status_badge()
    {
        $badges = array(
            'scheduled' => '<span class="goalv-badge goalv-badge-scheduled">Upcoming</span>',
            'live' => '<span class="goalv-badge goalv-badge-live">LIVE</span>',
            'paused' => '<span class="goalv-badge goalv-badge-paused">Half Time</span>',
            'finished' => '<span class="goalv-badge goalv-badge-finished">Full Time</span>',
            'postponed' => '<span class="goalv-badge goalv-badge-postponed">Postponed</span>',
            'cancelled' => '<span class="goalv-badge goalv-badge-cancelled">Cancelled</span>'
        );

        return $badges[$this->status] ?? '';
    }

    /**
     * Static: Get matches by status
     */
    public static function get_by_status($status, $limit = 10)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'goalv_matches';

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE status = %s ORDER BY match_date ASC LIMIT %d",
            $status,
            $limit
        ));

        $matches = array();
        foreach ($results as $row) {
            $matches[] = new self($row);
        }

        return $matches;
    }

    /**
     * Static: Get upcoming matches
     */
    public static function get_upcoming($limit = 10, $competition_id = null)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'goalv_matches';

        $where = "status = 'scheduled' AND match_date >= NOW()";

        if ($competition_id) {
            $where .= $wpdb->prepare(" AND competition_id = %d", $competition_id);
        }

        $results = $wpdb->get_results(
            "SELECT * FROM $table WHERE $where ORDER BY match_date ASC LIMIT $limit"
        );

        $matches = array();
        foreach ($results as $row) {
            $matches[] = new self($row);
        }

        return $matches;
    }

    /**
     * Static: Get live matches
     */
    public static function get_live()
    {
        return self::get_by_status('live', 50);
    }

    /**
     * Static: Count all matches
     * 
     * @param string $status Optional status filter
     * @return int
     */
    public static function count_all($status = null)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'goalv_matches';

        if ($status) {
            return (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE status = %s",
                $status
            ));
        }

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
    }

    /**
     * Static: Count matches by status
     * 
     * @param string $status Match status
     * @return int
     */
    public static function count_by_status($status)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'goalv_matches';

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE status = %s",
            $status
        ));
    }

    /**
     * Static: Count matches by competition
     * 
     * @param int $competition_id Competition ID
     * @param string $status Optional status filter
     * @return int
     */
    public static function count_by_competition($competition_id, $status = null)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'goalv_matches';

        if ($status) {
            return (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE competition_id = %d AND status = %s",
                $competition_id,
                $status
            ));
        }

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE competition_id = %d",
            $competition_id
        ));
    }

    /**
     * Static: Get recent matches
     * 
     * @param int $limit Number of matches
     * @param int $competition_id Optional competition filter
     * @return array
     */
    public static function get_recent($limit = 10, $competition_id = null)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'goalv_matches';

        $where = "status = 'finished'";

        if ($competition_id) {
            $where .= $wpdb->prepare(" AND competition_id = %d", $competition_id);
        }

        $results = $wpdb->get_results(
            "SELECT * FROM $table WHERE $where ORDER BY match_date DESC LIMIT $limit"
        );

        $matches = array();
        foreach ($results as $row) {
            $matches[] = new self($row);
        }

        return $matches;
    }

    /**
     * Convert to array
     */
    public function to_array($include_relations = false)
    {
        $data = array(
            'id' => $this->id,
            'api_match_id' => $this->api_match_id,
            'competition_id' => $this->competition_id,
            'home_team_id' => $this->home_team_id,
            'away_team_id' => $this->away_team_id,
            'matchday' => $this->matchday,
            'match_date' => $this->match_date,
            'status' => $this->status,
            'home_score' => $this->home_score,
            'away_score' => $this->away_score,
            'match_minute' => $this->match_minute,
            'venue' => $this->venue
        );

        if ($include_relations) {
            $data['competition'] = $this->get_competition() ? $this->get_competition()->to_array() : null;
            $data['home_team'] = $this->get_home_team() ? $this->get_home_team()->to_array() : null;
            $data['away_team'] = $this->get_away_team() ? $this->get_away_team()->to_array() : null;

            if ($this->is_live()) {
                $data['live_data'] = $this->get_live_data();
                $data['events'] = $this->get_events();
            }
        }

        return $data;
    }
}