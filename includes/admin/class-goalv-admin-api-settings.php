<?php
/**
 * GoalV Admin API Settings Module
 * Handles API-Football Ultra configuration
 * 
 * @package GoalV
 * @subpackage Admin
 * @since 8.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class GoalV_Admin_API_Settings
{
    /**
     * Constructor
     */
    public function __construct()
    {
        // Register AJAX handlers
        add_action('wp_ajax_goalv_test_api_connection', array($this, 'ajax_test_api_connection'));
        add_action('wp_ajax_goalv_save_api_key', array($this, 'ajax_save_api_key'));
        add_action('wp_ajax_goalv_get_api_usage', array($this, 'ajax_get_api_usage'));
    }

    /**
     * Render API settings page
     */
    public function render()
    {
        $api_key = get_option('goalv_api_football_key', '');
        $live_sync_enabled = get_option('goalv_enable_live_sync', true);
        ?>

        <div class="goalv-admin-section">
            <h2><?php _e('API-Football Ultra Configuration', 'goalv'); ?></h2>
            <p class="description">
                <?php _e('Configure your API-Football Ultra subscription. This replaces the old football-data.org API.', 'goalv'); ?>
            </p>

            <form method="post" action="options.php" id="goalv-api-settings-form">
                <?php settings_fields('goalv_api_settings'); ?>

                <table class="form-table">
                    <!-- API Key -->
                    <tr>
                        <th scope="row">
                            <label for="goalv_api_football_key"><?php _e('API-Football Key', 'goalv'); ?></label>
                        </th>
                        <td>
                            <input type="password" 
                                   id="goalv_api_football_key" 
                                   name="goalv_api_football_key"
                                   value="<?php echo esc_attr($api_key); ?>" 
                                   class="regular-text" 
                                   autocomplete="off" />
                            
                            <button type="button" 
                                    id="toggle-api-key" 
                                    class="button button-secondary">
                                <span class="dashicons dashicons-visibility"></span>
                                <?php _e('Show/Hide', 'goalv'); ?>
                            </button>

                            <button type="button" 
                                    id="test-api-connection" 
                                    class="button button-secondary"
                                    <?php echo empty($api_key) ? 'disabled' : ''; ?>>
                                <span class="dashicons dashicons-admin-plugins"></span>
                                <?php _e('Test Connection', 'goalv'); ?>
                            </button>

                            <span id="api-test-loader" class="spinner"></span>

                            <p class="description">
                                <?php 
                                printf(
                                    __('Get your API key from %s. Ultra plan: $29/month, 75k requests/day.', 'goalv'),
                                    '<a href="https://www.api-football.com/pricing" target="_blank">API-Football.com</a>'
                                );
                                ?>
                            </p>

                            <div id="api-test-result" style="margin-top: 10px;"></div>
                        </td>
                    </tr>

                    <!-- Live Sync Toggle -->
                    <tr>
                        <th scope="row">
                            <label for="goalv_enable_live_sync"><?php _e('Live Score Updates', 'goalv'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       id="goalv_enable_live_sync" 
                                       name="goalv_enable_live_sync" 
                                       value="1" 
                                       <?php checked($live_sync_enabled, true); ?> />
                                <?php _e('Enable automatic live score updates (30-second polling)', 'goalv'); ?>
                            </label>
                            <p class="description">
                                <?php _e('When enabled, live matches will be updated every 30 seconds. Disable to reduce API usage.', 'goalv'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(__('Save API Settings', 'goalv')); ?>
            </form>

           

            <!-- API Information -->
            <div class="goalv-api-info" style="margin-top: 30px; padding: 15px; background: #f9f9f9; border-left: 4px solid #0073aa;">
                <h4><?php _e('What Changed in v8.0?', 'goalv'); ?></h4>
                <ul>
                    <li><strong><?php _e('Old API:', 'goalv'); ?></strong> football-data.org (free tier, limited)</li>
                    <li><strong><?php _e('New API:', 'goalv'); ?></strong> API-Football Ultra (paid, 75k requests/day)</li>
                    <li><strong><?php _e('Benefits:', 'goalv'); ?></strong> <?php _e('1200+ leagues, live scores, historical data, match events', 'goalv'); ?></li>
                 
                </ul>
            </div>
        </div>

        <?php
    }

    /**
     * AJAX: Test API connection
     */
    public function ajax_test_api_connection()
    {
        // Verify nonce
        check_ajax_referer('goalv_admin_nonce', 'nonce');

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'goalv'));
        }

        $api_key = get_option('goalv_api_football_key', '');

        if (empty($api_key)) {
            wp_send_json_error(__('API key not configured', 'goalv'));
        }

        try {
            // Test connection using API client
            $api_client = new GoalV_API_Football_Client();
            $response = $api_client->request('timezone');

            if (isset($response['response']) && is_array($response['response'])) {
                wp_send_json_success(array(
                    'message' => __('API connection successful!', 'goalv'),
                    'timezones_available' => count($response['response'])
                ));
            } else {
                wp_send_json_error(__('API returned unexpected response', 'goalv'));
            }
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * AJAX: Save API key
     */
    public function ajax_save_api_key()
    {
        // Verify nonce
        check_ajax_referer('goalv_admin_nonce', 'nonce');

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'goalv'));
        }

        $api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';

        if (empty($api_key)) {
            wp_send_json_error(__('API key cannot be empty', 'goalv'));
        }

        // Validate key format (basic check)
        if (strlen($api_key) < 20) {
            wp_send_json_error(__('API key appears invalid (too short)', 'goalv'));
        }

        // Save the key
        update_option('goalv_api_football_key', $api_key);

        wp_send_json_success(array(
            'message' => __('API key saved successfully', 'goalv')
        ));
    }

    /**
     * AJAX: Get API usage stats
     */
    public function ajax_get_api_usage()
    {
        // Verify nonce
        check_ajax_referer('goalv_admin_nonce', 'nonce');

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'goalv'));
        }

        try {
            // Get usage from API client
            $api_client = new GoalV_API_Football_Client();
            $usage = $api_client->get_rate_limit_status();

            if ($usage) {
                $html = $this->render_usage_display($usage);
                wp_send_json_success(array('html' => $html));
            } else {
                wp_send_json_error(__('Unable to fetch usage stats', 'goalv'));
            }
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Render API usage display
     */
    private function render_usage_display($usage)
    {
        $used = isset($usage['requests_today']) ? $usage['requests_today'] : 0;
        $limit = isset($usage['daily_limit']) ? $usage['daily_limit'] : 75000;  
        $remaining = $limit - $used;
        $percentage = ($used / $limit) * 100;

        // Determine status color
        if ($percentage < 50) {
            $status_class = 'goalv-usage-good';
            $status_text = __('Good', 'goalv');
        } elseif ($percentage < 80) {
            $status_class = 'goalv-usage-warning';
            $status_text = __('Warning', 'goalv');
        } else {
            $status_class = 'goalv-usage-danger';
            $status_text = __('Critical', 'goalv');
        }

        ob_start();
        ?>
        <div class="goalv-usage-stats <?php echo esc_attr($status_class); ?>">
            <div class="goalv-usage-header">
                <h4><?php _e('API Usage (Today)', 'goalv'); ?></h4>
                <span class="goalv-usage-status"><?php echo esc_html($status_text); ?></span>
            </div>

            <div class="goalv-usage-meter">
                <div class="goalv-usage-bar">
                    <div class="goalv-usage-fill" style="width: <?php echo esc_attr($percentage); ?>%;"></div>
                </div>
            </div>

            <div class="goalv-usage-numbers">
                <div class="goalv-usage-stat">
                    <span class="goalv-usage-label"><?php _e('Used', 'goalv'); ?></span>
                    <span class="goalv-usage-value"><?php echo number_format($used); ?></span>
                </div>
                <div class="goalv-usage-stat">
                    <span class="goalv-usage-label"><?php _e('Remaining', 'goalv'); ?></span>
                    <span class="goalv-usage-value"><?php echo number_format($remaining); ?></span>
                </div>
                <div class="goalv-usage-stat">
                    <span class="goalv-usage-label"><?php _e('Daily Limit', 'goalv'); ?></span>
                    <span class="goalv-usage-value"><?php echo number_format($limit); ?></span>
                </div>
            </div>

            <?php if ($percentage > 80): ?>
                <div class="goalv-usage-warning-message">
                    <span class="dashicons dashicons-warning"></span>
                    <?php _e('High API usage detected! Consider reducing sync frequency.', 'goalv'); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}