<?php
/**
 * Frontend Display Handler - FIXED VERSION
 */

if (!defined('ABSPATH')) {
    exit;
}

class GoalV_Frontend
{

    public function __construct()
    {
        add_shortcode('goalv_matches', array($this, 'matches_shortcode'));
        add_action('wp_footer', array($this, 'add_browser_id_script'));
        // FIX: Ensure assets are enqueued when shortcode is used
        add_action('init', array($this, 'check_shortcode_usage'));

        add_action('wp_ajax_goalv_load_more_matches', array($this, 'handle_load_more_matches'));
        add_action('wp_ajax_nopriv_goalv_load_more_matches', array($this, 'handle_load_more_matches'));

    }

    /**
     * Check if shortcode is being used and enqueue assets
     */
    public function check_shortcode_usage()
    {
        global $post;

        if (is_admin()) {
            return;
        }

        // Check if we're on a page/post that might contain the shortcode
        if (
            has_shortcode(get_the_content(), 'goalv_matches') ||
            (is_singular() && $post && strpos($post->post_content, '[goalv_matches') !== false) ||
            is_front_page() || is_home()
        ) {

            add_action('wp_enqueue_scripts', array($this, 'force_enqueue_assets'));
        }
    }

    /**
     * Force enqueue assets when shortcode is detected - UPDATED for separated JS
     */
    public function force_enqueue_assets()
    {
        if (!wp_script_is('goalv-frontend', 'enqueued')) {
            // Enqueue CSS
            wp_enqueue_style(
                'goalv-style',
                GOALV_PLUGIN_URL . 'assets/css/goalv-style.css',
                array(),
                GOALV_VERSION
            );

            // Enqueue frontend JavaScript only
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
                'is_user_logged_in' => is_user_logged_in(),
                'allow_multiple_votes' => get_option('goalv_allow_multiple_votes', 'no') === 'yes'
            ));
        }
    }

    /**
     * Enhanced matches shortcode - UPDATE EXISTING METHOD
     */
    public function matches_shortcode($atts)
    {
        $atts = shortcode_atts(array(
            'template' => 'card',
            'limit' => 10,
            'weeks' => '', // Allow manual week override
            'show_week_headers' => '', // Allow header override
            'show_custom_options' => 'yes', // NEW: Allow hiding custom options

            'teams_label' => get_option('goalv_labels_teams', __('Teams', 'goalv')),
            'score_label' => get_option('goalv_labels_score', __('Score', 'goalv')),
            'status_label' => get_option('goalv_labels_status', __('Status', 'goalv')),
            'date_label' => get_option('goalv_labels_date', __('Date', 'goalv')),
            'predictions_label' => get_option('goalv_labels_predictions', __('Predictions', 'goalv')),
            'details_label' => get_option('goalv_labels_details', __('Details', 'goalv'))
        ), $atts, 'goalv_matches');

        // Ensure assets are loaded
        $this->force_enqueue_assets();

        // Check for manual week override
        if (!empty($atts['weeks'])) {
            $manual_weeks = array_map('trim', explode(',', $atts['weeks']));
            $matches = $this->get_matches_by_manual_weeks($manual_weeks, $atts['limit']);

            // Add week headers if requested
            if ($atts['show_week_headers'] === 'true') {
                foreach ($matches as &$match) {
                    $week_synced = get_post_meta($match->ID, 'goalv_week_synced', true);
                    $match->display_week = $week_synced;
                }
            }
        } else {
            $matches = $this->get_current_week_matches($atts['limit']);
        }

        // Enhanced: Better "no matches" messaging based on admin selection
        if (empty($matches)) {
            $admin = new GoalV_Admin();
            $weeks_info = $admin->get_homepage_weeks_info();

            $no_matches_message = '';

            if ($weeks_info['mode'] === 'current') {
                $no_matches_message = __('No matches available for the current week.', 'goalv');
            } elseif ($weeks_info['mode'] === 'custom') {
                $selected_weeks = array_map(function ($week) {
                    return "GW{$week}";
                }, $weeks_info['weeks']);
                $weeks_list = implode(', ', $selected_weeks);
                $no_matches_message = sprintf(
                    __('No matches available for selected weeks: %s', 'goalv'),
                    $weeks_list
                );
            } elseif ($weeks_info['mode'] === 'range') {
                $start_week = min($weeks_info['weeks']);
                $end_week = max($weeks_info['weeks']);
                $no_matches_message = sprintf(
                    __('No matches available for weeks GW%d to GW%d', 'goalv'),
                    $start_week,
                    $end_week
                );
            } else {
                $no_matches_message = __('No matches available for the selected period.', 'goalv');
            }

            return '<div class="goalv-no-matches"><p>' . $no_matches_message . '</p></div>';
        }

        // ENHANCED: Add custom options count to each match for template use
        $voting = new GoalV_Voting();
        foreach ($matches as &$match) {
            // Add vote results for homepage (basic options + custom basic options)
            $match->vote_results = $this->get_vote_percentages($match->ID, 'homepage');

            // Add custom options count for display
            $match->custom_options_count = $voting->get_custom_options_count($match->ID, 'basic');
            $match->custom_detailed_count = $voting->get_custom_options_count($match->ID, 'detailed');

            // Add user votes for display
            if (get_option('goalv_allow_multiple_votes', 'no') === 'yes') {
                $match->user_votes = $voting->get_user_votes($match->ID, 'homepage');
            } else {
                $match->user_vote = $voting->get_user_vote($match->ID, 'homepage');
            }
        }

        ob_start();

        // Check if we should show week headers
        $show_headers = false;
        if (
            $atts['show_week_headers'] === 'true' ||
            (!empty($matches[0]->display_week) && get_option('goalv_show_week_headers', 'yes') === 'yes')
        ) {
            $show_headers = true;
        }

        $template_args = array(
            'matches' => $matches,
            'show_week_headers' => $show_headers,
            'show_custom_options' => $atts['show_custom_options'] === 'yes',
            'multiple_votes_enabled' => get_option('goalv_allow_multiple_votes', 'no') === 'yes',
            'labels' => array(
                'teams' => $atts['teams_label'],
                'score' => $atts['score_label'],
                'status' => $atts['status_label'],
                'date' => $atts['date_label'],
                'predictions' => $atts['predictions_label'],
                'details' => $atts['details_label']
            )
        );

        if ($atts['template'] === 'grid') {
            $this->load_template('matches-grid', $template_args);
        } elseif ($atts['template'] === 'table') {
            $this->load_template('matches-table', $template_args);
        } else {
            $this->load_template('matches-card', $template_args);
        }

        return ob_get_clean();
    }

    /**
     * NEW: Get voting options with custom support for templates
     */
    public function get_match_voting_options($match_id, $vote_location = 'homepage')
    {
        $voting = new GoalV_Voting();
        $option_type = ($vote_location === 'homepage') ? 'basic' : 'detailed';

        $options = $voting->get_vote_options($match_id, $option_type);
        $results = $this->get_vote_percentages($match_id, $vote_location);

        // Combine options with current results
        $combined_options = array();
        foreach ($options as $option) {
            $option_data = array(
                'id' => $option->id,
                'text' => $option->option_text,
                'is_custom' => (bool) $option->is_custom,
                'display_order' => $option->display_order,
                'votes_count' => $option->votes_count,
                'percentage' => isset($results[$option->id]) ? $results[$option->id]['percentage'] : 0
            );
            $combined_options[] = $option_data;
        }

        return $combined_options;
    }

    /**
     * NEW: Check if match has custom options
     */
    public function match_has_custom_options($match_id, $option_type = null)
    {
        $voting = new GoalV_Voting();
        return $voting->get_custom_options_count($match_id, $option_type) > 0;
    }

    /**
     * NEW: Get custom options indicator text for UI
     */
    public function get_custom_options_indicator($match_id)
    {
        $voting = new GoalV_Voting();
        $basic_count = $voting->get_custom_options_count($match_id, 'basic');
        $detailed_count = $voting->get_custom_options_count($match_id, 'detailed');

        $indicators = array();

        if ($basic_count > 0) {
            $indicators[] = sprintf(_n('%d custom option', '%d custom options', $basic_count, 'goalv'), $basic_count);
        }

        if ($detailed_count > 0) {
            $indicators[] = sprintf(_n('%d detailed option', '%d detailed options', $detailed_count, 'goalv'), $detailed_count);
        }

        return implode(' + ', $indicators);
    }


    /**
     * Get matches by manual week selection (shortcode override)
     */
    private function get_matches_by_manual_weeks($weeks, $limit)
    {
        $matches = array();

        foreach ($weeks as $week) {
            // Handle both "5" and "GW5" formats
            $week_num = str_replace('GW', '', trim($week));
            if (is_numeric($week_num)) {
                $week_matches = $this->get_matches_by_week((int) $week_num);

                // Add week info to matches
                foreach ($week_matches as &$match) {
                    $match->display_week = "GW{$week_num}";
                }

                $matches = array_merge($matches, $week_matches);
            }
        }

        // Sort by date
        usort($matches, function ($a, $b) {
            return strtotime($a->match_date) - strtotime($b->match_date);
        });

        // Apply limit
        if ($limit > 0 && count($matches) > $limit) {
            $matches = array_slice($matches, 0, $limit);
        }

        return $matches;
    }

    /**
     * Get matches for homepage display based on admin settings
     */
    private function get_current_week_matches($limit)
    {
        // Get admin settings for homepage weeks
        $admin = new GoalV_Admin();
        $weeks_info = $admin->get_homepage_weeks_info();

        $matches = array();
        $weeks_with_matches = array(); // Track which weeks actually have matches

        // Get matches for each selected week
        foreach ($weeks_info['weeks'] as $week_num) {
            $week_matches = $this->get_matches_by_week($week_num, -1); // No limit per week

            if (!empty($week_matches)) {
                $weeks_with_matches[] = $week_num; // Track successful weeks

                // Add week information to matches if headers are enabled
                if ($weeks_info['show_headers'] && count($weeks_info['weeks']) > 1) {
                    foreach ($week_matches as &$match) {
                        $match->display_week = "GW{$week_num}";
                    }
                }

                $matches = array_merge($matches, $week_matches);
            }
        }

        // FIXED: Only use fallback if NO weeks had matches AND fallback is enabled
        // Previously: if (empty($matches) && $weeks_info['fallback_enabled'])
        // Now: More intelligent fallback logic
        if (empty($matches) && $weeks_info['fallback_enabled']) {
            // Only use fallback if we're in 'current' mode OR if truly no matches found
            if ($weeks_info['mode'] === 'current' || count($weeks_with_matches) === 0) {
                $matches = $this->get_fallback_matches($limit);

                // Mark fallback matches
                foreach ($matches as &$match) {
                    $match->is_fallback = true;
                }
            }
        }

        // If we're showing specific weeks and they're empty, show an informative message instead
        if (empty($matches) && in_array($weeks_info['mode'], ['custom', 'range'])) {
            // Don't use fallback for intentionally selected empty weeks
            // Let the shortcode method handle the "no matches" message
            return array();
        }

        // Sort all matches by date
        if (!empty($matches)) {
            usort($matches, function ($a, $b) {
                return strtotime($a->match_date) - strtotime($b->match_date);
            });

            // Apply limit
            if ($limit > 0 && count($matches) > $limit) {
                $matches = array_slice($matches, 0, $limit);
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
        }

        return $matches;
    }


    /**
     * Get matches for a specific week
     */
    private function get_matches_by_week($week_num, $limit = -1)
    {
        $args = array(
            'post_type' => 'goalv_matches',
            'posts_per_page' => $limit,
            'post_status' => 'publish',
            'meta_query' => array(
                array(
                    'key' => 'goalv_week_synced',
                    'value' => "GW{$week_num}",
                    'compare' => '='
                )
            ),
            'meta_key' => 'goalv_match_date',
            'orderby' => 'meta_value',
            'order' => 'ASC'
        );

        return get_posts($args);
    }

    /**
     * Get fallback matches when selected weeks are empty
     */
    private function get_fallback_matches($limit)
    {
        // Get upcoming matches regardless of week
        $today = date('Y-m-d H:i:s');
        $next_week = date('Y-m-d H:i:s', strtotime('+14 days'));

        $args = array(
            'post_type' => 'goalv_matches',
            'posts_per_page' => $limit,
            'post_status' => 'publish',
            'meta_query' => array(
                array(
                    'key' => 'goalv_match_date',
                    'value' => array($today, $next_week),
                    'compare' => 'BETWEEN',
                    'type' => 'DATETIME'
                )
            ),
            'meta_key' => 'goalv_match_date',
            'orderby' => 'meta_value',
            'order' => 'ASC'
        );

        $matches = get_posts($args);

        // Mark these as fallback matches
        foreach ($matches as &$match) {
            $match->is_fallback = true;
        }

        return $matches;
    }


    public function handle_load_more_matches()
    {
        check_ajax_referer('goalv_vote_nonce', 'nonce');

        $page = intval($_POST['page']);
        $per_page = intval($_POST['per_page']);

        if ($page < 1)
            $page = 1;
        if ($per_page < 1 || $per_page > 50)
            $per_page = 10;

        $matches = $this->get_paginated_matches($page, $per_page);

        if (empty($matches)) {
            wp_send_json_success(array(
                'matches' => '',
                'has_more' => false
            ));
        }

        // Generate HTML for new matches
        ob_start();
        foreach ($matches as $match) {
            $this->render_single_match_row($match);
        }
        $matches_html = ob_get_clean();

        wp_send_json_success(array(
            'matches' => $matches_html,
            'has_more' => count($matches) === $per_page
        ));
    }

    /**
     * Get paginated matches
     */
    private function get_paginated_matches($page = 1, $per_page = 10)
    {
        $current_week = date('Y-W');
        $offset = ($page - 1) * $per_page;

        $args = array(
            'post_type' => 'goalv_matches',
            'posts_per_page' => $per_page,
            'offset' => $offset,
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => 'goalv_week_synced',
                    'value' => $current_week,
                    'compare' => '='
                ),
                array(
                    'key' => 'goalv_week_synced',
                    'compare' => 'NOT EXISTS'
                )
            ),
            'meta_key' => 'goalv_match_date',
            'orderby' => 'meta_value',
            'order' => 'ASC'
        );

        $matches = get_posts($args);

        // Enhance matches with data
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

            $voting = new GoalV_Voting();
            $match->vote_options = $voting->get_vote_options($match->ID, 'basic');
            $match->user_vote = $voting->get_user_vote($match->ID, 'homepage');
            $match->vote_results = $this->get_vote_percentages($match->ID, 'homepage');
        }

        return $matches;
    }

    /**
     * Render single match row for AJAX loading
     */
    private function render_single_match_row($match)
    {
        ?>
        <div class="goalv-table-row goalv-match-row goalv-match-card" data-match-id="<?php echo esc_attr($match->ID); ?>">
            <!-- Teams Column -->
            <div class="goalv-col-teams">
                <div class="goalv-teams-stacked">
                    <div class="goalv-team-stacked goalv-home-stacked">
                        <img src="<?php echo esc_url($this->get_team_logo($match->home_team_logo, $match->home_team)); ?>"
                            alt="<?php echo esc_attr($match->home_team); ?>" class="goalv-team-logo-tiny"
                            onerror="this.src='<?php echo esc_url(GOALV_PLUGIN_URL . 'assets/images/default-team-logo.png'); ?>'">
                        <span class="goalv-team-name-stacked"><?php echo esc_html($match->home_team); ?></span>
                    </div>
                    <div class="goalv-team-vs">VS</div>
                    <div class="goalv-team-stacked goalv-away-stacked">
                        <img src="<?php echo esc_url($this->get_team_logo($match->away_team_logo, $match->away_team)); ?>"
                            alt="<?php echo esc_attr($match->away_team); ?>" class="goalv-team-logo-tiny"
                            onerror="this.src='<?php echo esc_url(GOALV_PLUGIN_URL . 'assets/images/default-team-logo.png'); ?>'">
                        <span class="goalv-team-name-stacked"><?php echo esc_html($match->away_team); ?></span>
                    </div>
                </div>
            </div>

            <!-- Score Column -->
            <div class="goalv-col-score">
                <?php if ($match->match_status === 'finished' || $match->match_status === 'live'): ?>
                    <span
                        class="goalv-score-display"><?php echo esc_html($match->home_score . ' - ' . $match->away_score); ?></span>
                <?php else: ?>
                    <span class="goalv-no-score">-</span>
                <?php endif; ?>
            </div>

            <!-- Status Column -->
            <div class="goalv-col-status">
                <span
                    class="goalv-status-inline"><?php echo $this->get_status_display($match->match_status, $match->home_score, $match->away_score); ?></span>
            </div>

            <!-- Date Column -->
            <div class="goalv-col-date">
                <span class="goalv-date-inline"><?php echo esc_html($this->format_match_date($match->match_date)); ?></span>
            </div>

            <!-- Predictions Column -->
            <div class="goalv-col-predictions">
                <?php if ($match->match_status !== 'finished'): ?>
                    <div class="goalv-voting-inline goalv-voting-section" data-match-id="<?php echo esc_attr($match->ID); ?>">
                        <?php foreach ($match->vote_options as $option): ?>
                            <?php
                            $is_selected = ($match->user_vote == $option->id);
                            $result = isset($match->vote_results[$option->id]) ? $match->vote_results[$option->id] : array('percentage' => 0, 'votes_count' => 0);
                            ?>
                            <button type="button"
                                class="goalv-vote-btn-inline goalv-vote-btn <?php echo $is_selected ? 'selected' : ''; ?>"
                                data-option-id="<?php echo esc_attr($option->id); ?>"
                                data-match-id="<?php echo esc_attr($match->ID); ?>" data-location="homepage">
                                <span class="goalv-option-text-inline"><?php echo esc_html($option->option_text); ?></span>
                                <span class="goalv-inline-vote-stats">
                                    <span
                                        class="goalv-percentage goalv-percentage-inline"><?php echo esc_html($result['percentage']); ?>%</span>
                                    <span class="goalv-inline-vote-count">(<?php echo esc_html($result['votes_count']); ?>)</span>
                                </span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Actions Column -->
            <div class="goalv-col-actions">
                <a href="<?php echo get_permalink($match->ID); ?>" class="goalv-details-btn-inline">Details</a>
            </div>
        </div>
        <?php
    }


    /**
     * Get vote percentages for a match
     */
    private function get_vote_percentages($match_id, $vote_location)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'goalv_vote_options';
        $option_type = ($vote_location === 'homepage') ? 'basic' : 'detailed';

        // UPDATED: Get all options including custom ones with proper ordering
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
     * Load template file
     */
    private function load_template($template_name, $args = array())
    {
        $template_path = GOALV_PLUGIN_PATH . 'templates/' . $template_name . '.php';

        if (file_exists($template_path)) {
            extract($args);
            include $template_path;
        } else {
            echo '<div class="goalv-error">' . sprintf(__('Template %s not found.', 'goalv'), $template_name) . '</div>';
        }
    }
    private function load_template_cached($template_name, $args = array())
    {
        static $template_cache = array();

        $cache_key = $template_name . '_' . md5(serialize($args));

        if (isset($template_cache[$cache_key])) {
            echo $template_cache[$cache_key];
            return;
        }

        ob_start();
        $this->load_template($template_name, $args);
        $output = ob_get_contents();
        ob_end_clean();

        $template_cache[$cache_key] = $output;
        echo $output;
    }

    /**
     * Get team logo with fallback
     */
    public function get_team_logo($logo_url, $team_name = '')
    {
        if (!empty($logo_url) && filter_var($logo_url, FILTER_VALIDATE_URL)) {
            return $logo_url;
        }

        // Return default logo
        return GOALV_PLUGIN_URL . 'assets/images/default-team-logo.png';
    }

    /**
     * Format match date for display
     */
    public function format_match_date($date_string)
    {
        if (empty($date_string)) {
            return '';
        }

        $date = new DateTime($date_string);
        $now = new DateTime();

        $diff = $now->diff($date);

        if ($date->format('Y-m-d') === $now->format('Y-m-d')) {
            return __('Today', 'goalv') . ' ' . $date->format('H:i');
        } elseif ($diff->days === 1 && $date > $now) {
            return __('Tomorrow', 'goalv') . ' ' . $date->format('H:i');
        } elseif ($diff->days === 1 && $date < $now) {
            return __('Yesterday', 'goalv') . ' ' . $date->format('H:i');
        } else {
            return $date->format('M j, H:i');
        }
    }

    /**
     * Get match status display text
     */
    public function get_status_display($status, $home_score = 0, $away_score = 0)
    {
        switch ($status) {
            case 'live':
                return '<span class="goalv-status-live">' . __('LIVE', 'goalv') . ' ' . $home_score . '-' . $away_score . '</span>';
            case 'finished':
                return '<span class="goalv-status-finished">' . __('FT', 'goalv') . ' ' . $home_score . '-' . $away_score . '</span>';
            case 'scheduled':
            default:
                return '<span class="goalv-status-scheduled">' . __('Upcoming', 'goalv') . '</span>';
        }
    }

    /**
     * Check if user can vote on this location
     */
    public function can_vote_on_location($vote_location)
    {
        if ($vote_location === 'details') {
            return is_user_logged_in();
        }

        return true; // Homepage voting allowed for everyone
    }

    /**
     * Get single match data for detail page
     */
    public function get_single_match_data($match_id)
    {
        $match = get_post($match_id);
        if (!$match || $match->post_type !== 'goalv_matches') {
            return null;
        }

        $match_data = new stdClass();
        $match_data->ID = $match->ID;
        $match_data->post_title = $match->post_title;
        $match_data->home_team = get_post_meta($match->ID, 'goalv_home_team', true);
        $match_data->away_team = get_post_meta($match->ID, 'goalv_away_team', true);
        $match_data->home_team_logo = get_post_meta($match->ID, 'goalv_home_team_logo', true);
        $match_data->away_team_logo = get_post_meta($match->ID, 'goalv_away_team_logo', true);
        $match_data->match_date = get_post_meta($match->ID, 'goalv_match_date', true);
        $match_data->match_status = get_post_meta($match->ID, 'goalv_match_status', true);
        $match_data->home_score = get_post_meta($match->ID, 'goalv_home_score', true);
        $match_data->away_score = get_post_meta($match->ID, 'goalv_away_score', true);
        $match_data->competition = get_post_meta($match->ID, 'goalv_competition', true);

        // Initialize voting class
        $voting = new GoalV_Voting();

        // UPDATED: Get detailed vote options including custom ones
        $match_data->vote_options = $voting->get_vote_options($match->ID, 'detailed');

        // NEW: Get grouped vote options for the new template structure
        $match_data->vote_options_grouped = $voting->get_vote_options_grouped($match->ID, 'detailed');

        // Support for multiple votes
        if (get_option('goalv_allow_multiple_votes', 'no') === 'yes') {
            $match_data->user_votes = $voting->get_user_votes($match->ID, 'details');
        } else {
            $match_data->user_vote = $voting->get_user_vote($match->ID, 'details');
        }

        $match_data->vote_results = $this->get_vote_percentages($match->ID, 'details');

        // Add voting statistics for admin view
        if (current_user_can('manage_options')) {
            $match_data->voting_stats = $voting->get_voting_statistics($match->ID);
        }

        return $match_data;
    }


    /**
     * Add browser ID script to footer - ENHANCED
     */
    public function add_browser_id_script()
    {
        if (!is_user_logged_in()) {
            ?>
            <script>
                // Generate browser ID for guest users
                function goalvGenerateBrowserId() {
                    var browserId = localStorage.getItem('goalv_browser_id');
                    if (!browserId) {
                        browserId = 'guest_' + Math.random().toString(36).substr(2, 9) + '_' + Date.now();
                        localStorage.setItem('goalv_browser_id', browserId);
                    }
                    return browserId;
                }

                // Make browser ID available globally
                window.goalvBrowserId = goalvGenerateBrowserId();

                // Debug info
                console.log('GoalV Browser ID:', window.goalvBrowserId);
            </script>
            <?php
        }
    }

    /**
     * Debug function to check if voting works
     */
    public function debug_voting_setup()
    {
        if (current_user_can('manage_options') && isset($_GET['goalv_debug'])) {
            $matches = $this->get_current_week_matches(5);
            echo '<pre style="background: #f1f1f1; padding: 20px; margin: 20px 0;">';
            echo 'GoalV Debug Info:' . "\n";
            echo 'Total matches: ' . count($matches) . "\n";
            echo 'User logged in: ' . (is_user_logged_in() ? 'Yes' : 'No') . "\n";
            echo 'Script enqueued: ' . (wp_script_is('goalv-admin', 'enqueued') ? 'Yes' : 'No') . "\n";

            if (!empty($matches)) {
                $first_match = $matches[0];
                echo 'First match ID: ' . $first_match->ID . "\n";
                echo 'Vote options: ' . count($first_match->vote_options) . "\n";
            }
            echo '</pre>';
        }
    }
}