<?php
/**
 * Custom Post Type Handler - FIXED VERSION
 */

if (!defined('ABSPATH')) {
    exit;
}

class GoalV_CPT
{

    public function __construct()
    {
        add_action('init', array($this, 'register_post_type'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_boxes'), 10, 2);

        // FIX: Add hooks for automatic vote option creation and week setting
        add_action('save_post', array($this, 'auto_set_week_meta'), 20, 2);
        add_action('transition_post_status', array($this, 'handle_post_publish'), 10, 3);
        add_action('wp_ajax_goalv_add_custom_option', array($this, 'ajax_add_custom_option'));
        add_action('wp_ajax_goalv_remove_custom_option', array($this, 'ajax_remove_custom_option'));
        add_action('wp_ajax_goalv_update_options_order', array($this, 'ajax_update_options_order'));


        add_filter('single_template', array($this, 'load_single_template'));
    }

    /**
     * Register custom post type
     */
    public function register_post_type()
    {
        $args = array(
            'label' => __('Football Matches', 'goalv'),
            'labels' => array(
                'name' => __('Football Matches', 'goalv'),
                'singular_name' => __('Football Match', 'goalv'),
                'add_new' => __('Add New Match', 'goalv'),
                'add_new_item' => __('Add New Football Match', 'goalv'),
                'edit_item' => __('Edit Football Match', 'goalv'),
                'new_item' => __('New Football Match', 'goalv'),
                'view_item' => __('View Football Match', 'goalv'),
                'search_items' => __('Search Football Matches', 'goalv'),
                'not_found' => __('No football matches found', 'goalv'),
                'not_found_in_trash' => __('No football matches found in trash', 'goalv'),
            ),
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'query_var' => true,
            'rewrite' => array('slug' => 'goalv-matches'),
            'capability_type' => 'post',
            'has_archive' => true,
            'hierarchical' => false,
            'menu_position' => 25,
            'menu_icon' => 'dashicons-awards',
            'supports' => array('title', 'editor', 'thumbnail'),
            'show_in_rest' => true,
        );

        register_post_type('goalv_matches', $args);
    }

    /**
     * FIX: Handle when post is published (new or updated)
     */
    public function handle_post_publish($new_status, $old_status, $post)
    {
        if ($post->post_type !== 'goalv_matches') {
            return;
        }

        // Only run for published posts
        if ($new_status !== 'publish') {
            return;
        }

        // Create vote options if they don't exist
        $this->ensure_vote_options_exist($post->ID);
    }

    /**
     * FIX: Ensure vote options exist for a match
     */
    /**
     * FIX: Ensure vote options exist for a match with proper categories
     */
    private function ensure_vote_options_exist($post_id)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'goalv_vote_options';

        // Get team names for detailed options
        $home_team = get_post_meta($post_id, 'goalv_home_team', true);
        $away_team = get_post_meta($post_id, 'goalv_away_team', true);

        // Use generic names if team names not available yet
        if (empty($home_team))
            $home_team = 'Home Team';
        if (empty($away_team))
            $away_team = 'Away Team';

        // Check if basic options already exist
        $existing_basic = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE match_id = %d AND option_type = 'basic' AND is_custom = 0",
            $post_id
        ));

        // Create default basic options if they don't exist
        if ($existing_basic == 0) {
            $basic_options = array(
                array('option_text' => 'Home Win', 'category' => 'match_result', 'display_order' => 1),
                array('option_text' => 'Draw', 'category' => 'match_result', 'display_order' => 2),
                array('option_text' => 'Away Win', 'category' => 'match_result', 'display_order' => 3)
            );

            foreach ($basic_options as $option) {
                $wpdb->insert($table, array(
                    'match_id' => $post_id,
                    'option_text' => $option['option_text'],
                    'option_type' => 'basic',
                    'category' => $option['category'], // FIXED: Added category
                    'votes_count' => 0,
                    'is_custom' => 0,
                    'display_order' => $option['display_order'], // FIXED: Use proper order
                    'created_by' => null
                ));
            }
        }

