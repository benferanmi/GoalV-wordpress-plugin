<?php
/**
 * GoalV Match Renderer
 * HTML generation and display helpers for matches
 * 
 * @package GoalV
 * @subpackage Frontend
 * @version 8.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class GoalV_Match_Renderer
{
    /**
     * Get team logo with fallback
     * 
     * @param string $logo_url Logo URL
     * @param string $team_name Team name for alt text
     * @return string Logo URL
     */
    public function get_team_logo($logo_url, $team_name = '')
    {
        if (!empty($logo_url) && filter_var($logo_url, FILTER_VALIDATE_URL)) {
            return $logo_url;
        }

        return GOALV_PLUGIN_URL . 'assets/images/default-team-logo.png';
    }

    /**
     * Format match date for display
     * 
     * @param string $date_string Date string
     * @return string Formatted date
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
     * Get match status display HTML
     * 
     * @param string $status Match status
     * @param int $home_score Home score
     * @param int $away_score Away score
     * @return string HTML output
     */
    public function get_status_display($status, $home_score = 0, $away_score = 0)
    {
        switch ($status) {
            case 'live':
                return '<span class="goalv-status-live">' . __('LIVE', 'goalv') . '</span>';
            case 'finished':
                return '<span class="goalv-status-finished">' . __('FT', 'goalv') . '</span>';
            case 'paused':
                return '<span class="goalv-status-paused">' . __('HT', 'goalv') . '</span>';
            case 'postponed':
                return '<span class="goalv-status-postponed">' . __('Postponed', 'goalv') . '</span>';
            case 'cancelled':
                return '<span class="goalv-status-cancelled">' . __('Cancelled', 'goalv') . '</span>';
            case 'scheduled':
            default:
                return '<span class="goalv-status-scheduled">' . __('Upcoming', 'goalv') . '</span>';
        }
    }

    /**
     * Get match status badge (with animation for live)
     * 
     * @param string $status Match status
     * @return string HTML badge
     */
    public function get_status_badge($status)
    {
        $badges = array(
            'scheduled' => '<span class="goalv-badge goalv-badge-scheduled">Upcoming</span>',
            'live' => '<span class="goalv-badge goalv-badge-live goalv-pulse">LIVE</span>',
            'paused' => '<span class="goalv-badge goalv-badge-paused">Half Time</span>',
            'finished' => '<span class="goalv-badge goalv-badge-finished">Full Time</span>',
            'postponed' => '<span class="goalv-badge goalv-badge-postponed">Postponed</span>',
            'cancelled' => '<span class="goalv-badge goalv-badge-cancelled">Cancelled</span>'
        );

        return $badges[$status] ?? '';
    }

    /**
     * Get match permalink (custom URL structure)
     * 
     * @param int $match_id Match database ID
     * @return string URL
     */
    public function get_match_permalink($match_id)
    {
        // Check if custom rewrite rules are active
        $use_custom_rewrites = get_option('goalv_use_custom_rewrites', true);

        if ($use_custom_rewrites && get_option('permalink_structure')) {
            // Use clean URL structure: /match/123/
            return home_url('/match/' . $match_id . '/');
        } else {
            // Fallback to query parameter for compatibility
            return home_url('/?goalv_match_id=' . $match_id);
        }
    }

    /**
     * Render voting button
     * 
     * @param object $option Vote option
     * @param object $match Match object
     * @param array $result Vote result data
     * @param string $location Vote location
     * @return string HTML output
     */
    public function render_vote_button($option, $match, $result, $location = 'homepage')
    {
        $is_selected = in_array($option->id, $match->user_votes);
        $percentage = $result['percentage'];
        $vote_count = $result['votes_count'];

        ob_start();
        ?>
        <button type="button" class="goalv-vote-btn <?php echo $is_selected ? 'selected' : ''; ?>"
            data-option-id="<?php echo esc_attr($option->id); ?>" data-match-id="<?php echo esc_attr($match->id); ?>"
            data-location="<?php echo esc_attr($location); ?>" title="<?php echo esc_attr($option->option_text); ?>" <?php echo $is_selected ? 'data-selected="true"' : ''; ?>>
            <span class="goalv-option-text"><?php echo esc_html($option->option_text); ?></span>
            <span class="goalv-vote-stats">
                <span class="goalv-percentage"><?php echo esc_html($percentage); ?>%</span>
                <span class="goalv-vote-count">(<?php echo esc_html($vote_count); ?>)</span>
            </span>
        </button>
        <?php
        return ob_get_clean();
    }

    /**
     * Check if voting is allowed for match
     * 
     * @param object $match Match object
     * @return bool
     */
    public function can_vote_on_match($match)
    {
        // No voting on finished matches
        if ($match->status === 'finished') {
            return false;
        }

        // No voting on cancelled/postponed
        if (in_array($match->status, array('cancelled', 'postponed'))) {
            return false;
        }

        return true;
    }

    /**
     * Get time until match starts
     * 
     * @param string $match_date Match date
     * @return string|null Time remaining
     */
    public function get_time_until_match($match_date)
    {
        $match_time = strtotime($match_date);
        $now = time();

        if ($match_time <= $now) {
            return null;
        }

        $diff = $match_time - $now;

        $days = floor($diff / 86400);
        $hours = floor(($diff % 86400) / 3600);
        $minutes = floor(($diff % 3600) / 60);

        if ($days > 0) {
            return sprintf(__('%d days, %d hours', 'goalv'), $days, $hours);
        } elseif ($hours > 0) {
            return sprintf(__('%d hours, %d minutes', 'goalv'), $hours, $minutes);
        } else {
            return sprintf(__('%d minutes', 'goalv'), $minutes);
        }
    }

    /**
     * Get match result indicator (home win, away win, draw)
     * 
     * @param object $match Match object
     * @return string|null Result indicator
     */
    public function get_match_result($match)
    {
        if ($match->status !== 'finished') {
            return null;
        }

        if ($match->home_score > $match->away_score) {
            return 'home_win';
        } elseif ($match->away_score > $match->home_score) {
            return 'away_win';
        } else {
            return 'draw';
        }
    }

    /**
     * Load template file
     * 
     * @param string $template_name Template name (without .php)
     * @param array $args Template arguments
     */
    public function load_template($template_name, $args = array())
    {
        $template_path = GOALV_PLUGIN_PATH . 'templates/' . $template_name . '.php';

        if (file_exists($template_path)) {
            extract($args);
            include $template_path;
        } else {
            echo '<div class="goalv-error">' .
                sprintf(__('Template %s not found.', 'goalv'), $template_name) .
                '</div>';
        }
    }

    /**
     * Render competition badge
     * 
     * @param string $competition_name Competition name
     * @return string HTML
     */
    public function render_competition_badge($competition_name)
    {
        return sprintf(
            '<span class="goalv-competition-badge">%s</span>',
            esc_html($competition_name)
        );
    }

    /**
     * Get score display HTML
     * 
     * @param object $match Match object
     * @return string HTML
     */
    public function get_score_display($match)
    {
        if (in_array($match->status, array('finished', 'live', 'paused'))) {
            return sprintf(
                '<span class="goalv-score-display">%d - %d</span>',
                $match->home_score,
                $match->away_score
            );
        }

        return '<span class="goalv-vs-text">VS</span>';
    }
}