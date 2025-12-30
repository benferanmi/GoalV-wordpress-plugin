<?php
/**
 * GoalV Admin Voting Settings Module
 * Handles voting configuration and custom options
 * Preserved from v7.0.5 - voting system functionality
 * 
 * @package GoalV
 * @subpackage Admin
 * @since 7.0.5 (preserved)
 * @version 8.0.0 (integrated into new admin system)
 */

if (!defined('ABSPATH')) {
    exit;
}

class GoalV_Admin_Voting
{
    /**
     * Constructor
     */
    public function __construct()
    {
        // Register AJAX handlers for custom voting options
        add_action('wp_ajax_goalv_add_custom_option', array($this, 'ajax_add_custom_option'));
        add_action('wp_ajax_goalv_remove_custom_option', array($this, 'ajax_remove_custom_option'));
        
        // Register AJAX handlers for category management
        add_action('wp_ajax_goalv_add_category', array($this, 'ajax_add_category'));
        add_action('wp_ajax_goalv_update_category', array($this, 'ajax_update_category'));
        add_action('wp_ajax_goalv_delete_category', array($this, 'ajax_delete_category'));
        add_action('wp_ajax_goalv_reorder_categories', array($this, 'ajax_reorder_categories'));
        
        // Add meta box for custom voting options
        add_action('add_meta_boxes', array($this, 'add_custom_voting_meta_box'));
    }

