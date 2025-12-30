<?php
/**
 * GoalV Admin Matches - Matches Management Module
 * 
 * Handles matches listing, filtering, bulk operations, and AJAX endpoints
 * 
 * @package GoalV
 * @subpackage Admin
 * @since 9.0.2
 */

if (!defined('ABSPATH')) {
    exit;
}

class GoalV_Admin_Matches
{
    /**
     * Constructor
     */
    public function __construct()
    {
        // Register AJAX handlers
        add_action('wp_ajax_goalv_get_match_details', array($this, 'ajax_get_match_details'));
        add_action('wp_ajax_goalv_delete_match', array($this, 'ajax_delete_match'));
        add_action('wp_ajax_goalv_update_match_status', array($this, 'ajax_update_match_status'));
        add_action('wp_ajax_goalv_resync_match', array($this, 'ajax_resync_match'));
        add_action('wp_ajax_goalv_export_matches_csv', array($this, 'ajax_export_matches_csv'));
        add_action('wp_ajax_goalv_fix_orphaned_matches', array($this, 'ajax_fix_orphaned_matches'));
    }

    /**
     * Render matches management page
     */
    public function render()
    {
        // Load matches page template
        require_once GOALV_PLUGIN_PATH . 'admin/pages/matches.php';
    }

    /**
     * Get matches with filters and pagination
     * 
     * @param array $args Query arguments
     * @return array Matches data with pagination info
     */
    public function get_matches($args = array())
    {
        global $wpdb;

        $defaults = array(
            'competition_id' => 0,
            'status' => '',
            'date_from' => '',
            'date_to' => '',
            'search' => '',
            'orderby' => 'match_date',
            'order' => 'DESC',
            'page' => 1,
            'per_page' => 50
        );

        $args = wp_parse_args($args, $defaults);

        // Build WHERE clause
        $where = array('1=1');
        $values = array();

        if (!empty($args['competition_id'])) {
            $where[] = 'm.competition_id = %d';
            $values[] = intval($args['competition_id']);
        }

        if (!empty($args['status'])) {
            $where[] = 'm.status = %s';
            $values[] = sanitize_text_field($args['status']);
        }

        if (!empty($args['date_from'])) {
            $where[] = 'DATE(m.match_date) >= %s';
            $values[] = sanitize_text_field($args['date_from']);
        }

        if (!empty($args['date_to'])) {
            $where[] = 'DATE(m.match_date) <= %s';
            $values[] = sanitize_text_field($args['date_to']);
        }

        if (!empty($args['search'])) {
            $where[] = '(ht.name LIKE %s OR at.name LIKE %s OR c.name LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $values[] = $search_term;
            $values[] = $search_term;
            $values[] = $search_term;
        }

        $where_sql = implode(' AND ', $where);

        // Count total matches
        $count_sql = "SELECT COUNT(DISTINCT m.id)
                      FROM {$wpdb->prefix}goalv_matches m
                      LEFT JOIN {$wpdb->prefix}goalv_competitions c ON m.competition_id = c.id
                      LEFT JOIN {$wpdb->prefix}goalv_teams ht ON m.home_team_id = ht.id
                      LEFT JOIN {$wpdb->prefix}goalv_teams at ON m.away_team_id = at.id
                      WHERE {$where_sql}";

        if (!empty($values)) {
            $total = $wpdb->get_var($wpdb->prepare($count_sql, $values));
        } else {
            $total = $wpdb->get_var($count_sql);
        }

        // Calculate pagination
        $per_page = max(1, intval($args['per_page']));
        $page = max(1, intval($args['page']));
        $total_pages = ceil($total / $per_page);
        $offset = ($page - 1) * $per_page;

        // Get matches
        $orderby = in_array($args['orderby'], array('match_date', 'id', 'status')) ? $args['orderby'] : 'match_date';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

        $sql = "SELECT 
                    m.*,
                    c.name AS competition_name,
                    c.logo_url AS competition_logo,
                    ht.name AS home_team_name,
                    ht.logo_url AS home_team_logo,
                    at.name AS away_team_name,
                    at.logo_url AS away_team_logo
                FROM {$wpdb->prefix}goalv_matches m
                LEFT JOIN {$wpdb->prefix}goalv_competitions c ON m.competition_id = c.id
                LEFT JOIN {$wpdb->prefix}goalv_teams ht ON m.home_team_id = ht.id
                LEFT JOIN {$wpdb->prefix}goalv_teams at ON m.away_team_id = at.id
                WHERE {$where_sql}
                ORDER BY m.{$orderby} {$order}
                LIMIT %d OFFSET %d";

        $query_values = array_merge($values, array($per_page, $offset));
        $matches = $wpdb->get_results($wpdb->prepare($sql, $query_values));

        return array(
            'matches' => $matches,
            'total' => intval($total),
            'page' => $page,
            'per_page' => $per_page,
            'pages' => $total_pages
        );
    }

