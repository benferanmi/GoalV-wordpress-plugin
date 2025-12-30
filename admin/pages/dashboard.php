<?php
/**
 * GoalV Admin Dashboard Page
 * Overview and quick stats
 * 
 * @package GoalV
 * @subpackage Admin
 * @since 8.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get system status
$api_key = get_option('goalv_api_football_key', '');
$api_configured = !empty($api_key);

$competition_model = new GoalV_Competition();
$match_model = new GoalV_Match();

$active_competitions = count($competition_model->get_active());
$total_matches = $match_model->count_all();
$upcoming_matches = $match_model->count_by_status('scheduled');
$live_matches = $match_model->count_by_status('live');

global $wpdb;
$total_votes = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}goalv_votes");
?>

<div class="goalv-dashboard">
    
    <!-- Welcome Header -->
    <div class="goalv-dashboard-header" style="margin-bottom: 30px;">
        <h2><?php _e('Welcome to GoalV Multi-League System', 'goalv'); ?></h2>
        <p class="description">
            <?php _e('Version 8.0 - Professional multi-league football prediction platform with autonomous syncing.', 'goalv'); ?>
        </p>
    </div>

    <?php if (!$api_configured): ?>
        <!-- Setup Required Notice -->
        <div class="notice notice-warning inline" style="margin-bottom: 20px;">
            <h3><?php _e('⚙️ Initial Setup Required', 'goalv'); ?></h3>
            <p><?php _e('Get started in 3 easy steps:', 'goalv'); ?></p>
            <ol>
                <li>
                    <strong><?php _e('Configure API Key:', 'goalv'); ?></strong>
                    <?php 
                    printf(
                        __('Go to %sAPI Settings%s and add your API-Football Ultra key', 'goalv'),
                        '<a href="' . admin_url('admin.php?page=goalv-settings&tab=api-settings') . '">',
                        '</a>'
                    );
                    ?>
                </li>
                <li>
                    <strong><?php _e('Enable Competitions:', 'goalv'); ?></strong>
                    <?php 
                    printf(
                        __('Visit %sCompetitions%s and activate your preferred leagues', 'goalv'),
                        '<a href="' . admin_url('admin.php?page=goalv-settings&tab=competitions') . '">',
                        '</a>'
                    );
                    ?>
                </li>
                <li>
                    <strong><?php _e('Sync Matches:', 'goalv'); ?></strong>
                    <?php 
                    printf(
                        __('Head to %sSync Manager%s and run your first sync', 'goalv'),
                        '<a href="' . admin_url('admin.php?page=goalv-settings&tab=sync') . '">',
                        '</a>'
                    );
                    ?>
                </li>
            </ol>
        </div>
    <?php endif; ?>

    <!-- Quick Stats -->
    <div class="goalv-quick-stats" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
        
        <div class="goalv-stat-card">
            <div class="goalv-stat-icon">
                <span class="dashicons dashicons-awards"></span>
            </div>
            <div class="goalv-stat-content">
                <h3><?php echo esc_html($active_competitions); ?></h3>
                <p><?php _e('Active Competitions', 'goalv'); ?></p>
            </div>
        </div>

        <div class="goalv-stat-card">
            <div class="goalv-stat-icon">
                <span class="dashicons dashicons-calendar-alt"></span>
            </div>
            <div class="goalv-stat-content">
                <h3><?php echo esc_html($total_matches); ?></h3>
                <p><?php _e('Total Matches', 'goalv'); ?></p>
            </div>
        </div>

        <div class="goalv-stat-card">
            <div class="goalv-stat-icon">
                <span class="dashicons dashicons-clock"></span>
            </div>
            <div class="goalv-stat-content">
                <h3><?php echo esc_html($upcoming_matches); ?></h3>
                <p><?php _e('Upcoming Matches', 'goalv'); ?></p>
            </div>
        </div>

        <div class="goalv-stat-card <?php echo $live_matches > 0 ? 'goalv-stat-live' : ''; ?>">
            <div class="goalv-stat-icon">
                <span class="dashicons dashicons-video-alt3"></span>
            </div>
            <div class="goalv-stat-content">
                <h3><?php echo esc_html($live_matches); ?></h3>
                <p><?php _e('Live Matches', 'goalv'); ?></p>
            </div>
        </div>

        <div class="goalv-stat-card">
            <div class="goalv-stat-icon">
                <span class="dashicons dashicons-thumbs-up"></span>
            </div>
            <div class="goalv-stat-content">
                <h3><?php echo esc_html(number_format($total_votes)); ?></h3>
                <p><?php _e('Total Votes Cast', 'goalv'); ?></p>
            </div>
        </div>

    </div>

    <!-- System Status Overview -->
    <div class="goalv-dashboard-section" style="margin-bottom: 30px;">
        <h3><?php _e('System Status', 'goalv'); ?></h3>
        
        <div class="goalv-status-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
            
            <!-- API Connection -->
            <div class="goalv-status-item">
                <?php if ($api_configured): ?>
                    <span class="dashicons dashicons-yes-alt" style="color: green;"></span>
                    <strong><?php _e('API Connected', 'goalv'); ?></strong>
                <?php else: ?>
                    <span class="dashicons dashicons-dismiss" style="color: red;"></span>
                    <strong><?php _e('API Not Configured', 'goalv'); ?></strong>
                <?php endif; ?>
            </div>

            <!-- Database Tables -->
            <div class="goalv-status-item">
                <?php
                $required_tables = array('goalv_competitions', 'goalv_teams', 'goalv_matches', 'goalv_votes');
                $tables_exist = true;
                foreach ($required_tables as $table) {
                    if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}{$table}'") != $wpdb->prefix . $table) {
                        $tables_exist = false;
                        break;
                    }
                }
                ?>
                <?php if ($tables_exist): ?>
                    <span class="dashicons dashicons-yes-alt" style="color: green;"></span>
                    <strong><?php _e('Database Ready', 'goalv'); ?></strong>
                <?php else: ?>
                    <span class="dashicons dashicons-dismiss" style="color: red;"></span>
                    <strong><?php _e('Database Issues', 'goalv'); ?></strong>
                <?php endif; ?>
            </div>

            <!-- Cron Jobs -->
            <div class="goalv-status-item">
                <?php
                $crons = _get_cron_array();
                $cron_scheduled = false;
                foreach ($crons as $timestamp => $cron) {
                    if (isset($cron['goalv_hourly_sync'])) {
                        $cron_scheduled = true;
                        break;
                    }
                }
                ?>
                <?php if ($cron_scheduled): ?>
                    <span class="dashicons dashicons-yes-alt" style="color: green;"></span>
                    <strong><?php _e('Auto-Sync Active', 'goalv'); ?></strong>
                <?php else: ?>
                    <span class="dashicons dashicons-dismiss" style="color: red;"></span>
                    <strong><?php _e('Auto-Sync Inactive', 'goalv'); ?></strong>
                <?php endif; ?>
            </div>

            <!-- Live Sync -->
            <div class="goalv-status-item">
                <?php $live_sync_enabled = get_option('goalv_enable_live_sync', true); ?>
                <?php if ($live_sync_enabled): ?>
                    <span class="dashicons dashicons-yes-alt" style="color: green;"></span>
                    <strong><?php _e('Live Scores Enabled', 'goalv'); ?></strong>
                <?php else: ?>
                    <span class="dashicons dashicons-minus" style="color: orange;"></span>
                    <strong><?php _e('Live Scores Disabled', 'goalv'); ?></strong>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <!-- What's New in v8.0 -->
    <div class="goalv-dashboard-section" style="margin-bottom: 30px; padding: 20px; background: #f9f9f9; border-left: 4px solid #0073aa;">
        <h3><?php _e('🎉 What\'s New in Version 8.0', 'goalv'); ?></h3>
        <ul>
            <li><strong><?php _e('Multi-League Support:', 'goalv'); ?></strong> <?php _e('Manage 10+ competitions simultaneously', 'goalv'); ?></li>
            <li><strong><?php _e('Real-Time Live Scores:', 'goalv'); ?></strong> <?php _e('30-second polling for active matches', 'goalv'); ?></li>
            <li><strong><?php _e('Autonomous Syncing:', 'goalv'); ?></strong> <?php _e('Fully automated background updates', 'goalv'); ?></li>
            <li><strong><?php _e('Enhanced API:', 'goalv'); ?></strong> <?php _e('Switched to API-Football Ultra (75k requests/day)', 'goalv'); ?></li>
            <li><strong><?php _e('Better Architecture:', 'goalv'); ?></strong> <?php _e('Modular admin system, normalized database', 'goalv'); ?></li>
        </ul>
    </div>

    <!-- Quick Actions -->
    <div class="goalv-dashboard-section">
        <h3><?php _e('Quick Actions', 'goalv'); ?></h3>
        
        <div class="goalv-quick-actions" style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="<?php echo admin_url('admin.php?page=goalv-settings&tab=api-settings'); ?>" class="button button-primary">
                <span class="dashicons dashicons-admin-settings"></span>
                <?php _e('Configure API', 'goalv'); ?>
            </a>

            <a href="<?php echo admin_url('admin.php?page=goalv-settings&tab=competitions'); ?>" class="button button-secondary">
                <span class="dashicons dashicons-awards"></span>
                <?php _e('Manage Competitions', 'goalv'); ?>
            </a>

            <a href="<?php echo admin_url('admin.php?page=goalv-settings&tab=sync'); ?>" class="button button-secondary">
                <span class="dashicons dashicons-update"></span>
                <?php _e('Sync Matches', 'goalv'); ?>
            </a>

            <a href="<?php echo admin_url('admin.php?page=goalv-settings&tab=system'); ?>" class="button button-secondary">
                <span class="dashicons dashicons-info"></span>
                <?php _e('System Health', 'goalv'); ?>
            </a>

            <a href="<?php echo admin_url('edit.php?post_type=goalv_matches'); ?>" class="button button-secondary">
                <span class="dashicons dashicons-list-view"></span>
                <?php _e('View All Matches', 'goalv'); ?>
            </a>
        </div>
    </div>

    <?php if ($live_matches > 0): ?>
        <!-- Live Matches Alert -->
        <div class="notice notice-info inline" style="margin-top: 30px;">
            <p>
                <strong><?php _e('⚡ Live Matches in Progress!', 'goalv'); ?></strong><br>
                <?php 
                printf(
                    __('There are currently %d live match(es). %sView in Sync Manager%s', 'goalv'),
                    $live_matches,
                    '<a href="' . admin_url('admin.php?page=goalv-settings&tab=sync') . '">',
                    '</a>'
                );
                ?>
            </p>
        </div>
    <?php endif; ?>

</div>

<style>
.goalv-stat-card {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    text-align: center;
    transition: box-shadow 0.3s;
}

.goalv-stat-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.goalv-stat-card.goalv-stat-live {
    border-left: 4px solid #ff0000;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.8; }
}

.goalv-stat-icon {
    font-size: 48px;
    color: #0073aa;
    margin-bottom: 10px;
}

.goalv-stat-content h3 {
    font-size: 36px;
    margin: 0;
    color: #333;
}

.goalv-stat-content p {
    margin: 5px 0 0 0;
    color: #666;
    font-size: 14px;
}

.goalv-status-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.goalv-status-item .dashicons {
    font-size: 24px;
}

.goalv-quick-actions .button {
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.goalv-quick-actions .dashicons {
    font-size: 16px;
}
</style>