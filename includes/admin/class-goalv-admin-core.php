<?php
/**
 * GoalV Admin Core - Main Admin Coordinator
 * Handles menu registration and loads all admin sub-modules
 * 
 * @package GoalV
 * @subpackage Admin
 * @since 8.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class GoalV_Admin_Core
{
    /**
     * Admin sub-modules
     */
    private $api_settings;
    private $competitions;
    private $sync_manager;
    private $system_info;
    private $voting_settings;
    private $matches;

    /**
     * Current active tab
     */
    private $current_tab;

    /**
     * Constructor
     */
    public function __construct()
    {
        // Load admin sub-modules
        $this->load_modules();

        // Register hooks
        add_action('admin_menu', array($this, 'register_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

        // Handle tab navigation
        $this->current_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'dashboard';
    }

    /**
     * Load admin sub-modules
     */
    /**
     * Load admin sub-modules with error handling
     */
    private function load_modules()
    {
        try {
            // API Settings Module
            require_once GOALV_PLUGIN_PATH . 'includes/admin/class-goalv-admin-api-settings.php';
            $this->api_settings = new GoalV_Admin_API_Settings();
        } catch (Exception $e) {
            error_log('GoalV: Failed to load API Settings module - ' . $e->getMessage());
            $this->api_settings = null;
        }

        try {
            // Competitions Module
            require_once GOALV_PLUGIN_PATH . 'includes/admin/class-goalv-admin-competitions.php';
            $this->competitions = new GoalV_Admin_Competitions();
        } catch (Exception $e) {
            error_log('GoalV: Failed to load Competitions module - ' . $e->getMessage());
            $this->competitions = null;
        }

        try {
            // Sync Manager Module
            require_once GOALV_PLUGIN_PATH . 'includes/admin/class-goalv-admin-sync.php';
            $this->sync_manager = new GoalV_Admin_Sync();
        } catch (Exception $e) {
            error_log('GoalV: Failed to load Sync Manager module - ' . $e->getMessage());
            $this->sync_manager = null;
        }

        try {
            // System Info Module
            require_once GOALV_PLUGIN_PATH . 'includes/admin/class-goalv-admin-system.php';
            $this->system_info = new GoalV_Admin_System();
        } catch (Exception $e) {
            error_log('GoalV: Failed to load System Info module - ' . $e->getMessage());
            $this->system_info = null;
        }

        try {
            // Voting Settings Module
            require_once GOALV_PLUGIN_PATH . 'includes/admin/class-goalv-admin-voting.php';
            $this->voting_settings = new GoalV_Admin_Voting();
        } catch (Exception $e) {
            error_log('GoalV: Failed to load Voting Settings module - ' . $e->getMessage());
            $this->voting_settings = null;
        }
        try {
            // Matches Module
            require_once GOALV_PLUGIN_PATH . 'includes/admin/class-goalv-admin-matches.php';
            $this->matches = new GoalV_Admin_Matches();
        } catch (Exception $e) {
            error_log('GoalV: Failed to load Matches module - ' . $e->getMessage());
            $this->matches = null;
        }
    }

    /**
     * Register admin menu
     */
    public function register_admin_menu()
    {
        add_menu_page(
            __('GoalV Settings', 'goalv'),
            __('GoalV', 'goalv'),
            'manage_options',
            'goalv-settings',
            array($this, 'render_admin_page'),
            'dashicons-awards',
            30
        );

        // Add submenu items for all 6 tabs
        add_submenu_page(
            'goalv-settings',
            __('Dashboard', 'goalv'),
            __('Dashboard', 'goalv'),
            'manage_options',
            'goalv-settings',
            array($this, 'render_admin_page')
        );

        add_submenu_page(
            'goalv-settings',
            __('API Settings', 'goalv'),
            __('API Settings', 'goalv'),
            'manage_options',
            'goalv-settings&tab=api-settings',
            array($this, 'render_admin_page')
        );

        add_submenu_page(
            'goalv-settings',
            __('Competitions', 'goalv'),
            __('Competitions', 'goalv'),
            'manage_options',
            'goalv-settings&tab=competitions',
            array($this, 'render_admin_page')
        );

        add_submenu_page(
            'goalv-settings',
            __('Sync Manager', 'goalv'),
            __('Sync Manager', 'goalv'),
            'manage_options',
            'goalv-settings&tab=sync',
            array($this, 'render_admin_page')
        );

        add_submenu_page(
            'goalv-settings',
            __('Voting Settings', 'goalv'),
            __('Voting Settings', 'goalv'),
            'manage_options',
            'goalv-settings&tab=voting',
            array($this, 'render_admin_page')
        );

        add_submenu_page(
            'goalv-settings',
            __('System Info', 'goalv'),
            __('System Info', 'goalv'),
            'manage_options',
            'goalv-settings&tab=system',
            array($this, 'render_admin_page')
        );
        add_submenu_page(
            'goalv-settings',
            __('Matches', 'goalv'),
            __('Matches', 'goalv'),
            'manage_options',
            'goalv-settings&tab=matches',
            array($this, 'render_admin_page')
        );
    }

    /**
     * Register settings
     */
    public function register_settings()
    {
        // API Settings
        register_setting('goalv_api_settings', 'goalv_api_football_key');
        register_setting('goalv_api_settings', 'goalv_enable_live_sync');

        // Voting Settings (preserved)
        register_setting('goalv_voting_settings', 'goalv_allow_vote_change');
        register_setting('goalv_voting_settings', 'goalv_allow_homepage_vote_change');
        register_setting('goalv_voting_settings', 'goalv_allow_details_vote_change');
        register_setting('goalv_voting_settings', 'goalv_allow_multiple_votes');
        register_setting('goalv_display_labels', 'goalv_labels_teams');

    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook_suffix)
    {
        // Only load on GoalV admin pages
        if (strpos($hook_suffix, 'goalv') === false) {
            return;
        }

        // Admin CSS
        wp_enqueue_style(
            'goalv-admin-style',
            GOALV_PLUGIN_URL . 'assets/css/goalv-admin.css',
            array(),
            GOALV_VERSION
        );

        // jQuery UI for sortables (competition ordering)
        wp_enqueue_script('jquery-ui-sortable');

        // ========================================
        // ENQUEUE SHARED JAVASCRIPT FILES
        // ========================================

        // 1. Toast System (must load first - used by all modules)
        wp_enqueue_script(
            'goalv-toast-system',
            GOALV_PLUGIN_URL . 'assets/js/shared/goalv-toast-system.js',
            array('jquery'),
            GOALV_VERSION,
            true
        );

        // 2. State Manager (must load second - used by all modules)
        wp_enqueue_script(
            'goalv-state-manager',
            GOALV_PLUGIN_URL . 'assets/js/shared/goalv-state-manager.js',
            array('jquery'),
            GOALV_VERSION,
            true
        );

        // ========================================
        // ENQUEUE ADMIN JAVASCRIPT FILES
        // ========================================

        // 3. Admin Utils (must load before other admin modules)
        wp_enqueue_script(
            'goalv-admin-utils',
            GOALV_PLUGIN_URL . 'assets/js/admin/goalv-admin-utils.js',
            array('jquery', 'goalv-toast-system', 'goalv-state-manager'),
            GOALV_VERSION,
            true
        );

        // 4. Admin Core (initializes the admin system)
        wp_enqueue_script(
            'goalv-admin-core',
            GOALV_PLUGIN_URL . 'assets/js/admin/goalv-admin-core.js',
            array('jquery', 'goalv-toast-system', 'goalv-state-manager', 'goalv-admin-utils'),
            GOALV_VERSION,
            true
        );

        // 5. Admin Dashboard
        wp_enqueue_script(
            'goalv-admin-dashboard',
            GOALV_PLUGIN_URL . 'assets/js/admin/goalv-admin-dashboard.js',
            array('jquery', 'goalv-admin-utils'),
            GOALV_VERSION,
            true
        );

        // 6. Admin API Settings
        wp_enqueue_script(
            'goalv-admin-api',
            GOALV_PLUGIN_URL . 'assets/js/admin/goalv-admin-api.js',
            array('jquery', 'goalv-admin-utils'),
            GOALV_VERSION,
            true
        );

        // 7. Admin Competitions
        wp_enqueue_script(
            'goalv-admin-competitions',
            GOALV_PLUGIN_URL . 'assets/js/admin/goalv-admin-competitions.js',
            array('jquery', 'jquery-ui-sortable', 'goalv-admin-utils'),
            GOALV_VERSION,
            true
        );
        // 7. Admin Matches
        wp_enqueue_script(
            'goalv-admin-matches',
            GOALV_PLUGIN_URL . 'assets/js/admin/goalv-admin-matches.js',
            array('jquery', 'goalv-admin-utils'),
            GOALV_VERSION,
            true
        );

        // 8. Admin Sync
        wp_enqueue_script(
            'goalv-admin-sync',
            GOALV_PLUGIN_URL . 'assets/js/admin/goalv-admin-sync.js',
            array('jquery', 'goalv-admin-utils'),
            GOALV_VERSION,
            true
        );

        // 9. Admin Voting
        wp_enqueue_script(
            'goalv-admin-voting',
            GOALV_PLUGIN_URL . 'assets/js/admin/goalv-admin-voting.js',
            array('jquery', 'jquery-ui-sortable', 'goalv-admin-utils'),
            GOALV_VERSION,
            true
        );

        // 10. Admin System
        wp_enqueue_script(
            'goalv-admin-system',
            GOALV_PLUGIN_URL . 'assets/js/admin/goalv-admin-system.js',
            array('jquery', 'goalv-admin-utils'),
            GOALV_VERSION,
            true
        );

        // ========================================
        // AJAX CONFIGURATION (for all admin scripts)
        // ========================================

        // Create GoalV.Ajax helper object
        $ajax_config = array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('goalv_admin_nonce'),
            'current_tab' => $this->current_tab,
            'strings' => array(
                'confirm_delete' => __('Are you sure you want to delete this?', 'goalv'),
                'sync_in_progress' => __('Sync in progress...', 'goalv'),
                'sync_success' => __('Sync completed successfully!', 'goalv'),
                'sync_error' => __('Sync failed. Please try again.', 'goalv'),
                'api_test_success' => __('API connection successful!', 'goalv'),
                'api_test_error' => __('API connection failed. Please check your key.', 'goalv')
            )
        );

        // Localize for goalv-admin-utils (it will create GoalV.Ajax object)
        wp_localize_script('goalv-admin-utils', 'goalvAjaxConfig', $ajax_config);
    }

    /**
     * Render main admin page with tabs
     */
    public function render_admin_page()
    {
        ?>
        <div class="wrap goalv-admin-wrap">
            <h1>
                <?php echo esc_html(get_admin_page_title()); ?>
            </h1>

            <?php settings_errors(); ?>

            <!-- Tab Navigation -->
            <nav class="nav-tab-wrapper goalv-tab-wrapper">
                <?php echo $this->render_tabs(); ?>
            </nav>

            <!-- Tab Content -->
            <div class="goalv-tab-content">
                <?php $this->render_tab_content(); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render navigation tabs
     */
    private function render_tabs()
    {
        $tabs = array(
            'dashboard' => array(
                'label' => __('Dashboard', 'goalv'),
                'icon' => 'dashicons-dashboard'
            ),
            'api-settings' => array(
                'label' => __('API Settings', 'goalv'),
                'icon' => 'dashicons-admin-settings'
            ),
            'competitions' => array(
                'label' => __('Competitions', 'goalv'),
                'icon' => 'dashicons-awards'
            ),
            'sync' => array(
                'label' => __('Sync Manager', 'goalv'),
                'icon' => 'dashicons-update'
            ),
            'matches' => array(
                'label' => __('Matches', 'goalv'),
                'icon' => 'dashicons-calendar-alt'
            ),
            'voting' => array(
                'label' => __('Voting Settings', 'goalv'),
                'icon' => 'dashicons-thumbs-up'
            ),
            'system' => array(
                'label' => __('System Info', 'goalv'),
                'icon' => 'dashicons-info'
            )
        );

        $output = '';

        foreach ($tabs as $tab_key => $tab_data) {
            $active = ($this->current_tab === $tab_key) ? 'nav-tab-active' : '';
            $url = admin_url('admin.php?page=goalv-settings&tab=' . $tab_key);

            $output .= sprintf(
                '<a href="%s" class="nav-tab %s"><span class="dashicons %s"></span> %s</a>',
                esc_url($url),
                esc_attr($active),
                esc_attr($tab_data['icon']),
                esc_html($tab_data['label'])
            );
        }

        return $output;
    }

    /**
     * Render current tab content
     */
    /**
     * Render current tab content
     */
    private function render_tab_content()
    {
        switch ($this->current_tab) {
            case 'dashboard':
                $this->render_dashboard();
                break;

            case 'api-settings':
                if ($this->api_settings) {
                    $this->api_settings->render();
                } else {
                    echo '<div class="notice notice-error"><p>API Settings module failed to load.</p></div>';
                }
                break;

            // case 'competitions':
            //     if ($this->competitions) {
            //         $this->competitions->render();
            //     } else {
            //         echo '<div class="notice notice-error"><p>Competitions module failed to load.</p></div>';
            //     }
            //     break;

            case 'competitions':
                if ($this->competitions) {
                    require_once GOALV_PLUGIN_PATH . 'admin/pages/competitions.php';
                } else {
                    echo '<div class="notice notice-error"><p>Competitions module failed to load.</p></div>';
                }
                break;

            case 'sync':
                if ($this->sync_manager) {
                    $this->sync_manager->render();
                } else {
                    echo '<div class="notice notice-error"><p>Sync Manager module failed to load.</p></div>';
                }
                break;

            case 'voting':
                if ($this->voting_settings) {
                    $this->voting_settings->render();
                } else {
                    echo '<div class="notice notice-error"><p>Voting Settings module failed to load.</p></div>';
                }
                break;

            case 'system':
                if ($this->system_info) {
                    $this->system_info->render();
                } else {
                    echo '<div class="notice notice-error"><p>System Info module failed to load.</p></div>';
                }
                break;
            case 'matches':
                if ($this->matches) {
                    $this->matches->render();
                } else {
                    echo '<div class="notice notice-error"><p>Matches module failed to load.</p></div>';
                }
                break;

            default:
                $this->render_dashboard();
        }
    }

    /**
     * Render dashboard tab
     */
    private function render_dashboard()
    {
        require_once GOALV_PLUGIN_PATH . 'admin/pages/dashboard.php';
    }

    /**
     * Get all admin modules (for access by sub-modules)
     */
    public function get_module($module_name)
    {
        switch ($module_name) {
            case 'api_settings':
                return $this->api_settings;
            case 'competitions':
                return $this->competitions;
            case 'sync_manager':
                return $this->sync_manager;
            case 'system_info':
                return $this->system_info;
            case 'voting_settings':
                return $this->voting_settings;
            case 'matches':
                return $this->matches;
            default:
                return null;
        }
    }
}