    /**
     * Get competitions list for dropdown
     * 
     * @return array Competitions
     */
    public function get_competitions_list()
    {
        global $wpdb;

        return $wpdb->get_results(
            "SELECT id, name, logo_url 
             FROM {$wpdb->prefix}goalv_competitions 
             WHERE is_active = 1
             ORDER BY name ASC"
        );
    }

    /**
     * Get match status counts
     * 
     * @return array Status counts
     */
    public function get_status_counts()
    {
        global $wpdb;

        $results = $wpdb->get_results(
            "SELECT status, COUNT(*) as count 
             FROM {$wpdb->prefix}goalv_matches 
             GROUP BY status",
            OBJECT_K
        );

        $counts = array(
            'scheduled' => 0,
            'live' => 0,
            'finished' => 0,
            'postponed' => 0,
            'cancelled' => 0
        );

        foreach ($results as $status => $data) {
            if (isset($counts[$status])) {
                $counts[$status] = intval($data->count);
            }
        }

        return $counts;
    }

    /**
     * Get orphaned matches (matches without vote options)
     * 
     * @return array Match IDs
     */
    public function get_orphaned_matches()
    {
        global $wpdb;

        return $wpdb->get_col(
            "SELECT m.id 
             FROM {$wpdb->prefix}goalv_matches m
             LEFT JOIN {$wpdb->prefix}goalv_vote_options vo ON m.id = vo.match_id
             WHERE vo.id IS NULL"
        );
    }

