<?php
/**
 * GoalV Database Query Diagnostic Tool
 * 
 * Upload to: wp-content/plugins/goalv-football-predictions/
 * Access at: https://goalvote.com/wp-content/plugins/goalv-football-predictions/db-diagnostic.php
 */

// Load WordPress
require_once('../../../wp-load.php');

// Security check
if (!current_user_can('manage_options')) {
    wp_die('Access denied. Administrator privileges required.');
}

global $wpdb;
$prefix = $wpdb->prefix;

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GoalV Database Diagnostic</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .section {
            background: white;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2271b1;
            border-bottom: 3px solid #2271b1;
            padding-bottom: 10px;
        }
        h2 {
            color: #135e96;
            margin-top: 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #f0f0f0;
            font-weight: 600;
        }
        .live-status {
            background: #dc3545;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: bold;
        }
        .finished-status {
            background: #28a745;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
        }
        .code {
            background: #f4f4f4;
            padding: 15px;
            border-left: 4px solid #2271b1;
            font-family: 'Courier New', monospace;
            overflow-x: auto;
            margin-top: 10px;
        }
        .warning {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin-top: 10px;
        }
        .error {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            padding: 15px;
            margin-top: 10px;
        }
        .success {
            background: #d4edda;
            border-left: 4px solid #28a745;
            padding: 15px;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <h1>🔍 GoalV Database Diagnostic Tool</h1>

    <!-- Query 1: Check Status Values -->
    <div class="section">
        <h2>1️⃣ Match Status Distribution</h2>
        <p><strong>Query:</strong> Check what status values exist in the database</p>
        <div class="code">
SELECT DISTINCT status, COUNT(*) as count 
FROM <?php echo $prefix; ?>goalv_matches 
GROUP BY status;
        </div>

        <?php
        $status_counts = $wpdb->get_results(
            "SELECT DISTINCT status, COUNT(*) as count 
             FROM {$prefix}goalv_matches 
             GROUP BY status"
        );
        ?>

        <table>
            <thead>
                <tr>
                    <th>Status</th>
                    <th>Count</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($status_counts)): ?>
                    <tr><td colspan="3">No matches found</td></tr>
                <?php else: ?>
                    <?php foreach ($status_counts as $row): ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($row->status); ?></strong>
                                <?php if (strtolower($row->status) === 'live'): ?>
                                    <span class="live-status">LIVE</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo number_format($row->count); ?></td>
                            <td>
                                <?php if (strtolower($row->status) === 'live' && $row->count > 0): ?>
                                    <span style="color: #dc3545;">⚠️ Found live matches!</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Query 2: Check Specific Matches -->
    <div class="section">
        <h2>2️⃣ Specific "Stuck" Matches Check</h2>
        <p><strong>Query:</strong> Find Leipzig, Betis, and Leeds matches</p>
        <div class="code">
SELECT id, api_match_id, home_team, away_team, status, 
       match_date, last_updated,
       TIMESTAMPDIFF(HOUR, match_date, NOW()) as hours_since_match
FROM <?php echo $prefix; ?>goalv_matches 
WHERE (home_team LIKE '%Leipzig%' OR home_team LIKE '%Betis%' OR home_team LIKE '%Leeds%')
   OR (away_team LIKE '%Frankfurt%' OR away_team LIKE '%Barcelona%' OR away_team LIKE '%Liverpool%')
