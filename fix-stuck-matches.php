<?php
/**
 * FIX #1: Clean Stuck LIVE Matches
 * 
 * This script fixes:
 * - Matches with status='live' and invalid dates (0000-00-00)
 * - Matches with status='live' older than 4 hours
 * 
 * Upload to: wp-content/plugins/goalv-football-predictions/
 * Access at: https://goalvote.com/wp-content/plugins/goalv-football-predictions/fix-stuck-matches.php
 */

// Load WordPress
require_once('../../../wp-load.php');

// Security check
if (!current_user_can('manage_options')) {
    wp_die('Access denied. Administrator privileges required.');
}

global $wpdb;
$prefix = $wpdb->prefix;
$matches_table = $prefix . 'goalv_matches';

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Fix Stuck Matches</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            max-width: 900px;
            margin: 40px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .card {
            background: white;
            padding: 25px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        h1 { color: #dc3545; margin-top: 0; }
        h2 { color: #2271b1; border-bottom: 2px solid #2271b1; padding-bottom: 10px; }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            text-decoration: none;
            font-weight: 600;
        }
        .btn:hover { background: #c82333; }
        .btn-success { background: #28a745; }
        .btn-success:hover { background: #218838; }
        .success { background: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 15px 0; }
        .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 15px 0; }
        .error { background: #f8d7da; border-left: 4px solid #dc3545; padding: 15px; margin: 15px 0; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f0f0f0; font-weight: 600; }
        .live-badge { background: #dc3545; color: white; padding: 4px 8px; border-radius: 3px; font-weight: bold; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; }
    </style>
</head>
<body>
    <h1>🔧 Fix Stuck LIVE Matches</h1>

    <?php
    // Check if fix was requested
    if (isset($_GET['action']) && $_GET['action'] === 'fix' && check_admin_referer('goalv_fix_matches', 'nonce')) {
        ?>
        <div class="card">
            <h2>🔄 Running Fixes...</h2>
            
            <?php
            // FIX 1: Update matches with invalid dates (0000-00-00)
            $fix1 = $wpdb->query(
                "UPDATE {$matches_table} 
                 SET status = 'finished', 
                     match_date = NOW(), 
                     last_updated = NOW()
                 WHERE status = 'live' 
                 AND (match_date = '0000-00-00 00:00:00' OR match_date IS NULL OR match_date = '')"
            );
            
            if ($fix1 !== false) {
                echo '<div class="success">✅ <strong>Fixed ' . $fix1 . ' matches with invalid dates (0000-00-00)</strong></div>';
            } else {
                echo '<div class="error">❌ Error fixing invalid dates: ' . $wpdb->last_error . '</div>';
            }
            
            // FIX 2: Finalize matches older than 4 hours
            $fix2 = $wpdb->query(
                "UPDATE {$matches_table} 
                 SET status = 'finished', 
                     last_updated = NOW()
                 WHERE status = 'live' 
                 AND match_date < DATE_SUB(NOW(), INTERVAL 4 HOUR)
                 AND match_date != '0000-00-00 00:00:00'"
            );
            
            if ($fix2 !== false) {
                echo '<div class="success">✅ <strong>Fixed ' . $fix2 . ' stale matches (older than 4 hours)</strong></div>';
            } else {
                echo '<div class="error">❌ Error fixing stale matches: ' . $wpdb->last_error . '</div>';
            }
            
            // FIX 3: Clean up orphaned live_scores entries
            $live_scores_table = $prefix . 'goalv_live_scores';
            $fix3 = $wpdb->query(
                "DELETE ls FROM {$live_scores_table} ls
                 LEFT JOIN {$matches_table} m ON ls.match_id = m.id
                 WHERE m.status != 'live' OR m.id IS NULL"
            );
            
            if ($fix3 !== false) {
                echo '<div class="success">✅ <strong>Cleaned ' . $fix3 . ' orphaned live_scores entries</strong></div>';
            }
            
            // Summary
            $total_fixed = $fix1 + $fix2;
            ?>
            
            <div class="success" style="margin-top: 30px; font-size: 18px;">
                <strong>🎉 FIXES COMPLETED!</strong><br>
                <strong>Total matches fixed: <?php echo $total_fixed; ?></strong>
            </div>
            
            <p style="margin-top: 20px;">
                <a href="<?php echo admin_url('admin.php?page=goalv-settings&tab=sync'); ?>" class="btn btn-success">
                    ← Back to Sync Manager
                </a>
                <a href="?action=check" class="btn" style="background: #6c757d; margin-left: 10px;">
                    Check Again
                </a>
            </p>
        </div>
        <?php
    } else {
        // SHOW CURRENT STATUS
        ?>
        <div class="card">
            <h2>📊 Current Status</h2>
            
            <?php
            // Get stuck matches with invalid dates
            $stuck_invalid = $wpdb->get_results(
                "SELECT id, home_team, away_team, status, match_date, last_updated
                 FROM {$matches_table}
                 WHERE status = 'live' 
                 AND (match_date = '0000-00-00 00:00:00' OR match_date IS NULL OR match_date = '')"
            );
            
            // Get stuck matches with old dates
            $stuck_old = $wpdb->get_results(
                "SELECT id, home_team, away_team, status, match_date, last_updated,
                        TIMESTAMPDIFF(HOUR, match_date, NOW()) as hours_since
                 FROM {$matches_table}
                 WHERE status = 'live' 
                 AND match_date < DATE_SUB(NOW(), INTERVAL 4 HOUR)
                 AND match_date != '0000-00-00 00:00:00'"
            );
            
            $total_stuck = count($stuck_invalid) + count($stuck_old);
            ?>
            
            <?php if ($total_stuck > 0): ?>
                <div class="error">
                    <strong>⚠️ FOUND <?php echo $total_stuck; ?> STUCK MATCHES</strong>
                </div>
                
                <?php if (!empty($stuck_invalid)): ?>
                    <h3>🔴 Matches with Invalid Dates (<?php echo count($stuck_invalid); ?>)</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Match</th>
                                <th>Status</th>
                                <th>Match Date</th>
                                <th>Last Updated</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stuck_invalid as $match): ?>
                                <tr>
                                    <td><?php echo $match->id; ?></td>
                                    <td><strong><?php echo esc_html($match->home_team . ' vs ' . $match->away_team); ?></strong></td>
                                    <td><span class="live-badge">LIVE</span></td>
                                    <td style="color: red;"><strong><?php echo $match->match_date ?: 'NULL'; ?></strong></td>
                                    <td><?php echo $match->last_updated; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
                
                <?php if (!empty($stuck_old)): ?>
                    <h3>🟠 Stale Matches (Older than 4 hours) (<?php echo count($stuck_old); ?>)</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Match</th>
                                <th>Status</th>
                                <th>Match Date</th>
                                <th>Hours Ago</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stuck_old as $match): ?>
                                <tr>
                                    <td><?php echo $match->id; ?></td>
                                    <td><strong><?php echo esc_html($match->home_team . ' vs ' . $match->away_team); ?></strong></td>
                                    <td><span class="live-badge">LIVE</span></td>
                                    <td><?php echo $match->match_date; ?></td>
                                    <td style="color: red;"><strong><?php echo number_format($match->hours_since); ?> hours</strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
                
                <div class="warning" style="margin-top: 30px;">
                    <h3>🔧 What This Fix Will Do:</h3>
                    <ol>
                        <li>Set all matches with <code>0000-00-00</code> dates to <code>status = 'finished'</code></li>
                        <li>Update their <code>match_date</code> to current time</li>
                        <li>Finalize all matches older than 4 hours</li>
                        <li>Clean up orphaned live_scores entries</li>
                    </ol>
                    <p><strong>This action is SAFE and REVERSIBLE via database backup.</strong></p>
                </div>
                
                <p style="margin-top: 30px;">
                    <a href="<?php echo wp_nonce_url('?action=fix', 'goalv_fix_matches', 'nonce'); ?>" 
                       class="btn"
                       onclick="return confirm('Fix <?php echo $total_stuck; ?> stuck matches?\n\nThis will:\n- Mark them as finished\n- Update their dates\n- Clean orphaned data\n\nContinue?');">
                        🔧 FIX ALL <?php echo $total_stuck; ?> MATCHES NOW
                    </a>
                </p>
                
            <?php else: ?>
                <div class="success">
                    <strong>✅ NO STUCK MATCHES FOUND</strong>
                    <p>All matches are in proper status. System is healthy!</p>
                </div>
                
                <p>
                    <a href="<?php echo admin_url('admin.php?page=goalv-settings&tab=sync'); ?>" class="btn btn-success">
                        ← Back to Sync Manager
                    </a>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }
    ?>
    
    <div style="text-align: center; margin-top: 30px; color: #666;">
        <p>Generated at: <?php echo current_time('Y-m-d H:i:s'); ?></p>
    </div>
</body>
</html>