        // Check if detailed options exist
        $existing_detailed = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE match_id = %d AND option_type = 'detailed' AND is_custom = 0",
            $post_id
        ));

        // Create default detailed options if they don't exist
        if ($existing_detailed == 0) {
            $detailed_options = array(
                // Match Result
                array('option_text' => $home_team . ' Win', 'category' => 'match_result', 'display_order' => 1),
                array('option_text' => 'Draw', 'category' => 'match_result', 'display_order' => 2),
                array('option_text' => $away_team . ' Win', 'category' => 'match_result', 'display_order' => 3),

                // Goals Threshold
                array('option_text' => 'Over 2.5 Goals', 'category' => 'goals_threshold', 'display_order' => 4),
                array('option_text' => 'Under 2.5 Goals', 'category' => 'goals_threshold', 'display_order' => 5),

                // Both Teams Score
                array('option_text' => 'Both Teams Score - Yes', 'category' => 'both_teams_score', 'display_order' => 6),
                array('option_text' => 'Both Teams Score - No', 'category' => 'both_teams_score', 'display_order' => 7),

                // Score Predictions
                array('option_text' => $home_team . ' Wins 2-1', 'category' => 'match_score', 'display_order' => 8),
                array('option_text' => $away_team . ' Wins 1-2', 'category' => 'match_score', 'display_order' => 9),
                array('option_text' => 'Match Ends 1-1', 'category' => 'match_score', 'display_order' => 10),

                // First to Score
                array('option_text' => $home_team . ' Scores First', 'category' => 'first_to_score', 'display_order' => 11),
                array('option_text' => $away_team . ' Scores First', 'category' => 'first_to_score', 'display_order' => 12)
            );

            foreach ($detailed_options as $option) {
                $wpdb->insert($table, array(
                    'match_id' => $post_id,
                    'option_text' => $option['option_text'],
                    'option_type' => 'detailed',
                    'category' => $option['category'], // FIXED: Added category
                    'votes_count' => 0,
                    'is_custom' => 0,
                    'display_order' => $option['display_order'], // FIXED: Use proper order
                    'created_by' => null
                ));
            }
        }
    }

    /**
     * Add meta boxes
     */
    public function add_meta_boxes()
    {
        add_meta_box(
            'goalv_match_details',
            __('Match Details', 'goalv'),
            array($this, 'match_details_callback'),
            'goalv_matches',
            'normal',
            'high'
        );

        add_meta_box(
            'goalv_match_teams',
            __('Team Information', 'goalv'),
            array($this, 'team_details_callback'),
            'goalv_matches',
            'normal',
            'high'
        );

        // NEW: Custom voting options meta box
        add_meta_box(
            'goalv_custom_voting_options',
            __('Custom Voting Options', 'goalv'),
            array($this, 'custom_voting_options_callback'),
            'goalv_matches',
            'normal',
            'default'
        );
    }



    /**
     * Match details meta box callback
     */
    public function match_details_callback($post)
    {
        wp_nonce_field('goalv_save_meta_box_data', 'goalv_meta_box_nonce');

        $api_match_id = get_post_meta($post->ID, 'goalv_api_match_id', true);
        $match_date = get_post_meta($post->ID, 'goalv_match_date', true);
        $match_status = get_post_meta($post->ID, 'goalv_match_status', true);
        $home_score = get_post_meta($post->ID, 'goalv_home_score', true);
        $away_score = get_post_meta($post->ID, 'goalv_away_score', true);
        $competition = get_post_meta($post->ID, 'goalv_competition', true);
        $week_synced = get_post_meta($post->ID, 'goalv_week_synced', true);

        echo '<table class="form-table">';
        echo '<tr><th><label for="goalv_api_match_id">' . __('API Match ID', 'goalv') . '</label></th>';
        echo '<td><input type="text" id="goalv_api_match_id" name="goalv_api_match_id" value="' . esc_attr($api_match_id) . '" class="regular-text" /></td></tr>';

        echo '<tr><th><label for="goalv_match_date">' . __('Match Date', 'goalv') . '</label></th>';
        echo '<td><input type="datetime-local" id="goalv_match_date" name="goalv_match_date" value="' . esc_attr($match_date) . '" class="regular-text" /></td></tr>';

        echo '<tr><th><label for="goalv_match_status">' . __('Match Status', 'goalv') . '</label></th>';
        echo '<td><select id="goalv_match_status" name="goalv_match_status">';
        echo '<option value="scheduled"' . selected($match_status, 'scheduled', false) . '>' . __('Scheduled', 'goalv') . '</option>';
        echo '<option value="live"' . selected($match_status, 'live', false) . '>' . __('Live', 'goalv') . '</option>';
        echo '<option value="finished"' . selected($match_status, 'finished', false) . '>' . __('Finished', 'goalv') . '</option>';
        echo '</select></td></tr>';

        echo '<tr><th><label for="goalv_home_score">' . __('Home Score', 'goalv') . '</label></th>';
        echo '<td><input type="number" id="goalv_home_score" name="goalv_home_score" value="' . esc_attr($home_score) . '" min="0" class="small-text" /></td></tr>';

        echo '<tr><th><label for="goalv_away_score">' . __('Away Score', 'goalv') . '</label></th>';
        echo '<td><input type="number" id="goalv_away_score" name="goalv_away_score" value="' . esc_attr($away_score) . '" min="0" class="small-text" /></td></tr>';

        echo '<tr><th><label for="goalv_competition">' . __('Competition', 'goalv') . '</label></th>';
        echo '<td><input type="text" id="goalv_competition" name="goalv_competition" value="' . esc_attr($competition) . '" class="regular-text" /></td></tr>';

        echo '<tr><th><label for="goalv_week_synced">' . __('Week Synced', 'goalv') . '</label></th>';
        echo '<td><input type="text" id="goalv_week_synced" name="goalv_week_synced" value="' . esc_attr($week_synced) . '" class="regular-text" readonly /></td></tr>';

        echo '</table>';
    }

    /**
     * Team details meta box callback
     */
    public function team_details_callback($post)
    {
        $home_team = get_post_meta($post->ID, 'goalv_home_team', true);
        $away_team = get_post_meta($post->ID, 'goalv_away_team', true);
        $home_team_logo = get_post_meta($post->ID, 'goalv_home_team_logo', true);
        $away_team_logo = get_post_meta($post->ID, 'goalv_away_team_logo', true);

        echo '<table class="form-table">';
        echo '<tr><th><label for="goalv_home_team">' . __('Home Team', 'goalv') . '</label></th>';
        echo '<td><input type="text" id="goalv_home_team" name="goalv_home_team" value="' . esc_attr($home_team) . '" class="regular-text" /></td></tr>';

        echo '<tr><th><label for="goalv_away_team">' . __('Away Team', 'goalv') . '</label></th>';
        echo '<td><input type="text" id="goalv_away_team" name="goalv_away_team" value="' . esc_attr($away_team) . '" class="regular-text" /></td></tr>';

        echo '<tr><th><label for="goalv_home_team_logo">' . __('Home Team Logo URL', 'goalv') . '</label></th>';
        echo '<td><input type="url" id="goalv_home_team_logo" name="goalv_home_team_logo" value="' . esc_attr($home_team_logo) . '" class="regular-text" />';
        if ($home_team_logo) {
            echo '<br><img src="' . esc_url($home_team_logo) . '" alt="Home Team Logo" style="max-width: 50px; margin-top: 5px;">';
        }
        echo '</td></tr>';

        echo '<tr><th><label for="goalv_away_team_logo">' . __('Away Team Logo URL', 'goalv') . '</label></th>';
        echo '<td><input type="url" id="goalv_away_team_logo" name="goalv_away_team_logo" value="' . esc_attr($away_team_logo) . '" class="regular-text" />';
        if ($away_team_logo) {
            echo '<br><img src="' . esc_url($away_team_logo) . '" alt="Away Team Logo" style="max-width: 50px; margin-top: 5px;">';
        }
        echo '</td></tr>';

        echo '</table>';
    }

    /**
     * Save meta box data
     */
    public function save_meta_boxes($post_id, $post)
    {
        // Prevent infinite loops and unauthorized saves
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        if ($post->post_type !== 'goalv_matches') {
            return;
        }

        if (!isset($_POST['goalv_meta_box_nonce']) || !wp_verify_nonce($_POST['goalv_meta_box_nonce'], 'goalv_save_meta_box_data')) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Prevent this hook from running again during this save
        remove_action('save_post', array($this, 'save_meta_boxes'));

        $fields = array(
            'goalv_api_match_id',
            'goalv_home_team',
            'goalv_away_team',
            'goalv_home_team_logo',
            'goalv_away_team_logo',
            'goalv_match_date',
            'goalv_match_status',
            'goalv_home_score',
            'goalv_away_score',
            'goalv_competition',
            'goalv_week_synced'
        );

        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, $field, sanitize_text_field($_POST[$field]));
            }
        }

        // Update post title if teams are set
        if (isset($_POST['goalv_home_team']) && isset($_POST['goalv_away_team'])) {
            $home_team = sanitize_text_field($_POST['goalv_home_team']);
            $away_team = sanitize_text_field($_POST['goalv_away_team']);
            if ($home_team && $away_team) {
                $title = $home_team . ' vs ' . $away_team;
                wp_update_post(array(
                    'ID' => $post_id,
                    'post_title' => $title
                ));
            }
        }

        // Re-add the hook for future saves
        add_action('save_post', array($this, 'save_meta_boxes'), 10, 2);
    }

    /**
     * FIX: Auto set week meta - now properly triggered
     */
    public function auto_set_week_meta($post_id, $post)
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if ($post->post_type !== 'goalv_matches') {
            return;
        }

        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        // Check if we're in admin and doing a real save
        if (!is_admin() || !current_user_can('edit_post', $post_id)) {
            return;
        }

        // Get match date
        $match_date = get_post_meta($post_id, 'goalv_match_date', true);

        if ($match_date) {
            // Calculate week from match date
            $week = date('Y-W', strtotime($match_date));
        } else {
            // Fallback to current week
            $week = date('Y-W');
        }

        update_post_meta($post_id, 'goalv_week_synced', $week);

        // FIX: Also ensure vote options are created
        $this->ensure_vote_options_exist($post_id);
    }

    /**
     * Load single template
     */
    public function load_single_template($template)
    {
        global $post;

        if ($post->post_type == 'goalv_matches') {
            $single_template = GOALV_PLUGIN_PATH . 'templates/single-goalv_matches.php';
            if (file_exists($single_template)) {
                return $single_template;
            }
        }

        return $template;
    }

    public function custom_voting_options_callback($post)
    {
        wp_nonce_field('goalv_save_custom_options', 'goalv_custom_options_nonce');

        // Enqueue admin scripts for this meta box
        wp_enqueue_script('jquery-ui-sortable');

        echo '<div id="goalv-custom-options-container">';
        echo '<p>' . __('Add custom voting options in addition to the default ones. These will appear on the single match page for logged-in users.', 'goalv') . '</p>';

        // Get existing custom options
        $custom_options = $this->get_custom_options($post->ID);

        // Add new option form
        echo '<div class="goalv-add-option-form">';
        echo '<h4>' . __('Add New Custom Option', 'goalv') . '</h4>';
        echo '<table class="form-table">';
        echo '<tr>';
        echo '<td><input type="text" id="new-option-text" placeholder="' . __('e.g., Home Team Scores in First 15 Minutes', 'goalv') . '" class="regular-text" /></td>';
        echo '<td>';
        echo '<select id="new-option-type">';
        echo '<option value="basic">' . __('Basic (Homepage)', 'goalv') . '</option>';
        echo '<option value="detailed">' . __('Detailed (Single Page)', 'goalv') . '</option>';
        echo '</select>';
        echo '</td>';
        echo '<td><button type="button" id="add-custom-option-btn" class="button button-secondary">' . __('Add Option', 'goalv') . '</button></td>';
        echo '</tr>';
        echo '</table>';
        echo '</div>';

        // Display existing options
        echo '<div id="existing-custom-options">';
        echo '<h4>' . __('Current Voting Options', 'goalv') . '</h4>';

        // Get all options (default + custom) grouped by type
        $all_options = $this->get_all_options_with_details($post->ID);

        foreach (array('basic', 'detailed') as $option_type) {
            $type_label = ($option_type === 'basic') ? __('Basic Options (Homepage)', 'goalv') : __('Detailed Options (Single Page)', 'goalv');
            echo '<div class="goalv-options-group">';
            echo '<h5>' . $type_label . '</h5>';

            if (!empty($all_options[$option_type])) {
                echo '<ul class="goalv-options-sortable" data-option-type="' . esc_attr($option_type) . '">';

                foreach ($all_options[$option_type] as $option) {
                    $is_custom = (bool) $option->is_custom;
                    $css_class = $is_custom ? 'custom-option' : 'default-option';

                    echo '<li class="goalv-option-item ' . $css_class . '" data-option-id="' . esc_attr($option->id) . '">';
                    echo '<span class="goalv-drag-handle">⚏⚏</span>';
                    echo '<span class="goalv-option-text">' . esc_html($option->option_text) . '</span>';
                    echo '<span class="goalv-option-votes">(' . esc_html($option->votes_count) . ' votes)</span>';

                    if ($is_custom) {
                        echo '<button type="button" class="goalv-remove-option button-link-delete" data-option-id="' . esc_attr($option->id) . '">' . __('Remove', 'goalv') . '</button>';
                    } else {
                        echo '<span class="goalv-default-label">' . __('Default', 'goalv') . '</span>';
                    }

                    echo '</li>';
                }

                echo '</ul>';
            } else {
                echo '<p class="description">' . __('No options available for this type.', 'goalv') . '</p>';
            }

            echo '</div>';
        }

        echo '</div>'; // #existing-custom-options
        echo '</div>'; // #goalv-custom-options-container

        // Add JavaScript for handling custom options
        $this->add_custom_options_script();
    }

    /**
     * NEW: Get custom options for a match
     */
    private function get_custom_options($match_id)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'goalv_vote_options';
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name 
             WHERE match_id = %d AND is_custom = 1 
             ORDER BY option_type, display_order ASC",
            $match_id
        ));
    }

    /**
     * NEW: Get all options with details for display
     */
    private function get_all_options_with_details($match_id)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'goalv_vote_options';
        $options = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name 
             WHERE match_id = %d 
             ORDER BY option_type, display_order ASC, id ASC",
            $match_id
        ));

        $grouped = array('basic' => array(), 'detailed' => array());

        foreach ($options as $option) {
            $grouped[$option->option_type][] = $option;
        }

        return $grouped;
    }

    /**
     * NEW: Add custom options JavaScript
     */
    private function add_custom_options_script()
    {
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function ($) {
                // Make options sortable
                $('.goalv-options-sortable').sortable({
                    handle: '.goalv-drag-handle',
                    placeholder: 'goalv-sort-placeholder',
                    update: function (event, ui) {
                        var optionType = $(this).data('option-type');
                        var order = [];

                        $(this).find('.goalv-option-item').each(function (index) {
                            order.push({
                                id: $(this).data('option-id'),
                                order: index + 1
                            });
                        });

                        // Save new order via AJAX
                        updateOptionsOrder(<?php echo get_the_ID(); ?>, optionType, order);
                    }
                });

                // Add new custom option
                $('#add-custom-option-btn').on('click', function () {
                    var optionText = $('#new-option-text').val().trim();
                    var optionType = $('#new-option-type').val();

                    if (optionText === '') {
                        alert('<?php _e('Please enter option text', 'goalv'); ?>');
                        return;
                    }

                    addCustomOption(<?php echo get_the_ID(); ?>, optionText, optionType);
                });

                // Remove custom option
                $(document).on('click', '.goalv-remove-option', function () {
                    var optionId = $(this).data('option-id');
                    var $item = $(this).closest('.goalv-option-item');

                    if (confirm('<?php _e('Are you sure you want to remove this option? All votes for this option will be lost.', 'goalv'); ?>')) {
                        removeCustomOption(optionId, $item);
                    }
                });

                // AJAX Functions
                function addCustomOption(matchId, optionText, optionType) {
                    $.post(ajaxurl, {
                        action: 'goalv_add_custom_option',
                        nonce: '<?php echo wp_create_nonce('goalv_custom_options'); ?>',
                        match_id: matchId,
                        option_text: optionText,
                        option_type: optionType
                    }, function (response) {
                        if (response.success) {
                            location.reload(); // Refresh to show new option
                        } else {
                            alert('Error: ' + response.data);
                        }
                    });
                }

                function removeCustomOption(optionId, $item) {
                    $.post(ajaxurl, {
                        action: 'goalv_remove_custom_option',
                        nonce: '<?php echo wp_create_nonce('goalv_custom_options'); ?>',
                        option_id: optionId
                    }, function (response) {
                        if (response.success) {
                            $item.fadeOut(300, function () {
                                $(this).remove();
                            });
                        } else {
                            alert('Error: ' + response.data);
                        }
                    });
                }

                function updateOptionsOrder(matchId, optionType, order) {
                    $.post(ajaxurl, {
                        action: 'goalv_update_options_order',
                        nonce: '<?php echo wp_create_nonce('goalv_custom_options'); ?>',
                        match_id: matchId,
                        option_type: optionType,
                        order: order
                    }, function (response) {
                        if (!response.success) {
                            alert('Error updating order: ' + response.data);
                        }
                    });
                }
            });
        </script>
        <?php
    }
    /**
     * NEW: AJAX handler for adding custom option
     */
    public function ajax_add_custom_option()
    {
        check_ajax_referer('goalv_custom_options', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Permission denied', 'goalv'));
        }

        $match_id = intval($_POST['match_id']);
        $option_text = sanitize_text_field($_POST['option_text']);
        $option_type = sanitize_text_field($_POST['option_type']);

        if (!$match_id || !$option_text || !in_array($option_type, array('basic', 'detailed'))) {
            wp_send_json_error(__('Invalid data provided', 'goalv'));
        }

        // Check if match exists
        if (get_post_type($match_id) !== 'goalv_matches') {
            wp_send_json_error(__('Match not found', 'goalv'));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'goalv_vote_options';

        // Get next display order
        $max_order = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(display_order) FROM $table_name WHERE match_id = %d AND option_type = %s",
            $match_id,
            $option_type
        ));

        $display_order = ($max_order ? $max_order + 1 : 100);

        // Insert new option
        $result = $wpdb->insert(
            $table_name,
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
            wp_send_json_success(__('Option added successfully', 'goalv'));
        } else {
            wp_send_json_error(__('Failed to add option', 'goalv'));
        }
    }

    /**
     * NEW: AJAX handler for removing custom option
     */
    public function ajax_remove_custom_option()
    {
        check_ajax_referer('goalv_custom_options', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Permission denied', 'goalv'));
        }

        $option_id = intval($_POST['option_id']);

        if (!$option_id) {
            wp_send_json_error(__('Invalid option ID', 'goalv'));
        }

        global $wpdb;
        $options_table = $wpdb->prefix . 'goalv_vote_options';
        $votes_table = $wpdb->prefix . 'goalv_votes';

        // Check if option exists and is custom
        $option = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $options_table WHERE id = %d AND is_custom = 1",
            $option_id
        ));

        if (!$option) {
            wp_send_json_error(__('Option not found or not removable', 'goalv'));
        }

        // Remove all votes for this option first
        $wpdb->delete($votes_table, array('option_id' => $option_id));

        // Remove the option
        $result = $wpdb->delete($options_table, array('id' => $option_id));

        if ($result) {
            wp_send_json_success(__('Option removed successfully', 'goalv'));
        } else {
            wp_send_json_error(__('Failed to remove option', 'goalv'));
        }
    }

    /**
     * NEW: AJAX handler for updating options order
     */
    public function ajax_update_options_order()
    {
        check_ajax_referer('goalv_custom_options', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Permission denied', 'goalv'));
        }

        $match_id = intval($_POST['match_id']);
        $option_type = sanitize_text_field($_POST['option_type']);
        $order = $_POST['order'];

        if (!$match_id || !$option_type || !is_array($order)) {
            wp_send_json_error(__('Invalid data provided', 'goalv'));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'goalv_vote_options';

        // Update display order for each option
        foreach ($order as $item) {
            $option_id = intval($item['id']);
            $display_order = intval($item['order']);

            $wpdb->update(
                $table_name,
                array('display_order' => $display_order),
                array('id' => $option_id, 'match_id' => $match_id),
                array('%d'),
                array('%d', '%d')
            );
        }

        wp_send_json_success(__('Order updated successfully', 'goalv'));
    }

}