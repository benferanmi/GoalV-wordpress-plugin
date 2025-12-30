<?php
/**
 * GoalV Database Setup - Multi-League Architecture
 * Creates all necessary tables for competitions, teams, matches, live scores, and sync tracking
 * 
 * @package GoalV
 * @version 8.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class GoalV_DB_Setup
{
    /**
     * Create all database tables
     */
    public static function create_tables()
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // 1. COMPETITIONS TABLE - Store all leagues/tournaments
        $table_competitions = $wpdb->prefix . 'goalv_competitions';
        $sql_competitions = "CREATE TABLE $table_competitions (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            api_competition_id varchar(50) NOT NULL,
            name varchar(255) NOT NULL,
            code varchar(50) DEFAULT NULL,
            country varchar(100) DEFAULT NULL,
            logo_url varchar(500) DEFAULT NULL,
            season_start date DEFAULT NULL,
            season_end date DEFAULT NULL,
            current_matchday int DEFAULT 1,
            total_matchdays int DEFAULT 38,
            is_active boolean DEFAULT true,
            sync_enabled boolean DEFAULT true,
            last_synced datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY api_competition_id (api_competition_id),
            KEY is_active (is_active),
            KEY sync_enabled (sync_enabled)
        ) $charset_collate;";

        // 2. TEAMS TABLE - Normalized team data (reusable across competitions)
        $table_teams = $wpdb->prefix . 'goalv_teams';
        $sql_teams = "CREATE TABLE $table_teams (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            api_team_id varchar(50) NOT NULL,
            name varchar(255) NOT NULL,
            short_name varchar(50) DEFAULT NULL,
            tla varchar(10) DEFAULT NULL,
            logo_url varchar(500) DEFAULT NULL,
            venue varchar(255) DEFAULT NULL,
            country varchar(100) DEFAULT NULL,
            founded_year int DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY api_team_id (api_team_id),
            KEY name (name(50))
        ) $charset_collate;";

        // 3. MATCHES TABLE - Core match data with new architecture
        $table_matches = $wpdb->prefix . 'goalv_matches';
        $sql_matches = "CREATE TABLE $table_matches (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            api_match_id varchar(50) NOT NULL,
            competition_id bigint(20) NOT NULL,
            home_team_id bigint(20) NOT NULL,
            away_team_id bigint(20) NOT NULL,
            matchday int DEFAULT NULL,
            match_date datetime NOT NULL,
            status enum('scheduled','postponed','cancelled','live','paused','finished','awarded') DEFAULT 'scheduled',
            home_score int DEFAULT NULL,
            away_score int DEFAULT NULL,
            home_halftime_score int DEFAULT NULL,
            away_halftime_score int DEFAULT NULL,
            home_fulltime_score int DEFAULT NULL,
            away_fulltime_score int DEFAULT NULL,
            match_minute int DEFAULT NULL,
            venue varchar(255) DEFAULT NULL,
            referee varchar(255) DEFAULT NULL,
            attendance int DEFAULT NULL,
            last_updated datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY api_match_id (api_match_id),
            KEY competition_id (competition_id),
            KEY home_team_id (home_team_id),
            KEY away_team_id (away_team_id),
            KEY status (status),
            KEY match_date (match_date),
            KEY competition_status_date (competition_id, status, match_date)
        ) $charset_collate;";

        // 4. LIVE SCORES TABLE - Real-time match data (separate for performance)
        $table_live_scores = $wpdb->prefix . 'goalv_live_scores';
        $sql_live_scores = "CREATE TABLE $table_live_scores (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            match_id bigint(20) NOT NULL,
            home_score int DEFAULT 0,
            away_score int DEFAULT 0,
            match_minute int DEFAULT NULL,
            added_time int DEFAULT NULL,
            period enum('first_half','halftime','second_half','extra_time','penalties','finished') DEFAULT 'first_half',
            status enum('live','paused','finished') DEFAULT 'live',
            last_goal_time datetime DEFAULT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY match_id (match_id),
            KEY status (status),
            KEY updated_at (updated_at)
        ) $charset_collate;";

        // 5. MATCH EVENTS TABLE - Goals, cards, substitutions
        $table_match_events = $wpdb->prefix . 'goalv_match_events';
        $sql_match_events = "CREATE TABLE $table_match_events (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            match_id bigint(20) NOT NULL,
            team_id bigint(20) NOT NULL,
            event_type enum('goal','penalty_goal','own_goal','yellow_card','red_card','substitution','penalty_miss') NOT NULL,
            player_name varchar(255) DEFAULT NULL,
            assist_player_name varchar(255) DEFAULT NULL,
            minute int NOT NULL,
            added_time int DEFAULT NULL,
            detail text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY match_id (match_id),
            KEY team_id (team_id),
            KEY event_type (event_type),
            KEY minute (minute)
        ) $charset_collate;";

        // 6. SYNC LOGS TABLE - Track all sync operations
        $table_sync_logs = $wpdb->prefix . 'goalv_sync_logs';
        $sql_sync_logs = "CREATE TABLE $table_sync_logs (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            sync_type enum('competitions','matches','live_scores','teams','full_sync') NOT NULL,
            competition_id bigint(20) DEFAULT NULL,
            status enum('started','success','partial','failed') NOT NULL,
            items_processed int DEFAULT 0,
            items_created int DEFAULT 0,
            items_updated int DEFAULT 0,
            items_failed int DEFAULT 0,
            api_calls_made int DEFAULT 0,
            duration_seconds decimal(10,2) DEFAULT NULL,
            error_message text DEFAULT NULL,
            metadata json DEFAULT NULL,
            started_at datetime DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY sync_type (sync_type),
            KEY status (status),
            KEY competition_id (competition_id),
            KEY started_at (started_at)
        ) $charset_collate;";

        // 7. KEEP EXISTING VOTE TABLES (enhanced with competition support)
        $table_vote_options = $wpdb->prefix . 'goalv_vote_options';
        $sql_vote_options = "CREATE TABLE $table_vote_options (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            match_id bigint(20) NOT NULL,
            option_text varchar(255) NOT NULL,
            option_type enum('basic','detailed') NOT NULL,
            category varchar(50) DEFAULT 'other',
            votes_count int DEFAULT 0,
            is_custom boolean DEFAULT false,
            display_order int DEFAULT 0,
            created_by bigint(20) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY match_id (match_id),
            KEY idx_match_type_order (match_id, option_type, display_order),
            KEY idx_category (category)
        ) $charset_collate;";

        $table_vote_categories = $wpdb->prefix . 'goalv_vote_categories';
        $sql_vote_categories = "CREATE TABLE $table_vote_categories (
            id int(11) NOT NULL AUTO_INCREMENT,
            category_key varchar(50) NOT NULL UNIQUE,
            category_label varchar(100) NOT NULL,
            display_order int DEFAULT 0,
            is_active boolean DEFAULT true,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_display_order (display_order, is_active)
        ) $charset_collate;";

        $table_votes = $wpdb->prefix . 'goalv_votes';
        $sql_votes = "CREATE TABLE $table_votes (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            match_id bigint(20) NOT NULL,
            option_id bigint(20) NOT NULL,
            user_id bigint(20) DEFAULT NULL,
            user_ip varchar(45) DEFAULT NULL,
            browser_id varchar(255) DEFAULT NULL,
            vote_time datetime DEFAULT CURRENT_TIMESTAMP,
            vote_location enum('homepage','details') NOT NULL,
            PRIMARY KEY (id),
            KEY match_id (match_id),
            KEY user_id (user_id),
            KEY browser_id (browser_id)
        ) $charset_collate;";

        $table_vote_summary = $wpdb->prefix . 'goalv_vote_summary';
        $sql_vote_summary = "CREATE TABLE $table_vote_summary (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            match_id bigint(20) NOT NULL,
            option_id bigint(20) NOT NULL,
            vote_location varchar(20) NOT NULL,
            total_votes int DEFAULT 0,
            last_updated datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY match_option_location (match_id, option_id, vote_location),
            KEY match_location (match_id, vote_location)
        ) $charset_collate;";

        // Execute all table creations
        dbDelta($sql_competitions);
        dbDelta($sql_teams);
        dbDelta($sql_matches);
        dbDelta($sql_live_scores);
        dbDelta($sql_match_events);
        dbDelta($sql_sync_logs);
        dbDelta($sql_vote_options);
        dbDelta($sql_vote_categories);
        dbDelta($sql_votes);
        dbDelta($sql_vote_summary);

        // Insert default data
        self::insert_default_data();

        // Update version
        update_option('goalv_db_version', '8.1.0');
        update_option('goalv_db_installed', current_time('mysql'));
    }

    /**
     * Insert default data
     */
    private static function insert_default_data()
    {
        global $wpdb;

        // Insert default vote categories (if not exists)
        $categories_table = $wpdb->prefix . 'goalv_vote_categories';
        $existing_categories = $wpdb->get_var("SELECT COUNT(*) FROM $categories_table");

        if ($existing_categories == 0) {
            $default_categories = array(
                array('category_key' => 'match_result', 'category_label' => 'Match Result', 'display_order' => 1),
                array('category_key' => 'match_score', 'category_label' => 'Exact Score', 'display_order' => 2),
                array('category_key' => 'goals_threshold', 'category_label' => 'Total Goals', 'display_order' => 3),
                array('category_key' => 'both_teams_score', 'category_label' => 'Both Teams to Score', 'display_order' => 4),
                array('category_key' => 'first_to_score', 'category_label' => 'First Team to Score', 'display_order' => 5),
                array('category_key' => 'other', 'category_label' => 'Other Predictions', 'display_order' => 6)
            );

            foreach ($default_categories as $category) {
                $wpdb->insert($categories_table, $category);
            }
        }

        // Insert default competitions (commonly requested leagues)
        $competitions_table = $wpdb->prefix . 'goalv_competitions';
        $existing_competitions = $wpdb->get_var("SELECT COUNT(*) FROM $competitions_table");

        if ($existing_competitions == 0) {
            $default_competitions = array(
                array(
                    'api_competition_id' => '39',
                    'name' => 'Premier League',
                    'code' => 'PL',
                    'country' => 'England',
                    'total_matchdays' => 38,
                    'is_active' => true,
                    'sync_enabled' => true
                ),
                array(
                    'api_competition_id' => '140',
                    'name' => 'La Liga',
                    'code' => 'PD',
                    'country' => 'Spain',
                    'total_matchdays' => 38,
                    'is_active' => true,
                    'sync_enabled' => true
                ),
                array(
                    'api_competition_id' => '135',
                    'name' => 'Serie A',
                    'code' => 'SA',
                    'country' => 'Italy',
                    'total_matchdays' => 38,
                    'is_active' => true,
                    'sync_enabled' => true
                ),
                array(
                    'api_competition_id' => '78',
                    'name' => 'Bundesliga',
                    'code' => 'BL1',
                    'country' => 'Germany',
                    'total_matchdays' => 34,
                    'is_active' => true,
                    'sync_enabled' => true
                ),
                array(
                    'api_competition_id' => '61',
                    'name' => 'Ligue 1',
                    'code' => 'FL1',
                    'country' => 'France',
                    'total_matchdays' => 38,
                    'is_active' => true,
                    'sync_enabled' => false
                ),
                array(
                    'api_competition_id' => '2',
                    'name' => 'UEFA Champions League',
                    'code' => 'CL',
                    'country' => 'Europe',
                    'total_matchdays' => 13,
                    'is_active' => true,
                    'sync_enabled' => false
                )
            );

            foreach ($default_competitions as $competition) {
                $wpdb->insert($competitions_table, $competition);
            }
        }

        // Set default plugin options
        add_option('goalv_api_provider', 'api-football'); // New provider
        add_option('goalv_api_football_key', ''); // API-Football key
        add_option('goalv_sync_interval', '3600'); // 1 hour for match data
        add_option('goalv_live_sync_interval', '30'); // 30 seconds for live scores
        add_option('goalv_enabled_competitions', array('39', '140', '135', '78')); // Default enabled leagues
        add_option('goalv_auto_sync_enabled', 'yes');
        add_option('goalv_live_sync_enabled', 'yes');
    }

    /**
     * Check if database needs upgrade
     */
    public static function needs_upgrade()
    {
        $current_version = get_option('goalv_db_version', '0.0.0');
        return version_compare($current_version, '8.1.0', '<');
    }

    /**
     * Drop all tables (for clean uninstall - optional)
     */
    public static function drop_tables()
    {
        global $wpdb;

        $tables = array(
            $wpdb->prefix . 'goalv_competitions',
            $wpdb->prefix . 'goalv_teams',
            $wpdb->prefix . 'goalv_matches',
            $wpdb->prefix . 'goalv_live_scores',
            $wpdb->prefix . 'goalv_match_events',
            $wpdb->prefix . 'goalv_sync_logs',
            $wpdb->prefix . 'goalv_vote_options',
            $wpdb->prefix . 'goalv_vote_categories',
            $wpdb->prefix . 'goalv_votes',
            $wpdb->prefix . 'goalv_vote_summary'
        );

        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }

        // Remove options
        delete_option('goalv_db_version');
        delete_option('goalv_db_installed');
    }

    public static function migrate_to_9_0_3()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'goalv_competitions';

        // Check if 'type' column exists
        $type_column_exists = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
                 WHERE TABLE_SCHEMA = %s 
                 AND TABLE_NAME = %s 
                 AND COLUMN_NAME = 'type'",
                DB_NAME,
                $table
            )
        );

        // Add 'type' column if it doesn't exist
        if (empty($type_column_exists)) {
            $wpdb->query(
                "ALTER TABLE $table 
                 ADD COLUMN type VARCHAR(50) DEFAULT NULL AFTER country"
            );
            error_log("GoalV Migration: Added 'type' column to competitions table");
        }

        // Check if 'current_season' column exists
        $season_column_exists = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
                 WHERE TABLE_SCHEMA = %s 
                 AND TABLE_NAME = %s 
                 AND COLUMN_NAME = 'current_season'",
                DB_NAME,
                $table
            )
        );

        // Add 'current_season' column if it doesn't exist
        if (empty($season_column_exists)) {
            $wpdb->query(
                "ALTER TABLE $table 
                 ADD COLUMN current_season VARCHAR(20) DEFAULT NULL AFTER country"
            );
            error_log("GoalV Migration: Added 'current_season' column to competitions table");
        }

        // Update existing competitions with default type
        $wpdb->query(
            "UPDATE $table 
             SET type = 'League' 
             WHERE type IS NULL OR type = ''"
        );

        error_log("GoalV Migration: Completed migration to 9.0.3");
    }

}