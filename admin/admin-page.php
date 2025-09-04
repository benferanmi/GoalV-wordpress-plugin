<?php
/**
 * Admin Settings Page Template - UPDATED WITH WEEK SELECTOR
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <?php settings_errors(); ?>
    
    <form method="post" action="options.php">
        <?php
        settings_fields('goalv_settings');
        ?>
        
        <div class="goalv-admin-sections">
            <!-- API Settings Section -->
            <div class="goalv-admin-section">
                <h2><?php _e('API Configuration', 'goalv'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="goalv_api_key"><?php _e('Football-Data.org API Key', 'goalv'); ?></label>
                        </th>
                        <td>
                            <?php $api_key = get_option('goalv_api_key', ''); ?>
                            <input type="password" 
                                   id="goalv_api_key" 
                                   name="goalv_api_key" 
                                   value="<?php echo esc_attr($api_key); ?>" 
                                   class="regular-text" />
                            <button type="button" id="toggle-api-key" class="button button-secondary" style="margin-left: 10px;">
                                <?php _e('Show/Hide', 'goalv'); ?>
                            </button>
                            <p class="description">
                                <?php 
                                printf(
                                    __('Get your free API key from %s. Free tier allows 10 calls per minute.', 'goalv'),
                                    '<a href="https://www.football-data.org/client/register" target="_blank">football-data.org</a>'
                                ); 
                                ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="goalv_competition_id"><?php _e('Competition', 'goalv'); ?></label>
                        </th>
                        <td>
                            <?php 
                            $competition_id = get_option('goalv_competition_id', '2021');
                            $competitions = array(
                                '2021' => 'Premier League (England)',
                                '2014' => 'La Liga (Spain)',
                                '2002' => 'Bundesliga (Germany)',
                                '2019' => 'Serie A (Italy)',
                                '2015' => 'Ligue 1 (France)',
                                '2001' => 'UEFA Champions League'
                            );
                            ?>
                            <select id="goalv_competition_id" name="goalv_competition_id">
                                <?php foreach ($competitions as $id => $name): ?>
                                    <option value="<?php echo esc_attr($id); ?>" <?php selected($competition_id, $id); ?>>
                                        <?php echo esc_html($name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php _e('Select which competition to sync matches from.', 'goalv'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Match Sync Section - UPDATED WITH WEEK SELECTOR -->
            <div class="goalv-admin-section">
                <h2><?php _e('Match Synchronization', 'goalv'); ?></h2>
                <div class="goalv-sync-section">
                    <p><?php _e('Sync football matches from Football-Data.org API by game week.', 'goalv'); ?></p>
                    
                    <?php
                    // Get week data for selector
                    $api = new GoalV_API();
                    $competition_id = get_option('goalv_competition_id', '2021');
                    $current_football_week = $api->get_current_football_week($competition_id);
                    $available_weeks = $api->get_available_weeks($competition_id);
                    ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="sync_week_selector"><?php _e('Select Game Week', 'goalv'); ?></label>
                            </th>
                            <td>
                                <select id="sync_week_selector" name="sync_week">
                                    <option value=""><?php _e('Auto-detect Next Week (Recommended)', 'goalv'); ?></option>
                                    <?php foreach ($available_weeks as $week_num => $week_label): ?>
                                        <option value="<?php echo esc_attr($week_num); ?>">
                                            <?php echo esc_html($week_label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">
                                    <?php _e('Auto-detect will find the next week with upcoming matches. Current detected week: ', 'goalv'); ?>
                                    <strong>GW<?php echo esc_html($current_football_week); ?></strong>
                                </p>
                            </td>
                        </tr>
                    </table>
                    
                    <div class="goalv-sync-controls">
                        <button type="button" id="sync-matches-btn" class="button button-primary">
                            <?php _e('Sync Next Week\'s Matches', 'goalv'); ?>
                        </button>
                        <button type="button" id="test-api-btn" class="button button-secondary" style="margin-left: 10px;">
                            <?php _e('Test API Connection', 'goalv'); ?>
                        </button>
                        <span id="sync-loader" class="spinner" style="float: none; margin-left: 10px;"></span>
                    </div>
                    
                    <div id="sync-result" style="margin-top: 15px;"></div>
                    
                    <?php
                    // Show last sync info with week
                    $last_sync = get_option('goalv_last_sync_time', '');
                    $last_week = get_option('goalv_last_synced_week', '');
                    if ($last_sync) {
                        echo '<div class="goalv-last-sync-info">';
                        echo '<p class="description">';
                        printf(
                            __('Last sync: %s', 'goalv'),
                            date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($last_sync))
                        );
                        if ($last_week) {
                            echo ' (' . esc_html($last_week) . ')';
                        }
                        echo '</p>';
                        echo '</div>';
                    }
                    ?>
                </div>
            </div>
            
            <!-- Current Week Status - NEW SECTION -->
            <div class="goalv-admin-section">
                <h2><?php _e('Current Week Status', 'goalv'); ?></h2>
                <table class="goalv-status-table">
                    <tr>
                        <td><strong><?php _e('Current Football Week', 'goalv'); ?></strong></td>
                        <td>GW<?php echo esc_html($current_football_week); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Last Synced Week', 'goalv'); ?></strong></td>
                        <td>
                            <?php 
                            $last_synced_week = get_option('goalv_last_synced_week', '');
                            echo $last_synced_week ? esc_html($last_synced_week) : __('Never synced', 'goalv');
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Matches This Week', 'goalv'); ?></strong></td>
                        <td>
                            <?php
                            $current_week_matches = get_posts(array(
                                'post_type' => 'goalv_matches',
                                'meta_key' => 'goalv_week_synced',
                                'meta_value' => 'GW' . $current_football_week,
                                'posts_per_page' => -1,
                                'fields' => 'ids'
                            ));
                            echo count($current_week_matches);
                            ?>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- select Game week to display - New Section -->
             <div class="goalv-admin-section">
    <h2><?php _e('Homepage Display Settings', 'goalv'); ?></h2>
    <table class="form-table">
        <tr>
            <th scope="row">
                <label for="goalv_homepage_week_mode"><?php _e('Homepage Week Selection', 'goalv'); ?></label>
            </th>
            <td>
                <?php 
                $homepage_week_mode = get_option('goalv_homepage_week_mode', 'current');
                ?>
                <fieldset>
                    <legend class="screen-reader-text"><span><?php _e('Homepage Week Selection', 'goalv'); ?></span></legend>
                    
                    <label for="homepage_mode_current">
                        <input type="radio" id="homepage_mode_current" name="goalv_homepage_week_mode" 
                               value="current" <?php checked($homepage_week_mode, 'current'); ?> />
                        <?php _e('Show Current Week Only', 'goalv'); ?>
                    </label><br>
                    
                    <label for="homepage_mode_custom">
                        <input type="radio" id="homepage_mode_custom" name="goalv_homepage_week_mode" 
                               value="custom" <?php checked($homepage_week_mode, 'custom'); ?> />
                        <?php _e('Show Selected Weeks', 'goalv'); ?>
                    </label><br>
                    
                    <label for="homepage_mode_range">
                        <input type="radio" id="homepage_mode_range" name="goalv_homepage_week_mode" 
                               value="range" <?php checked($homepage_week_mode, 'range'); ?> />
                        <?php _e('Show Week Range', 'goalv'); ?>
                    </label>
                </fieldset>
            </td>
        </tr>
        
        <tr id="custom-weeks-row" style="<?php echo ($homepage_week_mode !== 'custom') ? 'display:none;' : ''; ?>">
            <th scope="row">
                <label for="goalv_homepage_weeks"><?php _e('Select Weeks to Display', 'goalv'); ?></label>
            </th>
            <td>
                <?php 
                $selected_weeks = get_option('goalv_homepage_weeks', array());
                if (!is_array($selected_weeks)) {
                    $selected_weeks = array();
                }
                ?>
                <div class="goalv-week-checkboxes" style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px;">
                    <?php foreach ($available_weeks as $week_num => $week_label): ?>
                        <label style="display: block; margin-bottom: 5px;">
                            <input type="checkbox" name="goalv_homepage_weeks[]" 
                                   value="<?php echo esc_attr($week_num); ?>"
                                   <?php echo in_array($week_num, $selected_weeks) ? 'checked' : ''; ?> />
                            <?php echo esc_html($week_label); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <p class="description"><?php _e('Select multiple weeks to display on the homepage. Matches will be shown in chronological order.', 'goalv'); ?></p>
            </td>
        </tr>
        
        <tr id="week-range-row" style="<?php echo ($homepage_week_mode !== 'range') ? 'display:none;' : ''; ?>">
            <th scope="row">
                <label><?php _e('Week Range', 'goalv'); ?></label>
            </th>
            <td>
                <?php 
                $range_start = get_option('goalv_homepage_range_start', $current_football_week);
                $range_end = get_option('goalv_homepage_range_end', $current_football_week + 1);
                ?>
                <select name="goalv_homepage_range_start" id="goalv_homepage_range_start">
                    <?php foreach ($available_weeks as $week_num => $week_label): ?>
                        <option value="<?php echo esc_attr($week_num); ?>" <?php selected($range_start, $week_num); ?>>
                            <?php echo esc_html($week_label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <span style="margin: 0 10px;"><?php _e('to', 'goalv'); ?></span>
                
                <select name="goalv_homepage_range_end" id="goalv_homepage_range_end">
                    <?php foreach ($available_weeks as $week_num => $week_label): ?>
                        <option value="<?php echo esc_attr($week_num); ?>" <?php selected($range_end, $week_num); ?>>
                            <?php echo esc_html($week_label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <p class="description"><?php _e('Select a range of consecutive weeks to display.', 'goalv'); ?></p>
            </td>
        </tr>
        
        <tr>
            <th scope="row">
                <label for="goalv_show_week_headers"><?php _e('Display Options', 'goalv'); ?></label>
            </th>
            <td>
                <?php $show_week_headers = get_option('goalv_show_week_headers', 'yes'); ?>
                <label for="goalv_show_week_headers">
                    <input type="checkbox" id="goalv_show_week_headers" name="goalv_show_week_headers" 
                           value="yes" <?php checked($show_week_headers, 'yes'); ?> />
                    <?php _e('Show week headers when displaying multiple weeks', 'goalv'); ?>
                </label>
                
                <br><br>
                
                <?php $fallback_enabled = get_option('goalv_homepage_fallback', 'yes'); ?>
                <label for="goalv_homepage_fallback">
                    <input type="checkbox" id="goalv_homepage_fallback" name="goalv_homepage_fallback" 
                           value="yes" <?php checked($fallback_enabled, 'yes'); ?> />
                    <?php _e('Show upcoming weeks if selected weeks have no matches', 'goalv'); ?>
                </label>
            </td>
        </tr>
    </table>
    
    <!-- Preview section -->
    <div class="goalv-preview-section" style="background: #f9f9f9; padding: 15px; border-radius: 4px; margin-top: 15px;">
        <h4><?php _e('Preview', 'goalv'); ?></h4>
        <div id="goalv-homepage-preview">
            <p class="description"><?php _e('Select your display mode above to see a preview of which weeks will be shown on the homepage.', 'goalv'); ?></p>
        </div>
    </div>
</div>
            
            <!-- Voting Settings Section -->
            <div class="goalv-admin-section">
                <h2><?php _e('Voting Configuration', 'goalv'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('General Voting Settings', 'goalv'); ?></th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text">
                                    <span><?php _e('General Voting Settings', 'goalv'); ?></span>
                                </legend>
                                
                                <?php $allow_change = get_option('goalv_allow_vote_change', 'yes'); ?>
                                <label for="goalv_allow_vote_change">
                                    <input type="checkbox" 
                                           id="goalv_allow_vote_change" 
                                           name="goalv_allow_vote_change" 
                                           value="yes" 
                                           <?php checked($allow_change, 'yes'); ?> />
                                    <?php _e('Allow users to change their votes', 'goalv'); ?>
                                </label>
                                <p class="description">
                                    <?php _e('When disabled, users can only vote once per match.', 'goalv'); ?>
                                </p>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Location-Specific Settings', 'goalv'); ?></th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text">
                                    <span><?php _e('Location-Specific Settings', 'goalv'); ?></span>
                                </legend>
                                
                                <?php $allow_homepage_change = get_option('goalv_allow_homepage_vote_change', 'yes'); ?>
                                <label for="goalv_allow_homepage_vote_change">
                                    <input type="checkbox" 
                                           id="goalv_allow_homepage_vote_change" 
                                           name="goalv_allow_homepage_vote_change" 
                                           value="yes" 
                                           <?php checked($allow_homepage_change, 'yes'); ?> />
                                    <?php _e('Allow vote changes on homepage', 'goalv'); ?>
                                </label>
                                <br><br>
                                
                                <?php $allow_details_change = get_option('goalv_allow_details_vote_change', 'yes'); ?>
                                <label for="goalv_allow_details_vote_change">
                                    <input type="checkbox" 
                                           id="goalv_allow_details_vote_change" 
                                           name="goalv_allow_details_vote_change" 
                                           value="yes" 
                                           <?php checked($allow_details_change, 'yes'); ?> />
                                    <?php _e('Allow vote changes on match details page', 'goalv'); ?>
                                </label>
                                
                                <p class="description">
                                    <?php _e('These settings only apply when general vote changes are enabled above.', 'goalv'); ?>
                                </p>
                            </fieldset>
                        </td>
                    </tr>

                    <tr>
                    <th scope="row"><?php _e('Multiple Votes Settings', 'goalv'); ?></th>
                    <td>
                        <fieldset>
                            <legend class="screen-reader-text">
                                <span><?php _e('Multiple Votes Settings', 'goalv'); ?></span>
                            </legend>
                            
                            <?php $allow_multiple = get_option('goalv_allow_multiple_votes', 'no'); ?>
                            <label for="goalv_allow_multiple_votes">
                                <input type="checkbox" 
                                    id="goalv_allow_multiple_votes" 
                                    name="goalv_allow_multiple_votes" 
                                    value="yes" 
                                    <?php checked($allow_multiple, 'yes'); ?> />
                                <?php _e('Allow users to cast multiple votes per match', 'goalv'); ?>
                            </label>
                            <p class="description">
                                <?php _e('When enabled, users can vote for multiple options on the same match. When disabled, users can only select one option per match.', 'goalv'); ?>
                            </p>
                        </fieldset>
                    </td>
                </tr>
                </table>
            </div>

            <div class="goalv-admin-section">
    <h2><?php _e('Template Labels', 'goalv'); ?></h2>
    <p><?php _e('Customize the labels displayed in your match templates. Leave blank to use defaults.', 'goalv'); ?></p>
    <table class="form-table">
        <tr>
            <th scope="row">
                <label for="goalv_labels_teams"><?php _e('Teams Label', 'goalv'); ?></label>
            </th>
            <td>
                <?php $teams_label = get_option('goalv_labels_teams', ''); ?>
                <input type="text" 
                       id="goalv_labels_teams" 
                       name="goalv_labels_teams" 
                       value="<?php echo esc_attr($teams_label); ?>" 
                       class="regular-text" 
                       placeholder="<?php esc_attr_e('Teams', 'goalv'); ?>" />
                <p class="description"><?php _e('Default: "Teams"', 'goalv'); ?></p>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="goalv_labels_score"><?php _e('Score Label', 'goalv'); ?></label>
            </th>
            <td>
                <?php $score_label = get_option('goalv_labels_score', ''); ?>
                <input type="text" 
                       id="goalv_labels_score" 
                       name="goalv_labels_score" 
                       value="<?php echo esc_attr($score_label); ?>" 
                       class="regular-text" 
                       placeholder="<?php esc_attr_e('Score', 'goalv'); ?>" />
                <p class="description"><?php _e('Default: "Score"', 'goalv'); ?></p>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="goalv_labels_status"><?php _e('Status Label', 'goalv'); ?></label>
            </th>
            <td>
                <?php $status_label = get_option('goalv_labels_status', ''); ?>
                <input type="text" 
                       id="goalv_labels_status" 
                       name="goalv_labels_status" 
                       value="<?php echo esc_attr($status_label); ?>" 
                       class="regular-text" 
                       placeholder="<?php esc_attr_e('Status', 'goalv'); ?>" />
                <p class="description"><?php _e('Default: "Status"', 'goalv'); ?></p>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="goalv_labels_date"><?php _e('Date Label', 'goalv'); ?></label>
            </th>
            <td>
                <?php $date_label = get_option('goalv_labels_date', ''); ?>
                <input type="text" 
                       id="goalv_labels_date" 
                       name="goalv_labels_date" 
                       value="<?php echo esc_attr($date_label); ?>" 
                       class="regular-text" 
                       placeholder="<?php esc_attr_e('Date', 'goalv'); ?>" />
                <p class="description"><?php _e('Default: "Date"', 'goalv'); ?></p>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="goalv_labels_predictions"><?php _e('Predictions Label', 'goalv'); ?></label>
            </th>
            <td>
                <?php $predictions_label = get_option('goalv_labels_predictions', ''); ?>
                <input type="text" 
                       id="goalv_labels_predictions" 
                       name="goalv_labels_predictions" 
                       value="<?php echo esc_attr($predictions_label); ?>" 
                       class="regular-text" 
                       placeholder="<?php esc_attr_e('Predictions', 'goalv'); ?>" />
                <p class="description"><?php _e('Default: "Predictions"', 'goalv'); ?></p>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="goalv_labels_details"><?php _e('Details Label', 'goalv'); ?></label>
            </th>
            <td>
                <?php $details_label = get_option('goalv_labels_details', ''); ?>
                <input type="text" 
                       id="goalv_labels_details" 
                       name="goalv_labels_details" 
                       value="<?php echo esc_attr($details_label); ?>" 
                       class="regular-text" 
                       placeholder="<?php esc_attr_e('Details', 'goalv'); ?>" />
                <p class="description"><?php _e('Default: "Details"', 'goalv'); ?></p>
            </td>
        </tr>
    </table>
    
    <!-- Usage Examples -->
    <div class="goalv-label-examples" style="background: #f9f9f9; padding: 15px; border-radius: 4px; margin-top: 15px;">
        <h4><?php _e('Usage Examples', 'goalv'); ?></h4>
        <p><strong><?php _e('Shortcode with custom labels:', 'goalv'); ?></strong></p>
        <code>[goalv_matches teams_label="Football Clubs" predictions_label="Fan Votes" details_label="More Info"]</code>
        
        <p><strong><?php _e('Admin settings will be used as defaults:', 'goalv'); ?></strong></p>
        <code>[goalv_matches template="table"]</code>
    </div>
</div>
            
            <!-- Shortcode Usage Section -->
            <div class="goalv-admin-section">
                <h2><?php _e('Shortcode Usage', 'goalv'); ?></h2>
                <div class="goalv-shortcode-examples">
                    <h3><?php _e('Available Shortcodes', 'goalv'); ?></h3>
                    
                    <div class="goalv-shortcode-example">
                        <h4><?php _e('Card Template (Default)', 'goalv'); ?></h4>
                        <code>[goalv_matches]</code> <?php _e('or', 'goalv'); ?> <code>[goalv_matches template="card"]</code>
                        <p class="description"><?php _e('Displays matches as individual cards in a stacked list layout.', 'goalv'); ?></p>
                    </div>
                    
                    <div class="goalv-shortcode-example">
                        <h4><?php _e('Grid Template', 'goalv'); ?></h4>
                        <code>[goalv_matches template="grid"]</code>
                        <p class="description"><?php _e('Displays matches in a 2-column grid layout with compact design.', 'goalv'); ?></p>
                    </div>
                    
                    <div class="goalv-shortcode-example">
                        <h4><?php _e('Limit Results', 'goalv'); ?></h4>
                        <code>[goalv_matches limit="5"]</code>
                        <p class="description"><?php _e('Limit the number of matches displayed (default: 10).', 'goalv'); ?></p>
                    </div>
                </div>
            </div>
            
            <!-- System Status Section -->
            <div class="goalv-admin-section">
                <h2><?php _e('System Status', 'goalv'); ?></h2>
                <table class="goalv-status-table">
                    <tr>
                        <td><strong><?php _e('API Connection', 'goalv'); ?></strong></td>
                        <td>
                            <?php if (empty($api_key)): ?>
                                <span class="goalv-status-error"><?php _e('Not Configured', 'goalv'); ?></span>
                            <?php else: ?>
                                <span class="goalv-status-success"><?php _e('Configured', 'goalv'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Database Tables', 'goalv'); ?></strong></td>
                        <td>
                            <?php
                            global $wpdb;
                            $vote_options_table = $wpdb->prefix . 'goalv_vote_options';
                            $votes_table = $wpdb->prefix . 'goalv_votes';
                            
                            $options_exists = $wpdb->get_var("SHOW TABLES LIKE '$vote_options_table'") == $vote_options_table;
                            $votes_exists = $wpdb->get_var("SHOW TABLES LIKE '$votes_table'") == $votes_table;
                            
                            if ($options_exists && $votes_exists): ?>
                                <span class="goalv-status-success"><?php _e('All Tables Created', 'goalv'); ?></span>
                            <?php else: ?>
                                <span class="goalv-status-error"><?php _e('Missing Tables', 'goalv'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Total Matches', 'goalv'); ?></strong></td>
                        <td>
                            <?php
                            $match_count = wp_count_posts('goalv_matches');
                            echo esc_html($match_count->publish);
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Total Votes', 'goalv'); ?></strong></td>
                        <td>
                            <?php
                            $vote_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}goalv_votes");
                            echo esc_html($vote_count ? $vote_count : 0);
                            ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <?php submit_button(); ?>
    </form>
</div>

<script>
// Toggle API key visibility
jQuery(document).ready(function($) {
    $('#toggle-api-key').on('click', function() {
        var $apiKeyField = $('#goalv_api_key');
        var currentType = $apiKeyField.attr('type');
        
        if (currentType === 'password') {
            $apiKeyField.attr('type', 'text');
            $(this).text('Hide');
        } else {
            $apiKeyField.attr('type', 'password');
            $(this).text('Show');
        }
    });

       // Handle week mode changes
    $('input[name="goalv_homepage_week_mode"]').change(function() {
        var mode = $(this).val();
        
        $('#custom-weeks-row').toggle(mode === 'custom');
        $('#week-range-row').toggle(mode === 'range');
        
        updateHomepagePreview();
    });
    
    // Handle week selection changes
    $('input[name="goalv_homepage_weeks[]"], select[name="goalv_homepage_range_start"], select[name="goalv_homepage_range_end"]').change(function() {
        updateHomepagePreview();
    });
    
    // Update preview function
    function updateHomepagePreview() {
        var mode = $('input[name="goalv_homepage_week_mode"]:checked').val();
        var previewText = '';
        
        if (mode === 'current') {
            previewText = 'Homepage will show: Current Week (GW<?php echo $current_football_week; ?>) matches only';
        } else if (mode === 'custom') {
            var selectedWeeks = [];
            $('input[name="goalv_homepage_weeks[]"]:checked').each(function() {
                selectedWeeks.push('GW' + $(this).val());
            });
            
            if (selectedWeeks.length > 0) {
                previewText = 'Homepage will show: ' + selectedWeeks.join(', ') + ' (' + selectedWeeks.length + ' weeks)';
            } else {
                previewText = 'No weeks selected - homepage will be empty';
            }
        } else if (mode === 'range') {
            var startWeek = $('select[name="goalv_homepage_range_start"]').val();
            var endWeek = $('select[name="goalv_homepage_range_end"]').val();
            
            if (startWeek && endWeek) {
                var weekCount = Math.abs(endWeek - startWeek) + 1;
                previewText = 'Homepage will show: GW' + startWeek + ' to GW' + endWeek + ' (' + weekCount + ' weeks)';
            }
        }
        
        $('#goalv-homepage-preview').html('<p><strong>' + previewText + '</strong></p>');
    }
    
    // Initial preview update
    updateHomepagePreview();

});
</script>

<style>
.goalv-admin-sections {
    display: grid;
    gap: 20px;
    margin-top: 20px;
}

.goalv-admin-section {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
}

.goalv-admin-section h2 {
    margin-top: 0;
    border-bottom: 1px solid #eee;
    padding-bottom: 10px;
}

.goalv-sync-section {
    background: #f9f9f9;
    padding: 15px;
    border-radius: 4px;
}

.goalv-sync-controls {
    margin: 15px 0;
}

.goalv-shortcode-examples {
    background: #f9f9f9;
    padding: 15px;
    border-radius: 4px;
}

.goalv-shortcode-example {
    margin-bottom: 15px;
}

.goalv-shortcode-example h4 {
    margin-bottom: 5px;
}

.goalv-shortcode-example code {
    background: #fff;
    padding: 4px 8px;
    border: 1px solid #ddd;
    border-radius: 3px;
}

.goalv-status-table {
    width: 100%;
    border-collapse: collapse;
}

.goalv-status-table td {
    padding: 8px 0;
    border-bottom: 1px solid #eee;
}

.goalv-status-success {
    color: #46b450;
    font-weight: bold;
}

.goalv-status-error {
    color: #dc3232;
    font-weight: bold;
}

#sync-result .notice {
    margin: 10px 0;
    padding: 12px;
}

#sync-loader.is-active {
    visibility: visible;
}
</style>

<script>
jQuery(document).ready(function($) {
    $('#toggle-api-key').click(function() {
        var input = $('#goalv_api_key');
        if (input.attr('type') === 'password') {
            input.attr('type', 'text');
        } else {
            input.attr('type', 'password');
        }
    });
});
</script>