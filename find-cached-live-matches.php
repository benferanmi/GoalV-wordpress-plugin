<?php
/**
 * Find Cached Live Matches Data
 * This will locate WHERE the stale "3 live matches" data is cached
 * 
 * Upload to: wp-content/plugins/goalv-football-predictions/
 * Access at: https://goalvote.com/wp-content/plugins/goalv-football-predictions/find-cached-live-matches.php
 */

require_once('../../../wp-load.php');

if (!current_user_can('manage_options')) {
    wp_die('Access denied');
}

global $wpdb;
$prefix = $wpdb->prefix;

// Handle cache clearing
if (isset($_GET['action']) && $_GET['action'] === 'clear_all_cache' && check_admin_referer('clear_cache', 'nonce')) {
    // Clear WordPress transients
    $wpdb->query("DELETE FROM {$prefix}options WHERE option_name LIKE '_transient_%goalv%'");
    $wpdb->query("DELETE FROM {$prefix}options WHERE option_name LIKE '_transient_timeout_%goalv%'");
    
    // Clear object cache
    wp_cache_flush();
    
    // Clear specific GoalV options
    delete_option('goalv_live_matches_cache');
    delete_option('goalv_dashboard_stats');
    
    $message = "✅ All caches cleared! Refresh the Sync Manager page.";
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cache Diagnostic - Find Stale Data</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            padding: 20px;
            min-height: 100vh;
        }
        .container {
            max-width: 1600px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 { font-size: 32px; margin-bottom: 10px; }
        .content { padding: 30px; }
        .section {
            background: #f8f9fa;
            border: 3px solid #dee2e6;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 25px;
        }
        .section h2 {
            color: #667eea;
            font-size: 24px;
            margin-bottom: 20px;
            border-bottom: 3px solid #667eea;
            padding-bottom: 10px;
        }
        .alert {
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 2px solid;
            font-weight: 600;
        }
        .alert-success {
            background: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }
        .alert-danger {
            background: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }
        .alert-warning {
            background: #fff3cd;
            border-color: #ffeaa7;
            color: #856404;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            margin-top: 15px;
        }
        thead {
            background: #343a40;
            color: white;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border: 1px solid #dee2e6;
        }
        tbody tr:hover { background: #f8f9fa; }
        .btn {
            display: inline-block;
            padding: 15px 30px;
            background: #dc3545;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 700;
            font-size: 18px;
            transition: all 0.3s;
        }
        .btn:hover {
            background: #c82333;
            transform: translateY(-2px);
        }
        .btn-success { background: #28a745; }
        .btn-success:hover { background: #218838; }
        code {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 2px 8px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
        }
        .code-block {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 15px;
            border-radius: 6px;
            overflow-x: auto;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            margin: 15px 0;
        }
        .stat-box {
            background: white;
            border: 3px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            margin: 15px 0;
            text-align: center;
        }
        .stat-box h3 {
            color: #6c757d;
            font-size: 13px;
            text-transform: uppercase;
            margin-bottom: 10px;
        }
        .stat-box .value {
            font-size: 48px;
            font-weight: 700;
            color: #667eea;
        }
        .file-path {
            background: #fffbcc;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 15px 0;
            font-family: 'Courier New', monospace;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔍 Cache Diagnostic Tool</h1>
            <p>Finding where "Live Matches Monitor" gets its stale data</p>
        </div>

        <div class="content">

            <?php if (isset($message)): ?>
                <div class="alert alert-success">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <!-- SECTION 1: Verify Database is Clean -->
            <div class="section">
                <h2>1️⃣ Verify Database Status</h2>
                
                <?php
                $db_live_count = $wpdb->get_var(
                    "SELECT COUNT(*) FROM {$prefix}goalv_matches WHERE status = 'live'"
                );
                ?>

                <div class="stat-box">
                    <h3>Database Live Matches</h3>
                    <p class="value"><?php echo $db_live_count; ?></p>
                </div>

                <?php if ($db_live_count == 0): ?>
                    <div class="alert alert-success">
                        ✅ <strong>DATABASE IS CLEAN</strong> - 0 matches with status='live'
                    </div>
                <?php else: ?>
                    <div class="alert alert-danger">
                        ❌ <strong>DATABASE STILL HAS <?php echo $db_live_count; ?> LIVE MATCHES!</strong>
                    </div>
                <?php endif; ?>
            </div>

            <!-- SECTION 2: Check WordPress Transients -->
            <div class="section">
                <h2>2️⃣ WordPress Transients (Temporary Cache)</h2>
                <p>Checking for cached GoalV data in wp_options table...</p>

                <?php
                $transients = $wpdb->get_results(
                    "SELECT option_name, option_value 
                     FROM {$prefix}options 
                     WHERE option_name LIKE '%transient%goalv%'
                        OR option_name LIKE '%goalv%cache%'
                        OR option_name LIKE '%goalv%live%'
                     ORDER BY option_name"
                );
                ?>

                <div class="stat-box">
                    <h3>Found Transients/Cache</h3>
                    <p class="value"><?php echo count($transients); ?></p>
                </div>

                <?php if (!empty($transients)): ?>
                    <div class="alert alert-warning">
                        ⚠️ Found <?php echo count($transients); ?> cached entries
                    </div>

                    <table>
                        <thead>
                            <tr>
                                <th>Option Name</th>
                                <th>Value Preview</th>
                                <th>Contains "live"?</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transients as $t): ?>
                                <tr>
                                    <td><code><?php echo esc_html($t->option_name); ?></code></td>
                                    <td><?php echo esc_html(substr($t->option_value, 0, 100)); ?>...</td>
                                    <td>
                                        <?php 
                                        if (stripos($t->option_value, 'live') !== false) {
                                            echo '🔴 <strong>YES</strong>';
                                        } else {
                                            echo '✅ No';
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="alert alert-success">
                        ✅ No GoalV transients found
                    </div>
                <?php endif; ?>
            </div>

            <!-- SECTION 3: Check AJAX Endpoint -->
            <div class="section">
                <h2>3️⃣ Test Admin AJAX Endpoint</h2>
                <p>Simulating what the "Live Matches Monitor" fetches via AJAX...</p>

                <?php
                // Find the AJAX handler file
                $ajax_file = WP_PLUGIN_DIR . '/goalv-football-predictions/includes/admin/class-goalv-admin-ajax.php';
                $ajax_exists = file_exists($ajax_file);
                ?>

                <div class="alert alert-<?php echo $ajax_exists ? 'success' : 'danger'; ?>">
                    <?php if ($ajax_exists): ?>
                        ✅ AJAX handler file found: <code>class-goalv-admin-ajax.php</code>
                    <?php else: ?>
                        ❌ AJAX handler file NOT found!
                    <?php endif; ?>
                </div>

                <?php if ($ajax_exists): ?>
                    <div class="file-path">
                        <strong>File Location:</strong><br>
                        <?php echo $ajax_file; ?>
                    </div>

                    <p style="margin-top: 20px;"><strong>Looking for these AJAX actions:</strong></p>
                    <ul style="margin: 10px 0 0 20px; line-height: 1.8;">
                        <li><code>goalv_get_live_matches</code></li>
                        <li><code>goalv_get_dashboard_stats</code></li>
                        <li><code>goalv_refresh_live_monitor</code></li>
                    </ul>

                    <?php
                    // Try to directly query what the AJAX would return
                    $live_matches_ajax = $wpdb->get_results(
                        "SELECT id, home_team, away_team, home_score, away_score, 
                                status, competition_id, match_date
                         FROM {$prefix}goalv_matches
                         WHERE status = 'live'
                         ORDER BY match_date ASC
                         LIMIT 10"
                    );
                    ?>

                    <div class="stat-box" style="margin-top: 20px;">
                        <h3>AJAX Query Result</h3>
                        <p class="value"><?php echo count($live_matches_ajax); ?></p>
                    </div>

                    <?php if (empty($live_matches_ajax)): ?>
                        <div class="alert alert-success">
                            ✅ AJAX endpoint would return <strong>0 matches</strong>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-danger">
                            ❌ AJAX endpoint would return <strong><?php echo count($live_matches_ajax); ?> matches</strong>
                        </div>
                        <table>
                            <thead>
                                <tr>
                                    <th>Match</th>
                                    <th>Score</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($live_matches_ajax as $m): ?>
                                    <tr>
                                        <td><?php echo esc_html($m->home_team . ' vs ' . $m->away_team); ?></td>
                                        <td><strong><?php echo $m->home_score; ?> - <?php echo $m->away_score; ?></strong></td>
                                        <td><?php echo $m->status; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- SECTION 4: Check JavaScript Files -->
            <div class="section">
                <h2>4️⃣ JavaScript Files (Frontend State)</h2>
                <p>The "Live Matches Monitor" uses JavaScript to fetch and display data...</p>

                <?php
                $js_files = [
                    'goalv-admin-dashboard.js' => WP_PLUGIN_DIR . '/goalv-football-predictions/assets/js/admin/goalv-admin-dashboard.js',
                    'goalv-admin-sync.js' => WP_PLUGIN_DIR . '/goalv-football-predictions/assets/js/admin/goalv-admin-sync.js',
                    'goalv-state-manager.js' => WP_PLUGIN_DIR . '/goalv-football-predictions/assets/js/shared/goalv-state-manager.js'
                ];
                ?>

                <table>
                    <thead>
                        <tr>
                            <th>File</th>
                            <th>Exists?</th>
                            <th>Likely Function</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($js_files as $name => $path): ?>
                            <tr>
                                <td><code><?php echo $name; ?></code></td>
                                <td>
                                    <?php if (file_exists($path)): ?>
                                        <span style="color: green;">✅ Yes</span>
                                    <?php else: ?>
                                        <span style="color: red;">❌ No</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($name === 'goalv-admin-sync.js'): ?>
                                        <strong>← Likely controls "Live Matches Monitor"</strong>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="alert alert-warning" style="margin-top: 20px;">
                    <strong>🎯 LIKELY CAUSE:</strong><br>
                    The JavaScript file <code>goalv-admin-sync.js</code> is fetching data via AJAX and storing it in browser memory or localStorage.
                    <br><br>
                    <strong>Solution:</strong> User needs to hard refresh (Ctrl+Shift+R) or clear browser cache.
                </div>
            </div>

            <!-- SECTION 5: Check Browser Storage -->
            <div class="section">
                <h2>5️⃣ Browser Storage Instructions</h2>
                
                <div class="alert alert-warning">
                    <strong>⚠️ IMPORTANT:</strong> The stale "3 live matches" might be stored in your browser!
                </div>

                <p style="margin: 20px 0;"><strong>Clear Browser Data:</strong></p>
                <ol style="margin-left: 20px; line-height: 2;">
                    <li>Open the Sync Manager page</li>
                    <li>Open browser DevTools (F12)</li>
                    <li>Go to <strong>Application</strong> tab</li>
                    <li>Under <strong>Storage</strong>, check:
                        <ul style="margin-left: 20px;">
                            <li>Local Storage</li>
                            <li>Session Storage</li>
                            <li>IndexedDB</li>
                        </ul>
                    </li>
                    <li>Look for anything containing "goalv" or "live_matches"</li>
                    <li>Delete those entries</li>
                    <li>Then hard refresh: <code>Ctrl + Shift + R</code></li>
                </ol>
            </div>

            <!-- SECTION 6: Nuclear Option -->
            <div class="section" style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); border: none; text-align: center;">
                <h2 style="color: white; border-bottom: 3px solid white;">💣 NUCLEAR CACHE CLEAR</h2>
                
                <div style="background: rgba(255,255,255,0.1); padding: 20px; border-radius: 8px; margin: 20px 0;">
                    <p style="color: white; font-size: 16px; margin-bottom: 20px;">
                        This will clear <strong>ALL</strong> GoalV caches:
                    </p>
                    <ul style="color: white; list-style: none; line-height: 2;">
                        <li>✓ WordPress transients</li>
                        <li>✓ Object cache</li>
                        <li>✓ Custom GoalV options</li>
                    </ul>
                    
                    <a href="<?php echo wp_nonce_url('?action=clear_all_cache', 'clear_cache', 'nonce'); ?>" 
                       class="btn btn-success" 
                       style="margin-top: 20px;"
                       onclick="return confirm('Clear ALL GoalV caches?\n\nThis is safe but will force re-fetch of all data.\n\nContinue?');">
                        💣 CLEAR ALL CACHES NOW
                    </a>
                </div>
            </div>

            <!-- SECTION 7: Root Cause Summary -->
            <div class="section">
                <h2>📋 Root Cause Analysis</h2>

                <div style="background: white; padding: 20px; border-radius: 8px; border: 2px solid #667eea;">
                    <h3 style="color: #667eea; margin-top: 0;">What We Know:</h3>
                    <ul style="line-height: 2; margin-left: 20px;">
                        <li>✅ Database has <strong>0 live matches</strong> (clean)</li>
                        <li>✅ <code>finalize_stale_live_matches()</code> function works (fixed 3 matches)</li>
                        <li>❌ Sync Manager UI still shows <strong>3 different matches</strong></li>
                        <li>❌ Data is coming from <strong>cached source</strong>, not database</li>
                    </ul>

                    <h3 style="color: #667eea; margin-top: 30px;">Most Likely Causes (in order):</h3>
                    <ol style="line-height: 2; margin-left: 20px;">
                        <li><strong>Browser JavaScript cache</strong> - localStorage or sessionStorage holding old data</li>
                        <li><strong>WordPress object cache</strong> - If using Redis/Memcached</li>
                        <li><strong>AJAX endpoint caching</strong> - Response is cached somewhere</li>
                        <li><strong>CDN/Proxy cache</strong> - If using Cloudflare or similar</li>
                    </ol>

                    <h3 style="color: #667eea; margin-top: 30px;">Required Actions:</h3>
                    <ol style="line-height: 2; margin-left: 20px;">
                        <li>Click "CLEAR ALL CACHES" button above</li>
                        <li>Go to Sync Manager page</li>
                        <li>Hard refresh: <code>Ctrl + Shift + R</code> (Windows) or <code>Cmd + Shift + R</code> (Mac)</li>
                        <li>If still showing, check browser DevTools → Application → Storage</li>
                        <li>Manually delete any "goalv" entries</li>
                    </ol>
                </div>
            </div>

            <div style="text-align: center; padding: 20px; color: #6c757d;">
                <p>Generated at: <strong><?php echo current_time('Y-m-d H:i:s'); ?></strong></p>
            </div>

        </div>
    </div>
</body>
</html>