<?php
/**
 * Plugin Name: GoalV Football Predictions
 * Plugin URI: https://oluwaferanmi-developer-site.vercel.app/
 * Description: Complete football match prediction system with API integration and dual voting system
 * Version: 7.0.2
 * Changes: Stricting making it one vote by category.
 * Author: Opafunso Benjamin
 * License: GPL v2 or later
 * Text Domain: https://oluwaferanmi-developer-site.vercel.app/
 * Domain Path: /languages
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('GOALV_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GOALV_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('GOALV_VERSION', '1.0.0');

/**
 * Main GoalV Plugin Class
 */
class GoalV_Football_Predictions
{

    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('plugins_loaded', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }

    /**
     * Initialize plugin
     */
    public function init()
    {
        // Load text domain
        load_plugin_textdomain('goalv', false, dirname(plugin_basename(__FILE__)) . '/languages');

        // Include required files
        $this->includes();

        // Initialize classes
        $this->init_classes();

        // Register hooks
        $this->register_hooks();
    }

    /**
     * Include required files
     */
    private function includes()
    {
        require_once GOALV_PLUGIN_PATH . 'includes/class-goalv-cpt.php';
        require_once GOALV_PLUGIN_PATH . 'includes/class-goalv-admin.php';
        require_once GOALV_PLUGIN_PATH . 'includes/class-goalv-api.php';
        require_once GOALV_PLUGIN_PATH . 'includes/class-goalv-voting.php';
        require_once GOALV_PLUGIN_PATH . 'includes/class-goalv-frontend.php';
    }

    /**
     * Initialize classes
     */
    private function init_classes()
    {
        new GoalV_CPT();
        new GoalV_Admin();
        new GoalV_API();
        new GoalV_Voting();
        new GoalV_Frontend();
    }

    /**
     * Register plugin hooks
     */
    private function register_hooks()
    {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    /**
     * Enqueue frontend assets - UPDATED for separated JS
     */
    public function enqueue_frontend_assets()
    {
        // Always enqueue styles
        wp_enqueue_style(
            'goalv-style',
            GOALV_PLUGIN_URL . 'assets/css/goalv-style.css',
            array(),
            GOALV_VERSION
        );

        // Only enqueue frontend JS on frontend
        if (!is_admin()) {
            wp_enqueue_script(
                'goalv-frontend',
                GOALV_PLUGIN_URL . 'assets/js/goalv-frontend.js',
                array('jquery'),
                GOALV_VERSION,
                true
            );

            // Localize script for frontend AJAX
            wp_localize_script('goalv-frontend', 'goalv_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('goalv_vote_nonce'),
                'is_user_logged_in' => is_user_logged_in()
            ));
        }
    }

