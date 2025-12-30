<?php
/**
 * Vote Options Manager
 * Centralized management of vote option creation and maintenance
 * 
 * Extracted from old CPT class and modernized for database architecture
 * 
 * @package GoalV
 * @version 8.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class GoalV_Vote_Options_Manager
{
    /**
     * Create default vote options for a new match
     * Called automatically when matches are synced from API
     * 
     * @param int $match_id Match database ID
     * @param int $home_team_id Home team database ID
     * @param int $away_team_id Away team database ID
     * @return array Result with counts
     */
    public static function create_default_options($match_id, $home_team_id, $away_team_id)
    {
        global $wpdb;

        // Get team names
        $teams_table = $wpdb->prefix . 'goalv_teams';
        
        $home_team = $wpdb->get_var($wpdb->prepare(
            "SELECT name FROM $teams_table WHERE id = %d",
            $home_team_id
        ));
        
        $away_team = $wpdb->get_var($wpdb->prepare(
            "SELECT name FROM $teams_table WHERE id = %d",
            $away_team_id
        ));

        if (!$home_team || !$away_team) {
            return array(
                'success' => false,
                'error' => 'Team names not found'
            );
        }

        // Check if options already exist
        $options_table = $wpdb->prefix . 'goalv_vote_options';
        
        $existing_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $options_table WHERE match_id = %d",
            $match_id
        ));

        if ($existing_count > 0) {
            return array(
                'success' => true,
                'message' => 'Options already exist',
                'basic_created' => 0,
                'detailed_created' => 0
            );
        }

        // Create basic options (for homepage voting)
        $basic_count = self::create_basic_options($match_id, $home_team, $away_team);

        // Create detailed options (for single page voting)
        $detailed_count = self::create_detailed_options($match_id, $home_team, $away_team);

        return array(
            'success' => true,
            'message' => sprintf(
                'Created %d basic and %d detailed options for match %d',
                $basic_count,
                $detailed_count,
                $match_id
            ),
            'basic_created' => $basic_count,
            'detailed_created' => $detailed_count
        );
    }

    /**
     * Create basic vote options (Homepage: Home Win, Draw, Away Win)
     * 
     * @param int $match_id Match ID
     * @param string $home_team Home team name
     * @param string $away_team Away team name
     * @return int Number of options created
     */
    private static function create_basic_options($match_id, $home_team, $away_team)
    {
        global $wpdb;
        
        $options_table = $wpdb->prefix . 'goalv_vote_options';

        $basic_options = array(
            array(
                'text' => 'Home Win',
                'category' => 'match_result',
                'order' => 1
            ),
            array(
                'text' => 'Draw',
                'category' => 'match_result',
                'order' => 2
            ),
            array(
                'text' => 'Away Win',
                'category' => 'match_result',
                'order' => 3
            )
        );

        $created = 0;

        foreach ($basic_options as $option) {
            $result = $wpdb->insert(
                $options_table,
                array(
                    'match_id' => $match_id,
                    'option_text' => $option['text'],
                    'option_type' => 'basic',
                    'category' => $option['category'],
                    'display_order' => $option['order'],
                    'votes_count' => 0,
                    'is_custom' => 0,
                    'created_by' => null
                ),
                array('%d', '%s', '%s', '%s', '%d', '%d', '%d', '%d')
            );

            if ($result) {
                $created++;
            }
        }

        return $created;
    }

    /**
     * Create detailed vote options (Single page: 12 prediction options)
     * 
     * @param int $match_id Match ID
     * @param string $home_team Home team name
     * @param string $away_team Away team name
     * @return int Number of options created
     */
    private static function create_detailed_options($match_id, $home_team, $away_team)
    {
        global $wpdb;
        
        $options_table = $wpdb->prefix . 'goalv_vote_options';

        $detailed_options = array(
            // Match Result (3 options)
            array(
                'text' => $home_team . ' Win',
                'category' => 'match_result',
                'order' => 1
            ),
            array(
                'text' => 'Draw',
                'category' => 'match_result',
                'order' => 2
            ),
            array(
                'text' => $away_team . ' Win',
                'category' => 'match_result',
                'order' => 3
            ),
            
            // Goals Threshold (2 options)
            array(
                'text' => 'Over 2.5 Goals',
                'category' => 'goals_threshold',
                'order' => 4
            ),
            array(
                'text' => 'Under 2.5 Goals',
                'category' => 'goals_threshold',
                'order' => 5
            ),
            
            // Both Teams Score (2 options)
            array(
                'text' => 'Both Teams Score - Yes',
                'category' => 'both_teams_score',
                'order' => 6
            ),
            array(
                'text' => 'Both Teams Score - No',
                'category' => 'both_teams_score',
                'order' => 7
            ),
            
            // Score Predictions (3 options)
            array(
                'text' => $home_team . ' Wins 2-1',
                'category' => 'match_score',
                'order' => 8
            ),
            array(
                'text' => $away_team . ' Wins 1-2',
                'category' => 'match_score',
                'order' => 9
            ),
            array(
                'text' => 'Match Ends 1-1',
                'category' => 'match_score',
                'order' => 10
            ),
            
            // First to Score (2 options)
            array(
                'text' => $home_team . ' Scores First',
                'category' => 'first_to_score',
                'order' => 11
            ),
            array(
                'text' => $away_team . ' Scores First',
                'category' => 'first_to_score',
                'order' => 12
            )
        );

        $created = 0;

        foreach ($detailed_options as $option) {
            $result = $wpdb->insert(
                $options_table,
                array(
                    'match_id' => $match_id,
                    'option_text' => $option['text'],
                    'option_type' => 'detailed',
                    'category' => $option['category'],
                    'display_order' => $option['order'],
                    'votes_count' => 0,
                    'is_custom' => 0,
                    'created_by' => null
                ),
                array('%d', '%s', '%s', '%s', '%d', '%d', '%d', '%d')
            );

            if ($result) {
                $created++;
            }
        }

        return $created;
    }

    /**
     * Ensure vote options exist for a match (for existing matches without options)
     * 
     * @param int $match_id Match database ID
     * @return array Result
     */
    public static function ensure_options_exist($match_id)
    {
        global $wpdb;

        // Get match data
        $matches_table = $wpdb->prefix . 'goalv_matches';
        
        $match = $wpdb->get_row($wpdb->prepare(
            "SELECT home_team_id, away_team_id FROM $matches_table WHERE id = %d",
            $match_id
        ));

        if (!$match) {
            return array(
                'success' => false,
                'error' => 'Match not found'
            );
        }

        // Check if options already exist
        $options_table = $wpdb->prefix . 'goalv_vote_options';
        
        $existing_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $options_table WHERE match_id = %d",
            $match_id
        ));

        if ($existing_count > 0) {
            return array(
                'success' => true,
                'message' => 'Options already exist',
                'created' => false
            );
        }

        // Create options
        return self::create_default_options(
            $match_id,
            $match->home_team_id,
            $match->away_team_id
        );
    }

    /**
     * Add custom vote option (via admin)
     * 
     * @param int $match_id Match ID
     * @param string $option_text Option text
     * @param string $option_type 'basic' or 'detailed'
     * @param string $category Vote category
     * @param int $created_by User ID who created it
     * @return array Result
     */
    public static function add_custom_option($match_id, $option_text, $option_type, $category = 'other', $created_by = null)
    {
        global $wpdb;

        // Validate inputs
        if (empty(trim($option_text))) {
            return array(
                'success' => false,
                'error' => 'Option text cannot be empty'
            );
        }

        if (!in_array($option_type, array('basic', 'detailed'))) {
            return array(
                'success' => false,
                'error' => 'Invalid option type'
            );
        }

        // Check for duplicates
        $options_table = $wpdb->prefix . 'goalv_vote_options';
        
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $options_table 
             WHERE match_id = %d AND option_type = %s AND option_text = %s",
            $match_id,
            $option_type,
            trim($option_text)
        ));

        if ($existing > 0) {
            return array(
                'success' => false,
                'error' => 'Option already exists'
            );
        }

        // Get next display order
        $max_order = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(display_order) FROM $options_table 
             WHERE match_id = %d AND option_type = %s",
            $match_id,
            $option_type
        ));

        $display_order = $max_order ? ($max_order + 1) : 100;

        // Insert custom option
        $result = $wpdb->insert(
            $options_table,
            array(
                'match_id' => $match_id,
                'option_text' => trim($option_text),
                'option_type' => $option_type,
                'category' => $category,
                'display_order' => $display_order,
                'votes_count' => 0,
                'is_custom' => 1,
                'created_by' => $created_by
            ),
            array('%d', '%s', '%s', '%s', '%d', '%d', '%d', '%d')
        );

        if ($result) {
            return array(
                'success' => true,
                'message' => 'Custom option created',
                'option_id' => $wpdb->insert_id
            );
        }

        return array(
            'success' => false,
            'error' => 'Failed to create option'
        );
    }

    /**
     * Delete custom vote option
     * 
     * @param int $option_id Option ID
     * @return array Result
     */
    public static function delete_custom_option($option_id)
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
            return array(
                'success' => false,
                'error' => 'Option not found or not a custom option'
            );
        }

        // Delete all votes for this option
        $votes_deleted = $wpdb->delete(
            $votes_table,
            array('option_id' => $option_id),
            array('%d')
        );

        // Delete the option
        $option_deleted = $wpdb->delete(
            $options_table,
            array('id' => $option_id),
            array('%d')
        );

        if ($option_deleted) {
            // Clear cache
            delete_transient("goalv_vote_results_{$option->match_id}_homepage");
            delete_transient("goalv_vote_results_{$option->match_id}_details");

            return array(
                'success' => true,
                'message' => sprintf('Deleted option with %d votes', $votes_deleted),
                'votes_deleted' => $votes_deleted
            );
        }

        return array(
            'success' => false,
            'error' => 'Failed to delete option'
        );
    }

    /**
     * Update vote option display order
     * 
     * @param int $option_id Option ID
     * @param int $new_order New display order
     * @return bool Success
     */
    public static function update_display_order($option_id, $new_order)
    {
        global $wpdb;

        $options_table = $wpdb->prefix . 'goalv_vote_options';

        $result = $wpdb->update(
            $options_table,
            array('display_order' => $new_order),
            array('id' => $option_id),
            array('%d'),
            array('%d')
        );

        return $result !== false;
    }

    /**
     * Get vote options for a match
     * 
     * @param int $match_id Match ID
     * @param string $option_type 'basic' or 'detailed' or null for all
     * @return array Vote options
     */
    public static function get_options($match_id, $option_type = null)
    {
        global $wpdb;

        $options_table = $wpdb->prefix . 'goalv_vote_options';

        if ($option_type) {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $options_table 
                 WHERE match_id = %d AND option_type = %s
                 ORDER BY category, display_order ASC",
                $match_id,
                $option_type
            ));
        } else {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $options_table 
                 WHERE match_id = %d
                 ORDER BY option_type, category, display_order ASC",
                $match_id
            ));
        }
    }

    /**
     * Delete all vote options for a match
     * Used when deleting a match
     * 
     * @param int $match_id Match ID
     * @return array Result with counts
     */
    public static function delete_match_options($match_id)
    {
        global $wpdb;

        $options_table = $wpdb->prefix . 'goalv_vote_options';
        $votes_table = $wpdb->prefix . 'goalv_votes';

        // Count before deletion
        $options_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $options_table WHERE match_id = %d",
            $match_id
        ));

        $votes_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $votes_table WHERE match_id = %d",
            $match_id
        ));

        // Delete votes first
        $wpdb->delete(
            $votes_table,
            array('match_id' => $match_id),
            array('%d')
        );

        // Delete options
        $wpdb->delete(
            $options_table,
            array('match_id' => $match_id),
            array('%d')
        );

        // Clear cache
        delete_transient("goalv_vote_results_{$match_id}_homepage");
        delete_transient("goalv_vote_results_{$match_id}_details");

        return array(
            'success' => true,
            'options_deleted' => $options_count,
            'votes_deleted' => $votes_count
        );
    }

    /**
     * Batch create options for multiple matches
     * Useful for bulk operations
     * 
     * @param array $match_ids Array of match IDs
     * @return array Results summary
     */
    public static function batch_create_options($match_ids)
    {
        $results = array(
            'total' => count($match_ids),
            'created' => 0,
            'skipped' => 0,
            'failed' => 0
        );

        foreach ($match_ids as $match_id) {
            $result = self::ensure_options_exist($match_id);
            
            if ($result['success']) {
                if (isset($result['created']) && $result['created']) {
                    $results['created']++;
                } else {
                    $results['skipped']++;
                }
            } else {
                $results['failed']++;
            }
        }

        return $results;
    }
}