<?php
/**
 * API Settings Admin Page
 * Configuration for API-Football Ultra
 * 
 * @package GoalV
 * @since 8.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get current API key
$api_key = get_option('goalv_api_football_key', '');
$has_key = !empty($api_key);

// Get API usage stats if key exists
$usage_stats = array();
if ($has_key) {
    $client = new GoalV_API_Football_Client();
    $usage_stats = $client->get_request_stats();
}
?>

<div class="goalv-admin-section">
    <div class="goalv-section-header">
        <h2><?php _e('API-Football Configuration', 'goalv'); ?></h2>
        <p class="description">
            <?php _e('Configure your API-Football Ultra credentials. This API powers all match data, live scores, and competition information.', 'goalv'); ?>
        </p>
    </div>

    <!-- API Key Configuration Card -->
    <div class="goalv-card">
        <h3><?php _e('API Key', 'goalv'); ?></h3>
        
        <form method="post" action="options.php" id="goalv-api-settings-form">
            <?php settings_fields('goalv_api_settings'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="goalv_api_football_key"><?php _e('API Key', 'goalv'); ?></label>
                    </th>
                    <td>
                        <div class="goalv-input-group">
                            <input 
                                type="password" 
                                id="goalv_api_football_key" 
                                name="goalv_api_football_key" 
                                value="<?php echo esc_attr($api_key); ?>" 
                                class="regular-text"
                                placeholder="<?php esc_attr_e('Enter your API-Football key', 'goalv'); ?>"
                            />
                            <button type="button" id="toggle-api-key" class="button">
                                <span class="dashicons dashicons-visibility"></span>
                            </button>
                        </div>
                        <p class="description">
                            <?php 
                            printf(
                                __('Get your API key from %sAPI-Football%s. Ultra plan provides 75,000 requests/day.', 'goalv'),
                                '<a href="https://www.api-football.com/pricing" target="_blank">',
                                '</a>'
                            );
                            ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Connection Status', 'goalv'); ?></th>
                    <td>
                        <div id="api-connection-status">
                            <?php if ($has_key): ?>
                                <span class="goalv-status-badge goalv-status-unknown">
                                    <span class="dashicons dashicons-warning"></span>
                                    <?php _e('Not tested', 'goalv'); ?>
                                </span>
                            <?php else: ?>
                                <span class="goalv-status-badge goalv-status-error">
                                    <span class="dashicons dashicons-dismiss"></span>
                                    <?php _e('No API key configured', 'goalv'); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <div style="margin-top: 10px;">
                            <button 
                                type="button" 
                                id="test-api-connection" 
                                class="button button-secondary"
                                <?php echo !$has_key ? 'disabled' : ''; ?>
                            >
                                <span class="dashicons dashicons-update"></span>
                                <?php _e('Test Connection', 'goalv'); ?>
                            </button>
                            <span class="spinner" id="test-api-spinner"></span>
                        </div>
                        
                        <div id="api-test-result" style="margin-top: 10px;"></div>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(__('Save API Key', 'goalv')); ?>
        </form>
    </div>

    <?php if ($has_key && !empty($usage_stats)): ?>
    <!-- API Usage Statistics Card -->
    <div class="goalv-card" style="margin-top: 20px;">
        <h3><?php _e('API Usage Statistics', 'goalv'); ?></h3>
        
        <div class="goalv-stats-grid">
            <div class="goalv-stat-item">
                <div class="goalv-stat-label"><?php _e('Requests Today', 'goalv'); ?></div>
                <div class="goalv-stat-value"><?php echo number_format($usage_stats['requests_today']); ?></div>
            </div>
            
            <div class="goalv-stat-item">
                <div class="goalv-stat-label"><?php _e('Daily Limit', 'goalv'); ?></div>
                <div class="goalv-stat-value"><?php echo number_format($usage_stats['daily_limit']); ?></div>
            </div>
            
            <div class="goalv-stat-item">
                <div class="goalv-stat-label"><?php _e('Remaining', 'goalv'); ?></div>
                <div class="goalv-stat-value"><?php echo number_format($usage_stats['remaining']); ?></div>
            </div>
            
            <div class="goalv-stat-item">
                <div class="goalv-stat-label"><?php _e('Usage', 'goalv'); ?></div>
                <div class="goalv-stat-value">
                    <?php echo number_format($usage_stats['percentage_used'], 1); ?>%
                </div>
            </div>
        </div>
        
        <!-- Progress Bar -->
        <div class="goalv-progress-bar" style="margin-top: 15px;">
            <div class="goalv-progress-fill" style="width: <?php echo min(100, $usage_stats['percentage_used']); ?>%;"></div>
        </div>
        
        <?php if ($usage_stats['percentage_used'] > 80): ?>
            <div class="notice notice-warning inline" style="margin-top: 15px;">
                <p>
                    <span class="dashicons dashicons-warning"></span>
                    <?php _e('You are approaching your daily API limit. Requests will be throttled to prevent exhaustion.', 'goalv'); ?>
                </p>
            </div>
        <?php endif; ?>
        
        <p class="description" style="margin-top: 15px;">
            <?php 
            printf(
                __('Limit resets at: %s UTC', 'goalv'),
                date('H:i:s', $usage_stats['reset_time'])
            );
            ?>
        </p>
    </div>
    <?php endif; ?>

    <!-- API Information Card -->
    <div class="goalv-card" style="margin-top: 20px;">
        <h3><?php _e('API Information', 'goalv'); ?></h3>
        
        <table class="widefat">
            <tbody>
                <tr>
                    <th style="width: 200px;"><?php _e('Provider', 'goalv'); ?></th>
                    <td>API-Football (api-sports.io)</td>
                </tr>
                <tr>
                    <th><?php _e('Documentation', 'goalv'); ?></th>
                    <td>
                        <a href="https://www.api-football.com/documentation-v3" target="_blank">
                            <?php _e('View API Documentation', 'goalv'); ?>
                            <span class="dashicons dashicons-external"></span>
                        </a>
                    </td>
                </tr>
                <tr>
                    <th><?php _e('Plan Required', 'goalv'); ?></th>
                    <td>Ultra Plan ($29/month) - 75,000 requests/day</td>
                </tr>
                <tr>
                    <th><?php _e('Base URL', 'goalv'); ?></th>
                    <td><code>https://v3.football.api-sports.io/</code></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Toggle API key visibility
    $('#toggle-api-key').on('click', function() {
        const $input = $('#goalv_api_football_key');
        const $icon = $(this).find('.dashicons');
        
        if ($input.attr('type') === 'password') {
            $input.attr('type', 'text');
            $icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
        } else {
            $input.attr('type', 'password');
            $icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
        }
    });
    
    // Test API connection
    $('#test-api-connection').on('click', function() {
        const $button = $(this);
        const $spinner = $('#test-api-spinner');
        const $status = $('#api-connection-status');
        const $result = $('#api-test-result');
        
        $button.prop('disabled', true);
        $spinner.addClass('is-active');
        $result.html('');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'goalv_test_api_connection',
                nonce: '<?php echo wp_create_nonce('goalv_admin_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $status.html(
                        '<span class="goalv-status-badge goalv-status-success">' +
                        '<span class="dashicons dashicons-yes"></span>' +
                        '<?php _e('Connected', 'goalv'); ?></span>'
                    );
                    
                    $result.html(
                        '<div class="notice notice-success inline">' +
                        '<p><strong><?php _e('Connection successful!', 'goalv'); ?></strong> ' +
                        response.data.message + '</p></div>'
                    );
                } else {
                    $status.html(
                        '<span class="goalv-status-badge goalv-status-error">' +
                        '<span class="dashicons dashicons-dismiss"></span>' +
                        '<?php _e('Connection failed', 'goalv'); ?></span>'
                    );
                    
                    $result.html(
                        '<div class="notice notice-error inline">' +
                        '<p><strong><?php _e('Connection failed:', 'goalv'); ?></strong> ' +
                        response.data + '</p></div>'
                    );
                }
            },
            error: function() {
                $status.html(
                    '<span class="goalv-status-badge goalv-status-error">' +
                    '<span class="dashicons dashicons-dismiss"></span>' +
                    '<?php _e('Error', 'goalv'); ?></span>'
                );
                
                $result.html(
                    '<div class="notice notice-error inline">' +
                    '<p><?php _e('Network error occurred.', 'goalv'); ?></p></div>'
                );
            },
            complete: function() {
                $button.prop('disabled', false);
                $spinner.removeClass('is-active');
            }
        });
    });
});
</script>