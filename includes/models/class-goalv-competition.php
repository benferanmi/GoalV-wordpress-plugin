<?php
/**
 * Competition Model
 * Object-oriented interface for competition data
 * 
 * @package GoalV
 * @version 8.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class GoalV_Competition
{
    public $id;
    public $api_competition_id;
    public $name;
    public $code;
    public $country;
    public $type;
    public $current_season;
    public $logo_url;
    public $season_start;
    public $season_end;
    public $current_matchday;
    public $total_matchdays;
    public $is_active;
    public $sync_enabled;
    public $last_synced;

    /**
     * Constructor - load from database or create new
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
     * Load competition from database by ID
     */
    public function load($competition_id)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'goalv_competitions';
        $data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $competition_id
        ));

        if ($data) {
            $this->populate($data);
            return true;
        }

        return false;
    }

    /**
     * Populate object properties from data
     */
    private function populate($data)
    {
        $data = (object) $data;

        $this->id = $data->id ?? null;
        $this->api_competition_id = $data->api_competition_id ?? null;
        $this->name = $data->name ?? null;
        $this->code = $data->code ?? null;
        $this->country = $data->country ?? null;
        $this->type = $data->type ?? 'League';                    // ADDED
        $this->current_season = $data->current_season ?? null;    // ADDED
        $this->logo_url = $data->logo_url ?? null;
        $this->season_start = $data->season_start ?? null;
        $this->season_end = $data->season_end ?? null;
        $this->current_matchday = $data->current_matchday ?? 1;
        $this->total_matchdays = $data->total_matchdays ?? 38;
        $this->is_active = $data->is_active ?? true;
        $this->sync_enabled = $data->sync_enabled ?? false;
        $this->last_synced = $data->last_synced ?? null;
    }

    /**
     * Save competition to database
     */
    public function save()
    {
        global $wpdb;

        $table = $wpdb->prefix . 'goalv_competitions';

        $data = array(
            'api_competition_id' => $this->api_competition_id,
            'name' => $this->name,
            'code' => $this->code,
            'country' => $this->country,
            'type' => $this->type,                          // ADDED
            'current_season' => $this->current_season,      // ADDED
            'logo_url' => $this->logo_url,
            'season_start' => $this->season_start,
            'season_end' => $this->season_end,
            'current_matchday' => $this->current_matchday,
            'total_matchdays' => $this->total_matchdays,
            'is_active' => $this->is_active ? 1 : 0,
            'sync_enabled' => $this->sync_enabled ? 1 : 0,
            'last_synced' => $this->last_synced
        );

        if ($this->id) {
            // Update existing
            $wpdb->update(
                $table,
                $data,
                array('id' => $this->id),
                array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s'),
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
     * Delete competition
     */
    public function delete()
    {
        if (!$this->id) {
            return false;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'goalv_competitions';

        return $wpdb->delete($table, array('id' => $this->id), array('%d'));
    }

    /**
     * Get all matches for this competition
     */
    public function get_matches($status = null, $limit = 50)
    {
        global $wpdb;

        $matches_table = $wpdb->prefix . 'goalv_matches';

        $where = $wpdb->prepare("competition_id = %d", $this->id);

        if ($status) {
            $where .= $wpdb->prepare(" AND status = %s", $status);
        }

        return $wpdb->get_results(
            "SELECT * FROM $matches_table WHERE $where ORDER BY match_date ASC LIMIT $limit"
        );
    }

    /**
     * Get upcoming matches
     */
    public function get_upcoming_matches($limit = 10)
    {
        global $wpdb;

        $matches_table = $wpdb->prefix . 'goalv_matches';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $matches_table 
             WHERE competition_id = %d 
             AND status = 'scheduled' 
             AND match_date >= NOW()
             ORDER BY match_date ASC 
             LIMIT %d",
            $this->id,
            $limit
        ));
    }

    /**
     * Get live matches
     */
    public function get_live_matches()
    {
        return $this->get_matches('live');
    }

    /**
     * Get finished matches
     */
    public function get_finished_matches($limit = 20)
    {
        global $wpdb;

        $matches_table = $wpdb->prefix . 'goalv_matches';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $matches_table 
             WHERE competition_id = %d 
             AND status = 'finished'
             ORDER BY match_date DESC 
             LIMIT %d",
            $this->id,
            $limit
        ));
    }

    /**
     * Get total matches count
     */
    public function get_matches_count()
    {
        global $wpdb;

        $table = $wpdb->prefix . 'goalv_matches';

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE competition_id = %d",
            $this->id
        ));
    }

    /**
     * Enable/disable competition
     */
    public function set_active($active = true)
    {
        $this->is_active = $active;
        return $this->save();
    }

    /**
     * Enable/disable syncing
     */
    public function set_sync_enabled($enabled = true)
    {
        $this->sync_enabled = $enabled;
        return $this->save();
    }

    /**
     * Update last synced timestamp
     */
    public function mark_synced()
    {
        $this->last_synced = current_time('mysql');
        return $this->save();
    }

    /**
     * Check if competition needs syncing
     */
    public function needs_sync($threshold_minutes = 60)
    {
        if (!$this->sync_enabled || !$this->is_active) {
            return false;
        }

        if (!$this->last_synced) {
            return true;
        }

        $last_sync_time = strtotime($this->last_synced);
        $threshold_time = time() - ($threshold_minutes * 60);

        return $last_sync_time < $threshold_time;
    }

    /**
     * Get competition logo with fallback
     */
    public function get_logo($default = null)
    {
        if (!empty($this->logo_url) && filter_var($this->logo_url, FILTER_VALIDATE_URL)) {
            return $this->logo_url;
        }

        return $default ?: GOALV_PLUGIN_URL . 'assets/images/default-competition-logo.png';
    }

    /**
     * Get display name (name + country)
     */
    public function get_display_name()
    {
        return $this->country ? $this->name . ' (' . $this->country . ')' : $this->name;
    }

    /**
     * Static: Get all competitions
     */
    public static function get_all($active_only = false)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'goalv_competitions';

        $where = $active_only ? "WHERE is_active = 1" : "";

        $results = $wpdb->get_results("SELECT * FROM $table $where ORDER BY name ASC");

        $competitions = array();
        foreach ($results as $row) {
            $competitions[] = new self($row);
        }

        return $competitions;
    }

    /**
     * Static: Get all active competitions
     */
    public static function get_active()
    {
        global $wpdb;

        $table = $wpdb->prefix . 'goalv_competitions';

        $results = $wpdb->get_results(
            "SELECT * FROM $table WHERE is_active = 1 ORDER BY name ASC"
        );

        $competitions = array();
        foreach ($results as $row) {
            $competitions[] = new self($row);
        }

        return $competitions;
    }

    /**
     * Static: Get by API ID
     */
    public static function get_by_api_id($api_competition_id)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'goalv_competitions';

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE api_competition_id = %s",
            $api_competition_id
        ));

        return $row ? new self($row) : null;
    }

    /**
     * Static: Get competition by ID
     * 
     * @param int $competition_id Competition ID
     * @return GoalV_Competition|null
     */
    public static function get($competition_id)
    {
        return new self($competition_id);
    }

    /**
     * Static: Update competition
     * 
     * @param int $competition_id Competition ID
     * @param array $data Update data
     * @return bool Success
     */
    public static function update($competition_id, $data)
    {
        $competition = new self($competition_id);

        if (!$competition->id) {
            return false;
        }

        foreach ($data as $key => $value) {
            if (property_exists($competition, $key)) {
                $competition->$key = $value;
            }
        }

        return $competition->save() !== false;
    }

    /**
     * Static: Get enabled competitions for syncing
     */
    public static function get_sync_enabled()
    {
        global $wpdb;

        $table = $wpdb->prefix . 'goalv_competitions';

        $results = $wpdb->get_results(
            "SELECT * FROM $table WHERE is_active = 1 AND sync_enabled = 1 ORDER BY name ASC"
        );

        $competitions = array();
        foreach ($results as $row) {
            $competitions[] = new self($row);
        }

        return $competitions;
    }

    /**
     * Convert to array
     */
    public function to_array()
    {
        return array(
            'id' => $this->id,
            'api_competition_id' => $this->api_competition_id,
            'name' => $this->name,
            'code' => $this->code,
            'country' => $this->country,
            'type' => $this->type,                          // ADDED
            'current_season' => $this->current_season,      // ADDED
            'logo_url' => $this->logo_url,
            'season_start' => $this->season_start,
            'season_end' => $this->season_end,
            'current_matchday' => $this->current_matchday,
            'total_matchdays' => $this->total_matchdays,
            'is_active' => $this->is_active,
            'sync_enabled' => $this->sync_enabled,
            'last_synced' => $this->last_synced
        );
    }
}