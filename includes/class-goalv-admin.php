<?php
/**
 * Admin functionality - UPDATED WITH WEEK SELECTOR
 */

if (!defined('ABSPATH')) {
    exit;
}

class GoalV_Admin
{

    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'init_settings'));
        add_action('wp_ajax_goalv_sync_matches', array($this, 'ajax_sync_matches'));
        add_action('wp_ajax_goalv_test_api', array($this, 'ajax_test_api')); // NEW

        add_action('wp_ajax_goalv_add_custom_option', array($this, 'ajax_add_custom_option'));
        add_action('wp_ajax_goalv_remove_custom_option', array($this, 'ajax_remove_custom_option'));
        add_action('add_meta_boxes', array($this, 'add_custom_voting_meta_box'));
        add_action('save_post', array($this, 'save_custom_voting_options'));

        add_action('admin_notices', array($this, 'admin_notices'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('goalv_matches_synced', array($this, 'update_last_sync_time'));
        add_action('wp_ajax_goalv_get_homepage_weeks', array($this, 'ajax_get_homepage_weeks'));

        add_action('wp_ajax_goalv_add_category', array($this, 'ajax_add_category'));
        add_action('wp_ajax_goalv_update_category', array($this, 'ajax_update_category'));
        add_action('wp_ajax_goalv_delete_category', array($this, 'ajax_delete_category'));
        add_action('wp_ajax_goalv_reorder_categories', array($this, 'ajax_reorder_categories'));

    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook)
    {
        if (strpos($hook, 'goalv') === false) {
            return;
        }

        wp_enqueue_script(
            'goalv-admin-script',
            GOALV_PLUGIN_URL . 'assets/js/goalv-admin.js',
            array('jquery'),
            GOALV_VERSION,
            true
        );

        wp_localize_script('goalv-admin-script', 'goalv_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('goalv_vote_nonce'),
            'is_user_logged_in' => is_user_logged_in(),
            'is_admin' => true
        ));

        wp_enqueue_style(
            'goalv-admin-style',
            GOALV_PLUGIN_URL . 'assets/css/goalv-style.css',
            array(),
            GOALV_VERSION
        );
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu()
    {
        add_menu_page(
            __('GoalV Settings', 'goalv'),
            __('GoalV Settings', 'goalv'),
            'manage_options',
            'goalv-settings',
            array($this, 'settings_page'),
            'dashicons-awards',
            30
        );

        add_submenu_page(
            'goalv-settings',
            __('Match Sync', 'goalv'),
            __('Match Sync', 'goalv'),
            'manage_options',
            'goalv-sync',
            array($this, 'sync_page')
        );
    }

    /**
     * Initialize settings
     */
    public function init_settings()
    {
        register_setting('goalv_settings', 'goalv_api_key');
        register_setting('goalv_settings', 'goalv_competition_id');
        register_setting('goalv_settings', 'goalv_allow_vote_change');
        register_setting('goalv_settings', 'goalv_allow_homepage_vote_change');
        register_setting('goalv_settings', 'goalv_allow_details_vote_change');
        register_setting('goalv_settings', 'goalv_homepage_week_mode');
        register_setting('goalv_settings', 'goalv_homepage_weeks');
        register_setting('goalv_settings', 'goalv_homepage_range_start');
        register_setting('goalv_settings', 'goalv_homepage_range_end');
        register_setting('goalv_settings', 'goalv_show_week_headers');
        register_setting('goalv_settings', 'goalv_homepage_fallback');
        register_setting('goalv_settings', 'goalv_allow_multiple_votes');
        register_setting('goalv_settings', 'goalv_labels_teams');
        register_setting('goalv_settings', 'goalv_labels_score');
        register_setting('goalv_settings', 'goalv_labels_status');
        register_setting('goalv_settings', 'goalv_labels_date');
        register_setting('goalv_settings', 'goalv_labels_predictions');
        register_setting('goalv_settings', 'goalv_labels_details');

        add_settings_section(
            'goalv_api_section',
            __('API Settings', 'goalv'),
            array($this, 'api_section_callback'),
            'goalv_settings'
        );

        add_settings_section(
            'goalv_voting_section',
            __('Voting Settings', 'goalv'),
            array($this, 'voting_section_callback'),
            'goalv_settings'
        );

        add_settings_field(
            'goalv_api_key',
            __('Football-Data.org API Key', 'goalv'),
            array($this, 'api_key_callback'),
            'goalv_settings',
            'goalv_api_section'
        );

        add_settings_field(
            'goalv_competition_id',
            __('Competition ID', 'goalv'),
            array($this, 'competition_callback'),
            'goalv_settings',
            'goalv_api_section'
        );

        add_settings_field(
            'goalv_allow_vote_change',
            __('Allow Vote Changes', 'goalv'),
            array($this, 'allow_vote_change_callback'),
            'goalv_settings',
            'goalv_voting_section'
        );

        add_settings_field(
            'goalv_allow_homepage_vote_change',
            __('Allow Homepage Vote Changes', 'goalv'),
            array($this, 'allow_homepage_vote_change_callback'),
            'goalv_settings',
            'goalv_voting_section'
        );

        add_settings_field(
            'goalv_allow_details_vote_change',
            __('Allow Details Page Vote Changes', 'goalv'),
            array($this, 'allow_details_vote_change_callback'),
            'goalv_settings',
            'goalv_voting_section'
        );
    }

    /**
     * Settings page
     */
    public function settings_page()
    {
        include GOALV_PLUGIN_PATH . 'admin/admin-page.php';
    }

    /**
     * Sync page - UPDATED WITH WEEK SELECTOR
     */
    public function sync_page()
    {
        echo '<div class="wrap">';
        echo '<h1>' . __('Match Sync', 'goalv') . '</h1>';

        // Week selector section
        echo '<div class="card">';
        echo '<h2>' . __('Sync Football Matches', 'goalv') . '</h2>';

        // Get available weeks
        $api = new GoalV_API();
        $competition_id = get_option('goalv_competition_id', '2021');
        $available_weeks = $api->get_available_weeks($competition_id);
        $current_football_week = $api->get_current_football_week($competition_id);

        echo '<table class="form-table">';
        echo '<tr>';
        echo '<th scope="row"><label for="sync_week_selector">' . __('Select Game Week', 'goalv') . '</label></th>';
        echo '<td>';
        echo '<select id="sync_week_selector" name="sync_week">';
        echo '<option value="">' . __('Auto-detect (Recommended)', 'goalv') . '</option>';

        foreach ($available_weeks as $week_num => $week_label) {
            $selected = ($week_num == $current_football_week) ? 'selected' : '';
            echo "<option value='$week_num' $selected>$week_label</option>";
        }

        echo '</select>';
        echo '<p class="description">' . __('Auto-detect will find the next week with upcoming matches. Manual selection lets you sync specific weeks.', 'goalv') . '</p>';
        echo '</td>';
        echo '</tr>';
        echo '</table>';

        echo '<div class="goalv-sync-controls">';
        echo '<button type="button" id="sync-matches-btn" class="button button-primary">' . __('Sync Selected Week', 'goalv') . '</button>';
        echo '<button type="button" id="test-api-btn" class="button button-secondary" style="margin-left: 10px;">' . __('Test API Connection', 'goalv') . '</button>';
        echo '<span id="sync-loader" class="spinner" style="float: none; margin-left: 10px;"></span>';
        echo '</div>';

        echo '<div id="sync-result" style="margin-top: 15px;"></div>';
        echo '</div>';

        // Current status section
        echo '<div class="card" style="margin-top: 20px;">';
        echo '<h2>' . __('Current Status', 'goalv') . '</h2>';

        $last_synced_week = get_option('goalv_last_synced_week', '');
        $last_sync_time = get_option('goalv_last_sync_time', '');

        if ($last_synced_week && $last_sync_time) {
            echo '<p><strong>' . __('Last Synced Week:', 'goalv') . '</strong> ' . esc_html($last_synced_week) . '</p>';
            echo '<p><strong>' . __('Last Sync Time:', 'goalv') . '</strong> ' . esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($last_sync_time))) . '</p>';
        } else {
            echo '<p>' . __('No matches have been synced yet.', 'goalv') . '</p>';
        }

        echo '<p><strong>' . __('Calculated Current Football Week:', 'goalv') . '</strong> GW' . $current_football_week . '</p>';
        echo '</div>';

        // Recent matches section
        $recent_matches = get_posts(array(
            'post_type' => 'goalv_matches',
            'posts_per_page' => 10,
            'meta_key' => 'goalv_match_date',
            'orderby' => 'meta_value',
            'order' => 'DESC'
        ));

        if ($recent_matches) {
            echo '<div class="card" style="margin-top: 20px;">';
            echo '<h2>' . __('Recent Matches', 'goalv') . '</h2>';
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr><th>Match</th><th>Date</th><th>Status</th><th>Score</th><th>Game Week</th></tr></thead>';
            echo '<tbody>';

            foreach ($recent_matches as $match) {
                $home_team = get_post_meta($match->ID, 'goalv_home_team', true);
                $away_team = get_post_meta($match->ID, 'goalv_away_team', true);
                $match_date = get_post_meta($match->ID, 'goalv_match_date', true);
                $status = get_post_meta($match->ID, 'goalv_match_status', true);
                $home_score = get_post_meta($match->ID, 'goalv_home_score', true);
                $away_score = get_post_meta($match->ID, 'goalv_away_score', true);
                $week_synced = get_post_meta($match->ID, 'goalv_week_synced', true);

                echo '<tr>';
                echo '<td>' . esc_html($home_team . ' vs ' . $away_team) . '</td>';
                echo '<td>' . esc_html(date('M j, Y H:i', strtotime($match_date))) . '</td>';
                echo '<td><span class="goalv-status-' . esc_attr($status) . '">' . esc_html(ucfirst($status)) . '</span></td>';

                if ($status === 'finished') {
                    echo '<td>' . esc_html($home_score . ' - ' . $away_score) . '</td>';
                } else {
                    echo '<td>-</td>';
                }

                echo '<td>' . esc_html($week_synced) . '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
            echo '</div>';
        }

        echo '</div>';
    }

    /**
     * Section callbacks
     */
    public function api_section_callback()
    {
        echo '<p>' . __('Configure your Football-Data.org API settings here.', 'goalv') . '</p>';
    }

    public function voting_section_callback()
    {
        echo '<p>' . __('Configure voting behavior and permissions.', 'goalv') . '</p>';
    }

    /**
     * Field callbacks
     */
    public function api_key_callback()
    {
        $api_key = get_option('goalv_api_key', '');
        echo '<input type="password" id="goalv_api_key" name="goalv_api_key" value="' . esc_attr($api_key) . '" class="regular-text" />';
        echo '<p class="description">' . __('Get your free API key from football-data.org', 'goalv') . '</p>';
    }

    public function competition_callback()
    {
        $competition_id = get_option('goalv_competition_id', '2021');
        $competitions = array(
            '2021' => 'Premier League',
            '2014' => 'La Liga',
            '2002' => 'Bundesliga',
            '2019' => 'Serie A',
            '2015' => 'Ligue 1',
            '2001' => 'Champions League'
        );

        echo '<select id="goalv_competition_id" name="goalv_competition_id">';
        foreach ($competitions as $id => $name) {
            echo '<option value="' . esc_attr($id) . '"' . selected($competition_id, $id, false) . '>' . esc_html($name) . '</option>';
        }
        echo '</select>';
    }

    public function allow_vote_change_callback()
    {
        $allow_change = get_option('goalv_allow_vote_change', 'yes');
        echo '<input type="checkbox" id="goalv_allow_vote_change" name="goalv_allow_vote_change" value="yes"' . checked($allow_change, 'yes', false) . ' />';
        echo '<label for="goalv_allow_vote_change">' . __('Allow users to change their votes', 'goalv') . '</label>';
    }

    public function allow_homepage_vote_change_callback()
    {
        $allow_change = get_option('goalv_allow_homepage_vote_change', 'yes');
        echo '<input type="checkbox" id="goalv_allow_homepage_vote_change" name="goalv_allow_homepage_vote_change" value="yes"' . checked($allow_change, 'yes', false) . ' />';
        echo '<label for="goalv_allow_homepage_vote_change">' . __('Allow vote changes on homepage', 'goalv') . '</label>';
    }

    public function allow_details_vote_change_callback()
    {
        $allow_change = get_option('goalv_allow_details_vote_change', 'yes');
        echo '<input type="checkbox" id="goalv_allow_details_vote_change" name="goalv_allow_details_vote_change" value="yes"' . checked($allow_change, 'yes', false) . ' />';
        echo '<label for="goalv_allow_details_vote_change">' . __('Allow vote changes on details page', 'goalv') . '</label>';
    }

    /**
     * AJAX sync matches - UPDATED to handle week selection
     */
    public function ajax_sync_matches()
    {
        error_log('GoalV Sync: AJAX sync_matches called');

        // Verify nonce
        $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : '';
        if (!wp_verify_nonce($nonce, 'goalv_vote_nonce')) {
            error_log('GoalV Sync: Nonce verification failed');
            wp_send_json_error(__('Security check failed', 'goalv'));
            return;
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have sufficient permissions to access this page.', 'goalv'));
            return;
        }

        // Check API key
        $api_key = get_option('goalv_api_key', '');
        if (empty($api_key)) {
            wp_send_json_error(__('API key not configured. Please configure your Football-Data.org API key first.', 'goalv'));
            return;
        }

        try {
            // Get selected week (optional)
            $selected_week = isset($_POST['week']) && !empty($_POST['week']) ? (int) $_POST['week'] : null;

            error_log("GoalV Sync: Starting sync process for week: " . ($selected_week ? $selected_week : 'auto-detect'));

            // Initialize API class
            $api = new GoalV_API();
            $result = $api->sync_week_matches($selected_week);

            error_log('GoalV Sync: API result: ' . print_r($result, true));

            if ($result['success']) {
                update_option('goalv_last_sync_time', current_time('mysql'));
                do_action('goalv_matches_synced', $result);

                wp_send_json_success(array(
                    'message' => $result['message'],
                    'count' => isset($result['count']) ? $result['count'] : 0,
                    'week' => isset($result['week']) ? $result['week'] : '',
                    'timestamp' => current_time('mysql')
                ));
            } else {
                error_log('GoalV Sync: Failed - ' . $result['message']);
                wp_send_json_error($result['message']);
            }

        } catch (Exception $e) {
            error_log('GoalV Sync Error: ' . $e->getMessage());
            wp_send_json_error(__('An error occurred during sync: ', 'goalv') . $e->getMessage());
        }
    }

    /**
     * AJAX test API connection - NEW FUNCTION
     */
    public function ajax_test_api()
    {
        // Verify nonce
        $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : '';
        if (!wp_verify_nonce($nonce, 'goalv_vote_nonce')) {
            wp_send_json_error(__('Security check failed', 'goalv'));
            return;
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have sufficient permissions.', 'goalv'));
            return;
        }

        try {
            $api = new GoalV_API();
            $result = $api->test_api_connection();

            if ($result['success']) {
                wp_send_json_success(array(
                    'message' => __('API connection successful!', 'goalv'),
                    'competition' => $result['competition']
                ));
            } else {
                wp_send_json_error($result['message']);
            }

        } catch (Exception $e) {
            wp_send_json_error(__('Connection test failed: ', 'goalv') . $e->getMessage());
        }
    }
    public function debug_homepage_weeks()
    {
        if (!current_user_can('manage_options') || !isset($_GET['goalv_debug_weeks'])) {
            return;
        }

        echo '<div style="background: #f1f1f1; padding: 20px; margin: 20px 0; border-left: 4px solid #007cba;">';
        echo '<h3>GoalV Homepage Week Selection Debug</h3>';

        $weeks_info = $this->get_homepage_weeks_info();

        echo '<pre>';
        echo "Mode: " . $weeks_info['mode'] . "\n";
        echo "Selected Weeks: " . print_r($weeks_info['weeks'], true) . "\n";
        echo "Show Headers: " . ($weeks_info['show_headers'] ? 'Yes' : 'No') . "\n";
        echo "Fallback Enabled: " . ($weeks_info['fallback_enabled'] ? 'Yes' : 'No') . "\n";
        echo "</pre>";

        echo '<h4>Database Check</h4>';
        echo '<pre>';

        foreach ($weeks_info['weeks'] as $week_num) {
            $week_matches = get_posts(array(
                'post_type' => 'goalv_matches',
                'posts_per_page' => -1,
                'meta_query' => array(
                    array(
                        'key' => 'goalv_week_synced',
                        'value' => "GW{$week_num}",
                        'compare' => '='
                    )
                ),
                'fields' => 'ids'
            ));

            echo "GW{$week_num}: " . count($week_matches) . " matches\n";
        }
        echo '</pre>';

        echo '<h4>Database Values Check</h4>';
        echo '<pre>';
        echo "goalv_homepage_week_mode: " . get_option('goalv_homepage_week_mode', 'current') . "\n";
        echo "goalv_homepage_weeks: " . print_r(get_option('goalv_homepage_weeks', array()), true) . "\n";
        echo "goalv_homepage_range_start: " . get_option('goalv_homepage_range_start', 'not set') . "\n";
        echo "goalv_homepage_range_end: " . get_option('goalv_homepage_range_end', 'not set') . "\n";
        echo "goalv_show_week_headers: " . get_option('goalv_show_week_headers', 'yes') . "\n";
        echo "goalv_homepage_fallback: " . get_option('goalv_homepage_fallback', 'yes') . "\n";
        echo '</pre>';

        echo '</div>';
    }

    public function ajax_get_homepage_weeks()
    {
        // Verify nonce
        $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : '';
        if (!wp_verify_nonce($nonce, 'goalv_vote_nonce')) {
            wp_send_json_error(__('Security check failed', 'goalv'));
            return;
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have sufficient permissions.', 'goalv'));
            return;
        }

        try {
            $weeks_info = $this->get_homepage_weeks_info();
            wp_send_json_success($weeks_info);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function ajax_add_custom_option()
    {
        // Verify nonce
        $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : '';
        if (!wp_verify_nonce($nonce, 'goalv_vote_nonce')) {
            wp_send_json_error(__('Security check failed', 'goalv'));
            return;
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have sufficient permissions.', 'goalv'));
            return;
        }

        // Get and validate input
        $match_id = intval($_POST['match_id']);
        $option_text = sanitize_text_field($_POST['option_text']);
        $option_type = sanitize_text_field($_POST['option_type']);

        if (!$match_id || !$option_text || !$option_type) {
            wp_send_json_error(__('Invalid input data', 'goalv'));
            return;
        }

        // Validate match exists
        $match = get_post($match_id);
        if (!$match || $match->post_type !== 'goalv_matches') {
            wp_send_json_error(__('Match not found', 'goalv'));
            return;
        }

        // Validate option type
        if (!in_array($option_type, array('basic', 'detailed'))) {
            wp_send_json_error(__('Invalid option type', 'goalv'));
            return;
        }

        // Check for duplicate options
        global $wpdb;
        $options_table = $wpdb->prefix . 'goalv_vote_options';

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $options_table 
         WHERE match_id = %d AND option_type = %s AND option_text = %s",
            $match_id,
            $option_type,
            $option_text
        ));

        if ($existing > 0) {
            wp_send_json_error(__('An option with this text already exists', 'goalv'));
            return;
        }

        // Get next display order
        $max_order = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(display_order) FROM $options_table 
         WHERE match_id = %d AND option_type = %s AND is_custom = 1",
            $match_id,
            $option_type
        ));

        $display_order = $max_order ? ($max_order + 1) : 1;

        // Insert custom option
        $result = $wpdb->insert(
            $options_table,
            array(
                'match_id' => $match_id,
                'option_text' => $option_text,
                'option_type' => $option_type,
                'votes_count' => 0,
                'is_custom' => 1,
                'display_order' => $display_order,
                'created_by' => get_current_user_id()
            ),
            array('%d', '%s', '%s', '%d', '%d', '%d', '%d')
        );

        if ($result) {
            $option_id = $wpdb->insert_id;

            // Log success
            error_log("GoalV: Added custom option {$option_id} for match {$match_id}: {$option_text}");

            wp_send_json_success(array(
                'message' => __('Custom option added successfully', 'goalv'),
                'option_id' => $option_id,
                'option_text' => $option_text,
                'display_order' => $display_order
            ));
        } else {
            error_log("GoalV: Failed to add custom option for match {$match_id}: " . $wpdb->last_error);
            wp_send_json_error(__('Failed to add custom option to database', 'goalv'));
        }
    }

    /**
     * AJAX handler for removing custom voting options
     * Add this method to your GoalV_Admin class as well
     */
    public function ajax_remove_custom_option()
    {
        // Verify nonce
        $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : '';
        if (!wp_verify_nonce($nonce, 'goalv_vote_nonce')) {
            wp_send_json_error(__('Security check failed', 'goalv'));
            return;
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have sufficient permissions.', 'goalv'));
            return;
        }

        $option_id = intval($_POST['option_id']);
        if (!$option_id) {
            wp_send_json_error(__('Invalid option ID', 'goalv'));
            return;
        }

        global $wpdb;
        $options_table = $wpdb->prefix . 'goalv_vote_options';
        $votes_table = $wpdb->prefix . 'goalv_votes';

        // Verify it's a custom option
        $option = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $options_table WHERE id = %d AND is_custom = 1",
            $option_id
        ));

        if (!$option) {
            wp_send_json_error(__('Custom option not found', 'goalv'));
            return;
        }

        // Delete all votes for this option first
        $votes_deleted = $wpdb->delete($votes_table, array('option_id' => $option_id));

        // Delete the option
        $option_deleted = $wpdb->delete($options_table, array('id' => $option_id));

        if ($option_deleted) {
            // Clear vote cache
            if (class_exists('GoalV_Voting')) {
                $voting = new GoalV_Voting();
                $voting->clear_vote_cache($option->match_id);
            }

            error_log("GoalV: Deleted custom option {$option_id} with {$votes_deleted} votes");

            wp_send_json_success(array(
                'message' => __('Custom option removed successfully', 'goalv'),
                'votes_deleted' => $votes_deleted
            ));
        } else {
            wp_send_json_error(__('Failed to remove custom option', 'goalv'));
        }
    }


    // Add this helper method:
    public function get_homepage_weeks_info()
    {
        $mode = get_option('goalv_homepage_week_mode', 'current');
        $competition_id = get_option('goalv_competition_id', '2021');
        $api = new GoalV_API();
        $current_week = $api->get_current_football_week($competition_id);

        $weeks_to_display = array();

        switch ($mode) {
            case 'current':
                $weeks_to_display = array($current_week);
                break;

            case 'custom':
                $selected_weeks = get_option('goalv_homepage_weeks', array());
                if (is_array($selected_weeks) && !empty($selected_weeks)) {
                    $weeks_to_display = array_map('intval', $selected_weeks);
                    sort($weeks_to_display);
                } else {
                    // Fallback to current week if nothing selected
                    $weeks_to_display = array($current_week);
                }
                break;

            case 'range':
                $start_week = (int) get_option('goalv_homepage_range_start', $current_week);
                $end_week = (int) get_option('goalv_homepage_range_end', $current_week + 1);

                // Ensure proper order
                if ($start_week > $end_week) {
                    $temp = $start_week;
                    $start_week = $end_week;
                    $end_week = $temp;
                }

                for ($i = $start_week; $i <= $end_week; $i++) {
                    if ($i > 0 && $i <= 38) {
                        $weeks_to_display[] = $i;
                    }
                }
                break;
        }

        return array(
            'mode' => $mode,
            'weeks' => $weeks_to_display,
            'show_headers' => get_option('goalv_show_week_headers', 'yes') === 'yes',
            'fallback_enabled' => get_option('goalv_homepage_fallback', 'yes') === 'yes'
        );
    }

    /**
     * Add meta box for custom voting options
     */
    public function add_custom_voting_meta_box()
    {
        add_meta_box(
            'goalv_custom_voting_options',
            __('Custom Voting Options', 'goalv'),
            array($this, 'render_custom_voting_meta_box'),
            'goalv_matches',
            'normal',
            'default'
        );
    }

    /**
     * Update last sync time
     */
    public function update_last_sync_time($result)
    {
        if (isset($result['success']) && $result['success']) {
            update_option('goalv_last_sync_time', current_time('mysql'));
            update_option('goalv_last_sync_count', $result['count'] ?? 0);
            if (isset($result['week'])) {
                update_option('goalv_last_synced_week', $result['week']);
            }
        }
    }

    /**
     * Admin notices
     */
    public function admin_notices()
    {
        $screen = get_current_screen();

        if (!$screen || strpos($screen->id, 'goalv') === false) {
            return;
        }

        $api_key = get_option('goalv_api_key', '');

        if (empty($api_key)) {
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p>';
            printf(
                __('Please configure your Football-Data.org API key to start syncing matches. %sGet your free API key here%s.', 'goalv'),
                '<a href="https://www.football-data.org/client/register" target="_blank">',
                '</a>'
            );
            echo '</p>';
            echo '</div>';
        } else {
            // Check if we have any matches
            $match_count = wp_count_posts('goalv_matches');
            if ($match_count->publish == 0) {
                echo '<div class="notice notice-info is-dismissible">';
                echo '<p>' . __('No matches found. Go to Match Sync to sync this week\'s matches.', 'goalv') . '</p>';
                echo '</div>';
            }
        }

        // Show last sync info
        $last_sync = get_option('goalv_last_sync_time', '');
        $last_week = get_option('goalv_last_synced_week', '');

        if ($last_sync && isset($_GET['page']) && $_GET['page'] === 'goalv-settings') {
            $sync_time = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($last_sync));
            $sync_count = get_option('goalv_last_sync_count', 0);

            echo '<div class="notice notice-success is-dismissible">';
            echo '<p>';
            if ($last_week) {
                printf(
                    __('Last successful sync: %s (%s - %d matches)', 'goalv'),
                    $sync_time,
                    $last_week,
                    $sync_count
                );
            } else {
                printf(
                    __('Last successful sync: %s (%d matches)', 'goalv'),
                    $sync_time,
                    $sync_count
                );
            }
            echo '</p>';
            echo '</div>';
        }
    }

    /**
     * Render custom voting options meta box
     */
    public function render_custom_voting_meta_box($post)
    {
        wp_nonce_field('goalv_custom_voting_nonce', 'goalv_custom_voting_nonce');

        $voting = new GoalV_Voting();
        $all_options = $voting->get_vote_options($post->ID, 'detailed');

        // Filter only custom options
        $custom_options = array_filter($all_options, function ($option) {
            return isset($option->is_custom) && $option->is_custom == 1;
        });

        // Get available categories from database
        $categories = $voting->get_available_categories();
        $category_options = array();
        foreach ($categories as $cat) {
            $category_options[$cat->category_key] = $cat->category_label;
        }
        ?>

        <div id="goalv-custom-options-container">
            <p class="description">
                <?php _e('Add custom voting options for this match. These will be grouped by category alongside default options.', 'goalv'); ?>
            </p>

            <div id="goalv-custom-options-list">
                <?php if (!empty($custom_options)): ?>
                    <?php foreach ($custom_options as $index => $option): ?>
                        <div class="goalv-custom-option" data-index="<?php echo esc_attr($index); ?>">
                            <table class="form-table">
                                <tr>
                                    <td style="width: 40%;">
                                        <input type="text" name="goalv_custom_options[<?php echo esc_attr($index); ?>][text]"
                                            value="<?php echo esc_attr($option->option_text); ?>"
                                            placeholder="<?php esc_attr_e('Option text', 'goalv'); ?>" class="regular-text" />
                                        <input type="hidden" name="goalv_custom_options[<?php echo esc_attr($index); ?>][id]"
                                            value="<?php echo esc_attr($option->id); ?>" />
                                    </td>
                                    <td style="width: 30%;">
                                        <select name="goalv_custom_options[<?php echo esc_attr($index); ?>][category]">
                                            <?php foreach ($category_options as $key => $label): ?>
                                                <option value="<?php echo esc_attr($key); ?>" <?php selected(isset($option->category) ? $option->category : 'other', $key); ?>>
                                                    <?php echo esc_html($label); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td style="width: 30%;">
                                        <button type="button" class="button goalv-remove-custom-option"
                                            data-option-id="<?php echo esc_attr($option->id); ?>">
                                            <?php _e('Remove', 'goalv'); ?>
                                        </button>
                                        <input type="hidden" name="goalv_custom_options[<?php echo esc_attr($index); ?>][order]"
                                            value="<?php echo esc_attr(isset($option->display_order) ? $option->display_order : 100); ?>" />
                                    </td>
                                </tr>
                            </table>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div id="goalv-add-custom-option" style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">
                <table class="form-table">
                    <tr>
                        <td style="width: 40%;">
                            <input type="text" id="new-custom-option-text"
                                placeholder="<?php esc_attr_e('Enter new option text', 'goalv'); ?>" class="regular-text" />
                        </td>
                        <td style="width: 30%;">
                            <select id="new-custom-option-category">
                                <?php foreach ($category_options as $key => $label): ?>
                                    <option value="<?php echo esc_attr($key); ?>">
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td style="width: 30%;">
                            <button type="button" id="add-custom-option-btn" class="button button-secondary">
                                <?php _e('Add Option', 'goalv'); ?>
                            </button>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <script>
            jQuery(document).ready(function ($) {
                var optionIndex = <?php echo count($custom_options); ?>;

                // Add new custom option
                $('#add-custom-option-btn').on('click', function () {
                    var text = $('#new-custom-option-text').val().trim();
                    var category = $('#new-custom-option-category').val();

                    if (!text) {
                        alert('<?php _e('Please enter option text', 'goalv'); ?>');
                        return;
                    }

                    var html = '<div class="goalv-custom-option" data-index="' + optionIndex + '">';
                    html += '<table class="form-table"><tr>';
                    html += '<td style="width: 40%;"><input type="text" name="goalv_custom_options[' + optionIndex + '][text]" value="' + text + '" class="regular-text" />';
                    html += '<input type="hidden" name="goalv_custom_options[' + optionIndex + '][id]" value="0" /></td>';
                    html += '<td style="width: 30%;"><select name="goalv_custom_options[' + optionIndex + '][category]">';

                    <?php foreach ($category_options as $key => $label): ?>
                        html += '<option value="<?php echo esc_js($key); ?>"' + (category === '<?php echo esc_js($key); ?>' ? ' selected' : '') + '><?php echo esc_js($label); ?></option>';
                    <?php endforeach; ?>

                    html += '</select></td>';
                    html += '<td style="width: 30%;"><button type="button" class="button goalv-remove-custom-option"><?php _e('Remove', 'goalv'); ?></button>';
                    html += '<input type="hidden" name="goalv_custom_options[' + optionIndex + '][order]" value="' + (100 + optionIndex) + '" /></td>';
                    html += '</tr></table></div>';

                    $('#goalv-custom-options-list').append(html);

                    $('#new-custom-option-text').val('');
                    optionIndex++;
                });

                // Remove custom option
                $(document).on('click', '.goalv-remove-custom-option', function () {
                    if (confirm('<?php _e('Are you sure you want to remove this option?', 'goalv'); ?>')) {
                        $(this).closest('.goalv-custom-option').remove();
                    }
                });

                // Allow Enter key to add option
                $('#new-custom-option-text').on('keypress', function (e) {
                    if (e.which === 13) {
                        e.preventDefault();
                        $('#add-custom-option-btn').click();
                    }
                });
            });
        </script>

        <style>
            .goalv-custom-option {
                background: #f9f9f9;
                border: 1px solid #ddd;
                margin-bottom: 10px;
                border-radius: 4px;
            }

            .goalv-custom-option .form-table {
                margin: 0;
            }

            .goalv-custom-option .form-table td {
                padding: 8px 12px;
                border-bottom: 0;
            }

            #goalv-add-custom-option {
                background: #fff;
            }
        </style>
        <?php
    }

    /**
     * Save custom voting options
     */
    public function save_custom_voting_options($post_id)
    {
        // Verify nonce
        if (
            !isset($_POST['goalv_custom_voting_nonce']) ||
            !wp_verify_nonce($_POST['goalv_custom_voting_nonce'], 'goalv_custom_voting_nonce')
        ) {
            return;
        }

        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Check if this is an autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Process custom options
        if (isset($_POST['goalv_custom_options']) && is_array($_POST['goalv_custom_options'])) {
            $this->process_custom_options_data($post_id, $_POST['goalv_custom_options']);
        }
    }

    /**
     * AJAX handler for adding new category
     */
    public function ajax_add_category()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'goalv_admin_nonce')) {
            wp_send_json_error(__('Security check failed', 'goalv'));
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'goalv'));
        }

        $category_key = sanitize_key($_POST['category_key']);
        $category_label = sanitize_text_field($_POST['category_label']);

        if (empty($category_key) || empty($category_label)) {
            wp_send_json_error(__('Category key and label are required', 'goalv'));
        }

        // Validate key format
        if (!preg_match('/^[a-z_]+$/', $category_key)) {
            wp_send_json_error(__('Category key must contain only lowercase letters and underscores', 'goalv'));
        }

        $voting = new GoalV_Voting();
        $category_id = $voting->create_category($category_key, $category_label);

        if ($category_id) {
            wp_send_json_success(array(
                'message' => __('Category added successfully', 'goalv'),
                'category_id' => $category_id,
                'category_key' => $category_key,
                'category_label' => $category_label
            ));
        } else {
            wp_send_json_error(__('Failed to add category. Key might already exist.', 'goalv'));
        }
    }

    /**
     * AJAX handler for updating category
     */
    public function ajax_update_category()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'goalv_admin_nonce')) {
            wp_send_json_error(__('Security check failed', 'goalv'));
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'goalv'));
        }

        $category_id = intval($_POST['category_id']);
        $category_label = sanitize_text_field($_POST['category_label']);
        $display_order = intval($_POST['display_order']);

        if ($category_id <= 0 || empty($category_label)) {
            wp_send_json_error(__('Invalid category data', 'goalv'));
        }

        $voting = new GoalV_Voting();
        $success = $voting->update_category($category_id, $category_label, $display_order);

        if ($success) {
            wp_send_json_success(__('Category updated successfully', 'goalv'));
        } else {
            wp_send_json_error(__('Failed to update category', 'goalv'));
        }
    }

    /**
     * AJAX handler for deleting category
     */
    public function ajax_delete_category()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'goalv_admin_nonce')) {
            wp_send_json_error(__('Security check failed', 'goalv'));
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'goalv'));
        }

        $category_id = intval($_POST['category_id']);

        if ($category_id <= 0) {
            wp_send_json_error(__('Invalid category ID', 'goalv'));
        }

        $voting = new GoalV_Voting();
        $success = $voting->delete_category($category_id);

        if ($success) {
            wp_send_json_success(__('Category deleted successfully', 'goalv'));
        } else {
            wp_send_json_error(__('Failed to delete category. Cannot delete "other" category.', 'goalv'));
        }
    }

    /**
     * AJAX handler for reordering categories
     */
    public function ajax_reorder_categories()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'goalv_admin_nonce')) {
            wp_send_json_error(__('Security check failed', 'goalv'));
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'goalv'));
        }

        $category_order = json_decode(stripslashes($_POST['category_order']), true);

        if (!is_array($category_order)) {
            wp_send_json_error(__('Invalid order data', 'goalv'));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'goalv_vote_categories';

        foreach ($category_order as $index => $category_id) {
            $wpdb->update(
                $table_name,
                array('display_order' => $index + 1),
                array('id' => intval($category_id)),
                array('%d'),
                array('%d')
            );
        }

        wp_send_json_success(__('Categories reordered successfully', 'goalv'));
    }


    /**
     * UPDATED: Process custom options data with database category validation
     */
    private function process_custom_options_data($match_id, $options_data)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'goalv_vote_options';

        // Get valid categories from database
        $voting = new GoalV_Voting();
        $categories = $voting->get_available_categories();
        $valid_categories = array();
        foreach ($categories as $cat) {
            $valid_categories[] = $cat->category_key;
        }

        foreach ($options_data as $option_data) {
            $text = sanitize_text_field($option_data['text']);
            $category = sanitize_text_field($option_data['category']);
            $option_id = (int) $option_data['id'];
            $order = (int) $option_data['order'];

            if (empty($text)) {
                continue;
            }

            // Validate category against database
            if (!in_array($category, $valid_categories)) {
                $category = 'other';
            }

            if ($option_id > 0) {
                // Update existing option
                $wpdb->update(
                    $table_name,
                    array(
                        'option_text' => $text,
                        'category' => $category,
                        'display_order' => $order
                    ),
                    array('id' => $option_id, 'match_id' => $match_id),
                    array('%s', '%s', '%d'),
                    array('%d', '%d')
                );
            } else {
                // Create new option
                $wpdb->insert(
                    $table_name,
                    array(
                        'match_id' => $match_id,
                        'option_text' => $text,
                        'option_type' => 'detailed',
                        'category' => $category,
                        'display_order' => $order,
                        'is_custom' => 1,
                        'created_by' => get_current_user_id()
                    ),
                    array('%d', '%s', '%s', '%s', '%d', '%d', '%d')
                );
            }
        }
    }
}