<?php
/**
 * Sync Manager Admin Page
 * Manual sync controls and sync status monitoring
 * 
 * @package GoalV
 * @since 8.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}
if (!current_user_can('manage_options')) {
    wp_die('Insufficient permissions');
}

// Create and pass nonce
$admin_nonce = wp_create_nonce('goalv_admin_nonce');

// Get sync manager and scheduler
$sync_manager = new GoalV_Sync_Manager();
$sync_scheduler = new GoalV_Sync_Scheduler();

// Get sync stats
$sync_stats = $sync_manager->get_sync_stats();
$schedule_info = $sync_scheduler->get_schedule_info();
$cron_health = $sync_scheduler->check_cron_health();

// Get recent logs
$recent_logs = $sync_manager->get_recent_logs(20);

// Get live matches count
$live_scores_api = new GoalV_API_Live_Scores();
$live_matches_count = $live_scores_api->get_live_matches_count();
?>

<!-- AJAX CONFIG - MUST BE FIRST -->
<script type="text/javascript">
(function() {
    window.goalvAjaxConfig = {
        ajax_url: '<?php echo admin_url('admin-ajax.php'); ?>',
        nonce: '<?php echo wp_create_nonce('goalv_admin_nonce'); ?>'
    };
    console.log('✓ GoalV AJAX Config initialized');
    console.log('  - AJAX URL:', window.goalvAjaxConfig.ajax_url);
    console.log('  - Nonce:', window.goalvAjaxConfig.nonce ? 'Present' : 'MISSING');
})();
</script>

<div class="goalv-admin-section">
    <div class="goalv-section-header">
        <h2><?php _e('Sync Manager', 'goalv'); ?></h2>
        <p class="description">
            <?php _e('Monitor synchronization status and manually trigger sync operations.', 'goalv'); ?>
        </p>
    </div>

    <!-- Quick Stats -->
    <div class="goalv-stats-row">
        <div class="goalv-stat-card">
            <div class="goalv-stat-icon dashicons dashicons-clock"></div>
            <div class="goalv-stat-content">
                <div class="goalv-stat-value">
                    <?php echo $sync_stats['last_full_sync'] ? human_time_diff(strtotime($sync_stats['last_full_sync']), current_time('timestamp')) : 'Never'; ?>
                </div>
                <div class="goalv-stat-label"><?php _e('Last Full Sync', 'goalv'); ?></div>
            </div>
        </div>
        
        <div class="goalv-stat-card">
            <div class="goalv-stat-icon dashicons dashicons-update"></div>
            <div class="goalv-stat-content">
                <div class="goalv-stat-value">
                    <?php echo $sync_stats['last_live_sync'] ? human_time_diff(strtotime($sync_stats['last_live_sync']), current_time('timestamp')) : 'Never'; ?>
                </div>
                <div class="goalv-stat-label"><?php _e('Last Live Update', 'goalv'); ?></div>
            </div>
        </div>
        
        <div class="goalv-stat-card goalv-stat-success">
            <div class="goalv-stat-icon dashicons dashicons-media-spreadsheet"></div>
            <div class="goalv-stat-content">
                <div class="goalv-stat-value"><?php echo number_format($sync_stats['syncs_today']); ?></div>
                <div class="goalv-stat-label"><?php _e('Syncs Today', 'goalv'); ?></div>
            </div>
        </div>
        
        <div class="goalv-stat-card <?php echo $live_matches_count > 0 ? 'goalv-stat-live' : ''; ?>">
            <div class="goalv-stat-icon dashicons dashicons-welcome-view-site"></div>
            <div class="goalv-stat-content">
                <div class="goalv-stat-value"><?php echo number_format($live_matches_count); ?></div>
                <div class="goalv-stat-label"><?php _e('Live Matches', 'goalv'); ?></div>
            </div>
        </div>
    </div>

    <!-- Manual Sync Controls -->
    <div class="goalv-card">
        <h3><?php _e('Manual Sync Operations', 'goalv'); ?></h3>
        
        <div class="goalv-sync-buttons">
            <div class="goalv-sync-button-group">
                <button type="button" id="sync-competitions-btn" class="button button-primary button-large">
                    <span class="dashicons dashicons-download"></span>
                    <?php _e('Fetch Competitions', 'goalv'); ?>
                </button>
                <p class="description"><?php _e('Update league information and metadata', 'goalv'); ?></p>
            </div>
            
            <div class="goalv-sync-button-group">
                <button type="button" id="sync-matches-btn" class="button button-primary button-large">
                    <span class="dashicons dashicons-calendar-alt"></span>
                    <?php _e('Sync Matches (Next 7 Days)', 'goalv'); ?>
                </button>
                <p class="description"><?php _e('Fetch upcoming matches for all active competitions', 'goalv'); ?></p>
            </div>
            
            <div class="goalv-sync-button-group">
                <button type="button" id="sync-live-btn" class="button button-secondary button-large">
                    <span class="dashicons dashicons-update"></span>
                    <?php _e('Update Live Scores', 'goalv'); ?>
                </button>
                <p class="description"><?php _e('Force update all currently live matches', 'goalv'); ?></p>
            </div>
            
            <div class="goalv-sync-button-group">
                <button type="button" id="sync-full-btn" class="button button-secondary button-large">
                    <span class="dashicons dashicons-backup"></span>
                    <?php _e('Force Full Resync', 'goalv'); ?>
                </button>
                <p class="description"><?php _e('Complete sync: competitions + matches + live scores', 'goalv'); ?></p>
            </div>
        </div>
        
        <div id="sync-progress" style="display: none; margin-top: 20px;">
            <div class="goalv-progress-bar">
                <div class="goalv-progress-fill goalv-progress-animated" style="width: 100%;"></div>
            </div>
            <p id="sync-status-text" style="text-align: center; margin-top: 10px;"></p>
        </div>
        
        <div id="sync-result" style="margin-top: 20px;"></div>
    </div>

    <!-- Automated Sync Status -->
    <div class="goalv-card" style="margin-top: 20px;">
        <h3><?php _e('Automated Sync Schedule', 'goalv'); ?></h3>
        
        <?php if (!$cron_health['wp_cron_enabled']): ?>
            <div class="notice notice-warning inline">
                <p>
                    <span class="dashicons dashicons-warning"></span>
                    <strong><?php _e('WP-Cron is disabled.', 'goalv'); ?></strong>
                    <?php _e('Automated syncing will not work. Please configure an external cron job.', 'goalv'); ?>
                </p>
            </div>
        <?php endif; ?>
        
        <table class="widefat">
            <thead>
                <tr>
                    <th><?php _e('Schedule', 'goalv'); ?></th>
                    <th><?php _e('Frequency', 'goalv'); ?></th>
                    <th><?php _e('Next Run', 'goalv'); ?></th>
                    <th><?php _e('Status', 'goalv'); ?></th>
                    <th><?php _e('Actions', 'goalv'); ?></th>
                </tr>
            </thead>
            <tbody>
                <!-- Hourly Full Sync -->
                <tr>
                    <td>
                        <strong><?php _e('Full Sync', 'goalv'); ?></strong>
                        <p class="description"><?php _e('Competitions + Matches', 'goalv'); ?></p>
                    </td>
                    <td><?php _e('Every 1 hour', 'goalv'); ?></td>
                    <td>
                        <?php if ($schedule_info['hourly_sync']['is_scheduled']): ?>
                            <strong><?php echo esc_html($schedule_info['hourly_sync']['next_run']); ?></strong>
                            <p class="description"><?php echo esc_html($schedule_info['hourly_sync']['time_until']); ?></p>
                        <?php else: ?>
                            <span class="goalv-status-badge goalv-status-error"><?php _e('Not Scheduled', 'goalv'); ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($schedule_info['hourly_sync']['is_scheduled']): ?>
                            <span class="goalv-status-badge goalv-status-success">
                                <span class="dashicons dashicons-yes"></span>
                                <?php _e('Active', 'goalv'); ?>
                            </span>
                        <?php else: ?>
                            <span class="goalv-status-badge goalv-status-error">
                                <span class="dashicons dashicons-dismiss"></span>
                                <?php _e('Inactive', 'goalv'); ?>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <button type="button" class="button button-small trigger-hourly-btn">
                            <?php _e('Run Now', 'goalv'); ?>
                        </button>
                    </td>
                </tr>
                
                <!-- Live Score Sync (30 seconds) -->
                <tr>
                    <td>
                        <strong><?php _e('Live Score Updates', 'goalv'); ?></strong>
                        <p class="description"><?php _e('Real-time match scores', 'goalv'); ?></p>
                    </td>
                    <td><?php _e('Every 30 seconds', 'goalv'); ?></td>
                    <td>
                        <?php if ($schedule_info['live_sync']['is_scheduled']): ?>
                            <strong><?php echo esc_html($schedule_info['live_sync']['next_run']); ?></strong>
                            <p class="description"><?php echo esc_html($schedule_info['live_sync']['time_until']); ?></p>
                        <?php else: ?>
                            <span class="goalv-status-badge goalv-status-warning"><?php _e('Disabled', 'goalv'); ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <label class="goalv-toggle">
                            <input type="checkbox" 
                                   id="toggle-live-sync" 
                                   <?php checked($schedule_info['live_sync']['enabled'], true); ?> />
                            <span class="goalv-toggle-slider"></span>
                        </label>
                        <span class="goalv-toggle-label">
                            <?php echo $schedule_info['live_sync']['enabled'] ? __('Enabled', 'goalv') : __('Disabled', 'goalv'); ?>
                        </span>
                    </td>
                    <td>
                        <button type="button" class="button button-small trigger-live-btn">
                            <?php _e('Run Now', 'goalv'); ?>
                        </button>
                    </td>
                </tr>
                
                <!-- Daily Cleanup -->
                <tr>
                    <td>
                        <strong><?php _e('Daily Cleanup', 'goalv'); ?></strong>
                        <p class="description"><?php _e('Logs, cache, old matches', 'goalv'); ?></p>
                    </td>
                    <td><?php _e('Daily at 3:00 AM', 'goalv'); ?></td>
                    <td>
                        <?php if ($schedule_info['daily_cleanup']['is_scheduled']): ?>
                            <strong><?php echo esc_html($schedule_info['daily_cleanup']['next_run']); ?></strong>
                            <p class="description"><?php echo esc_html($schedule_info['daily_cleanup']['time_until']); ?></p>
                        <?php else: ?>
                            <span class="goalv-status-badge goalv-status-error"><?php _e('Not Scheduled', 'goalv'); ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($schedule_info['daily_cleanup']['is_scheduled']): ?>
                            <span class="goalv-status-badge goalv-status-success">
                                <span class="dashicons dashicons-yes"></span>
                                <?php _e('Active', 'goalv'); ?>
                            </span>
                        <?php else: ?>
                            <span class="goalv-status-badge goalv-status-error">
                                <span class="dashicons dashicons-dismiss"></span>
                                <?php _e('Inactive', 'goalv'); ?>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <button type="button" class="button button-small trigger-cleanup-btn">
                            <?php _e('Run Now', 'goalv'); ?>
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Sync Logs -->
    <div class="goalv-card" style="margin-top: 20px;">
        <h3><?php _e('Recent Sync Logs', 'goalv'); ?></h3>
        
        <div class="goalv-logs-controls" style="margin-bottom: 15px;">
            <button type="button" id="refresh-sync-logs" class="button button-secondary">
                <span class="dashicons dashicons-update"></span>
                <?php _e('Refresh', 'goalv'); ?>
            </button>
            
            <button type="button" id="clear-sync-logs" class="button button-secondary">
                <span class="dashicons dashicons-trash"></span>
                <?php _e('Clear Old Logs', 'goalv'); ?>
            </button>
        </div>
        
        <div id="sync-logs-container">
            <?php if (empty($recent_logs)): ?>
                <p class="description"><?php _e('No sync logs found.', 'goalv'); ?></p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 150px;"><?php _e('Time', 'goalv'); ?></th>
                            <th style="width: 120px;"><?php _e('Type', 'goalv'); ?></th>
                            <th style="width: 100px;"><?php _e('Status', 'goalv'); ?></th>
                            <th><?php _e('Details', 'goalv'); ?></th>
                            <th style="width: 100px;"><?php _e('Duration', 'goalv'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_logs as $log): ?>
                            <tr>
                                <td>
                                    <?php echo date_i18n('M j, H:i:s', strtotime($log->started_at)); ?>
                                </td>
                                <td>
                                    <code><?php echo esc_html($log->sync_type); ?></code>
                                </td>
                                <td>
                                    <?php
                                    $status_class = '';
                                    $status_icon = '';
                                    
                                    switch ($log->status) {
                                        case 'success':
                                            $status_class = 'goalv-status-success';
                                            $status_icon = 'yes';
                                            break;
                                        case 'failed':
                                            $status_class = 'goalv-status-error';
                                            $status_icon = 'dismiss';
                                            break;
                                        case 'partial':
                                            $status_class = 'goalv-status-warning';
                                            $status_icon = 'warning';
                                            break;
                                        default:
                                            $status_class = 'goalv-status-info';
                                            $status_icon = 'info';
                                    }
                                    ?>
                                    <span class="goalv-status-badge <?php echo $status_class; ?>">
                                        <span class="dashicons dashicons-<?php echo $status_icon; ?>"></span>
                                        <?php echo ucfirst($log->status); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($log->items_processed > 0): ?>
                                        <strong><?php echo number_format($log->items_processed); ?></strong> processed
                                        (<?php echo number_format($log->items_created); ?> new, 
                                        <?php echo number_format($log->items_updated); ?> updated)
                                    <?php endif; ?>
                                    
                                    <?php if ($log->error_message): ?>
                                        <br><span class="description" style="color: #d63638;">
                                            <?php echo esc_html($log->error_message); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo $log->duration_seconds ? number_format($log->duration_seconds, 2) . 's' : '-'; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- SYNC MANAGER INITIALIZATION -->
<script type="text/javascript">
(function($) {
    'use strict';
    
    $(document).ready(function() {
        console.log('Sync Manager page ready, checking dependencies...');
        
        // Check all required dependencies
        if (!window.GoalV) {
            console.error('ERROR: window.GoalV not loaded!');
            return;
        }
        
        if (!window.GoalV.Ajax) {
            console.error('ERROR: GoalV.Ajax not available!');
            return;
        }
        
        if (!window.GoalV.Sync) {
            console.error('ERROR: GoalV.Sync not loaded!');
            return;
        }
        
        console.log('✓ All dependencies available');
        console.log('  - GoalV.Ajax:', typeof window.GoalV.Ajax.request);
        console.log('  - GoalV.Sync:', typeof window.GoalV.Sync.init);
        
        // Initialize
        window.GoalV.Sync.init();
        console.log('✓ Sync Manager initialized');
    });
})(jQuery);
</script>