    /**
     * Get match statistics (votes, options, etc.)
     * 
     * @param int $match_id Match ID
     * @return array Statistics
     */
    public function get_match_statistics($match_id)
    {
        global $wpdb;

        $votes_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}goalv_votes WHERE match_id = %d",
            $match_id
        ));

        $options_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}goalv_vote_options WHERE match_id = %d",
            $match_id
        ));

        return array(
            'total_votes' => intval($votes_count),
            'total_options' => intval($options_count),
            'has_votes' => $votes_count > 0,
            'has_options' => $options_count > 0
        );
    }

    /**
     * AJAX: Get match details for modal
     */
    public function ajax_get_match_details()
    {
        check_ajax_referer('goalv_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'goalv'));
        }

        $match_id = isset($_POST['match_id']) ? intval($_POST['match_id']) : 0;

        if (!$match_id) {
            wp_send_json_error(__('Invalid match ID', 'goalv'));
        }

        global $wpdb;

        // Get match with full details
        $match = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                m.*,
                c.name AS competition_name,
                c.logo_url AS competition_logo,
                ht.name AS home_team_name,
                ht.logo_url AS home_team_logo,
                at.name AS away_team_name,
                at.logo_url AS away_team_logo
            FROM {$wpdb->prefix}goalv_matches m
            LEFT JOIN {$wpdb->prefix}goalv_competitions c ON m.competition_id = c.id
            LEFT JOIN {$wpdb->prefix}goalv_teams ht ON m.home_team_id = ht.id
            LEFT JOIN {$wpdb->prefix}goalv_teams at ON m.away_team_id = at.id
            WHERE m.id = %d",
            $match_id
        ));

        if (!$match) {
            wp_send_json_error(__('Match not found', 'goalv'));
        }

        // Get vote options with counts
        $vote_options = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                vo.*,
                vc.category_label as category,
                COUNT(v.id) as votes_count
            FROM {$wpdb->prefix}goalv_vote_options vo
            LEFT JOIN {$wpdb->prefix}goalv_vote_categories vc ON vo.category = vc.category_key
            LEFT JOIN {$wpdb->prefix}goalv_votes v ON vo.id = v.option_id
            WHERE vo.match_id = %d
            GROUP BY vo.id
            ORDER BY vo.option_type, vo.display_order",
            $match_id
        ));

        // Get voting statistics
        $total_votes = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}goalv_votes WHERE match_id = %d",
            $match_id
        ));

        // Get most popular option
        $most_popular = $wpdb->get_row($wpdb->prepare(
            "SELECT vo.option_text, COUNT(v.id) as votes
             FROM {$wpdb->prefix}goalv_vote_options vo
             LEFT JOIN {$wpdb->prefix}goalv_votes v ON vo.id = v.option_id
             WHERE vo.match_id = %d
             GROUP BY vo.id
             ORDER BY votes DESC
             LIMIT 1",
            $match_id
        ));

        wp_send_json_success(array(
            'match' => $match,
            'vote_options' => $vote_options,
            'stats' => array(
                'total_votes' => intval($total_votes),
                'most_popular' => $most_popular
            )
        ));
    }

    /**
     * AJAX: Delete match
     */
    public function ajax_delete_match()
    {
        check_ajax_referer('goalv_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'goalv'));
        }

        $match_id = isset($_POST['match_id']) ? intval($_POST['match_id']) : 0;

        if (!$match_id) {
            wp_send_json_error(__('Invalid match ID', 'goalv'));
        }

        global $wpdb;

        // Delete in correct order (foreign key dependencies)
        
        // 1. Delete votes
        $wpdb->delete(
            $wpdb->prefix . 'goalv_votes',
            array('match_id' => $match_id),
            array('%d')
        );

        // 2. Delete vote summary
        $wpdb->delete(
            $wpdb->prefix . 'goalv_vote_summary',
            array('match_id' => $match_id),
            array('%d')
        );

        // 3. Delete vote options
        $wpdb->delete(
            $wpdb->prefix . 'goalv_vote_options',
            array('match_id' => $match_id),
            array('%d')
        );

        // 4. Delete live scores
        $wpdb->delete(
            $wpdb->prefix . 'goalv_live_scores',
            array('match_id' => $match_id),
            array('%d')
        );

        // 5. Delete match events
        $wpdb->delete(
            $wpdb->prefix . 'goalv_match_events',
            array('match_id' => $match_id),
            array('%d')
        );

        // 6. Delete match itself
        $result = $wpdb->delete(
            $wpdb->prefix . 'goalv_matches',
            array('id' => $match_id),
            array('%d')
        );

        if ($result) {
            // Clear caches
            delete_transient("goalv_vote_results_{$match_id}_homepage");
            delete_transient("goalv_vote_results_{$match_id}_details");

            wp_send_json_success(array(
                'message' => __('Match deleted successfully', 'goalv')
            ));
        } else {
            wp_send_json_error(__('Failed to delete match', 'goalv'));
        }
    }

    /**
     * AJAX: Update match status
     */
    public function ajax_update_match_status()
    {
        check_ajax_referer('goalv_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'goalv'));
        }

        $match_id = isset($_POST['match_id']) ? intval($_POST['match_id']) : 0;
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';

        if (!$match_id || !$status) {
            wp_send_json_error(__('Invalid parameters', 'goalv'));
        }

        $valid_statuses = array('scheduled', 'live', 'paused', 'finished', 'postponed', 'cancelled', 'awarded');
        
        if (!in_array($status, $valid_statuses)) {
            wp_send_json_error(__('Invalid status', 'goalv'));
        }

        global $wpdb;

        $result = $wpdb->update(
            $wpdb->prefix . 'goalv_matches',
            array('status' => $status),
            array('id' => $match_id),
            array('%s'),
            array('%d')
        );

        if ($result !== false) {
            wp_send_json_success(array(
                'message' => sprintf(__('Match status updated to %s', 'goalv'), $status)
            ));
        } else {
            wp_send_json_error(__('Failed to update status', 'goalv'));
        }
    }

    /**
     * AJAX: Resync match from API
     */
    public function ajax_resync_match()
    {
        check_ajax_referer('goalv_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'goalv'));
        }

        $match_id = isset($_POST['match_id']) ? intval($_POST['match_id']) : 0;

        if (!$match_id) {
            wp_send_json_error(__('Invalid match ID', 'goalv'));
        }

        global $wpdb;

        // Get match API ID
        $api_match_id = $wpdb->get_var($wpdb->prepare(
            "SELECT api_match_id FROM {$wpdb->prefix}goalv_matches WHERE id = %d",
            $match_id
        ));

        if (!$api_match_id) {
            wp_send_json_error(__('Match API ID not found', 'goalv'));
        }

        try {
            // Initialize API client
            $api_matches = new GoalV_API_Matches();
            
            // Resync single match
            $result = $api_matches->sync_single_match($api_match_id);

            if ($result && isset($result['success']) && $result['success']) {
                wp_send_json_success(array(
                    'message' => __('Match resynced successfully', 'goalv')
                ));
            } else {
                wp_send_json_error(__('Failed to resync match from API', 'goalv'));
            }
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * AJAX: Export matches to CSV
     */
    public function ajax_export_matches_csv()
    {
        check_ajax_referer('goalv_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'goalv'));
        }

        // Get filter parameters
        $args = array(
            'competition_id' => isset($_GET['competition_id']) ? intval($_GET['competition_id']) : 0,
            'status' => isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '',
            'date_from' => isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '',
            'date_to' => isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '',
            'search' => isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '',
            'per_page' => 999999 // Get all matches for export
        );

        $matches_data = $this->get_matches($args);

        // Set headers for CSV download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=goalv-matches-' . date('Y-m-d') . '.csv');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Open output stream
        $output = fopen('php://output', 'w');

        // Write CSV headers
        fputcsv($output, array(
            'ID',
            'Competition',
            'Home Team',
            'Away Team',
            'Home Score',
            'Away Score',
            'Status',
            'Match Date',
            'Venue',
            'Referee',
            'Total Votes'
        ));

        // Write match data
        foreach ($matches_data['matches'] as $match) {
            $stats = $this->get_match_statistics($match->id);

            fputcsv($output, array(
                $match->id,
                $match->competition_name,
                $match->home_team_name,
                $match->away_team_name,
                $match->home_score ?? '-',
                $match->away_score ?? '-',
                ucfirst($match->status),
                date('Y-m-d H:i', strtotime($match->match_date)),
                $match->venue ?? '-',
                $match->referee ?? '-',
                $stats['total_votes']
            ));
        }

        fclose($output);
        exit;
    }

    /**
     * AJAX: Fix orphaned matches (create missing vote options)
     */
    public function ajax_fix_orphaned_matches()
    {
        check_ajax_referer('goalv_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'goalv'));
        }

        $orphaned_ids = $this->get_orphaned_matches();

        if (empty($orphaned_ids)) {
            wp_send_json_success(array(
                'message' => __('No orphaned matches found', 'goalv')
            ));
        }

        // Use Vote Options Manager to batch create options
        $result = GoalV_Vote_Options_Manager::batch_create_options($orphaned_ids);

        wp_send_json_success(array(
            'message' => sprintf(
                __('Fixed %d orphaned matches. Created vote options for %d matches.', 'goalv'),
                count($orphaned_ids),
                $result['created']
            ),
            'details' => $result
        ));
    }
}