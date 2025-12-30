<?php
/**
 * ONE-TIME CLEANUP SCRIPT
 * Run this ONCE to fix matches stuck in LIVE status
 * 
 * INSTRUCTIONS:
 * 1. Save this as: wp-content/plugins/GoalvPreviction/cleanup-stuck-matches.php
 * 2. Go to: https://goalvote.com/wp-admin/tools.php (or any admin page)
 * 3. Add ?run_goalv_cleanup=1 to URL
 * 4. Example: https://goalvote.com/wp-admin/tools.php?run_goalv_cleanup=1
 * 5. DELETE this file after running once
 */

// Hook into WordPress admin
add_action('admin_init', 'goalv_run_cleanup_script');

function goalv_run_cleanup_script() {
    // Check if cleanup is requested
    if (!isset($_GET['run_goalv_cleanup']) || $_GET['run_goalv_cleanup'] != '1') {
        return;
    }

    // Security check
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    global $wpdb;
    $matches_table = $wpdb->prefix . 'goalv_matches';
    
    echo '<div style="padding: 20px; background: #fff; margin: 20px;">';
    echo '<h1>GoalV Cleanup Script</h1>';
    echo '<hr>';
    
    // Step 1: Find stuck matches
    $stuck_matches = $wpdb->get_results(
        "SELECT id, api_match_id, home_team_id, away_team_id, match_date, status 
         FROM {$matches_table} 
         WHERE status = 'live' 
         AND match_date < DATE_SUB(NOW(), INTERVAL 3 HOUR)
         ORDER BY match_date DESC"
    );
    
    echo '<h2>Step 1: Found Stuck Matches</h2>';
    echo '<p>Matches stuck in LIVE status from more than 3 hours ago:</p>';
    
    if (empty($stuck_matches)) {
        echo '<p style="color: green;"><strong>✓ No stuck matches found! System is healthy.</strong></p>';
    } else {
        echo '<table border="1" cellpadding="10" style="border-collapse: collapse;">';
        echo '<tr><th>Match ID</th><th>API ID</th><th>Match Date</th><th>Current Status</th></tr>';
        
        foreach ($stuck_matches as $match) {
            echo '<tr>';
            echo '<td>' . $match->id . '</td>';
            echo '<td>' . $match->api_match_id . '</td>';
            echo '<td>' . $match->match_date . '</td>';
            echo '<td style="color: red; font-weight: bold;">' . $match->status . '</td>';
            echo '</tr>';
        }
        
        echo '</table>';
        echo '<p><strong>Total stuck matches: ' . count($stuck_matches) . '</strong></p>';
    }
    
    // Step 2: Fix stuck matches
    if (!empty($stuck_matches)) {
        echo '<hr>';
        echo '<h2>Step 2: Fixing Stuck Matches</h2>';
        
        $updated = $wpdb->query(
            "UPDATE {$matches_table} 
             SET status = 'finished', last_updated = NOW()
             WHERE status = 'live' 
             AND match_date < DATE_SUB(NOW(), INTERVAL 3 HOUR)"
        );
        
        if ($updated !== false) {
            echo '<p style="color: green; font-size: 18px;"><strong>✓ SUCCESS!</strong></p>';
            echo '<p>Fixed <strong>' . $updated . '</strong> stuck matches.</p>';
            echo '<p>All matches have been updated to "finished" status.</p>';
        } else {
            echo '<p style="color: red;"><strong>✗ ERROR: Update failed</strong></p>';
            echo '<p>Database error: ' . $wpdb->last_error . '</p>';
        }
    }
    
    // Step 3: Check sync logs table
    echo '<hr>';
    echo '<h2>Step 3: Checking Sync Logs</h2>';
    
    $logs_table = $wpdb->prefix . 'goalv_sync_logs';
    $log_count = $wpdb->get_var("SELECT COUNT(*) FROM {$logs_table}");
    
    echo '<p>Total sync logs in database: <strong>' . $log_count . '</strong></p>';
    
    if ($log_count == 0) {
        echo '<p style="color: orange;"><strong>⚠ WARNING: No sync logs found!</strong></p>';
        echo '<p>This means sync logging is not working. Check class-goalv-sync-manager.php</p>';
    } else {
        // Show recent logs
        $recent_logs = $wpdb->get_results(
            "SELECT * FROM {$logs_table} ORDER BY started_at DESC LIMIT 5"
        );
        
        echo '<p style="color: green;">✓ Sync logging is working</p>';
        echo '<h3>Recent Logs:</h3>';
        echo '<table border="1" cellpadding="10" style="border-collapse: collapse;">';
        echo '<tr><th>Type</th><th>Status</th><th>Started At</th><th>Message</th></tr>';
        
        foreach ($recent_logs as $log) {
            echo '<tr>';
            echo '<td>' . $log->sync_type . '</td>';
            echo '<td>' . $log->status . '</td>';
            echo '<td>' . $log->started_at . '</td>';
            echo '<td>' . ($log->error_message ? $log->error_message : 'N/A') . '</td>';
            echo '</tr>';
        }
        
        echo '</table>';
    }
    
    // Step 4: Test log insertion
    echo '<hr>';
    echo '<h2>Step 4: Testing Log Insertion</h2>';
    
    $test_log = $wpdb->insert(
        $logs_table,
        array(
            'sync_type' => 'test',
            'status' => 'success',
            'error_message' => 'Cleanup script test log',
            'started_at' => current_time('mysql'),
            'completed_at' => current_time('mysql')
        ),
        array('%s', '%s', '%s', '%s', '%s')
    );
    
    if ($test_log !== false) {
        echo '<p style="color: green;"><strong>✓ Log insertion works!</strong></p>';
        echo '<p>Test log ID: ' . $wpdb->insert_id . '</p>';
    } else {
        echo '<p style="color: red;"><strong>✗ Log insertion FAILED</strong></p>';
        echo '<p>Error: ' . $wpdb->last_error . '</p>';
    }
    
    // Step 5: Check WP-Cron
    echo '<hr>';
    echo '<h2>Step 5: Checking WP-Cron Status</h2>';
    
    $crons = _get_cron_array();
    $goalv_crons = array('goalv_live_sync', 'goalv_hourly_sync', 'goalv_daily_cleanup');
    
    echo '<table border="1" cellpadding="10" style="border-collapse: collapse;">';
    echo '<tr><th>Cron Hook</th><th>Status</th><th>Next Run</th></tr>';
    
    foreach ($goalv_crons as $hook) {
        $scheduled = false;
        $next_run = 'Not scheduled';
        
        foreach ($crons as $timestamp => $cron) {
            if (isset($cron[$hook])) {
                $scheduled = true;
                $next_run = date('Y-m-d H:i:s', $timestamp);
                break;
            }
        }
        
        $status_color = $scheduled ? 'green' : 'red';
        $status_text = $scheduled ? '✓ Scheduled' : '✗ Not Scheduled';
        
        echo '<tr>';
        echo '<td>' . $hook . '</td>';
        echo '<td style="color: ' . $status_color . '; font-weight: bold;">' . $status_text . '</td>';
        echo '<td>' . $next_run . '</td>';
        echo '</tr>';
    }
    
    echo '</table>';
    
    // Final summary
    echo '<hr>';
    echo '<h2>🎉 Cleanup Complete!</h2>';
    echo '<div style="background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px;">';
    echo '<h3 style="margin-top: 0;">Next Steps:</h3>';
    echo '<ol>';
    echo '<li><strong>Replace the broken PHP files</strong> with the fixed versions provided</li>';
    echo '<li><strong>Delete this cleanup script file</strong> after running</li>';
    echo '<li><strong>Go to WP-Cron Control</strong> and manually run "goalv_live_sync" once</li>';
    echo '<li><strong>Monitor the Sync Manager page</strong> to see if logs appear</li>';
    echo '</ol>';
    echo '</div>';
    
    echo '<p style="margin-top: 20px;"><a href="' . admin_url('admin.php?page=goalv-settings&tab=sync') . '" class="button button-primary">Go to Sync Manager</a></p>';
    
    echo '</div>';
    
    die(); // Stop WordPress from loading
}