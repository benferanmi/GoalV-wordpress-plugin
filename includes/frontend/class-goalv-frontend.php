<?php
/**
 * GoalV Frontend Coordinator - REFACTORED
 * Lightweight orchestrator for modular frontend system
 * 
 * @package GoalV
 * @version 8.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class GoalV_Frontend
{
    private $query;
    private $shortcode;
    private $renderer;

    public function __construct()
    {
        // Initialize modular components
        $this->query = new GoalV_Match_Query();
        $this->shortcode = new GoalV_Shortcode_Handler();
        $this->renderer = new GoalV_Match_Renderer();

        // Register hooks
        add_shortcode('goalv_matches', array($this, 'matches_shortcode'));
        add_action('wp_footer', array($this, 'add_browser_id_script'));
        add_action('init', array($this, 'register_custom_rewrites'));
    }

    /**
     * Main shortcode handler
     * 
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function matches_shortcode($atts)
    {
        // Ensure assets are loaded
        $this->force_enqueue_assets();

        // Parse attributes
        $parsed_atts = $this->shortcode->parse_attributes($atts);

        // Get matches
        $matches = $this->shortcode->get_matches($parsed_atts);

        // No matches found
        if (empty($matches)) {
            return $this->shortcode->get_no_matches_message($parsed_atts);
        }

        // Prepare template arguments
        $template_args = $this->shortcode->prepare_template_args($matches, $parsed_atts);

        // Render template
        ob_start();
        $this->renderer->load_template('matches-' . $parsed_atts['template'], $template_args);
        return ob_get_clean();
    }

    /**
     * Get single match data (for single page template)
     * 
     * @param int $match_id Match database ID
     * @return object|null Match object
     */
    public function get_single_match_data($match_id)
    {
        return $this->query->get_single_match($match_id);
    }

    /**
     * Force enqueue frontend assets
     */
    public function force_enqueue_assets()
    {
        if (!wp_script_is('goalv-frontend', 'enqueued')) {
            wp_enqueue_style(
                'goalv-style',
                GOALV_PLUGIN_URL . 'assets/css/goalv-style.css',
                array(),
                GOALV_VERSION
            );

            wp_enqueue_script(
                'goalv-frontend',
                GOALV_PLUGIN_URL . 'assets/js/goalv-frontend.js',
                array('jquery'),
                GOALV_VERSION,
                true
            );

            wp_localize_script('goalv-frontend', 'goalv_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('goalv_vote_nonce'),
                'is_user_logged_in' => is_user_logged_in(),
                'allow_multiple_votes' => get_option('goalv_allow_multiple_votes', 'no') === 'yes'
            ));
        }
    }

    /**
     * Add browser ID script for guest voting
     */
    public function add_browser_id_script()
    {
        if (!is_user_logged_in()) {
            ?>
            <script>
                function goalvGenerateBrowserId() {
                    var browserId = localStorage.getItem('goalv_browser_id');
                    if (!browserId) {
                        browserId = 'guest_' + Math.random().toString(36).substr(2, 9) + '_' + Date.now();
                        localStorage.setItem('goalv_browser_id', browserId);
                    }
                    return browserId;
                }
                window.goalvBrowserId = goalvGenerateBrowserId();
            </script>
            <?php
        }
    }

    /**
     * Register custom rewrite rules for match URLs
     */
    public function register_custom_rewrites()
    {
        // Custom URL structure: /match/123/
        add_rewrite_rule(
            '^match/([0-9]+)/?$',
            'index.php?goalv_match_id=$matches[1]',
            'top'
        );

        add_rewrite_tag('%goalv_match_id%', '([0-9]+)');
    }

    /**
     * Delegate methods to renderer (backward compatibility)
     */
    public function get_team_logo($logo_url, $team_name = '')
    {
        return $this->renderer->get_team_logo($logo_url, $team_name);
    }

    public function format_match_date($date_string)
    {
        return $this->renderer->format_match_date($date_string);
    }

    public function get_status_display($status, $home_score = 0, $away_score = 0)
    {
        return $this->renderer->get_status_display($status, $home_score, $away_score);
    }

    public function get_match_permalink($match_id)
    {
        return $this->renderer->get_match_permalink($match_id);
    }

    /**
     * Get query instance (for external use)
     */
    public function get_query()
    {
        return $this->query;
    }

    /**
     * Get renderer instance (for external use)
     */
    public function get_renderer()
    {
        return $this->renderer;
    }
}