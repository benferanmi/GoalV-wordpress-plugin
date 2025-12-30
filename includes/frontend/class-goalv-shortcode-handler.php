<?php
/**
 * GoalV Shortcode Handler
 * Processes shortcode attributes and determines what matches to display
 * 
 * @package GoalV
 * @subpackage Frontend
 * @version 8.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class GoalV_Shortcode_Handler
{
    private $query;

    public function __construct()
    {
        $this->query = new GoalV_Match_Query();
    }

    /**
     * Parse and validate shortcode attributes
     * 
     * @param array $atts Shortcode attributes
     * @return array Processed attributes
     */
    public function parse_attributes($atts)
    {
        $defaults = array(
            'template' => 'card',
            'limit' => 10,
            'competition' => null,
            'status' => null,
            'date_from' => null,
            'date_to' => null,
            'show_week_headers' => 'no',
            'show_custom_options' => 'yes',
            'teams_label' => get_option('goalv_labels_teams', __('Teams', 'goalv')),
            'score_label' => get_option('goalv_labels_score', __('Score', 'goalv')),
            'status_label' => get_option('goalv_labels_status', __('Status', 'goalv')),
            'date_label' => get_option('goalv_labels_date', __('Date', 'goalv')),
            'predictions_label' => get_option('goalv_labels_predictions', __('Predictions', 'goalv')),
            'details_label' => get_option('goalv_labels_details', __('Details', 'goalv'))
        );

        $parsed = shortcode_atts($defaults, $atts, 'goalv_matches');

        // Validate template
        if (!in_array($parsed['template'], array('card', 'grid', 'table', 'betfair', 'betfairdaily'))) {
            $parsed['template'] = 'card';
        }

        // Validate limit
        $parsed['limit'] = max(1, min(100, intval($parsed['limit'])));

        // Convert competition name to ID if provided
        if ($parsed['competition'] && !is_numeric($parsed['competition'])) {
            $parsed['competition'] = $this->get_competition_id_by_name($parsed['competition']);
        }

        return $parsed;
    }

    /**
     * Determine which matches to retrieve based on attributes
     * 
     * @param array $atts Parsed attributes
     * @return array Match objects
     */
    public function get_matches($atts)
    {
        $matches = array();

        // Priority 1: Date range filter
        if (!empty($atts['date_from']) && !empty($atts['date_to'])) {
            $matches = $this->query->get_matches_by_date_range(
                $atts['date_from'],
                $atts['date_to'],
                $atts['limit']
            );
        }
        // Priority 2: Status filter
        elseif (!empty($atts['status'])) {
            $matches = $this->query->get_matches_by_status(
                $atts['status'],
                $atts['limit']
            );
        }
        // Priority 3: Competition filter
        elseif (!empty($atts['competition'])) {
            $matches = $this->query->get_matches_by_competition(
                $atts['competition'],
                $atts['limit']
            );
        }
        // Default: Upcoming matches
        else {
            $matches = $this->query->get_upcoming_matches($atts['limit']);
        }

        return $matches;
    }

    /**
     * Apply sorting to matches
     * 
     * @param array $matches Match objects
     * @param string $sort_by Sort field
     * @param string $sort_order Sort direction (ASC/DESC)
     * @return array Sorted matches
     */
    public function sort_matches($matches, $sort_by = 'match_date', $sort_order = 'ASC')
    {
        if (empty($matches)) {
            return $matches;
        }

        usort($matches, function ($a, $b) use ($sort_by, $sort_order) {
            $val_a = isset($a->$sort_by) ? $a->$sort_by : '';
            $val_b = isset($b->$sort_by) ? $b->$sort_by : '';

            if ($sort_order === 'DESC') {
                return $val_b <=> $val_a;
            }

            return $val_a <=> $val_b;
        });

        return $matches;
    }

    /**
     * Filter matches by various criteria
     * 
     * @param array $matches Match objects
     * @param array $filters Filter criteria
     * @return array Filtered matches
     */
    public function filter_matches($matches, $filters = array())
    {
        if (empty($matches) || empty($filters)) {
            return $matches;
        }

        return array_filter($matches, function ($match) use ($filters) {
            // Filter by team
            if (isset($filters['team'])) {
                $team_name = strtolower($filters['team']);
                if (
                    stripos($match->home_team, $team_name) === false &&
                    stripos($match->away_team, $team_name) === false
                ) {
                    return false;
                }
            }

            // Filter by competition
            if (isset($filters['competition'])) {
                if (stripos($match->competition, $filters['competition']) === false) {
                    return false;
                }
            }

            // Filter by status
            if (isset($filters['status'])) {
                if ($match->status !== $filters['status']) {
                    return false;
                }
            }

            return true;
        });
    }

    /**
     * Get competition ID by name
     * 
     * @param string $name Competition name
     * @return int|null Competition ID
     */
    private function get_competition_id_by_name($name)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'goalv_competitions';

        $id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE name LIKE %s OR code LIKE %s LIMIT 1",
            '%' . $wpdb->esc_like($name) . '%',
            '%' . $wpdb->esc_like($name) . '%'
        ));

        return $id ? (int) $id : null;
    }

    /**
     * Prepare template arguments
     * 
     * @param array $matches Match objects
     * @param array $atts Shortcode attributes
     * @return array Template arguments
     */
    public function prepare_template_args($matches, $atts)
    {
        return array(
            'matches' => $matches,
            'show_week_headers' => $atts['show_week_headers'] === 'yes',
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
    }

    /**
     * Get no matches message based on context
     * 
     * @param array $atts Shortcode attributes
     * @return string HTML message
     */
    public function get_no_matches_message($atts)
    {
        $message = __('No matches available.', 'goalv');

        if (!empty($atts['competition'])) {
            $comp_name = $this->get_competition_name($atts['competition']);
            $message = sprintf(
                __('No matches available for %s.', 'goalv'),
                $comp_name
            );
        } elseif (!empty($atts['status'])) {
            $status_labels = array(
                'live' => __('live', 'goalv'),
                'scheduled' => __('scheduled', 'goalv'),
                'finished' => __('finished', 'goalv')
            );
            $status_label = isset($status_labels[$atts['status']])
                ? $status_labels[$atts['status']]
                : $atts['status'];

            $message = sprintf(
                __('No %s matches available.', 'goalv'),
                $status_label
            );
        } elseif (!empty($atts['date_from']) && !empty($atts['date_to'])) {
            $message = sprintf(
                __('No matches between %s and %s.', 'goalv'),
                date('M j, Y', strtotime($atts['date_from'])),
                date('M j, Y', strtotime($atts['date_to']))
            );
        }

        return '<div class="goalv-no-matches"><p>' . esc_html($message) . '</p></div>';
    }

    /**
     * Get competition name by ID
     * 
     * @param int $competition_id Competition ID
     * @return string Competition name
     */
    private function get_competition_name($competition_id)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'goalv_competitions';

        $name = $wpdb->get_var($wpdb->prepare(
            "SELECT name FROM $table WHERE id = %d",
            $competition_id
        ));

        return $name ? $name : __('Unknown Competition', 'goalv');
    }
}