    /**
     * Enqueue admin assets - UPDATED for separated JS
     */
    public function enqueue_admin_assets($hook_suffix)
    {
        // Only load on GoalV admin pages
        if (strpos($hook_suffix, 'goalv') === false && strpos($hook_suffix, 'edit.php?post_type=goalv_matches') === false) {
            return;
        }

        // Admin styles
        wp_enqueue_style(
            'goalv-admin-style',
            GOALV_PLUGIN_URL . 'assets/css/goalv-style.css',
            array(),
            GOALV_VERSION
        );

        // Admin JavaScript
        wp_enqueue_script(
            'goalv-admin',
            GOALV_PLUGIN_URL . 'assets/js/goalv-admin.js',
            array('jquery'),
            GOALV_VERSION,
            true
        );

        // Localize script for admin AJAX
        wp_localize_script('goalv-admin', 'goalv_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('goalv_vote_nonce'),
            'is_user_logged_in' => is_user_logged_in()
        ));
    }

    /**
     * Plugin activation
     */
    public function activate()
    {
        $this->create_tables();
        $this->create_vote_summary_table();
        $this->upgrade_database_for_custom_options();
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate()
    {
        flush_rewrite_rules();
    }

    /**
     * Create database tables
     */
    private function create_tables()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Create vote_options table - ENHANCED with custom options and category support
        $table_vote_options = $wpdb->prefix . 'goalv_vote_options';
        $sql_vote_options = "CREATE TABLE $table_vote_options (
        id int(11) NOT NULL AUTO_INCREMENT,
        match_id bigint(20) NOT NULL,
        option_text varchar(255) NOT NULL,
        option_type enum('basic','detailed') NOT NULL,
        category varchar(50) DEFAULT 'other',
        votes_count int(11) DEFAULT 0,
        is_custom BOOLEAN DEFAULT FALSE,
        display_order INT DEFAULT 0,
        created_by bigint(20) DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY match_id (match_id),
        KEY idx_match_type_order (match_id, option_type, display_order),
        KEY idx_category (category)
    ) $charset_collate;";

        // Create vote_categories table
        $table_vote_categories = $wpdb->prefix . 'goalv_vote_categories';
        $sql_vote_categories = "CREATE TABLE $table_vote_categories (
        id int(11) NOT NULL AUTO_INCREMENT,
        category_key varchar(50) NOT NULL UNIQUE,
        category_label varchar(100) NOT NULL,
        display_order int(11) DEFAULT 0,
        is_active boolean DEFAULT true,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_display_order (display_order, is_active)
    ) $charset_collate;";

        // Create votes table (unchanged)
        $table_votes = $wpdb->prefix . 'goalv_votes';
        $sql_votes = "CREATE TABLE $table_votes (
        id int(11) NOT NULL AUTO_INCREMENT,
        match_id bigint(20) NOT NULL,
        option_id int(11) NOT NULL,
        user_id bigint(20) NULL,
        user_ip varchar(45),
        browser_id varchar(255),
        vote_time datetime DEFAULT CURRENT_TIMESTAMP,
        vote_location enum('homepage','details') NOT NULL,
        PRIMARY KEY (id),
        KEY match_id (match_id),
        KEY user_id (user_id),
        KEY browser_id (browser_id)
    ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_vote_options);
        dbDelta($sql_vote_categories);
        dbDelta($sql_votes);

        // Insert default categories
        $this->insert_default_categories();

        // Set default options
        add_option('goalv_api_key', '');
        add_option('goalv_competition_id', '2021');
        add_option('goalv_allow_vote_change', 'yes');
        add_option('goalv_allow_homepage_vote_change', 'yes');
        add_option('goalv_allow_details_vote_change', 'yes');
        add_option('goalv_allow_multiple_votes', 'no');
    }

    public function create_vote_summary_table()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'goalv_vote_summary';

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        match_id bigint(20) NOT NULL,
        option_id mediumint(9) NOT NULL,
        vote_location varchar(20) NOT NULL,
        total_votes int DEFAULT 0,
        last_updated datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY match_option_location (match_id, option_id, vote_location),
        KEY match_location (match_id, vote_location)
    ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function upgrade_database_for_custom_options()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'goalv_vote_options';

        // Check if the new columns exist
        $columns = $wpdb->get_results("DESCRIBE $table_name");
        $column_names = array_column($columns, 'Field');

        // Add missing columns
        if (!in_array('is_custom', $column_names)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN is_custom BOOLEAN DEFAULT FALSE");
        }

        if (!in_array('display_order', $column_names)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN display_order INT DEFAULT 0");
        }

        if (!in_array('created_by', $column_names)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN created_by bigint(20) DEFAULT NULL");
        }

        if (!in_array('created_at', $column_names)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN created_at datetime DEFAULT CURRENT_TIMESTAMP");
        }

        // Add indexes if they don't exist
        $indexes = $wpdb->get_results("SHOW INDEX FROM $table_name");
        $index_names = array_column($indexes, 'Key_name');

        if (!in_array('idx_match_type_order', $index_names)) {
            $wpdb->query("ALTER TABLE $table_name ADD INDEX idx_match_type_order (match_id, option_type, display_order)");
        }

        // Update existing records to have proper display_order
        $wpdb->query("
        UPDATE $table_name SET display_order = id 
        WHERE display_order = 0 OR display_order IS NULL
    ");
    }

    /**
     * Insert default vote categories
     */
    private function insert_default_categories()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'goalv_vote_categories';

        // Check if categories already exist
        $existing_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");

        if ($existing_count > 0) {
            return; // Categories already exist
        }

        // Default categories for vote grouping
        $default_categories = array(
            array(
                'category_key' => 'match_result',
                'category_label' => __('Match Result', 'goalv'),
                'display_order' => 1
            ),
            array(
                'category_key' => 'match_score',
                'category_label' => __('Exact Score', 'goalv'),
                'display_order' => 2
            ),
            array(
                'category_key' => 'goals_threshold',
                'category_label' => __('Total Goals', 'goalv'),
                'display_order' => 3
            ),
            array(
                'category_key' => 'both_teams_score',
                'category_label' => __('Both Teams to Score', 'goalv'),
                'display_order' => 4
            ),
            array(
                'category_key' => 'first_to_score',
                'category_label' => __('First Team to Score', 'goalv'),
                'display_order' => 5
            ),
            array(
                'category_key' => 'other',
                'category_label' => __('Other Predictions', 'goalv'),
                'display_order' => 6
            )
        );

        // Insert categories
        foreach ($default_categories as $category) {
            $wpdb->insert($table_name, $category);
        }
    }

}

// Initialize the plugin
new GoalV_Football_Predictions();