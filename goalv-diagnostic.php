<?php
/**
 * GoalV Standalone Diagnostic & Fix Tool
 * 
 * INSTALLATION:
 * 1. Save this file as: goalv-diagnostic.php
 * 2. Upload to: wp-content/plugins/GoalvPreviction/
 * 3. Access at: https://goalvote.com/wp-content/plugins/GoalvPreviction/goalv-diagnostic.php
 * 
 * SECURITY: This file checks for admin login before running
 */

// Load WordPress
require_once('../../../wp-load.php');

// Security check - must be logged in as admin
if (!is_user_logged_in() || !current_user_can('manage_options')) {
    wp_die('You must be logged in as an administrator to access this tool.');
}

// Handle actions
$action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
$message = '';
$message_type = '';

if ($action === 'fix_stuck_matches') {
    global $wpdb;
    $matches_table = $wpdb->prefix . 'goalv_matches';
    
    $updated = $wpdb->query("
        UPDATE {$matches_table}
        SET status = 'finished', updated_at = NOW()
        WHERE LOWER(status) = 'live'
        AND match_date < DATE_SUB(NOW(), INTERVAL 3 HOUR)
    ");
    
    $message = "✅ Fixed {$updated} stuck matches!";
    $message_type = 'success';
}

if ($action === 'clear_old_live') {
    global $wpdb;
    $matches_table = $wpdb->prefix . 'goalv_matches';
    
    $updated = $wpdb->query("
        UPDATE {$matches_table}
        SET status = 'finished', updated_at = NOW()
        WHERE LOWER(status) = 'live'
        AND match_date < DATE_SUB(NOW(), INTERVAL 1 DAY)
    ");
    
    $message = "✅ Cleared {$updated} old LIVE matches (older than 24 hours)!";
    $message_type = 'success';
}

if ($action === 'reschedule_live_sync') {
    wp_clear_scheduled_hook('goalv_live_sync');
    
    if (!wp_next_scheduled('goalv_live_sync')) {
        wp_schedule_event(time(), 'every_30_seconds', 'goalv_live_sync');
        $message = "✅ Live sync cron rescheduled successfully!";
        $message_type = 'success';
    } else {
        $message = "✅ Live sync was already scheduled!";
        $message_type = 'info';
    }
}

if ($action === 'test_cron') {
    // Force run the live sync function
    if (class_exists('GoalV_Sync_Scheduler')) {
        $scheduler = new GoalV_Sync_Scheduler();
        $scheduler->run_live_sync();
        $message = "✅ Manually triggered live sync function!";
        $message_type = 'success';
    } else {
        $message = "❌ Could not find GoalV_Sync_Scheduler class";
        $message_type = 'error';
    }
}

// Get diagnostic data
global $wpdb;
$matches_table = $wpdb->prefix . 'goalv_matches';
$logs_table = $wpdb->prefix . 'goalv_sync_logs';
$live_scores_table = $wpdb->prefix . 'goalv_live_scores';

// Find stuck LIVE matches
$stuck_matches = $wpdb->get_results("
    SELECT id, api_match_id, home_team, away_team, status, match_date, updated_at,
           TIMESTAMPDIFF(HOUR, match_date, NOW()) as hours_stuck
    FROM {$matches_table}
    WHERE LOWER(status) = 'live'
    ORDER BY match_date DESC
    LIMIT 50
");

// Get recent sync logs
$recent_logs = $wpdb->get_results("
    SELECT * FROM {$logs_table}
    ORDER BY id DESC
    LIMIT 20
");

// Check cron schedules
$cron_status = array(
    'live_sync' => wp_next_scheduled('goalv_live_sync'),
    'hourly_sync' => wp_next_scheduled('goalv_hourly_sync'),
    'daily_cleanup' => wp_next_scheduled('goalv_daily_cleanup')
);

// Get live scores count
$live_scores_count = $wpdb->get_var("SELECT COUNT(*) FROM {$live_scores_table}");

// Get table structure to verify columns
$logs_columns = $wpdb->get_results("SHOW COLUMNS FROM {$logs_table}");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GoalV Diagnostic Tool</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            min-height: 100vh;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            color: white;
            padding: 30px;
        }
        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        .header p {
            opacity: 0.9;
            font-size: 16px;
        }
        .content {
            padding: 30px;
        }
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-weight: 600;
        }
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #10b981;
        }
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #ef4444;
        }
        .alert-warning {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #f59e0b;
        }
        .alert-info {
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #3b82f6;
        }
        .section {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 25px;
        }
        .section h2 {
            color: #1f2937;
            font-size: 20px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .badge-danger {
            background: #ef4444;
            color: white;
        }
        .badge-success {
            background: #10b981;
            color: white;
        }
        .badge-warning {
            background: #f59e0b;
            color: white;
        }
        .badge-info {
            background: #3b82f6;
            color: white;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 8px;
            overflow: hidden;
        }
        thead {
            background: #1f2937;
            color: white;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        th {
            font-weight: 600;
            font-size: 14px;
        }
        td {
            font-size: 14px;
            color: #374151;
        }
        tbody tr:hover {
            background: #f9fafb;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 15px;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }
        .btn-primary {
            background: #667eea;
            color: white;
        }
        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        .btn-danger {
            background: #ef4444;
            color: white;
        }
        .btn-danger:hover {
            background: #dc2626;
        }
        .btn-success {
            background: #10b981;
            color: white;
        }
        .btn-success:hover {
            background: #059669;
        }
        .btn-warning {
            background: #f59e0b;
            color: white;
        }
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            border: 2px solid #e5e7eb;
        }
        .stat-card h3 {
            color: #6b7280;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 8px;
        }
        .stat-card .value {
            font-size: 32px;
            font-weight: 700;
            color: #1f2937;
        }
        .stat-card.danger .value {
            color: #ef4444;
        }
        .stat-card.success .value {
            color: #10b981;
        }
        .action-buttons {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-top: 20px;
        }
        code {
            background: #1f2937;
            color: #10b981;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 13px;
        }
        .timestamp {
            font-size: 12px;
            color: #6b7280;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔧 GoalV Diagnostic & Fix Tool</h1>
            <p>Real-time system diagnostics and automated fixes</p>
        </div>

        <div class="content">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <!-- Quick Stats -->
            <div class="stat-grid">
                <div class="stat-card <?php echo count($stuck_matches) > 0 ? 'danger' : 'success'; ?>">
                    <h3>Stuck LIVE Matches</h3>
                    <div class="value"><?php echo count($stuck_matches); ?></div>
                </div>
                <div class="stat-card">
                    <h3>Sync Logs</h3>
                    <div class="value"><?php echo count($recent_logs); ?></div>
                </div>
                <div class="stat-card">
                    <h3>Live Scores Data</h3>
                    <div class="value"><?php echo $live_scores_count; ?></div>
                </div>
                <div class="stat-card <?php echo $cron_status['live_sync'] ? 'success' : 'danger'; ?>">
                    <h3>Live Sync Status</h3>
                    <div class="value"><?php echo $cron_status['live_sync'] ? '✓' : '✗'; ?></div>
                </div>
            </div>

            <!-- Cron Status -->
            <div class="section">
                <h2>📅 WP-Cron Schedule Status</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Cron Hook</th>
                            <th>Status</th>
                            <th>Next Run</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>goalv_live_sync</code> (Every 30 seconds)</td>
                            <td>
                                <?php if ($cron_status['live_sync']): ?>
                                    <span class="badge badge-success">Scheduled</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Not Scheduled</span>
                                <?php endif; ?>
                            </td>
                            <td class="timestamp">
                                <?php 
                                if ($cron_status['live_sync']) {
                                    echo date('Y-m-d H:i:s', $cron_status['live_sync']);
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <td><code>goalv_hourly_sync</code></td>
                            <td>
                                <?php if ($cron_status['hourly_sync']): ?>
                                    <span class="badge badge-success">Scheduled</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">Not Scheduled</span>
                                <?php endif; ?>
                            </td>
                            <td class="timestamp">
                                <?php 
                                if ($cron_status['hourly_sync']) {
                                    echo date('Y-m-d H:i:s', $cron_status['hourly_sync']);
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <td><code>goalv_daily_cleanup</code></td>
                            <td>
                                <?php if ($cron_status['daily_cleanup']): ?>
                                    <span class="badge badge-success">Scheduled</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">Not Scheduled</span>
                                <?php endif; ?>
                            </td>
                            <td class="timestamp">
                                <?php 
                                if ($cron_status['daily_cleanup']) {
                                    echo date('Y-m-d H:i:s', $cron_status['daily_cleanup']);
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div class="action-buttons">
                    <a href="?action=reschedule_live_sync" class="btn btn-warning">Reschedule Live Sync</a>
                    <a href="?action=test_cron" class="btn btn-primary">Test Run Live Sync Now</a>
                </div>
            </div>

            <!-- Stuck Matches -->
            <?php if (count($stuck_matches) > 0): ?>
            <div class="section">
                <h2>⚠️ Stuck LIVE Matches (<?php echo count($stuck_matches); ?>)</h2>
                
                <div class="alert alert-warning" style="margin-bottom: 20px;">
                    <strong>Issue:</strong> These matches are marked as LIVE but their match date has passed. 
                    They should be marked as "finished".
                </div>

                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Match</th>
                            <th>Status</th>
                            <th>Match Date</th>
                            <th>Hours Stuck</th>
                            <th>Last Updated</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stuck_matches as $match): ?>
                        <tr>
                            <td><?php echo $match->id; ?></td>
                            <td><strong><?php echo esc_html($match->home_team); ?></strong> vs <strong><?php echo esc_html($match->away_team); ?></strong></td>
                            <td><span class="badge badge-danger">LIVE</span></td>
                            <td class="timestamp"><?php echo $match->match_date; ?></td>
                            <td><span class="badge badge-warning"><?php echo $match->hours_stuck; ?>h</span></td>
                            <td class="timestamp"><?php echo $match->updated_at; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="action-buttons">
                    <a href="?action=fix_stuck_matches" class="btn btn-danger" onclick="return confirm('Fix all matches stuck for more than 3 hours?')">
                        Fix Matches (3+ Hours Old)
                    </a>
                    <a href="?action=clear_old_live" class="btn btn-danger" onclick="return confirm('This will mark ALL old LIVE matches as finished. Continue?')">
                        Clear All Old LIVE (24+ Hours)
                    </a>
                </div>
            </div>
            <?php else: ?>
            <div class="section">
                <h2>✅ No Stuck Matches Found</h2>
                <p style="color: #10b981; margin-top: 10px;">All matches are properly synced!</p>
            </div>
            <?php endif; ?>

            <!-- Recent Sync Logs -->
            <div class="section">
                <h2>📋 Recent Sync Logs (Last 20)</h2>
                
                <?php if (count($recent_logs) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Message</th>
                            <th>Timestamp</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_logs as $log): ?>
                        <tr>
                            <td><?php echo $log->id; ?></td>
                            <td><code><?php echo esc_html($log->sync_type ?? 'N/A'); ?></code></td>
                            <td>
                                <?php 
                                $status = $log->status ?? 'unknown';
                                $badge_class = $status === 'success' ? 'badge-success' : ($status === 'error' ? 'badge-danger' : 'badge-info');
                                ?>
                                <span class="badge <?php echo $badge_class; ?>"><?php echo esc_html($status); ?></span>
                            </td>
                            <td><?php echo esc_html($log->message ?? 'No message'); ?></td>
                            <td class="timestamp">
                                <?php 
                                // Try different column names
                                $timestamp = $log->created_at ?? $log->started_at ?? $log->sync_time ?? 'Unknown';
                                echo $timestamp;
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="alert alert-warning">
                    <strong>⚠️ No sync logs found!</strong> This indicates the sync logging is broken.
                    <br><br>
                    <strong>Likely cause:</strong> Database column mismatch (code uses <code>sync_time</code> but table has <code>started_at</code>)
                </div>
                <?php endif; ?>
            </div>

            <!-- Database Schema Check -->
            <div class="section">
                <h2>🗄️ Sync Logs Table Schema</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Column Name</th>
                            <th>Type</th>
                            <th>Null</th>
                            <th>Key</th>
                            <th>Default</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs_columns as $col): ?>
                        <tr>
                            <td><code><?php echo $col->Field; ?></code></td>
                            <td><?php echo $col->Type; ?></td>
                            <td><?php echo $col->Null; ?></td>
                            <td><?php echo $col->Key; ?></td>
                            <td><?php echo $col->Default ?? 'NULL'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div class="alert alert-info" style="margin-top: 20px;">
                    <strong>📌 Important:</strong> Check if the table has <code>started_at</code> or <code>sync_time</code> column.
                    The code might be using the wrong column name!
                </div>
            </div>

            <!-- System Info -->
            <div class="section">
                <h2>ℹ️ System Information</h2>
                <table>
                    <tbody>
                        <tr>
                            <td><strong>WordPress Version</strong></td>
                            <td><?php echo get_bloginfo('version'); ?></td>
                        </tr>
                        <tr>
                            <td><strong>PHP Version</strong></td>
                            <td><?php echo phpversion(); ?></td>
                        </tr>
                        <tr>
                            <td><strong>MySQL Version</strong></td>
                            <td><?php echo $wpdb->db_version(); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Table Prefix</strong></td>
                            <td><code><?php echo $wpdb->prefix; ?></code></td>
                        </tr>
                        <tr>
                            <td><strong>Current Time</strong></td>
                            <td><?php echo current_time('mysql'); ?></td>
                        </tr>
                        <tr>
                            <td><strong>WP-Cron Enabled</strong></td>
                            <td><?php echo defined('DISABLE_WP_CRON') && DISABLE_WP_CRON ? '❌ Disabled' : '✅ Enabled'; ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Instructions -->
            <div class="alert alert-info">
                <strong>🔄 After Making Changes:</strong>
                <ol style="margin: 10px 0 0 20px; line-height: 1.8;">
                    <li>Refresh this page to see updated results</li>
                    <li>Check your live match page to verify fixes</li>
                    <li>Go to WP-Crontrol and click "Run now" on <code>goalv_live_sync</code> to test</li>
                    <li>Clear browser cache if changes don't appear</li>
                </ol>
            </div>
        </div>
    </div>
</body>
</html>