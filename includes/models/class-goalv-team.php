<?php
/**
 * Team Model
 * Object-oriented interface for team data
 * 
 * @package GoalV
 * @version 8.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class GoalV_Team
{
    public $id;
    public $api_team_id;
    public $name;
    public $short_name;
    public $tla;
    public $logo_url;
    public $venue;
    public $country;
    public $founded_year;

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
     * Load team from database by ID
     */
    public function load($team_id)
    {
        global $wpdb;
        
        $table = $wpdb->prefix . 'goalv_teams';
        $data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $team_id
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
        $this->api_team_id = $data->api_team_id ?? null;
        $this->name = $data->name ?? null;
        $this->short_name = $data->short_name ?? null;
        $this->tla = $data->tla ?? null;
        $this->logo_url = $data->logo_url ?? null;
        $this->venue = $data->venue ?? null;
        $this->country = $data->country ?? null;
        $this->founded_year = $data->founded_year ?? null;
    }

    /**
     * Save team to database
     */
    public function save()
    {
        global $wpdb;
        
        $table = $wpdb->prefix . 'goalv_teams';
        
        $data = array(
            'api_team_id' => $this->api_team_id,
            'name' => $this->name,
            'short_name' => $this->short_name,
            'tla' => $this->tla,
            'logo_url' => $this->logo_url,
            'venue' => $this->venue,
            'country' => $this->country,
            'founded_year' => $this->founded_year
        );

        if ($this->id) {
            // Update existing
            $wpdb->update(
                $table,
                $data,
                array('id' => $this->id),
                array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d'),
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
     * Delete team
     */
    public function delete()
    {
        if (!$this->id) {
            return false;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'goalv_teams';
        
        return $wpdb->delete($table, array('id' => $this->id), array('%d'));
    }

    /**
     * Get team logo with fallback
     */
    public function get_logo($default = null)
    {
        if (!empty($this->logo_url) && filter_var($this->logo_url, FILTER_VALIDATE_URL)) {
            return $this->logo_url;
        }

        return $default ?: GOALV_PLUGIN_URL . 'assets/images/default-team-logo.png';
    }

    /**
     * Get display name (short name if available, otherwise full name)
     */
    public function get_display_name()
    {
        return $this->short_name ?: $this->name;
    }

    /**
     * Get all home matches
     */
    public function get_home_matches($status = null, $limit = 20)
    {
        global $wpdb;
        
        $table = $wpdb->prefix . 'goalv_matches';
        
        $where = $wpdb->prepare("home_team_id = %d", $this->id);
        
        if ($status) {
            $where .= $wpdb->prepare(" AND status = %s", $status);
        }
        
        return $wpdb->get_results(
            "SELECT * FROM $table WHERE $where ORDER BY match_date DESC LIMIT $limit"
        );
    }

    /**
     * Get all away matches
     */
    public function get_away_matches($status = null, $limit = 20)
    {
        global $wpdb;
        
        $table = $wpdb->prefix . 'goalv_matches';
        
        $where = $wpdb->prepare("away_team_id = %d", $this->id);
        
        if ($status) {
            $where .= $wpdb->prepare(" AND status = %s", $status);
        }
        
        return $wpdb->get_results(
            "SELECT * FROM $table WHERE $where ORDER BY match_date DESC LIMIT $limit"
        );
    }

    /**
     * Get all matches (home and away)
     */
    public function get_all_matches($status = null, $limit = 20)
    {
        global $wpdb;
        
        $table = $wpdb->prefix . 'goalv_matches';
        
        $where = $wpdb->prepare("(home_team_id = %d OR away_team_id = %d)", $this->id, $this->id);
        
        if ($status) {
            $where .= $wpdb->prepare(" AND status = %s", $status);
        }
        
        return $wpdb->get_results(
            "SELECT * FROM $table WHERE $where ORDER BY match_date DESC LIMIT $limit"
        );
    }

    /**
     * Get upcoming matches
     */
    public function get_upcoming_matches($limit = 10)
    {
        global $wpdb;
        
        $table = $wpdb->prefix . 'goalv_matches';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table 
             WHERE (home_team_id = %d OR away_team_id = %d)
             AND status = 'scheduled'
             AND match_date >= NOW()
             ORDER BY match_date ASC 
             LIMIT %d",
            $this->id,
            $this->id,
            $limit
        ));
    }

    /**
     * Get team statistics
     */
    public function get_stats()
    {
        global $wpdb;
        
        $table = $wpdb->prefix . 'goalv_matches';
        
        // Home stats
        $home_stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as played,
                SUM(CASE WHEN status = 'finished' AND home_score > away_score THEN 1 ELSE 0 END) as wins,
                SUM(CASE WHEN status = 'finished' AND home_score = away_score THEN 1 ELSE 0 END) as draws,
                SUM(CASE WHEN status = 'finished' AND home_score < away_score THEN 1 ELSE 0 END) as losses,
                SUM(CASE WHEN status = 'finished' THEN home_score ELSE 0 END) as goals_for,
                SUM(CASE WHEN status = 'finished' THEN away_score ELSE 0 END) as goals_against
             FROM $table 
             WHERE home_team_id = %d AND status = 'finished'",
            $this->id
        ), ARRAY_A);

        // Away stats
        $away_stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as played,
                SUM(CASE WHEN status = 'finished' AND away_score > home_score THEN 1 ELSE 0 END) as wins,
                SUM(CASE WHEN status = 'finished' AND away_score = home_score THEN 1 ELSE 0 END) as draws,
                SUM(CASE WHEN status = 'finished' AND away_score < home_score THEN 1 ELSE 0 END) as losses,
                SUM(CASE WHEN status = 'finished' THEN away_score ELSE 0 END) as goals_for,
                SUM(CASE WHEN status = 'finished' THEN home_score ELSE 0 END) as goals_against
             FROM $table 
             WHERE away_team_id = %d AND status = 'finished'",
            $this->id
        ), ARRAY_A);

        // Combined stats
        return array(
            'total' => array(
                'played' => $home_stats['played'] + $away_stats['played'],
                'wins' => $home_stats['wins'] + $away_stats['wins'],
                'draws' => $home_stats['draws'] + $away_stats['draws'],
                'losses' => $home_stats['losses'] + $away_stats['losses'],
                'goals_for' => $home_stats['goals_for'] + $away_stats['goals_for'],
                'goals_against' => $home_stats['goals_against'] + $away_stats['goals_against']
            ),
            'home' => $home_stats,
            'away' => $away_stats
        );
    }

    /**
     * Static: Get all teams
     */
    public static function get_all($limit = 100)
    {
        global $wpdb;
        
        $table = $wpdb->prefix . 'goalv_teams';
        
        $results = $wpdb->get_results("SELECT * FROM $table ORDER BY name ASC LIMIT $limit");
        
        $teams = array();
        foreach ($results as $row) {
            $teams[] = new self($row);
        }
        
        return $teams;
    }

    /**
     * Static: Get by API ID
     */
    public static function get_by_api_id($api_team_id)
    {
        global $wpdb;
        
        $table = $wpdb->prefix . 'goalv_teams';
        
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE api_team_id = %s",
            $api_team_id
        ));

        return $row ? new self($row) : null;
    }

    /**
     * Static: Search teams by name
     */
    public static function search($search_term, $limit = 20)
    {
        global $wpdb;
        
        $table = $wpdb->prefix . 'goalv_teams';
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table 
             WHERE name LIKE %s OR short_name LIKE %s OR tla LIKE %s
             ORDER BY name ASC 
             LIMIT %d",
            '%' . $wpdb->esc_like($search_term) . '%',
            '%' . $wpdb->esc_like($search_term) . '%',
            '%' . $wpdb->esc_like($search_term) . '%',
            $limit
        ));
        
        $teams = array();
        foreach ($results as $row) {
            $teams[] = new self($row);
        }
        
        return $teams;
    }

    /**
     * Convert to array
     */
    public function to_array()
    {
        return array(
            'id' => $this->id,
            'api_team_id' => $this->api_team_id,
            'name' => $this->name,
            'short_name' => $this->short_name,
            'tla' => $this->tla,
            'logo_url' => $this->logo_url,
            'venue' => $this->venue,
            'country' => $this->country,
            'founded_year' => $this->founded_year
        );
    }
}