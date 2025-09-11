<?php
/**
 * Voting System Handler - FIXED VERSION
 */

if (!defined('ABSPATH')) {
    exit;
}

class GoalV_Voting
{

    public function __construct()
    {
        add_action('wp_ajax_goalv_cast_vote', array($this, 'handle_vote'));
        add_action('wp_ajax_nopriv_goalv_cast_vote', array($this, 'handle_vote'));
        add_action('wp_ajax_goalv_get_vote_results', array($this, 'get_vote_results'));
        add_action('wp_ajax_nopriv_goalv_get_vote_results', array($this, 'get_vote_results'));
    }

    /**
     * Handle vote submission - UPDATED WITH MULTIPLE VOTES SUPPORT
     */
    public function handle_vote()
    {
        check_ajax_referer('goalv_vote_nonce', 'nonce');

        $match_id = intval($_POST['match_id']);
        $option_id = intval($_POST['option_id']);
        $vote_location = sanitize_text_field($_POST['vote_location']);

        // Handle 'table' location as 'homepage' for data sync
        if ($vote_location === 'table') {
            $vote_location = 'homepage';
        }

        if (!$match_id || !$option_id || !in_array($vote_location, array('homepage', 'details'))) {
            wp_send_json_error(__('Invalid vote data', 'goalv'));
        }

        // Check if match exists
        $match = get_post($match_id);
        if (!$match || $match->post_type !== 'goalv_matches') {
            wp_send_json_error(__('Match not found', 'goalv'));
        }

        // Check if voting is allowed for this location
        if ($vote_location === 'details' && !is_user_logged_in()) {
            wp_send_json_error(__('Login required for detailed voting', 'goalv'));
        }

        // Check if option exists and belongs to match
        $option = $this->get_vote_option($option_id, $match_id);
        if (!$option) {
            wp_send_json_error(__('Invalid voting option', 'goalv'));
        }

        // NEW: Get the category of the option being voted on
        $option_category = $option->category;

        // NEW: Check if user already voted in THIS CATEGORY
        $existing_category_vote = $this->get_existing_vote_in_category($match_id, $option_category, $vote_location);

        $result = false;
        $action = '';

        if ($existing_category_vote) {
            // User has already voted in this category

            if ($existing_category_vote->option_id == $option_id) {
                // User is clicking the same option they already voted for - remove vote (toggle off)
                $result = $this->remove_vote($existing_category_vote->id, $option_id);
                $action = 'removed';
            } else {
                // User is changing their vote within the same category - update vote
                $result = $this->update_vote($existing_category_vote->id, $option_id, $existing_category_vote->option_id);
                $action = 'changed';
            }
        } else {
            // User hasn't voted in this category yet - cast new vote
            $result = $this->cast_new_vote($match_id, $option_id, $vote_location);
            $action = 'added';
        }

        if ($result) {
            // Clear vote cache
            $this->clear_vote_cache($match_id);

            // Get updated user votes by category
            $user_votes_by_category = $this->get_user_votes_by_category($match_id, $vote_location);

            // Get updated results
            $vote_results = $this->calculate_vote_percentages($match_id, $vote_location);

            $message = '';
            switch ($action) {
                case 'added':
                    $message = __('Vote recorded successfully', 'goalv');
                    break;
                case 'changed':
                    $message = __('Vote updated successfully', 'goalv');
                    break;
                case 'removed':
                    $message = __('Vote removed successfully', 'goalv');
                    break;
            }

            wp_send_json_success(array(
                'message' => $message,
                'action' => $action,
                'category' => $option_category,
                'results' => $vote_results,
                'user_votes_by_category' => $user_votes_by_category,
                'one_vote_per_category' => true
            ));
        } else {
            wp_send_json_error(__('Failed to process vote', 'goalv'));
        }
    }