ORDER BY match_date DESC;
        </div>

        <?php
        $stuck_matches = $wpdb->get_results(
            "SELECT id, api_match_id, home_team, away_team, status, 
                    match_date, last_updated,
                    TIMESTAMPDIFF(HOUR, match_date, NOW()) as hours_since_match
             FROM {$prefix}goalv_matches 
             WHERE (home_team LIKE '%Leipzig%' OR home_team LIKE '%Betis%' OR home_team LIKE '%Leeds%')
                OR (away_team LIKE '%Frankfurt%' OR away_team LIKE '%Barcelona%' OR away_team LIKE '%Liverpool%')
             ORDER BY match_date DESC"
        );
        ?>

        <?php if (empty($stuck_matches)): ?>
            <div class="success">✅ No matches found with those team names</div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Match</th>
                        <th>Status</th>
                        <th>Match Date</th>
                        <th>Hours Ago</th>
                        <th>Last Updated</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stuck_matches as $match): ?>
                        <tr>
                            <td><?php echo $match->id; ?></td>
                            <td>
                                <strong><?php echo esc_html($match->home_team); ?></strong> vs 
                                <strong><?php echo esc_html($match->away_team); ?></strong>
                            </td>
                            <td>
                                <?php if (strtolower($match->status) === 'live'): ?>
                                    <span class="live-status"><?php echo strtoupper($match->status); ?></span>
                                <?php else: ?>
                                    <span class="finished-status"><?php echo strtoupper($match->status); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $match->match_date; ?></td>
                            <td>
                                <?php 
                                $hours = $match->hours_since_match;
                                echo number_format($hours) . ' hours';
                                if ($hours > 4 && strtolower($match->status) === 'live') {
                                    echo ' <span style="color:red;">⚠️ STALE!</span>';
                                }
                                ?>
                            </td>
                            <td><?php echo $match->last_updated; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Query 3: Sync Logs Table Schema -->
    <div class="section">
        <h2>3️⃣ Sync Logs Table Schema</h2>
        <p><strong>Query:</strong> Check column names in sync_logs table</p>
        <div class="code">SHOW COLUMNS FROM <?php echo $prefix; ?>goalv_sync_logs;</div>

        <?php
        $columns = $wpdb->get_results("SHOW COLUMNS FROM {$prefix}goalv_sync_logs");
        ?>

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
                <?php foreach ($columns as $col): ?>
                    <tr>
                        <td>
                            <code><?php echo $col->Field; ?></code>
                            <?php if ($col->Field === 'started_at' || $col->Field === 'created_at'): ?>
                                <strong style="color: #2271b1;">← TIMESTAMP COLUMN</strong>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $col->Type; ?></td>
                        <td><?php echo $col->Null; ?></td>
                        <td><?php echo $col->Key; ?></td>
                        <td><?php echo $col->Default ?: 'NULL'; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php
        $has_started_at = false;
        $has_created_at = false;
        foreach ($columns as $col) {
            if ($col->Field === 'started_at') $has_started_at = true;
            if ($col->Field === 'created_at') $has_created_at = true;
        }
        ?>

        <div style="margin-top: 15px;">
            <?php if ($has_started_at): ?>
                <div class="success">✅ Table has <code>started_at</code> column</div>
            <?php endif; ?>
            
            <?php if ($has_created_at): ?>
                <div class="warning">⚠️ Table has <code>created_at</code> column</div>
            <?php endif; ?>

            <?php if (!$has_started_at && !$has_created_at): ?>
                <div class="error">❌ No timestamp column found!</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Query 4: Recent Sync Logs -->
    <div class="section">
        <h2>4️⃣ Recent Sync Logs (Testing Query)</h2>
        <p><strong>Testing:</strong> Try to fetch logs with correct column name</p>

        <?php
        // Try with started_at (correct)
        $logs_started = $wpdb->get_results(
            "SELECT * FROM {$prefix}goalv_sync_logs 
             ORDER BY started_at DESC LIMIT 5"
        );
        ?>

        <?php if ($wpdb->last_error): ?>
            <div class="error">
                <strong>❌ Query with 'started_at' FAILED:</strong><br>
                <?php echo $wpdb->last_error; ?>
            </div>
        <?php else: ?>
            <div class="success">✅ Query with 'started_at' WORKED! Found <?php echo count($logs_started); ?> logs</div>
            
            <?php if (!empty($logs_started)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Started At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs_started as $log): ?>
                            <tr>
                                <td><?php echo $log->id; ?></td>
                                <td><code><?php echo $log->sync_type; ?></code></td>
                                <td><?php echo $log->status; ?></td>
                                <td><?php echo $log->started_at; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Query 5: Live Matches (Testing Model Method) -->
    <div class="section">
        <h2>5️⃣ Test GoalV_Match::get_by_status('live')</h2>
        <p><strong>Testing:</strong> Use the model method to fetch live matches</p>

        <?php
        try {
            $live_matches_model = GoalV_Match::get_by_status('live');
            ?>
            <div class="success">✅ GoalV_Match::get_by_status('live') WORKED! Found <?php echo count($live_matches_model); ?> matches</div>
            
            <?php if (!empty($live_matches_model)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Match</th>
                            <th>Status</th>
                            <th>Match Date</th>
                            <th>Hours Since</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($live_matches_model as $match): ?>
                            <?php
                            $hours_since = (time() - strtotime($match->match_date)) / 3600;
                            ?>
                            <tr>
                                <td><?php echo $match->id; ?></td>
                                <td>
                                    <?php 
                                    $home = $match->get_home_team();
                                    $away = $match->get_away_team();
                                    echo esc_html($home->name ?? 'Unknown') . ' vs ' . esc_html($away->name ?? 'Unknown');
                                    ?>
                                </td>
                                <td><span class="live-status"><?php echo strtoupper($match->status); ?></span></td>
                                <td><?php echo $match->match_date; ?></td>
                                <td>
                                    <?php 
                                    echo number_format($hours_since, 1) . ' hours';
                                    if ($hours_since > 4) {
                                        echo ' <span style="color:red; font-weight:bold;">⚠️ STALE!</span>';
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p><em>No live matches found</em></p>
            <?php endif; ?>
        <?php
        } catch (Exception $e) {
            ?>
            <div class="error">
                <strong>❌ GoalV_Match::get_by_status('live') FAILED:</strong><br>
                <?php echo $e->getMessage(); ?>
            </div>
            <?php
        }
        ?>
    </div>

    <!-- Summary -->
    <div class="section">
        <h2>📋 Summary & Next Steps</h2>
        
        <?php
        $live_count = 0;
        foreach ($status_counts as $row) {
            if (strtolower($row->status) === 'live') {
                $live_count = $row->count;
            }
        }
        ?>

        <?php if ($live_count > 0): ?>
            <div class="error">
                <h3>❌ ISSUE CONFIRMED:</h3>
                <ul>
                    <li><strong><?php echo $live_count; ?> matches stuck in LIVE status</strong></li>
                    <li>These matches should be marked as 'finished'</li>
                    <li>The finalize_stale_live_matches() function is not running properly</li>
                </ul>
            </div>
        <?php else: ?>
            <div class="success">
                <h3>✅ NO STUCK MATCHES:</h3>
                <p>Database shows 0 matches in LIVE status. The issue might be in how the admin page displays data.</p>
            </div>
        <?php endif; ?>

        <h3>Column Issues Found:</h3>
        <ul>
            <li><strong>Sync Logs Table:</strong> Uses <code><?php echo $has_started_at ? 'started_at' : 'created_at'; ?></code> column</li>
            <li><strong>Admin Page Code:</strong> Queries use <code><?php echo !$has_started_at && $has_created_at ? 'created_at (correct)' : 'created_at (WRONG - should be started_at)'; ?></code></li>
        </ul>

        <h3>What to Share with Developer:</h3>
        <ol>
            <li>Take screenshot of this entire page</li>
            <li>Send the screenshot showing all query results</li>
            <li>Specifically note:
                <ul>
                    <li>How many matches have status='live'</li>
                    <li>Which column exists in sync_logs (started_at or created_at)</li>
                    <li>If GoalV_Match::get_by_status() works</li>
                </ul>
            </li>
        </ol>
    </div>

    <div style="text-align: center; margin-top: 30px; color: #666;">
        <p>Generated at: <?php echo current_time('Y-m-d H:i:s'); ?></p>
        <p><a href="<?php echo admin_url('admin.php?page=goalv-settings&tab=sync'); ?>">← Back to Sync Manager</a></p>
    </div>
</body>
</html>