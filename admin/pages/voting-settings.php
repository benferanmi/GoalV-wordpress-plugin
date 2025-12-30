<?php
/**
 * Voting Settings Admin Page
 * Preserves existing voting functionality from v7.0.5
 * 
 * @package GoalV
 * @since 7.0.5 (preserved)
 * @version 8.1.0 (integrated)
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get voting instance
$voting = new GoalV_Voting();
$categories = $voting->get_available_categories();

// Get current settings
$allow_vote_change = get_option('goalv_allow_vote_change', 'yes');
$allow_homepage_change = get_option('goalv_allow_homepage_vote_change', 'yes');
$allow_details_change = get_option('goalv_allow_details_vote_change', 'yes');
$allow_multiple_votes = get_option('goalv_allow_multiple_votes', 'no');
?>

<div class="goalv-admin-section">
    <div class="goalv-section-header">
        <h2><?php _e('Voting Settings', 'goalv'); ?></h2>
        <p class="description">
            <?php _e('Configure voting behavior, permissions, and vote categories.', 'goalv'); ?>
        </p>
    </div>

    <!-- Voting Permissions -->
    <div class="goalv-card">
        <h3><?php _e('Voting Permissions', 'goalv'); ?></h3>
        
        <form method="post" action="options.php" id="goalv-voting-settings-form">
            <?php settings_fields('goalv_voting_settings'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Global Vote Changes', 'goalv'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   name="goalv_allow_vote_change" 
                                   value="yes" 
                                   <?php checked($allow_vote_change, 'yes'); ?> />
                            <?php _e('Allow users to change their votes', 'goalv'); ?>
                        </label>
                        <p class="description">
                            <?php _e('If enabled, users can change their vote after submitting. Affects all voting locations.', 'goalv'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Homepage Vote Changes', 'goalv'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   name="goalv_allow_homepage_vote_change" 
                                   value="yes" 
                                   <?php checked($allow_homepage_change, 'yes'); ?>
                                   <?php disabled($allow_vote_change, 'no'); ?> />
                            <?php _e('Allow vote changes on homepage', 'goalv'); ?>
                        </label>
                        <p class="description">
                            <?php _e('Specific permission for homepage voting widgets.', 'goalv'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Details Page Vote Changes', 'goalv'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   name="goalv_allow_details_vote_change" 
                                   value="yes" 
                                   <?php checked($allow_details_change, 'yes'); ?>
                                   <?php disabled($allow_vote_change, 'no'); ?> />
                            <?php _e('Allow vote changes on match details page', 'goalv'); ?>
                        </label>
                        <p class="description">
                            <?php _e('Specific permission for single match pages.', 'goalv'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Multiple Votes', 'goalv'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   name="goalv_allow_multiple_votes" 
                                   value="yes" 
                                   <?php checked($allow_multiple_votes, 'yes'); ?> />
                            <?php _e('Allow multiple votes per match (across different categories)', 'goalv'); ?>
                        </label>
                        <p class="description">
                            <?php _e('If disabled, users can only vote once per match regardless of category.', 'goalv'); ?>
                        </p>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(__('Save Voting Settings', 'goalv')); ?>
        </form>
    </div>

    <!-- Vote Categories Management -->
    <div class="goalv-card" style="margin-top: 20px;">
        <h3><?php _e('Vote Categories', 'goalv'); ?></h3>
        <p class="description">
            <?php _e('Organize voting options into categories. Categories help group related predictions together.', 'goalv'); ?>
        </p>
        
        <table class="wp-list-table widefat fixed striped" id="categories-table">
            <thead>
                <tr>
                    <th style="width: 50px;"><?php _e('Order', 'goalv'); ?></th>
                    <th><?php _e('Category Key', 'goalv'); ?></th>
                    <th><?php _e('Display Label', 'goalv'); ?></th>
                    <th><?php _e('Status', 'goalv'); ?></th>
                    <th><?php _e('Actions', 'goalv'); ?></th>
                </tr>
            </thead>
            <tbody id="categories-sortable">
                <?php if (empty($categories)): ?>
                    <tr>
                        <td colspan="5" class="no-items">
                            <?php _e('No categories found.', 'goalv'); ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($categories as $category): ?>
                        <tr data-category-id="<?php echo esc_attr($category->id); ?>">
                            <td class="drag-handle" style="cursor: move;">
                                <span class="dashicons dashicons-menu"></span>
                                <?php echo esc_html($category->display_order); ?>
                            </td>
                            <td>
                                <code><?php echo esc_html($category->category_key); ?></code>
                            </td>
                            <td>
                                <strong class="category-label"><?php echo esc_html($category->category_label); ?></strong>
                                <div class="row-actions">
                                    <span class="edit">
                                        <a href="#" class="edit-category-btn" data-category-id="<?php echo esc_attr($category->id); ?>">
                                            <?php _e('Edit', 'goalv'); ?>
                                        </a>
                                    </span>
                                </div>
                            </td>
                            <td>
                                <?php if ($category->is_active): ?>
                                    <span class="goalv-status-badge goalv-status-success">
                                        <span class="dashicons dashicons-yes"></span>
                                        <?php _e('Active', 'goalv'); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="goalv-status-badge goalv-status-error">
                                        <span class="dashicons dashicons-dismiss"></span>
                                        <?php _e('Inactive', 'goalv'); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($category->category_key !== 'other'): ?>
                                    <button type="button" 
                                            class="button button-small delete-category-btn" 
                                            data-category-id="<?php echo esc_attr($category->id); ?>">
                                        <span class="dashicons dashicons-trash"></span>
                                        <?php _e('Delete', 'goalv'); ?>
                                    </button>
                                <?php else: ?>
                                    <span class="description"><?php _e('Default category', 'goalv'); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        
        <!-- Add New Category Form -->
        <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd;">
            <h4><?php _e('Add New Category', 'goalv'); ?></h4>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="new_category_key"><?php _e('Category Key', 'goalv'); ?></label>
                    </th>
                    <td>
                        <input type="text" 
                               id="new_category_key" 
                               class="regular-text" 
                               placeholder="<?php esc_attr_e('e.g., halftime_result', 'goalv'); ?>" />
                        <p class="description">
                            <?php _e('Lowercase letters and underscores only. Used for internal reference.', 'goalv'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="new_category_label"><?php _e('Display Label', 'goalv'); ?></label>
                    </th>
                    <td>
                        <input type="text" 
                               id="new_category_label" 
                               class="regular-text" 
                               placeholder="<?php esc_attr_e('e.g., Halftime Result', 'goalv'); ?>" />
                        <p class="description">
                            <?php _e('Human-readable label shown to users.', 'goalv'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"></th>
                    <td>
                        <button type="button" id="add-category-btn" class="button button-primary">
                            <span class="dashicons dashicons-plus"></span>
                            <?php _e('Add Category', 'goalv'); ?>
                        </button>
                        <span class="spinner" id="category-spinner"></span>
                    </td>
                </tr>
            </table>
        </div>
        
        <div id="category-message" style="margin-top: 15px;"></div>
    </div>

    <!-- Display Labels -->
    <div class="goalv-card" style="margin-top: 20px;">
        <h3><?php _e('Display Labels', 'goalv'); ?></h3>
        <p class="description">
            <?php _e('Customize text labels shown on the frontend.', 'goalv'); ?>
        </p>
        
        <form method="post" action="options.php">
            <?php settings_fields('goalv_display_labels'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="goalv_labels_teams"><?php _e('Teams Label', 'goalv'); ?></label>
                    </th>
                    <td>
                        <input type="text" 
                               id="goalv_labels_teams" 
                               name="goalv_labels_teams" 
                               value="<?php echo esc_attr(get_option('goalv_labels_teams', __('Teams', 'goalv'))); ?>" 
                               class="regular-text" />
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="goalv_labels_score"><?php _e('Score Label', 'goalv'); ?></label>
                    </th>
                    <td>
                        <input type="text" 
                               id="goalv_labels_score" 
                               name="goalv_labels_score" 
                               value="<?php echo esc_attr(get_option('goalv_labels_score', __('Score', 'goalv'))); ?>" 
                               class="regular-text" />
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="goalv_labels_status"><?php _e('Status Label', 'goalv'); ?></label>
                    </th>
                    <td>
                        <input type="text" 
                               id="goalv_labels_status" 
                               name="goalv_labels_status" 
                               value="<?php echo esc_attr(get_option('goalv_labels_status', __('Status', 'goalv'))); ?>" 
                               class="regular-text" />
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="goalv_labels_date"><?php _e('Date Label', 'goalv'); ?></label>
                    </th>
                    <td>
                        <input type="text" 
                               id="goalv_labels_date" 
                               name="goalv_labels_date" 
                               value="<?php echo esc_attr(get_option('goalv_labels_date', __('Date', 'goalv'))); ?>" 
                               class="regular-text" />
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="goalv_labels_predictions"><?php _e('Predictions Label', 'goalv'); ?></label>
                    </th>
                    <td>
                        <input type="text" 
                               id="goalv_labels_predictions" 
                               name="goalv_labels_predictions" 
                               value="<?php echo esc_attr(get_option('goalv_labels_predictions', __('Predictions', 'goalv'))); ?>" 
                               class="regular-text" />
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="goalv_labels_details"><?php _e('Details Label', 'goalv'); ?></label>
                    </th>
                    <td>
                        <input type="text" 
                               id="goalv_labels_details" 
                               name="goalv_labels_details" 
                               value="<?php echo esc_attr(get_option('goalv_labels_details', __('View Details', 'goalv'))); ?>" 
                               class="regular-text" />
                    </td>
                </tr>
            </table>
            
            <?php submit_button(__('Save Display Labels', 'goalv')); ?>
        </form>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    const nonce = '<?php echo wp_create_nonce('goalv_admin_nonce'); ?>';
    
    // Add new category
    $('#add-category-btn').on('click', function() {
        const categoryKey = $('#new_category_key').val().trim();
        const categoryLabel = $('#new_category_label').val().trim();
        const $button = $(this);
        const $spinner = $('#category-spinner');
        const $message = $('#category-message');
        
        if (!categoryKey || !categoryLabel) {
            $message.html(
                '<div class="notice notice-error inline"><p>' +
                '<?php _e('Please fill in both fields.', 'goalv'); ?></p></div>'
            );
            return;
        }
        
        $button.prop('disabled', true);
        $spinner.addClass('is-active');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'goalv_add_category',
                nonce: nonce,
                category_key: categoryKey,
                category_label: categoryLabel
            },
            success: function(response) {
                if (response.success) {
                    $message.html(
                        '<div class="notice notice-success inline"><p>' +
                        response.data.message + '</p></div>'
                    );
                    
                    // Clear inputs
                    $('#new_category_key, #new_category_label').val('');
                    
                    // Reload to show new category
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    $message.html(
                        '<div class="notice notice-error inline"><p>' +
                        response.data + '</p></div>'
                    );
                }
            },
            error: function() {
                $message.html(
                    '<div class="notice notice-error inline"><p>' +
                    '<?php _e('Network error occurred.', 'goalv'); ?></p></div>'
                );
            },
            complete: function() {
                $button.prop('disabled', false);
                $spinner.removeClass('is-active');
            }
        });
    });
    
    // Delete category
    $('.delete-category-btn').on('click', function() {
        const categoryId = $(this).data('category-id');
        const $row = $(this).closest('tr');
        
        if (!confirm('<?php _e('Are you sure you want to delete this category? All voting options using this category will be moved to "Other".', 'goalv'); ?>')) {
            return;
        }
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'goalv_delete_category',
                nonce: nonce,
                category_id: categoryId
            },
            success: function(response) {
                if (response.success) {
                    $row.fadeOut(300, function() {
                        $(this).remove();
                    });
                } else {
                    alert(response.data);
                }
            }
        });
    });
    
    // Make categories sortable (jQuery UI required)
    if ($.fn.sortable) {
        $('#categories-sortable').sortable({
            handle: '.drag-handle',
            update: function(event, ui) {
                const order = $(this).sortable('toArray', {attribute: 'data-category-id'});
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'goalv_reorder_categories',
                        nonce: nonce,
                        category_order: JSON.stringify(order)
                    }
                });
            }
        });
    }
});
</script>