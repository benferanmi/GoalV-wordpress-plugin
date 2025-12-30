<?php
/**
 * Plugin Name: GoalV Football Predictions
 * Plugin URI: https://oluwaferanmi-developer-site.vercel.app/
 * Description: Multi-league football prediction platform with live scores and autonomous syncing
 * Version: 9.1.35
 * changes: fixing elementor page not opening issue
 * Author: Opafunso Benjamin
 * License: GPL v2 or later
 * Text Domain: goalv
 * Domain Path: /languages
 */

// Prevent direct accesss
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('GOALV_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GOALV_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('GOALV_VERSION', '9.0.1');

error_log('🚀 GoalV Plugin Loaded - Version: ' . GOALV_VERSION . ' - Time: ' . current_time('mysql'));

/**
 * Main GoalV Plugin Class - Pure Database System
 */
class GoalV_Football_Predictions
{
    private $sync_scheduler;

    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('plugins_loaded', array($this, 'init'));
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

        if (is_admin()) {
            self::check_database_migration();
        }
    }

    /**
     * Include required files - OPTIMIZED LOADING ORDER
     * 
     * CRITICAL: Vote Options Manager MUST load BEFORE API classes
     */
    private function includes()
    {
        // ============================================
        // PHASE 1: Database Setup
        // ============================================
        require_once GOALV_PLUGIN_PATH . 'includes/database/class-goalv-db-setup.php';

        // ============================================
        // PHASE 2: Data Models (Load Early)
        // ============================================
        require_once GOALV_PLUGIN_PATH . 'includes/models/class-goalv-competition.php';
        require_once GOALV_PLUGIN_PATH . 'includes/models/class-goalv-team.php';
        require_once GOALV_PLUGIN_PATH . 'includes/models/class-goalv-match.php';

        // ============================================
        // PHASE 3: Voting System (BEFORE API Layer!)
        // ============================================
        require_once GOALV_PLUGIN_PATH . 'includes/voting/class-goalv-vote-options-manager.php';
        require_once GOALV_PLUGIN_PATH . 'includes/class-goalv-voting.php';

        // ============================================
        // PHASE 4: API Layer (Depends on Vote Options Manager)
        // ============================================
        require_once GOALV_PLUGIN_PATH . 'includes/api/class-goalv-api-football-client.php';
        require_once GOALV_PLUGIN_PATH . 'includes/api/class-goalv-api-competitions.php';
        require_once GOALV_PLUGIN_PATH . 'includes/api/class-goalv-api-matches.php';
        require_once GOALV_PLUGIN_PATH . 'includes/api/class-goalv-api-live-scores.php';

        // ============================================
        // PHASE 5: Autonomous Sync System
        // ============================================
        require_once GOALV_PLUGIN_PATH . 'includes/sync/class-goalv-sync-manager.php';
        require_once GOALV_PLUGIN_PATH . 'includes/sync/class-goalv-sync-scheduler.php';
        require_once GOALV_PLUGIN_PATH . 'includes/sync/class-goalv-sync-live-scores.php';

        // ============================================
        // PHASE 6: Admin System (Modular)
        // ============================================
        require_once GOALV_PLUGIN_PATH . 'includes/admin/class-goalv-admin-core.php';
        require_once GOALV_PLUGIN_PATH . 'includes/admin/class-goalv-admin-ajax.php';

        // ============================================
        // PHASE 7: Modular Frontend System
        // ============================================
        require_once GOALV_PLUGIN_PATH . 'includes/frontend/class-goalv-match-query.php';
        require_once GOALV_PLUGIN_PATH . 'includes/frontend/class-goalv-match-renderer.php';
        require_once GOALV_PLUGIN_PATH . 'includes/frontend/class-goalv-shortcode-handler.php';
        require_once GOALV_PLUGIN_PATH . 'includes/frontend/class-goalv-frontend.php';
    }

    /**
     * Initialize classes
     */
    private function init_classes()
    {
        // Initialize sync scheduler
        $this->sync_scheduler = new GoalV_Sync_Scheduler();

        // Initialize voting system
        new GoalV_Voting();

        // Initialize modular frontend
        new GoalV_Frontend();

        // Initialize admin system
        new GoalV_Admin_Core();

        if (is_admin()) {
            new GoalV_Admin_AJAX();
        }
    }

    /**
     * Register plugin hooks
     */
    private function register_hooks()
    {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_filter('template_include', array($this, 'load_single_match_template'));
    }

    /**
     * Load single match template (custom URL handler)
     */
    public function load_single_match_template($template)
    {
        $match_id = get_query_var('goalv_match_id');

        if ($match_id) {
            $single_template = GOALV_PLUGIN_PATH . 'templates/single-goalv_matches.php';
            if (file_exists($single_template)) {
                return $single_template;
            }
        }

        return $template;
    }

    /**
     * Enqueue frontend assets - MODULAR LOADING
     */
    public function enqueue_frontend_assets()
    {
        // Shared modules
        wp_enqueue_script(
            'goalv-ajax-handler',
            GOALV_PLUGIN_URL . 'assets/js/shared/goalv-ajax-handler.js',
            array('jquery'),
            GOALV_VERSION,
            true
        );

        wp_enqueue_script(
            'goalv-toast-system',
            GOALV_PLUGIN_URL . 'assets/js/shared/goalv-toast-system.js',
            array('jquery'),
            GOALV_VERSION,
            true
        );

        // Frontend modules
        wp_enqueue_script(
            'goalv-frontend-core',
            GOALV_PLUGIN_URL . 'assets/js/frontend/goalv-frontend-core.js',
            array('jquery', 'goalv-ajax-handler'),
            GOALV_VERSION,
            true
        );

        wp_enqueue_script(
            'goalv-frontend-voting',
            GOALV_PLUGIN_URL . 'assets/js/frontend/goalv-frontend-voting.js',
            array('goalv-frontend-core'),
            GOALV_VERSION,
            true
        );

        wp_enqueue_script(
            'goalv-frontend-live',
            GOALV_PLUGIN_URL . 'assets/js/frontend/goalv-frontend-live.js',
            array('goalv-frontend-core'),
            GOALV_VERSION,
            true
        );

        // Localization
        wp_localize_script('goalv-frontend-core', 'goalv_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('goalv_vote_nonce'),
            'is_user_logged_in' => is_user_logged_in(),
            'poll_interval' => 30000
        ));

        // Frontend CSS
        wp_enqueue_style(
            'goalv-style',
            GOALV_PLUGIN_URL . 'assets/css/goalv-style.css',
            array(),
            GOALV_VERSION
        );
    }

    public static function check_database_migration()
    {
        $current_db_version = get_option('goalv_db_version', '0.0.0');
        $target_db_version = '9.0.3'; // Your current plugin version

        if (version_compare($current_db_version, $target_db_version, '<')) {
            require_once GOALV_PLUGIN_PATH . 'includes/database/class-goalv-db-setup.php';
            GoalV_DB_Setup::migrate_to_9_0_3();
            update_option('goalv_db_version', $target_db_version);
            error_log('GoalV: Database migrated to version ' . $target_db_version);
        }
    }

    /**
     * Enqueue admin assets - MODULAR TAB-SPECIFIC LOADING
     */
    public function enqueue_admin_assets($hook_suffix)
    {
        // Only load on GoalV pages
        if (strpos($hook_suffix, 'goalv') === false) {
            return;
        }

        // ============================================
        // CORE MODULES (Always Load)
        // ============================================
        $core_scripts = array(
            'goalv-ajax-handler' => 'shared/goalv-ajax-handler.js',
            'goalv-toast-system' => 'shared/goalv-toast-system.js',
            'goalv-state-manager' => 'shared/goalv-state-manager.js',
            'goalv-admin-utils' => 'admin/goalv-admin-utils.js',
            'goalv-admin-core' => 'admin/goalv-admin-core.js'
        );

        foreach ($core_scripts as $handle => $path) {
            wp_enqueue_script(
                $handle,
                GOALV_PLUGIN_URL . "assets/js/{$path}",
                array('jquery'),
                GOALV_VERSION,
                true
            );
        }

        // ============================================
        // TAB-SPECIFIC MODULES (Load Based on Tab)
        // ============================================
        $current_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'dashboard';

        $tab_scripts = array(
            'dashboard' => 'admin/goalv-admin-dashboard.js',
            'api-settings' => 'admin/goalv-admin-api.js',
            'competitions' => 'admin/goalv-admin-competitions.js',
            'sync' => 'admin/goalv-admin-sync.js',
            'voting' => 'admin/goalv-admin-voting.js',
            'system' => 'admin/goalv-admin-system.js'
        );

        if (isset($tab_scripts[$current_tab])) {
            $handle = 'goalv-admin-' . str_replace('-', '_', $current_tab);

            wp_enqueue_script(
                $handle,
                GOALV_PLUGIN_URL . "assets/js/{$tab_scripts[$current_tab]}",
                array('goalv-admin-core'),
                GOALV_VERSION,
                true
            );

            // jQuery UI Sortable for voting tab
            if ($current_tab === 'voting') {
                wp_enqueue_script('jquery-ui-sortable');
            }
        }

        // ============================================
        // LOCALIZATION
        // ============================================
        wp_localize_script('goalv-admin-core', 'goalv_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('goalv_admin_nonce'),
            'current_tab' => $current_tab,
            'strings' => array(
                'confirm_delete' => __('Are you sure?', 'goalv'),
                'sync_success' => __('Sync completed!', 'goalv'),
                'sync_error' => __('Sync failed', 'goalv')
            )
        ));

        // Admin CSS
        wp_enqueue_style(
            'goalv-admin-style',
            GOALV_PLUGIN_URL . 'assets/css/goalv-admin.css',
            array(),
            GOALV_VERSION
        );
    }

    /**
     * Plugin activation
     */
    public static function activate()
    {
        // Load required classes in CORRECT ORDER
        require_once GOALV_PLUGIN_PATH . 'includes/database/class-goalv-db-setup.php';

        // Load models first
        require_once GOALV_PLUGIN_PATH . 'includes/models/class-goalv-competition.php';
        require_once GOALV_PLUGIN_PATH . 'includes/models/class-goalv-team.php';
        require_once GOALV_PLUGIN_PATH . 'includes/models/class-goalv-match.php';

        // Load voting system BEFORE API
        require_once GOALV_PLUGIN_PATH . 'includes/voting/class-goalv-vote-options-manager.php';

        // Then load API classes
        require_once GOALV_PLUGIN_PATH . 'includes/api/class-goalv-api-football-client.php';
        require_once GOALV_PLUGIN_PATH . 'includes/api/class-goalv-api-competitions.php';
        require_once GOALV_PLUGIN_PATH . 'includes/api/class-goalv-api-matches.php';
        require_once GOALV_PLUGIN_PATH . 'includes/api/class-goalv-api-live-scores.php';

        // Then sync system
        require_once GOALV_PLUGIN_PATH . 'includes/sync/class-goalv-sync-manager.php';
        require_once GOALV_PLUGIN_PATH . 'includes/sync/class-goalv-sync-scheduler.php';
        require_once GOALV_PLUGIN_PATH . 'includes/sync/class-goalv-sync-live-scores.php';

        // Create database tables
        GoalV_DB_Setup::create_tables();

        // Run database migration (for updates)
        self::check_database_migration();

        // Schedule sync jobs
        $scheduler = new GoalV_Sync_Scheduler();
        $scheduler->schedule_all_events();

        // Set version and options
        update_option('goalv_plugin_version', GOALV_VERSION);
        add_option('goalv_enable_live_sync', true);
        add_option('goalv_api_football_key', '');

        // Flush rewrite rules for custom URLs
        flush_rewrite_rules();

        error_log('========================================');
        error_log('GoalV Plugin v' . GOALV_VERSION . ' activated');
        error_log('- Pure database architecture (NO CPT)');
        error_log('- Modular frontend system');
        error_log('- Autonomous sync enabled');
        error_log('- Vote Options Manager loaded BEFORE API');
        error_log('- Custom match URLs: /match/{id}/');
        error_log('========================================');
    }

    /**
     * Plugin deactivation
     */
    public static function deactivate()
    {
        // Load scheduler class
        require_once GOALV_PLUGIN_PATH . 'includes/sync/class-goalv-sync-scheduler.php';
        require_once GOALV_PLUGIN_PATH . 'includes/sync/class-goalv-sync-manager.php';

        $scheduler = new GoalV_Sync_Scheduler();
        $scheduler->unschedule_all_events();

        flush_rewrite_rules();

        error_log('GoalV Plugin deactivated - Sync jobs stopped');
    }
}

// Initialize the plugin
new GoalV_Football_Predictions();

// Register activation/deactivation hooks OUTSIDE the class
register_activation_hook(__FILE__, array('GoalV_Football_Predictions', 'activate'));
register_deactivation_hook(__FILE__, array('GoalV_Football_Predictions', 'deactivate'));