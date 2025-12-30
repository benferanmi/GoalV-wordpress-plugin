<?php
/**
 * GoalV Admin Competitions Module
 * Handles multi-league management interface
 * 
 * @package GoalV
 * @subpackage Admin
 * @since 8.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class GoalV_Admin_Competitions
{
    /**
     * Constructor
     */
    public function __construct()
    {
        // Register AJAX handlers

    }

    /**
     * Render competitions management page
     */
    public function render()
    {
        $api_key = get_option('goalv_api_football_key', '');
        ?>

        <div class="goalv-admin-section">
            <h2><?php _e('Competition Management', 'goalv'); ?></h2>
            <p class="description">
                <?php _e('Enable or disable football competitions/leagues. Only active competitions will sync matches.', 'goalv'); ?>
            </p>

            <?php if (empty($api_key)): ?>
                <!-- API Key Required Notice -->
                <div class="notice notice-warning">
                    <p>
                        <strong><?php _e('API Key Required', 'goalv'); ?></strong><br>
                        <?php
                        printf(
                            __('Please configure your API-Football key in %sAPI Settings%s first.', 'goalv'),
                            '<a href="' . admin_url('admin.php?page=goalv-settings&tab=api-settings') . '">',
                            '</a>'
                        );
                        ?>
                    </p>
                </div>
            <?php else: ?>

                <!-- Fetch Competitions Button -->
                <div class="goalv-competitions-actions" style="margin-bottom: 20px;">
                    <button type="button" id="fetch-competitions-btn" class="button button-primary">
                        <span class="dashicons dashicons-download"></span>
                        <?php _e('Fetch Available Competitions from API', 'goalv'); ?>
                    </button>
                    <span id="fetch-loader" class="spinner"></span>

                    <button type="button" id="refresh-competitions-list" class="button button-secondary" style="margin-left: 10px;">
                        <span class="dashicons dashicons-update"></span>
                        <?php _e('Refresh List', 'goalv'); ?>
                    </button>
                </div>

                <div id="fetch-result" style="margin-bottom: 20px;"></div>

                <!-- Competitions List -->
                <div class="goalv-competitions-container">
                    <div id="competitions-list-loading" style="display: none;">
                        <p><span class="spinner is-active"></span> <?php _e('Loading competitions...', 'goalv'); ?></p>
                    </div>

                    <div id="competitions-list">
                        <?php $this->render_competitions_table(); ?>
                    </div>
                </div>

            <?php endif; ?>

            <!-- Competition Info -->
            <div class="goalv-competition-info"
                style="margin-top: 30px; padding: 15px; background: #f9f9f9; border-left: 4px solid #0073aa;">
                <h4><?php _e('How Competition Management Works', 'goalv'); ?></h4>
                <ul>
                    <li><?php _e('Click "Fetch Available Competitions" to load leagues from API-Football', 'goalv'); ?></li>
                    <li><?php _e('Toggle competitions ON/OFF to control which leagues sync matches', 'goalv'); ?></li>
                    <li><?php _e('Active competitions will automatically sync matches hourly', 'goalv'); ?></li>
                    <li><?php _e('Recommended: Start with 3-5 major leagues to manage API usage', 'goalv'); ?></li>
                </ul>

                <h4 style="margin-top: 20px;"><?php _e('Popular Competitions to Enable', 'goalv'); ?></h4>
                <ul>
                    <li><strong>Premier League</strong> (England) - ID: 39</li>
                    <li><strong>La Liga</strong> (Spain) - ID: 140</li>
                    <li><strong>Serie A</strong> (Italy) - ID: 135</li>
                    <li><strong>Bundesliga</strong> (Germany) - ID: 78</li>
                    <li><strong>Ligue 1</strong> (France) - ID: 61</li>
                    <li><strong>Champions League</strong> (Europe) - ID: 2</li>
                </ul>
            </div>
        </div>

        <?php
    }

    /**
     * Render competitions table
     */
    private function render_competitions_table()
    {
        // Get competitions from database
        $competition_model = new GoalV_Competition();
        $competitions = $competition_model->get_all();

        if (empty($competitions)) {
            echo '<div class="goalv-no-competitions">';
            echo '<p>' . __('No competitions found. Click "Fetch Available Competitions" to load from API.', 'goalv') . '</p>';
            echo '</div>';
            return;
        }

        // Count active competitions
        $active_count = count(array_filter($competitions, function ($comp) {
            return $comp->is_active == 1;
        }));

        ?>
        <div class="goalv-competitions-stats" style="margin-bottom: 15px;">
            <span class="goalv-stat-badge">
                <strong><?php echo esc_html($active_count); ?></strong> <?php _e('Active', 'goalv'); ?>
            </span>
            <span class="goalv-stat-badge">
                <strong><?php echo count($competitions); ?></strong> <?php _e('Total Available', 'goalv'); ?>
            </span>
        </div>

        <table class="wp-list-table widefat fixed striped goalv-competitions-table">
            <thead>
                <tr>
                    <th style="width: 50px;"><?php _e('Logo', 'goalv'); ?></th>
                    <th><?php _e('Competition', 'goalv'); ?></th>
                    <th><?php _e('Country', 'goalv'); ?></th>
                    <th><?php _e('Type', 'goalv'); ?></th>
                    <th><?php _e('Season', 'goalv'); ?></th>
                    <th style="width: 100px;"><?php _e('Status', 'goalv'); ?></th>
                    <th style="width: 120px;"><?php _e('Actions', 'goalv'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($competitions as $competition): ?>
                    <tr class="competition-row" data-competition-id="<?php echo esc_attr($competition->id); ?>">
                        <td>
                            <?php if (!empty($competition->logo_url)): ?>
                                <img src="<?php echo esc_url($competition->logo_url); ?>"
                                    alt="<?php echo esc_attr($competition->name); ?>"
                                    style="width: 30px; height: 30px; object-fit: contain;">
                            <?php else: ?>
                                <span class="dashicons dashicons-awards" style="font-size: 30px; color: #999;"></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?php echo esc_html($competition->name); ?></strong>
                            <div class="row-actions">
                                <span class="goalv-competition-api-id">API ID:
                                    <?php echo esc_html($competition->api_competition_id); ?></span>
                            </div>
                        </td>
                        <td><?php echo esc_html($competition->country); ?></td>
                        <td><?php echo esc_html($competition->type); ?></td>
                        <td>
                            <?php
                            if (!empty($competition->current_season)) {
                                echo esc_html($competition->current_season);
                            } else {
                                echo '<span style="color: #999;">—</span>';
                            }
                            ?>
                        </td>
                        <td>
                            <?php if ($competition->is_active): ?>
                                <span class="goalv-status-badge goalv-status-active">
                                    <span class="dashicons dashicons-yes-alt"></span>
                                    <?php _e('Active', 'goalv'); ?>
                                </span>
                            <?php else: ?>
                                <span class="goalv-status-badge goalv-status-inactive">
                                    <span class="dashicons dashicons-dismiss"></span>
                                    <?php _e('Inactive', 'goalv'); ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button type="button" class="button button-small toggle-competition-btn"
                                data-competition-id="<?php echo esc_attr($competition->id); ?>"
                                data-current-status="<?php echo esc_attr($competition->is_active); ?>">
                                <?php if ($competition->is_active): ?>
                                    <span class="dashicons dashicons-no"></span>
                                    <?php _e('Disable', 'goalv'); ?>
                                <?php else: ?>
                                    <span class="dashicons dashicons-yes"></span>
                                    <?php _e('Enable', 'goalv'); ?>
                                <?php endif; ?>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }


}