    /**
     * Render voting settings page
     */
    public function render()
    {
        ?>
        <div class="goalv-admin-section">
            <h2><?php _e('Voting Configuration', 'goalv'); ?></h2>
            <p class="description">
                <?php _e('Configure voting behavior and manage vote categories.', 'goalv'); ?>
            </p>

            <form method="post" action="options.php">
                <?php settings_fields('goalv_voting_settings'); ?>

                <!-- General Voting Settings -->
                <div style="margin-bottom: 30px;">
                    <h3><?php _e('General Voting Settings', 'goalv'); ?></h3>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Vote Changes', 'goalv'); ?></th>
                            <td>
                                <?php $allow_change = get_option('goalv_allow_vote_change', 'yes'); ?>
                                <label>
                                    <input type="checkbox" 
                                           name="goalv_allow_vote_change" 
                                           value="yes" 
                                           <?php checked($allow_change, 'yes'); ?> />
                                    <?php _e('Allow users to change their votes', 'goalv'); ?>
                                </label>
                                <p class="description">
                                    <?php _e('When disabled, users can only vote once per match.', 'goalv'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php _e('Location-Specific Settings', 'goalv'); ?></th>
                            <td>
                                <fieldset>
                                    <?php $allow_homepage_change = get_option('goalv_allow_homepage_vote_change', 'yes'); ?>
                                    <label>
                                        <input type="checkbox" 
                                               name="goalv_allow_homepage_vote_change" 
                                               value="yes" 
                                               <?php checked($allow_homepage_change, 'yes'); ?> />
                                        <?php _e('Allow vote changes on homepage', 'goalv'); ?>
                                    </label>
                                    <br><br>

                                    <?php $allow_details_change = get_option('goalv_allow_details_vote_change', 'yes'); ?>
                                    <label>
                                        <input type="checkbox" 
                                               name="goalv_allow_details_vote_change" 
                                               value="yes" 
                                               <?php checked($allow_details_change, 'yes'); ?> />
                                        <?php _e('Allow vote changes on match details page', 'goalv'); ?>
                                    </label>

                                    <p class="description">
                                        <?php _e('These settings only apply when general vote changes are enabled.', 'goalv'); ?>
                                    </p>
                                </fieldset>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php _e('Multiple Votes', 'goalv'); ?></th>
                            <td>
                                <?php $allow_multiple = get_option('goalv_allow_multiple_votes', 'no'); ?>
                                <label>
                                    <input type="checkbox" 
                                           name="goalv_allow_multiple_votes" 
                                           value="yes" 
                                           <?php checked($allow_multiple, 'yes'); ?> />
                                    <?php _e('Allow users to cast multiple votes per match', 'goalv'); ?>
                                </label>
                                <p class="description">
                                    <?php _e('When enabled, users can vote for multiple options on the same match.', 'goalv'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Category Management -->
                <div style="margin-bottom: 30px;">
                    <h3><?php _e('Prediction Categories', 'goalv'); ?></h3>
                    <p class="description">
                        <?php _e('Manage vote categories to organize predictions.', 'goalv'); ?>
                    </p>

                    <?php $this->render_category_management(); ?>
                </div>

                <?php submit_button(__('Save Voting Settings', 'goalv')); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render category management section
     */
    private function render_category_management()
    {
        $voting = new GoalV_Voting();
        $categories = $voting->get_available_categories();
        ?>

        <!-- Add New Category Form -->
        <div class="goalv-add-category-form" style="background: #f9f9f9; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
            <h4><?php _e('Add New Category', 'goalv'); ?></h4>
            <table class="form-table">
                <tr>
                    <td style="width: 40%;">
                        <label for="new-category-key"><?php _e('Category Key', 'goalv'); ?></label>
                        <input type="text" 
                               id="new-category-key" 
                               class="regular-text"
                               placeholder="<?php esc_attr_e('e.g., player_performance', 'goalv'); ?>" />
                        <p class="description">
                            <?php _e('Unique identifier (lowercase letters and underscores only)', 'goalv'); ?>
                        </p>
                    </td>
                    <td style="width: 40%;">
                        <label for="new-category-label"><?php _e('Display Label', 'goalv'); ?></label>
                        <input type="text" 
                               id="new-category-label" 
                               class="regular-text"
                               placeholder="<?php esc_attr_e('e.g., Player Performance', 'goalv'); ?>" />
                        <p class="description">
                            <?php _e('Label shown to users', 'goalv'); ?>
                        </p>
                    </td>
                    <td style="width: 20%;">
                        <br>
                        <button type="button" id="add-category-btn" class="button button-secondary">
                            <?php _e('Add Category', 'goalv'); ?>
                        </button>
                        <span id="add-category-loader" class="spinner"></span>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Existing Categories List -->
        <div class="goalv-categories-list">
            <h4><?php _e('Existing Categories', 'goalv'); ?></h4>
            
            <?php if (!empty($categories)): ?>
                <p class="description" style="margin-bottom: 15px;">
                    <?php _e('Drag and drop to reorder categories.', 'goalv'); ?>
                </p>

                <div id="categories-sortable-list" class="goalv-categories-sortable">
                    <?php foreach ($categories as $category): ?>
                        <div class="goalv-category-item" data-category-id="<?php echo esc_attr($category->id); ?>">
                            <div class="goalv-category-content">
                                <span class="goalv-drag-handle dashicons dashicons-menu"></span>

                                <div class="goalv-category-info">
                                    <strong><?php echo esc_html($category->category_label); ?></strong>
                                    <span class="goalv-category-key">(<?php echo esc_html($category->category_key); ?>)</span>
                                    <div class="goalv-category-meta">
                                        <?php
                                        global $wpdb;
                                        $count = $wpdb->get_var($wpdb->prepare(
                                            "SELECT COUNT(*) FROM {$wpdb->prefix}goalv_vote_options WHERE category = %s",
                                            $category->category_key
                                        ));
                                        printf(__('%d options', 'goalv'), $count);
                                        ?>
                                    </div>
                                </div>

                                <div class="goalv-category-actions">
                                    <?php if ($category->category_key !== 'other'): ?>
                                        <button type="button" 
                                                class="button button-small goalv-edit-category"
                                                data-category-id="<?php echo esc_attr($category->id); ?>"
                                                data-category-label="<?php echo esc_attr($category->category_label); ?>">
                                            <?php _e('Edit', 'goalv'); ?>
                                        </button>
                                        <button type="button" 
                                                class="button button-small button-link-delete goalv-delete-category"
                                                data-category-id="<?php echo esc_attr($category->id); ?>">
                                            <?php _e('Delete', 'goalv'); ?>
                                        </button>
                                    <?php else: ?>
                                        <span class="goalv-default-label">
                                            <?php _e('Default Category', 'goalv'); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Edit form (hidden by default) -->
                            <div class="goalv-edit-form" style="display: none;">
                                <input type="text" 
                                       class="edit-category-label regular-text" 
                                       value="<?php echo esc_attr($category->category_label); ?>" />
                                <button type="button" class="button button-primary save-category-edit">
                                    <?php _e('Save', 'goalv'); ?>
                                </button>
                                <button type="button" class="button cancel-category-edit">
                                    <?php _e('Cancel', 'goalv'); ?>
                                </button>
                                <span class="edit-category-loader spinner"></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="description"><?php _e('No categories found.', 'goalv'); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Add custom voting options meta box
     */
    public function add_custom_voting_meta_box()
    {
        add_meta_box(
            'goalv_custom_voting_options',
            __('Custom Voting Options', 'goalv'),
            array($this, 'render_custom_voting_meta_box'),
            'goalv_matches',
            'normal',
            'default'
        );
    }

    /**
     * Render custom voting options meta box
     */
    public function render_custom_voting_meta_box($post)
    {
        wp_nonce_field('goalv_vote_nonce', 'goalv_custom_voting_nonce');

        $voting = new GoalV_Voting();
        $all_options = $voting->get_vote_options($post->ID, 'detailed');
        $custom_options = array_filter($all_options, function($option) {
            return isset($option->is_custom) && $option->is_custom == 1;
        });

        $categories = $voting->get_available_categories();
        ?>

        <div id="goalv-custom-options-container">
            <p class="description">
                <?php _e('Add custom voting options for this match.', 'goalv'); ?>
            </p>

            <div id="goalv-custom-options-list">
                <?php if (!empty($custom_options)): ?>
                    <?php foreach ($custom_options as $option): ?>
                        <div class="goalv-custom-option-item" data-option-id="<?php echo esc_attr($option->id); ?>">
                            <strong><?php echo esc_html($option->option_text); ?></strong>
                            <span class="goalv-option-category"><?php echo esc_html($option->category); ?></span>
                            <button type="button" class="button button-small goalv-remove-option" data-option-id="<?php echo esc_attr($option->id); ?>">
                                <?php _e('Remove', 'goalv'); ?>
                            </button>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="description"><?php _e('No custom options added.', 'goalv'); ?></p>
                <?php endif; ?>
            </div>

            <div style="margin-top: 15px;">
                <input type="text" id="new-custom-option-text" class="regular-text" placeholder="<?php esc_attr_e('New option text', 'goalv'); ?>" />
                <select id="new-custom-option-category">
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo esc_attr($cat->category_key); ?>">
                            <?php echo esc_html($cat->category_label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="button" id="add-custom-option-btn" class="button button-secondary">
                    <?php _e('Add Option', 'goalv'); ?>
                </button>
                <span id="custom-option-loader" class="spinner"></span>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX: Add custom voting option (preserved from v7)
     */
    public function ajax_add_custom_option()
    {
        check_ajax_referer('goalv_vote_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'goalv'));
        }

        $match_id = intval($_POST['match_id']);
        $option_text = sanitize_text_field($_POST['option_text']);
        $option_type = sanitize_text_field($_POST['option_type']);
        $category = isset($_POST['category']) ? sanitize_text_field($_POST['category']) : 'other';

        if (!$match_id || !$option_text) {
            wp_send_json_error(__('Invalid input', 'goalv'));
        }

        global $wpdb;
        $table = $wpdb->prefix . 'goalv_vote_options';

        // Get display order
        $max_order = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(display_order) FROM $table WHERE match_id = %d AND option_type = %s AND is_custom = 1",
            $match_id, $option_type
        ));

        $result = $wpdb->insert($table, array(
            'match_id' => $match_id,
            'option_text' => $option_text,
            'option_type' => $option_type,
            'category' => $category,
            'is_custom' => 1,
            'display_order' => ($max_order ? $max_order + 1 : 1),
            'created_by' => get_current_user_id()
        ));

        if ($result) {
            wp_send_json_success(array(
                'option_id' => $wpdb->insert_id,
                'message' => __('Option added', 'goalv')
            ));
        } else {
            wp_send_json_error(__('Failed to add option', 'goalv'));
        }
    }

    /**
     * AJAX: Remove custom voting option
     */
    public function ajax_remove_custom_option()
    {
        check_ajax_referer('goalv_vote_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'goalv'));
        }

        $option_id = intval($_POST['option_id']);

        global $wpdb;
        $deleted = $wpdb->delete(
            $wpdb->prefix . 'goalv_vote_options',
            array('id' => $option_id, 'is_custom' => 1)
        );

        if ($deleted) {
            wp_send_json_success(__('Option removed', 'goalv'));
        } else {
            wp_send_json_error(__('Failed to remove option', 'goalv'));
        }
    }

    /**
     * AJAX handlers for categories (preserved from v7)
     */
    public function ajax_add_category()
    {
        check_ajax_referer('goalv_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'goalv'));
        }

        $key = sanitize_key($_POST['category_key']);
        $label = sanitize_text_field($_POST['category_label']);

        $voting = new GoalV_Voting();
        $id = $voting->create_category($key, $label);

        if ($id) {
            wp_send_json_success(array('category_id' => $id));
        } else {
            wp_send_json_error(__('Failed to add category', 'goalv'));
        }
    }

    public function ajax_update_category()
    {
        check_ajax_referer('goalv_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'goalv'));
        }

        $id = intval($_POST['category_id']);
        $label = sanitize_text_field($_POST['category_label']);
        $order = intval($_POST['display_order']);

        $voting = new GoalV_Voting();
        $success = $voting->update_category($id, $label, $order);

        wp_send_json_success();
    }

    public function ajax_delete_category()
    {
        check_ajax_referer('goalv_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'goalv'));
        }

        $id = intval($_POST['category_id']);
        
        $voting = new GoalV_Voting();
        $success = $voting->delete_category($id);

        if ($success) {
            wp_send_json_success();
        } else {
            wp_send_json_error(__('Cannot delete default category', 'goalv'));
        }
    }

    public function ajax_reorder_categories()
    {
        check_ajax_referer('goalv_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'goalv'));
        }

        $order = json_decode(stripslashes($_POST['category_order']), true);

        global $wpdb;
        foreach ($order as $index => $id) {
            $wpdb->update(
                $wpdb->prefix . 'goalv_vote_categories',
                array('display_order' => $index + 1),
                array('id' => intval($id))
            );
        }

        wp_send_json_success();
    }
}