    /**
     * Get existing vote in a specific category for one-vote-per-category system
     * @param int $match_id - Match identifier
     * @param string $category - Vote option category 
     * @param string $vote_location - Voting context
     * @return object|null - Existing vote record or null
     */
    private function get_existing_vote_in_category($match_id, $category, $vote_location)
    {
        global $wpdb;

        $votes_table = $wpdb->prefix . 'goalv_votes';
        $options_table = $wpdb->prefix . 'goalv_vote_options';

        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            return $wpdb->get_row($wpdb->prepare(
                "SELECT v.* 
             FROM $votes_table v
             JOIN $options_table vo ON v.option_id = vo.id  
             WHERE v.match_id = %d AND vo.category = %s AND v.user_id = %d AND v.vote_location = %s",
                $match_id,
                $category,
                $user_id,
                $vote_location
            ));
        } else {
            $browser_id = $this->get_browser_id();
            $user_ip = $this->get_user_ip();
            return $wpdb->get_row($wpdb->prepare(
                "SELECT v.* 
             FROM $votes_table v
             JOIN $options_table vo ON v.option_id = vo.id
             WHERE v.match_id = %d AND vo.category = %s AND v.browser_id = %s AND v.user_ip = %s AND v.vote_location = %s AND v.user_id IS NULL",
                $match_id,
                $category,
                $browser_id,
                $user_ip,
                $vote_location
            ));
        }
    }

    /**
     * Get existing vote for specific option (for multiple votes mode)
     */
    private function get_existing_option_vote($match_id, $option_id, $vote_location)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'goalv_votes';

        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            return $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name WHERE match_id = %d AND option_id = %d AND user_id = %d AND vote_location = %s",
                $match_id,
                $option_id,
                $user_id,
                $vote_location
            ));
        } else {
            $browser_id = $this->get_browser_id();
            $user_ip = $this->get_user_ip();
            return $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name WHERE match_id = %d AND option_id = %d AND browser_id = %s AND user_ip = %s AND vote_location = %s AND user_id IS NULL",
                $match_id,
                $option_id,
                $browser_id,
                $user_ip,
                $vote_location
            ));
        }
    }

    /**
     * Remove a vote (for toggle functionality)
     */
    private function remove_vote($vote_id, $option_id)
    {
        global $wpdb;

        $votes_table = $wpdb->prefix . 'goalv_votes';
        $options_table = $wpdb->prefix . 'goalv_vote_options';

        // Remove vote
        $result = $wpdb->delete($votes_table, array('id' => $vote_id));

        if ($result) {
            // Decrement vote count
            $wpdb->query($wpdb->prepare(
                "UPDATE $options_table SET votes_count = votes_count - 1 WHERE id = %d AND votes_count > 0",
                $option_id
            ));
        }

        return $result;
    }

    /**
     * Get user's current votes for a match (modified for multiple votes)
     */
    public function get_user_votes($match_id, $vote_location)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'goalv_votes';

        if ($vote_location === 'table') {
            $vote_location = 'homepage';
        }

        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $votes = $wpdb->get_results($wpdb->prepare(
                "SELECT option_id FROM $table_name WHERE match_id = %d AND user_id = %d AND vote_location = %s",
                $match_id,
                $user_id,
                $vote_location
            ));
        } else {
            $browser_id = $this->get_browser_id();
            $user_ip = $this->get_user_ip();
            $votes = $wpdb->get_results($wpdb->prepare(
                "SELECT option_id FROM $table_name WHERE match_id = %d AND browser_id = %s AND user_ip = %s AND vote_location = %s AND user_id IS NULL",
                $match_id,
                $browser_id,
                $user_ip,
                $vote_location
            ));
        }

        return array_column($votes, 'option_id');
    }

    /**
     * Get vote results
     */
    public function get_vote_results()
    {
        $match_id = intval($_GET['match_id']);
        $vote_location = sanitize_text_field($_GET['vote_location']);

        // FIX 4: Handle 'table' location as 'homepage'
        if ($vote_location === 'table') {
            $vote_location = 'homepage';
        }

        if (!$match_id || !in_array($vote_location, array('homepage', 'details'))) {
            wp_send_json_error(__('Invalid request', 'goalv'));
        }

        $results = $this->calculate_vote_percentages($match_id, $vote_location);
        wp_send_json_success($results);
    }

    /**
     * Get vote option
     */
    private function get_vote_option($option_id, $match_id)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'goalv_vote_options';
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d AND match_id = %d",
            $option_id,
            $match_id
        ));
    }

    /**
     * Get existing vote - MODIFIED for better guest user handling
     */
    private function get_existing_vote($match_id, $vote_location)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'goalv_votes';

        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            return $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name WHERE match_id = %d AND user_id = %d AND vote_location = %s",
                $match_id,
                $user_id,
                $vote_location
            ));
        } else {
            // FIX 5: Better guest user tracking - use unique session per match
            $browser_id = $this->get_browser_id();
            $user_ip = $this->get_user_ip();

            // For guest users, check by browser_id and match_id combination
            // This allows guests to vote on multiple matches
            return $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name WHERE match_id = %d AND browser_id = %s AND user_ip = %s AND vote_location = %s AND user_id IS NULL",
                $match_id,
                $browser_id,
                $user_ip,
                $vote_location
            ));
        }
    }

    /**
     * Check if vote changes are allowed
     */
    private function can_change_vote($vote_location)
    {
        $general_setting = get_option('goalv_allow_vote_change', 'yes');
        if ($general_setting !== 'yes') {
            return false;
        }

        if ($vote_location === 'homepage') {
            return get_option('goalv_allow_homepage_vote_change', 'yes') === 'yes';
        } else {
            return get_option('goalv_allow_details_vote_change', 'yes') === 'yes';
        }
    }

    /**
     * Cast new vote
     */
    private function cast_new_vote($match_id, $option_id, $vote_location)
    {
        global $wpdb;

        $vote_data = array(
            'match_id' => $match_id,
            'option_id' => $option_id,
            'vote_location' => $vote_location,
            'user_ip' => $this->get_user_ip(),
            'browser_id' => $this->get_browser_id()
        );

        if (is_user_logged_in()) {
            $vote_data['user_id'] = get_current_user_id();
        } else {
            $vote_data['user_id'] = null; // Explicitly set null for guest users
        }

        $votes_table = $wpdb->prefix . 'goalv_votes';
        $options_table = $wpdb->prefix . 'goalv_vote_options';

        // Insert vote
        $result = $wpdb->insert($votes_table, $vote_data);

        if ($result) {
            // Increment vote count
            $wpdb->query($wpdb->prepare(
                "UPDATE $options_table SET votes_count = votes_count + 1 WHERE id = %d",
                $option_id
            ));
        }

        return $result;
    }

    /**
     * Update existing vote
     */
    private function update_vote($vote_id, $new_option_id, $old_option_id)
    {
        global $wpdb;

        $votes_table = $wpdb->prefix . 'goalv_votes';
        $options_table = $wpdb->prefix . 'goalv_vote_options';

        // Update vote
        $result = $wpdb->update(
            $votes_table,
            array('option_id' => $new_option_id),
            array('id' => $vote_id)
        );

        if ($result !== false) {
            // Decrement old option count
            $wpdb->query($wpdb->prepare(
                "UPDATE $options_table SET votes_count = votes_count - 1 WHERE id = %d AND votes_count > 0",
                $old_option_id
            ));

            // Increment new option count
            $wpdb->query($wpdb->prepare(
                "UPDATE $options_table SET votes_count = votes_count + 1 WHERE id = %d",
                $new_option_id
            ));
        }

        return $result !== false;
    }

    /**
     * Calculate vote percentages
     */


    private function calculate_vote_percentages($match_id, $vote_location)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'goalv_vote_options';
        $option_type = ($vote_location === 'homepage') ? 'basic' : 'detailed';

        // Get all options for this match and type (including custom options)
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
            $results[] = array(
                'option_id' => $option->id,
                'option_text' => $option->option_text,
                'votes_count' => $option->votes_count,
                'percentage' => $percentage,
                'is_custom' => (bool) $option->is_custom,
                'display_order' => $option->display_order
            );
        }

        return $results;
    }

    /**
     * Get vote option details - NEW METHOD
     */
    public function get_vote_option_details($option_id)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'goalv_vote_options';
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $option_id
        ));
    }

    /**
     * Get user's current vote for a match - MODIFIED
     */
    public function get_user_vote($match_id, $vote_location)
    {
        // Handle table location as homepage
        if ($vote_location === 'table') {
            $vote_location = 'homepage';
        }

        $existing_vote = $this->get_existing_vote($match_id, $vote_location);
        return $existing_vote ? $existing_vote->option_id : null;
    }

    /**
     * Get user's votes organized by category for one-vote-per-category system
     * @param int $match_id - Match identifier  
     * @param string $vote_location - Voting location (homepage/details)
     * @return array - Array with category as key, option_id as value
     */
    public function get_user_votes_by_category($match_id, $vote_location)
    {
        global $wpdb;

        $votes_table = $wpdb->prefix . 'goalv_votes';
        $options_table = $wpdb->prefix . 'goalv_vote_options';

        if ($vote_location === 'table') {
            $vote_location = 'homepage';
        }

        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $results = $wpdb->get_results($wpdb->prepare(
                "SELECT v.option_id, vo.category 
             FROM $votes_table v
             JOIN $options_table vo ON v.option_id = vo.id
             WHERE v.match_id = %d AND v.user_id = %d AND v.vote_location = %s",
                $match_id,
                $user_id,
                $vote_location
            ));
        } else {
            $browser_id = $this->get_browser_id();
            $user_ip = $this->get_user_ip();
            $results = $wpdb->get_results($wpdb->prepare(
                "SELECT v.option_id, vo.category 
             FROM $votes_table v
             JOIN $options_table vo ON v.option_id = vo.id
             WHERE v.match_id = %d AND v.browser_id = %s AND v.user_ip = %s AND v.vote_location = %s AND v.user_id IS NULL",
                $match_id,
                $browser_id,
                $user_ip,
                $vote_location
            ));
        }

        // Convert to category => option_id array
        $votes_by_category = array();
        foreach ($results as $result) {
            $votes_by_category[$result->category] = intval($result->option_id);
        }

        return $votes_by_category;
    }

    /**
     * Get custom options count for a match - NEW METHOD
     */
    public function get_custom_options_count($match_id, $option_type = null)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'goalv_vote_options';

        if ($option_type) {
            return $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name 
             WHERE match_id = %d AND option_type = %s AND is_custom = 1",
                $match_id,
                $option_type
            ));
        } else {
            return $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name 
             WHERE match_id = %d AND is_custom = 1",
                $match_id
            ));
        }
    }

    /**
     * Get voting statistics for admin - NEW METHOD
     */
    public function get_voting_statistics($match_id)
    {
        global $wpdb;

        $options_table = $wpdb->prefix . 'goalv_vote_options';
        $votes_table = $wpdb->prefix . 'goalv_votes';

        // Get option counts
        $total_options = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $options_table WHERE match_id = %d",
            $match_id
        ));

        $custom_options = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $options_table WHERE match_id = %d AND is_custom = 1",
            $match_id
        ));

        $basic_options = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $options_table WHERE match_id = %d AND option_type = 'basic'",
            $match_id
        ));

        $detailed_options = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $options_table WHERE match_id = %d AND option_type = 'detailed'",
            $match_id
        ));

        // Get vote counts
        $total_votes = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $votes_table WHERE match_id = %d",
            $match_id
        ));

        $homepage_votes = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $votes_table WHERE match_id = %d AND vote_location = 'homepage'",
            $match_id
        ));

        $details_votes = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $votes_table WHERE match_id = %d AND vote_location = 'details'",
            $match_id
        ));

        $unique_voters = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT COALESCE(user_id, browser_id)) FROM $votes_table WHERE match_id = %d",
            $match_id
        ));

        return array(
            'total_options' => (int) $total_options,
            'custom_options' => (int) $custom_options,
            'basic_options' => (int) $basic_options,
            'detailed_options' => (int) $detailed_options,
            'total_votes' => (int) $total_votes,
            'homepage_votes' => (int) $homepage_votes,
            'details_votes' => (int) $details_votes,
            'unique_voters' => (int) $unique_voters
        );
    }

    /**
     * Validate custom option - NEW METHOD
     */
    public function validate_custom_option($option_text, $option_type, $match_id)
    {
        $errors = array();

        // Validate option text
        if (empty(trim($option_text))) {
            $errors[] = __('Option text cannot be empty', 'goalv');
        }

        if (strlen($option_text) > 255) {
            $errors[] = __('Option text is too long (maximum 255 characters)', 'goalv');
        }

        // Validate option type
        if (!in_array($option_type, array('basic', 'detailed'))) {
            $errors[] = __('Invalid option type', 'goalv');
        }

        // Check for duplicate options
        global $wpdb;
        $table_name = $wpdb->prefix . 'goalv_vote_options';

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name 
         WHERE match_id = %d AND option_type = %s AND option_text = %s",
            $match_id,
            $option_type,
            trim($option_text)
        ));

        if ($existing > 0) {
            $errors[] = __('An option with this text already exists', 'goalv');
        }

        return $errors;
    }

    /**
     * Delete custom option and its votes - NEW METHOD
     */
    public function delete_custom_option($option_id)
    {
        global $wpdb;

        $options_table = $wpdb->prefix . 'goalv_vote_options';
        $votes_table = $wpdb->prefix . 'goalv_votes';

        // Verify it's a custom option
        $option = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $options_table WHERE id = %d AND is_custom = 1",
            $option_id
        ));

        if (!$option) {
            return false;
        }

        // Delete all votes for this option first
        $votes_deleted = $wpdb->delete($votes_table, array('option_id' => $option_id));

        // Delete the option
        $option_deleted = $wpdb->delete($options_table, array('id' => $option_id));

        // Clear cache if successful
        if ($option_deleted) {
            $this->clear_vote_cache($option->match_id);

            // Log the deletion
            error_log("GoalV: Deleted custom option {$option_id} with {$votes_deleted} votes");

            return true;
        }

        return false;
    }


    /**
     * Get browser ID - ENHANCED
     */
    private function get_browser_id()
    {
        if (isset($_POST['browser_id']) && !empty($_POST['browser_id'])) {
            return sanitize_text_field($_POST['browser_id']);
        }

        // Fallback: generate based on user agent and IP with session
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        $ip = $this->get_user_ip();
        $session_id = session_id() ? session_id() : 'no_session';

        return substr(md5($user_agent . $ip . $session_id), 0, 32);
    }

    /**
     * Get user IP
     */
    private function get_user_ip()
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            return $_SERVER['REMOTE_ADDR'];
        }
    }

    /**
     * Get vote options grouped by category - NEW METHOD
     */
    public function get_vote_options_grouped($match_id, $option_type = 'detailed')
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'goalv_vote_options';
        $options = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name 
         WHERE match_id = %d AND option_type = %s 
         ORDER BY 
            CASE category
                WHEN 'match_result' THEN 1
                WHEN 'match_score' THEN 2
                WHEN 'goals_threshold' THEN 3
                WHEN 'both_teams_score' THEN 4
                WHEN 'first_to_score' THEN 5
                ELSE 6
            END,
            is_custom ASC, 
            display_order ASC, 
            id ASC",
            $match_id,
            $option_type
        ));

        return $this->group_options_by_category($options);
    }

    /**
     * UPDATED: Group options by category with database labels
     */
    private function group_options_by_category($options)
    {
        // Get categories from database
        $categories = $this->get_available_categories();
        $category_labels = array();
        $category_orders = array();

        foreach ($categories as $cat) {
            $category_labels[$cat->category_key] = $cat->category_label;
            $category_orders[$cat->category_key] = $cat->display_order;
        }

        $grouped = array();

        foreach ($options as $option) {
            $category = $option->category ?: 'other';

            if (!isset($grouped[$category])) {
                $grouped[$category] = array(
                    'label' => isset($category_labels[$category])
                        ? $category_labels[$category]
                        : ucfirst(str_replace('_', ' ', $category)),
                    'options' => array(),
                    'order' => isset($category_orders[$category])
                        ? $category_orders[$category]
                        : 999
                );
            }

            $grouped[$category]['options'][] = $option;
        }

        // Sort by category order
        uasort($grouped, function ($a, $b) {
            return $a['order'] - $b['order'];
        });

        return $grouped;
    }


    /**
     * UPDATED: Get category order from database
     */
    private function get_category_order($category)
    {
        $cat_obj = $this->get_category_by_key($category);
        return $cat_obj ? $cat_obj->display_order : 999;
    }



    /**
     * Get vote options for a match - UPDATED to include custom options with proper ordering
     */
    public function get_vote_options($match_id, $option_type = 'basic')
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'goalv_vote_options';
        return $wpdb->get_results($wpdb->prepare(
            "SELECT *, COALESCE(category, 'other') as category FROM $table_name 
         WHERE match_id = %d AND option_type = %s 
         ORDER BY 
            CASE COALESCE(category, 'other')
                WHEN 'match_result' THEN 1
                WHEN 'match_score' THEN 2
                WHEN 'goals_threshold' THEN 3
                WHEN 'both_teams_score' THEN 4
                WHEN 'first_to_score' THEN 5
                ELSE 6
            END,
            is_custom ASC, 
            display_order ASC, 
            id ASC",
            $match_id,
            $option_type
        ));
    }

    /**
     * UPDATED: Get default category for option text with database fallback
     */
    public function get_default_category($option_text)
    {
        $option_text = strtolower($option_text);

        // Pattern matching logic (same as before)
        if (
            preg_match('/\b(win|wins|victory)\b/', $option_text) ||
            in_array($option_text, ['draw', 'tie'])
        ) {
            return 'match_result';
        }

        if (strpos($option_text, 'over') !== false || strpos($option_text, 'under') !== false) {
            return 'goals_threshold';
        }

        if (strpos($option_text, 'both teams score') !== false) {
            return 'both_teams_score';
        }

        if (preg_match('/\d+-\d+/', $option_text)) {
            return 'match_score';
        }

        if (strpos($option_text, 'scores first') !== false || strpos($option_text, 'first to score') !== false) {
            return 'first_to_score';
        }

        return 'other';
    }

    /**
     * Get all vote options for a match (both basic and detailed) - NEW METHOD
     */
    public function get_all_vote_options($match_id)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'goalv_vote_options';
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name 
         WHERE match_id = %d 
         ORDER BY 
            option_type ASC, 
            is_custom ASC, 
            display_order ASC, 
            id ASC",
            $match_id
        ));
    }

    public function get_vote_results_cached($match_id, $vote_location)
    {
        $cache_key = "goalv_vote_results_{$match_id}_{$vote_location}";
        $cached_results = get_transient($cache_key);

        if ($cached_results !== false) {
            return $cached_results;
        }

        $results = $this->calculate_vote_percentages($match_id, $vote_location);
        set_transient($cache_key, $results, 300); // Cache for 5 minutes

        return $results;
    }


    /**
     * Get all active categories from database
     * @return array Array of category objects
     */
    public function get_available_categories()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'goalv_vote_categories';

        $categories = $wpdb->get_results(
            "SELECT * FROM $table_name 
         WHERE is_active = 1 
         ORDER BY display_order ASC, category_label ASC"
        );

        return $categories ? $categories : array();
    }

    /**
     * Get category details by key
     * @param string $category_key Category identifier
     * @return object|null Category object or null
     */
    public function get_category_by_key($category_key)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'goalv_vote_categories';

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE category_key = %s AND is_active = 1",
            $category_key
        ));
    }

    /**
     * Create new category
     * @param string $category_key Unique key for category
     * @param string $category_label Display label
     * @param int $display_order Display order
     * @return int|false Category ID on success, false on failure
     */
    public function create_category($category_key, $category_label, $display_order = null)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'goalv_vote_categories';

        // Check if key already exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE category_key = %s",
            $category_key
        ));

        if ($existing > 0) {
            return false;
        }

        // Get next display order if not provided
        if ($display_order === null) {
            $max_order = $wpdb->get_var("SELECT MAX(display_order) FROM $table_name");
            $display_order = $max_order ? ($max_order + 1) : 1;
        }

        $result = $wpdb->insert(
            $table_name,
            array(
                'category_key' => $category_key,
                'category_label' => $category_label,
                'display_order' => $display_order,
                'is_active' => 1
            ),
            array('%s', '%s', '%d', '%d')
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Update existing category
     * @param int $category_id Category ID to update
     * @param string $category_label New label
     * @param int $display_order New display order
     * @return bool Success status
     */
    public function update_category($category_id, $category_label, $display_order)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'goalv_vote_categories';

        return $wpdb->update(
            $table_name,
            array(
                'category_label' => $category_label,
                'display_order' => $display_order
            ),
            array('id' => $category_id),
            array('%s', '%d'),
            array('%d')
        );
    }

    /**
     * Delete category (mark as inactive)
     * @param int $category_id Category ID to delete
     * @return bool Success status
     */
    public function delete_category($category_id)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'goalv_vote_categories';

        // Don't allow deletion of 'other' category
        $category = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $category_id
        ));

        if (!$category || $category->category_key === 'other') {
            return false;
        }

        // Move options in this category to 'other'
        $options_table = $wpdb->prefix . 'goalv_vote_options';
        $wpdb->update(
            $options_table,
            array('category' => 'other'),
            array('category' => $category->category_key),
            array('%s'),
            array('%s')
        );

        // Mark category as inactive
        return $wpdb->update(
            $table_name,
            array('is_active' => 0),
            array('id' => $category_id),
            array('%d'),
            array('%d')
        );
    }


    // Clear cache when votes are cast:
    public function clear_vote_cache($match_id)
    {
        delete_transient("goalv_vote_results_{$match_id}_homepage");
        delete_transient("goalv_vote_results_{$match_id}_details");
    }
}