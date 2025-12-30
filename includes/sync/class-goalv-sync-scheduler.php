<?php
/**
 * GoalV Sync Scheduler - WP-Cron Management
 * Sets up and manages automated background sync jobs
 * 
 * @package GoalV
 * @version 8.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class GoalV_Sync_Scheduler
{
    private $sync_manager;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->sync_manager = new GoalV_Sync_Manager();
        $this->register_hooks();
    }

    /**
     * Register WordPress hooks
     */
    private function register_hooks()
    {
        // Register custom cron schedules
        add_filter('cron_schedules', array($this, 'add_custom_cron_schedules'));

        // Hook cron actions to methods
        add_action('goalv_hourly_sync', array($this, 'run_hourly_sync'));
        add_action('goalv_live_sync', array($this, 'run_live_sync'));
        add_action('goalv_daily_cleanup', array($this, 'run_daily_cleanup'));
    }

    /**
     * Add custom cron intervals
     * 
     * @param array $schedules Existing schedules
     * @return array Modified schedules
     */
    public function add_custom_cron_schedules($schedules)
    {
        // 30-second interval for live scores
        $schedules['thirty_seconds'] = array(
            'interval' => 30,
            'display' => __('Every 30 Seconds', 'goalv')
        );

        // 5-minute interval (backup option)
        $schedules['five_minutes'] = array(
            'interval' => 300,
            'display' => __('Every 5 Minutes', 'goalv')
        );

        return $schedules;
    }

    /**
     * Initialize all scheduled events
     * Called on plugin activation
     */
    public function schedule_all_events()
    {
        // Clear any existing schedules first
        $this->unschedule_all_events();

        // 1. Hourly full sync (competitions + matches)
        if (!wp_next_scheduled('goalv_hourly_sync')) {
            wp_schedule_event(time(), 'hourly', 'goalv_hourly_sync');
            error_log('GoalV: Scheduled hourly sync');
        }


        if (!wp_next_scheduled('goalv_live_sync')) {
            wp_schedule_event(time(), 'thirty_seconds', 'goalv_live_sync');
            error_log('GoalV: Scheduled 30-second live sync');
        }

        // 3. Daily cleanup (logs, cache, old matches)
        if (!wp_next_scheduled('goalv_daily_cleanup')) {
            wp_schedule_event(strtotime('03:00:00'), 'daily', 'goalv_daily_cleanup');
            error_log('GoalV: Scheduled daily cleanup at 3 AM');
        }
    }

    /**
     * Remove all scheduled events
     * Called on plugin deactivation
     */
    public function unschedule_all_events()
    {
        $events = array('goalv_hourly_sync', 'goalv_live_sync', 'goalv_daily_cleanup');

        foreach ($events as $event) {
            $timestamp = wp_next_scheduled($event);
            if ($timestamp) {
                wp_unschedule_event($timestamp, $event);
            }
            wp_clear_scheduled_hook($event); // Clear all instances
        }

        error_log('GoalV: Unscheduled all cron events');
    }

    /**
     * Hourly sync callback
     * Syncs competitions and all matches
     */
    public function run_hourly_sync()
    {
        // Prevent concurrent runs
        $lock = get_transient('goalv_hourly_sync_lock');
        if ($lock) {
            error_log('GoalV: Hourly sync already running, skipping...');
            return;
        }

        // Set lock (expires in 10 minutes)
        set_transient('goalv_hourly_sync_lock', true, 600);

        try {
            error_log('GoalV: Starting hourly sync...');
            $result = $this->sync_manager->sync_all();

            error_log(sprintf(
                'GoalV: Hourly sync completed - Duration: %s, Errors: %d',
                $result['duration'] ?? 'unknown',
                count($result['errors'] ?? array())
            ));
        } catch (Exception $e) {
            error_log('GoalV: Hourly sync exception - ' . $e->getMessage());
        } finally {
            // Release lock
            delete_transient('goalv_hourly_sync_lock');
        }
    }

    /**
     * Live sync callback (30 seconds)
     * Updates live match scores only
     */
    public function run_live_sync()
    {
        // Skip if live sync disabled
        if (!$this->is_live_sync_enabled()) {
            return;
        }

        // Prevent concurrent runs
        $lock = get_transient('goalv_live_sync_lock');
        if ($lock) {
            return; // Silent skip (runs every 30 sec, overlaps expected)
        }

        // Set lock (expires in 45 seconds - buffer for slow APIs)
        set_transient('goalv_live_sync_lock', true, 45);

        try {
            $result = $this->sync_manager->sync_live_matches();

            // Only log if there are active matches
            if (isset($result['live_matches']) && $result['live_matches'] > 0) {
                error_log(sprintf(
                    'GoalV: Live sync - %d matches updated',
                    $result['live_matches']
                ));
            }
        } catch (Exception $e) {
            error_log('GoalV: Live sync exception - ' . $e->getMessage());
        } finally {
            // Release lock
            delete_transient('goalv_live_sync_lock');
        }
    }

    /**
     * Daily cleanup callback
     * Cleans old logs, expired cache, finished matches
     */
    public function run_daily_cleanup()
    {
        error_log('GoalV: Starting daily cleanup...');

        try {
            // 1. Clean old sync logs (keep last 1000)
            $this->sync_manager->cleanup_old_logs();

            // 2. Clean expired transients
            $this->cleanup_expired_transients();

            // 3. Archive old finished matches (older than 30 days)
            $this->archive_old_matches();

            // 4. Clean old vote data (optional)
            $this->cleanup_old_votes();

            error_log('GoalV: Daily cleanup completed');
        } catch (Exception $e) {
            error_log('GoalV: Daily cleanup exception - ' . $e->getMessage());
        }
    }

    /**
     * Clean expired transients (cache cleanup)
     */
    private function cleanup_expired_transients()
    {
        global $wpdb;

        // Delete expired GoalV transients
        $deleted = $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_timeout_goalv_%' 
             AND option_value < UNIX_TIMESTAMP()"
        );

        // Delete orphaned transient values
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_goalv_%' 
             AND option_name NOT LIKE '_transient_timeout_%'
             AND option_name NOT IN (
                 SELECT REPLACE(option_name, '_transient_timeout_', '_transient_') 
                 FROM {$wpdb->options} 
                 WHERE option_name LIKE '_transient_timeout_goalv_%'
             )"
        );

        error_log("GoalV: Cleaned expired transients (deleted: {$deleted})");
    }

    /**
     * Archive finished matches older than 30 days
     */
    private function archive_old_matches()
    {
        global $wpdb;
        $matches_table = $wpdb->prefix . 'goalv_matches';

        // Update status to 'archived' instead of deleting
        $updated = $wpdb->query(
            "UPDATE {$matches_table} 
             SET status = 'archived' 
             WHERE status = 'finished' 
             AND match_date < DATE_SUB(NOW(), INTERVAL 30 DAY)
             AND status != 'archived'"
        );

        if ($updated > 0) {
            error_log("GoalV: Archived {$updated} old matches");
        }
    }

    /**
     * Clean old anonymous votes (older than 90 days)
     */
    private function cleanup_old_votes()
    {
        global $wpdb;
        $votes_table = $wpdb->prefix . 'goalv_votes';

        // Delete anonymous votes older than 90 days
        $deleted = $wpdb->query(
            "DELETE FROM {$votes_table} 
             WHERE user_id IS NULL 
             AND vote_time < DATE_SUB(NOW(), INTERVAL 90 DAY)"
        );

        if ($deleted > 0) {
            error_log("GoalV: Cleaned {$deleted} old anonymous votes");
        }
    }

    /**
     * Check if live sync is enabled
     * 
     * @return bool
     */
    private function is_live_sync_enabled()
    {
        return (bool) get_option('goalv_enable_live_sync', true);
    }

    /**
     * Enable/disable live sync
     * 
     * @param bool $enable
     */
    public function toggle_live_sync($enable)
    {
        update_option('goalv_enable_live_sync', (bool) $enable);

        if ($enable) {
            // Schedule if not already scheduled
            if (!wp_next_scheduled('goalv_live_sync')) {
                wp_schedule_event(time(), 'thirty_seconds', 'goalv_live_sync');
                error_log('GoalV: Live sync enabled and scheduled');
            }
        } else {
            // Unschedule
            $timestamp = wp_next_scheduled('goalv_live_sync');
            if ($timestamp) {
                wp_unschedule_event($timestamp, 'goalv_live_sync');
            }
            wp_clear_scheduled_hook('goalv_live_sync');
            error_log('GoalV: Live sync disabled and unscheduled');
        }
    }

    /**
     * Get next scheduled run times
     * 
     * @return array Schedule info
     */
    public function get_schedule_info()
    {
        $hourly_next = wp_next_scheduled('goalv_hourly_sync');
        $live_next = wp_next_scheduled('goalv_live_sync');
        $daily_next = wp_next_scheduled('goalv_daily_cleanup');

        return array(
            'hourly_sync' => array(
                'next_run' => $hourly_next ? date('Y-m-d H:i:s', $hourly_next) : 'Not scheduled',
                'time_until' => $hourly_next ? human_time_diff($hourly_next) : 'N/A',
                'is_scheduled' => (bool) $hourly_next
            ),
            'live_sync' => array(
                'next_run' => $live_next ? date('Y-m-d H:i:s', $live_next) : 'Not scheduled',
                'time_until' => $live_next ? human_time_diff($live_next) : 'N/A',
                'is_scheduled' => (bool) $live_next,
                'enabled' => $this->is_live_sync_enabled()
            ),
            'daily_cleanup' => array(
                'next_run' => $daily_next ? date('Y-m-d H:i:s', $daily_next) : 'Not scheduled',
                'time_until' => $daily_next ? human_time_diff($daily_next) : 'N/A',
                'is_scheduled' => (bool) $daily_next
            )
        );
    }

    /**
     * Manual trigger: Run hourly sync now
     * For admin panel "Sync Now" button
     */
    public function trigger_hourly_sync_now()
    {
        // Clear lock to allow manual run
        delete_transient('goalv_hourly_sync_lock');

        $this->run_hourly_sync();
    }

    /**
     * Manual trigger: Run live sync now
     */
    public function trigger_live_sync_now()
    {
        // Clear lock
        delete_transient('goalv_live_sync_lock');

        $this->run_live_sync();
    }

    /**
     * Check if cron is working
     * 
     * @return array Status
     */
    public function check_cron_health()
    {
        $health = array(
            'wp_cron_enabled' => !(defined('DISABLE_WP_CRON') && DISABLE_WP_CRON),
            'schedules_exist' => false,
            'issues' => array()
        );

        // Check if schedules exist
        $hourly = wp_next_scheduled('goalv_hourly_sync');
        $live = wp_next_scheduled('goalv_live_sync');

        $health['schedules_exist'] = (bool) $hourly;

        if (!$health['wp_cron_enabled']) {
            $health['issues'][] = 'WP-Cron is disabled. Use external cron job.';
        }

        if (!$hourly) {
            $health['issues'][] = 'Hourly sync not scheduled. Reactivate plugin.';
        }

        if ($this->is_live_sync_enabled() && !$live) {
            $health['issues'][] = 'Live sync enabled but not scheduled.';
        }

        return $health;
    }
}