<?php
/**
 * System Info Admin Page
 * System health checks and diagnostics
 * 
 * @package GoalV
 * @since 8.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get system information
global $wpdb;

// Database table status
$tables = array(
    'competitions' => $wpdb->prefix . 'goalv_competitions',
    'teams' => $wpdb->prefix . 'goalv_teams',
    'matches' => $wpdb->prefix . 'goalv_matches',
    'live_scores' => $wpdb->prefix . 'goalv_live_scores',
    'match_events' => $wpdb->prefix . 'goalv_match_events',
    'sync_logs' => $wpdb->prefix . 'goalv_sync_logs',
    'vote_options' => $wpdb->prefix . 'goalv_vote_options',
    'vote_categories' => $wpdb->prefix . 'goalv_vote_categories',
    'votes' => $wpdb->prefix . 'goalv_votes',
    'vote_summary' => $wpdb->prefix . 'goalv_vote_summary'
);

// Get row counts
$table_stats = array();
foreach ($tables as $key => $table) {
    $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
    $table_stats[$key] = array(
        'name' => $table,
        'count' => $count,
        'exists' => $wpdb->get_var("SHOW TABLES LIKE '$table'") ? true : false
    );
}

// Get sync manager for health check
$sync_manager = new GoalV_Sync_Manager();
$health = $sync_manager->health_check();

// Get API client
$api_client = new GoalV_API_Football_Client();
$recent_errors = $api_client->get_recent_errors(10);

// Get WP-Cron info
$sync_scheduler = new GoalV_Sync_Scheduler();
$cron_health = $sync_scheduler->check_cron_health();

// Get WordPress info
$wp_info = array(
    'version' => get_bloginfo('version'),
    'php_version' => phpversion(),
    'mysql_version' => $wpdb->db_version(),
    'memory_limit' => ini_get('memory_limit'),
    'max_execution_time' => ini_get('max_execution_time'),
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'timezone' => wp_timezone_string()
);

// Plugin version
$plugin_version = get_option('goalv_db_version', '8.1.0');
$db_installed = get_option('goalv_db_installed', '-');
?>

<div class="goalv-admin-section">
    <div class="goalv-section-header">
        <h2><?php _e('System Information', 'goalv'); ?></h2>
        <p class="description">
            <?php _e('System health, database status, and diagnostic information.', 'goalv'); ?>
        </p>
    </div>

    <!-- Health Status Overview -->
    <div class="goalv-card">
        <h3><?php _e('System Health', 'goalv'); ?></h3>
        
        <div class="goalv-health-status">
            <?php
            $health_class = '';
            $health_icon = '';
            $health_text = '';
            
            switch ($health['status']) {
                case 'healthy':
                    $health_class = 'goalv-health-good';
                    $health_icon = 'yes-alt';
                    $health_text = __('System is healthy', 'goalv');
                    break;
                case 'warning':
                    $health_class = 'goalv-health-warning';
                    $health_icon = 'warning';
                    $health_text = __('System has warnings', 'goalv');
                    break;
                case 'error':
                    $health_class = 'goalv-health-error';
                    $health_icon = 'dismiss';
                    $health_text = __('System has errors', 'goalv');
                    break;
            }
            ?>
            
            <div class="goalv-health-badge <?php echo $health_class; ?>">
                <span class="dashicons dashicons-<?php echo $health_icon; ?>"></span>
                <span><?php echo $health_text; ?></span>
            </div>
            
            <?php if (!empty($health['issues'])): ?>
                <ul class="goalv-health-issues">
                    <?php foreach ($health['issues'] as $issue): ?>
                        <li>
                            <span class="dashicons dashicons-warning"></span>
                            <?php echo esc_html($issue); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        
        <div style="margin-top: 15px;">
            <button type="button" id="run-health-check-btn" class="button button-primary">
                <span class="dashicons dashicons-update"></span>
                <?php _e('Run Health Check', 'goalv'); ?>
            </button>
            
            <button type="button" id="clear-cache-btn" class="button button-secondary">
                <span class="dashicons dashicons-trash"></span>
                <?php _e('Clear Cache', 'goalv'); ?>
            </button>
            
            <span class="spinner" id="health-spinner"></span>
        </div>
        
        <div id="health-result" style="margin-top: 15px;"></div>
    </div>

    <!-- Database Status -->
    <div class="goalv-card" style="margin-top: 20px;">
        <h3><?php _e('Database Tables', 'goalv'); ?></h3>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Table Name', 'goalv'); ?></th>
                    <th><?php _e('Status', 'goalv'); ?></th>
                    <th><?php _e('Row Count', 'goalv'); ?></th>
                    <th><?php _e('Actions', 'goalv'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($table_stats as $key => $stats): ?>
                    <tr>
                        <td>
                            <code><?php echo esc_html($stats['name']); ?></code>
                        </td>
                        <td>
                            <?php if ($stats['exists']): ?>
                                <span class="goalv-status-badge goalv-status-success">
                                    <span class="dashicons dashicons-yes"></span>
                                    <?php _e('OK', 'goalv'); ?>
                                </span>
                            <?php else: ?>
                                <span class="goalv-status-badge goalv-status-error">
                                    <span class="dashicons dashicons-dismiss"></span>
                                    <?php _e('Missing', 'goalv'); ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?php echo number_format($stats['count']); ?></strong> rows
                        </td>
                        <td>
                            <?php if ($stats['exists']): ?>
                                <button type="button" 
                                        class="button button-small optimize-table-btn" 
                                        data-table="<?php echo esc_attr($stats['name']); ?>">
                                    <?php _e('Optimize', 'goalv'); ?>
                                </button>
                            <?php else: ?>
                                <button type="button" 
                                        class="button button-small button-primary create-table-btn" 
                                        data-table="<?php echo esc_attr($key); ?>">
                                    <?php _e('Create', 'goalv'); ?>
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="2"><strong><?php _e('Total Records:', 'goalv'); ?></strong></th>
                    <th colspan="2">
                        <strong><?php echo number_format(array_sum(array_column($table_stats, 'count'))); ?></strong> rows
                    </th>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- WordPress & Server Info -->
    <div class="goalv-card" style="margin-top: 20px;">
        <h3><?php _e('WordPress & Server Information', 'goalv'); ?></h3>
        
        <table class="widefat">
            <tbody>
                <tr>
                    <th style="width: 250px;"><?php _e('Plugin Version', 'goalv'); ?></th>
                    <td><strong><?php echo esc_html($plugin_version); ?></strong></td>
                </tr>
                <tr>
                    <th><?php _e('Database Installed', 'goalv'); ?></th>
                    <td><?php echo esc_html($db_installed); ?></td>
                </tr>
                <tr>
                    <th><?php _e('WordPress Version', 'goalv'); ?></th>
                    <td><?php echo esc_html($wp_info['version']); ?></td>
                </tr>
                <tr>
                    <th><?php _e('PHP Version', 'goalv'); ?></th>
                    <td>
                        <?php echo esc_html($wp_info['php_version']); ?>
                        <?php if (version_compare($wp_info['php_version'], '7.4', '<')): ?>
                            <span class="goalv-status-badge goalv-status-warning">
                                <?php _e('Outdated', 'goalv'); ?>
                            </span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th><?php _e('MySQL Version', 'goalv'); ?></th>
                    <td><?php echo esc_html($wp_info['mysql_version']); ?></td>
                </tr>
                <tr>
                    <th><?php _e('Memory Limit', 'goalv'); ?></th>
                    <td><?php echo esc_html($wp_info['memory_limit']); ?></td>
                </tr>
                <tr>
                    <th><?php _e('Max Execution Time', 'goalv'); ?></th>
                    <td><?php echo esc_html($wp_info['max_execution_time']); ?> seconds</td>
                </tr>
                <tr>
                    <th><?php _e('Upload Max Filesize', 'goalv'); ?></th>
                    <td><?php echo esc_html($wp_info['upload_max_filesize']); ?></td>
                </tr>
                <tr>
                    <th><?php _e('Timezone', 'goalv'); ?></th>
                    <td><?php echo esc_html($wp_info['timezone']); ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- WP-Cron Status -->
    <div class="goalv-card" style="margin-top: 20px;">
        <h3><?php _e('WP-Cron Status', 'goalv'); ?></h3>
        
        <?php if (!$cron_health['wp_cron_enabled']): ?>
            <div class="notice notice-error inline">
                <p>
                    <strong><?php _e('WP-Cron is disabled!', 'goalv'); ?></strong>
                    <?php _e('Automated syncing will not work. Configure an external cron job or enable WP-Cron.', 'goalv'); ?>
                </p>
            </div>
        <?php else: ?>
            <div class="notice notice-success inline">
                <p>
                    <span class="dashicons dashicons-yes"></span>
                    <?php _e('WP-Cron is enabled and functioning.', 'goalv'); ?>
                </p>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($cron_health['issues'])): ?>
            <ul class="goalv-health-issues">
                <?php foreach ($cron_health['issues'] as $issue): ?>
                    <li>
                        <span class="dashicons dashicons-warning"></span>
                        <?php echo esc_html($issue); ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        
        <p style="margin-top: 15px;">
            <button type="button" id="test-cron-btn" class="button button-secondary">
                <span class="dashicons dashicons-update"></span>
                <?php _e('Test Cron Jobs', 'goalv'); ?>
            </button>
        </p>
    </div>

    <!-- Recent API Errors -->
    <?php if (!empty($recent_errors)): ?>
    <div class="goalv-card" style="margin-top: 20px;">
        <h3><?php _e('Recent API Errors', 'goalv'); ?></h3>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 150px;"><?php _e('Time', 'goalv'); ?></th>
                    <th><?php _e('Endpoint', 'goalv'); ?></th>
                    <th><?php _e('Error', 'goalv'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_errors as $error): ?>
                    <tr>
                        <td><?php echo date_i18n('M j, H:i:s', strtotime($error['timestamp'])); ?></td>
                        <td><code><?php echo esc_html($error['endpoint']); ?></code></td>
                        <td><?php echo esc_html($error['error']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Debug Information (Expandable) -->
    <div class="goalv-card" style="margin-top: 20px;">
        <h3><?php _e('Debug Information', 'goalv'); ?></h3>
        
        <button type="button" id="toggle-debug-info" class="button button-secondary">
            <span class="dashicons dashicons-visibility"></span>
            <?php _e('Show Debug Info', 'goalv'); ?>
        </button>
        
        <div id="debug-info-content" style="display: none; margin-top: 15px;">
            <textarea readonly style="width: 100%; height: 300px; font-family: monospace; font-size: 12px;"><?php
// Generate debug info
$debug_info = array(
    'Plugin Version' => $plugin_version,
    'WordPress Version' => $wp_info['version'],
    'PHP Version' => $wp_info['php_version'],
    'MySQL Version' => $wp_info['mysql_version'],
    'Active Theme' => wp_get_theme()->get('Name') . ' ' . wp_get_theme()->get('Version'),
    'Active Plugins' => implode(', ', array_keys(get_plugins())),
    'WP_DEBUG' => defined('WP_DEBUG') && WP_DEBUG ? 'Enabled' : 'Disabled',
    'WP_CRON' => defined('DISABLE_WP_CRON') && DISABLE_WP_CRON ? 'Disabled' : 'Enabled',
    'Server Software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'Memory Limit' => $wp_info['memory_limit'],
    'Max Execution Time' => $wp_info['max_execution_time'] . 's',
    'Timezone' => $wp_info['timezone'],
    'Database Tables' => count(array_filter(array_column($table_stats, 'exists'))) . ' of ' . count($table_stats),
    'Total Database Rows' => number_format(array_sum(array_column($table_stats, 'count')))
);

foreach ($debug_info as $key => $value) {
    echo esc_html($key) . ': ' . esc_html($value) . "\n";
}
            ?></textarea>
            
            <button type="button" id="copy-debug-info" class="button button-secondary" style="margin-top: 10px;">
                <span class="dashicons dashicons-clipboard"></span>
                <?php _e('Copy to Clipboard', 'goalv'); ?>
            </button>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    const nonce = '<?php echo wp_create_nonce('goalv_admin_nonce'); ?>';
    
    // Run health check
    $('#run-health-check-btn').on('click', function() {
        const $button = $(this);
        const $spinner = $('#health-spinner');
        const $result = $('#health-result');
        
        $button.prop('disabled', true);
        $spinner.addClass('is-active');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'goalv_run_health_check',
                nonce: nonce
            },
            success: function(response) {
                if (response.success) {
                    $result.html(
                        '<div class="notice notice-success inline"><p>' +
                        response.data.message + '</p></div>'
                    );
                    
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    $result.html(
                        '<div class="notice notice-error inline"><p>' +
                        response.data + '</p></div>'
                    );
                }
            },
            complete: function() {
                $button.prop('disabled', false);
                $spinner.removeClass('is-active');
            }
        });
    });
    
    // Clear cache
    $('#clear-cache-btn').on('click', function() {
        if (!confirm('<?php _e('Are you sure you want to clear all API cache?', 'goalv'); ?>')) {
            return;
        }
        
        const $button = $(this);
        const $spinner = $('#health-spinner');
        
        $button.prop('disabled', true);
        $spinner.addClass('is-active');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'goalv_clear_cache',
                nonce: nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#health-result').html(
                        '<div class="notice notice-success inline"><p>' +
                        '<?php _e('Cache cleared successfully!', 'goalv'); ?></p></div>'
                    );
                }
            },
            complete: function() {
                $button.prop('disabled', false);
                $spinner.removeClass('is-active');
            }
        });
    });
    
    // Toggle debug info
    $('#toggle-debug-info').on('click', function() {
        const $content = $('#debug-info-content');
        const $icon = $(this).find('.dashicons');
        
        if ($content.is(':visible')) {
            $content.slideUp();
            $icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
            $(this).find('span:not(.dashicons)').text('<?php _e('Show Debug Info', 'goalv'); ?>');
        } else {
            $content.slideDown();
            $icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
            $(this).find('span:not(.dashicons)').text('<?php _e('Hide Debug Info', 'goalv'); ?>');
        }
    });
    
    // Copy debug info
    $('#copy-debug-info').on('click', function() {
        const $textarea = $('#debug-info-content textarea');
        $textarea.select();
        document.execCommand('copy');
        
        $(this).text('<?php _e('Copied!', 'goalv'); ?>');
        
        setTimeout(() => {
            $(this).html('<span class="dashicons dashicons-clipboard"></span> <?php _e('Copy to Clipboard', 'goalv'); ?>');
        }, 2000);
    });
    
    // Optimize table
    $('.optimize-table-btn').on('click', function() {
        const table = $(this).data('table');
        const $button = $(this);
        
        $button.prop('disabled', true).text('<?php _e('Optimizing...', 'goalv'); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'goalv_optimize_table',
                nonce: nonce,
                table: table
            },
            success: function(response) {
                if (response.success) {
                    $button.text('<?php _e('Done!', 'goalv'); ?>');
                    setTimeout(function() {
                        $button.prop('disabled', false).text('<?php _e('Optimize', 'goalv'); ?>');
                    }, 2000);
                }
            }
        });
    });
});
</script>