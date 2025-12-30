<?php
/**
 * Competitions Management Admin Page - FIXED & ENHANCED
 * Multi-league configuration with filters, pagination, and sorting
 * 
 * @package GoalV
 * @since 8.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get pagination and filters
$page = isset($_GET['comp_page']) ? max(1, intval($_GET['comp_page'])) : 1;
$per_page = 100;
$offset = ($page - 1) * $per_page;

$filter_status = isset($_GET['comp_status']) ? sanitize_text_field($_GET['comp_status']) : '';
$filter_country = isset($_GET['comp_country']) ? sanitize_text_field($_GET['comp_country']) : '';
$search = isset($_GET['comp_search']) ? sanitize_text_field($_GET['comp_search']) : '';

// Get all competitions from database with sorting: ACTIVE FIRST, then inactive
global $wpdb;
$competitions_table = $wpdb->prefix . 'goalv_competitions';

// Build query with filters
$where_clauses = array('1=1');

if ($filter_status === 'active') {
    $where_clauses[] = 'is_active = 1';
} elseif ($filter_status === 'inactive') {
    $where_clauses[] = 'is_active = 0';
}

if ($filter_country) {
    $where_clauses[] = $wpdb->prepare('country = %s', $filter_country);
}

if ($search) {
    $where_clauses[] = $wpdb->prepare('name LIKE %s OR code LIKE %s', '%' . $search . '%', '%' . $search . '%');
}

$where = implode(' AND ', $where_clauses);

// Count total
$total = $wpdb->get_var("SELECT COUNT(*) FROM $competitions_table WHERE $where");
$total_pages = ceil($total / $per_page);

// Get competitions - SORTED: active first, then by name
$competitions = $wpdb->get_results(
    "SELECT * FROM $competitions_table 
     WHERE $where 
     ORDER BY is_active DESC, name ASC 
     LIMIT $per_page OFFSET $offset"
);

// Count active competitions
$active_count = $wpdb->get_var("SELECT COUNT(*) FROM $competitions_table WHERE is_active = 1");
$sync_enabled_count = $wpdb->get_var("SELECT COUNT(*) FROM $competitions_table WHERE sync_enabled = 1");

// Get unique countries for filter dropdown
$countries = $wpdb->get_col("SELECT DISTINCT country FROM $competitions_table WHERE country IS NOT NULL AND country != '' ORDER BY country ASC");
?>

<div class="goalv-admin-section">
    <div class="goalv-section-header">
        <h2><?php _e('Competition Management', 'goalv'); ?></h2>
        <p class="description">
            <?php _e('Manage which leagues and competitions to track. Sorted by active first, then inactive.', 'goalv'); ?>
        </p>
    </div>

    <!-- Summary Stats -->
    <div class="goalv-stats-row">
        <div class="goalv-stat-card">
            <div class="goalv-stat-icon dashicons dashicons-awards"></div>
            <div class="goalv-stat-content">
                <div class="goalv-stat-value"><?php echo $total; ?></div>
                <div class="goalv-stat-label"><?php _e('Total Competitions', 'goalv'); ?></div>
            </div>
        </div>
        
        <div class="goalv-stat-card goalv-stat-success">
            <div class="goalv-stat-icon dashicons dashicons-yes"></div>
            <div class="goalv-stat-content">
                <div class="goalv-stat-value"><?php echo $active_count; ?></div>
                <div class="goalv-stat-label"><?php _e('Active', 'goalv'); ?></div>
            </div>
        </div>
        
        <div class="goalv-stat-card goalv-stat-info">
            <div class="goalv-stat-icon dashicons dashicons-update"></div>
            <div class="goalv-stat-content">
                <div class="goalv-stat-value"><?php echo $sync_enabled_count; ?></div>
                <div class="goalv-stat-label"><?php _e('Sync Enabled', 'goalv'); ?></div>
            </div>
        </div>
    </div>

    <!-- Actions Bar -->
    <div class="goalv-card">
        <div class="goalv-actions-bar" style="display: flex; gap: 10px; margin-bottom: 15px; flex-wrap: wrap;">
            <button type="button" id="fetch-competitions-btn" class="button button-primary">
                <span class="dashicons dashicons-download"></span>
                <?php _e('Fetch from API', 'goalv'); ?>
            </button>
            
            <button type="button" id="refresh-competitions-btn" class="button button-secondary">
                <span class="dashicons dashicons-update"></span>
                <?php _e('Refresh List', 'goalv'); ?>
            </button>
            
            <button type="button" id="bulk-enable-btn" class="button button-secondary" disabled>
                <span class="dashicons dashicons-yes"></span>
                <?php _e('Enable Selected', 'goalv'); ?>
            </button>
            
            <button type="button" id="bulk-disable-btn" class="button button-secondary" disabled>
                <span class="dashicons dashicons-no"></span>
                <?php _e('Disable Selected', 'goalv'); ?>
            </button>
            
            <span class="spinner" id="competitions-spinner"></span>
        </div>
        
        <div id="competitions-message"></div>
    </div>

    <!-- Filters -->
    <div class="goalv-card" style="margin-top: 20px; padding: 15px; background: #f9f9f9;">
        <h3><?php _e('Filters & Search', 'goalv'); ?></h3>
        <form method="GET" action="" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end;">
            <input type="hidden" name="page" value="goalv-settings">
            <input type="hidden" name="tab" value="competitions">
            
            <!-- Search -->
            <div>
                <label for="comp-search"><?php _e('Search:', 'goalv'); ?></label>
                <input type="text" id="comp-search" name="comp_search" value="<?php echo esc_attr($search); ?>" placeholder="Competition name or code...">
            </div>
            
            <!-- Status Filter -->
            <div>
                <label for="comp-status"><?php _e('Status:', 'goalv'); ?></label>
                <select id="comp-status" name="comp_status">
                    <option value=""><?php _e('All', 'goalv'); ?></option>
                    <option value="active" <?php selected($filter_status, 'active'); ?>><?php _e('Active Only', 'goalv'); ?></option>
                    <option value="inactive" <?php selected($filter_status, 'inactive'); ?>><?php _e('Inactive Only', 'goalv'); ?></option>
                </select>
            </div>
            
            <!-- Country Filter -->
            <?php if (!empty($countries)): ?>
                <div>
                    <label for="comp-country"><?php _e('Country:', 'goalv'); ?></label>
                    <select id="comp-country" name="comp_country">
                        <option value=""><?php _e('All Countries', 'goalv'); ?></option>
                        <?php foreach ($countries as $country): ?>
                                <option value="<?php echo esc_attr($country); ?>" <?php selected($filter_country, $country); ?>>
                                    <?php echo esc_html($country); ?>
                                </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            
            <!-- Submit -->
            <button type="submit" class="button button-secondary"><?php _e('Filter', 'goalv'); ?></button>
            <a href="<?php echo admin_url('admin.php?page=goalv-settings&tab=competitions'); ?>" class="button button-secondary">
                <?php _e('Reset', 'goalv'); ?>
            </a>
        </form>
    </div>

    <!-- Competitions Table -->
    <div class="goalv-card" style="margin-top: 20px;">
        <table class="wp-list-table widefat fixed striped" id="competitions-table">
            <thead>
                <tr>
                    <td class="check-column">
                        <input type="checkbox" id="select-all-competitions" />
                    </td>
                    <th style="width: 50px;"><?php _e('Logo', 'goalv'); ?></th>
                    <th><?php _e('Competition', 'goalv'); ?></th>
                    <th><?php _e('Country', 'goalv'); ?></th>
                    <th><?php _e('Code', 'goalv'); ?></th>
                    <th><?php _e('Matches', 'goalv'); ?></th>
                    <th><?php _e('Last Synced', 'goalv'); ?></th>
                    <th><?php _e('Status', 'goalv'); ?></th>
                    <th><?php _e('Sync', 'goalv'); ?></th>
                    <th><?php _e('Actions', 'goalv'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($competitions)): ?>
                        <tr>
                            <td colspan="10" class="no-items">
                                <?php _e('No competitions found. Click "Fetch from API" to load available competitions.', 'goalv'); ?>
                            </td>
                        </tr>
                <?php else: ?>
                        <?php foreach ($competitions as $comp):
                            // Get match count
                            $matches_table = $wpdb->prefix . 'goalv_matches';
                            $match_count = $wpdb->get_var($wpdb->prepare(
                                "SELECT COUNT(*) FROM $matches_table WHERE competition_id = %d",
                                $comp->id
                            ));
                            ?>
                                <tr data-competition-id="<?php echo esc_attr($comp->id); ?>">
                                    <th class="check-column">
                                        <input type="checkbox" class="competition-checkbox" value="<?php echo esc_attr($comp->id); ?>" />
                                    </th>
                                    <td>
                                        <?php if (!empty($comp->logo_url)): ?>
                                                <img src="<?php echo esc_url($comp->logo_url); ?>" 
                                                     alt="<?php echo esc_attr($comp->name); ?>" 
                                                     style="width: 30px; height: 30px; object-fit: contain;" />
                                        <?php else: ?>
                                                <span class="dashicons dashicons-awards"></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo esc_html($comp->name); ?></strong>
                                        <div class="row-actions">
                                            <span>API ID: <?php echo esc_html($comp->api_competition_id); ?></span>
                                        </div>
                                    </td>
                                    <td><?php echo esc_html($comp->country ?: '-'); ?></td>
                                    <td><code><?php echo esc_html($comp->code ?: '-'); ?></code></td>
                                    <td>
                                        <strong><?php echo number_format($match_count); ?></strong>
                                        <?php if ($match_count > 0): ?>
                                                <a href="<?php echo admin_url('edit.php?post_type=goalv_matches&competition=' . $comp->id); ?>" class="row-actions">
                                                    <?php _e('View', 'goalv'); ?>
                                                </a>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($comp->last_synced): ?>
                                                <?php echo human_time_diff(strtotime($comp->last_synced), current_time('timestamp')) . ' ' . __('ago', 'goalv'); ?>
                                        <?php else: ?>
                                                <span class="description"><?php _e('Never', 'goalv'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button type="button" class="button button-small toggle-active-btn" 
                                                data-competition-id="<?php echo esc_attr($comp->id); ?>"
                                                data-current-status="<?php echo esc_attr($comp->is_active); ?>">
                                            <?php if ($comp->is_active): ?>
                                                    <span class="dashicons dashicons-yes-alt" style="color: green;"></span>
                                                    <?php _e('Active', 'goalv'); ?>
                                            <?php else: ?>
                                                    <span class="dashicons dashicons-dismiss" style="color: #d63638;"></span>
                                                    <?php _e('Inactive', 'goalv'); ?>
                                            <?php endif; ?>
                                        </button>
                                    </td>
                                    <td>
                                        <label class="goalv-toggle">
                                            <input type="checkbox" 
                                                   class="toggle-sync" 
                                                   data-competition-id="<?php echo esc_attr($comp->id); ?>"
                                                   <?php checked($comp->sync_enabled, 1); ?>
                                                   <?php disabled($comp->is_active, 0); ?> />
                                            <span class="goalv-toggle-slider"></span>
                                        </label>
                                        <span class="goalv-toggle-label">
                                            <?php echo $comp->sync_enabled ? __('On', 'goalv') : __('Off', 'goalv'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button type="button" 
                                                class="button button-small sync-single-btn" 
                                                data-competition-id="<?php echo esc_attr($comp->id); ?>">
                                            <span class="dashicons dashicons-update"></span>
                                            <?php _e('Sync', 'goalv'); ?>
                                        </button>
                                    </td>
                                </tr>
                        <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="tablenav bottom" style="margin-top: 20px;">
            <div class="tablenav-pages">
                <span class="displaying-num">
                    <?php printf(_n('%d item', '%d items', $total, 'goalv'), $total); ?>
                </span>
                <span class="pagination-links">
                    <?php
                    $base_url = admin_url('admin.php?page=goalv-settings&tab=competitions');
                    if ($search)
                        $base_url .= '&comp_search=' . urlencode($search);
                    if ($filter_status)
                        $base_url .= '&comp_status=' . urlencode($filter_status);
                    if ($filter_country)
                        $base_url .= '&comp_country=' . urlencode($filter_country);

                    if ($page > 1) {
                        echo '<a class="first-page button" href="' . esc_url($base_url . '&comp_page=1') . '"><span aria-hidden="true">&laquo;</span></a>';
                        echo '<a class="prev-page button" href="' . esc_url($base_url . '&comp_page=' . ($page - 1)) . '"><span aria-hidden="true">&lsaquo;</span></a>';
                    } else {
                        echo '<span class="tablenav-pages-navspan button disabled">&laquo;</span>';
                        echo '<span class="tablenav-pages-navspan button disabled">&lsaquo;</span>';
                    }

                    echo '<span class="screen-reader-text">Current Page</span>';
                    echo '<span id="table-paging-input" class="paging-input">';
                    printf(__('Page %d of %d', 'goalv'), $page, $total_pages);
                    echo '</span>';

                    if ($page < $total_pages) {
                        echo '<a class="next-page button" href="' . esc_url($base_url . '&comp_page=' . ($page + 1)) . '"><span aria-hidden="true">&rsaquo;</span></a>';
                        echo '<a class="last-page button" href="' . esc_url($base_url . '&comp_page=' . $total_pages) . '"><span aria-hidden="true">&raquo;</span></a>';
                    } else {
                        echo '<span class="tablenav-pages-navspan button disabled">&rsaquo;</span>';
                        echo '<span class="tablenav-pages-navspan button disabled">&raquo;</span>';
                    }
                    ?>
                </span>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
    /* =========================
   GoalV Summary Stats
   ========================= */

.goalv-stats-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 16px;
  margin: 20px 0;
}

/* Card */
.goalv-stat-card {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 18px 20px;
  background: #ffffff;
  border-radius: 10px;
  border-left: 4px solid #2271b1; /* WP blue */
  box-shadow: 0 4px 10px rgba(0, 0, 0, 0.06);
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.goalv-stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 18px rgba(0, 0, 0, 0.1);
}

/* Icon */
.goalv-stat-icon {
  font-size: 30px;
  color: #2271b1;
}

/* Content */
.goalv-stat-content {
  display: flex;
  flex-direction: column;
}

.goalv-stat-value {
  font-size: 26px;
  font-weight: 700;
  line-height: 1.2;
  color: #1d2327;
}

.goalv-stat-label {
  font-size: 13px;
  color: #646970;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

/* Variants */
.goalv-stat-success {
  border-left-color: #46b450;
}

.goalv-stat-success .goalv-stat-icon {
  color: #46b450;
}

.goalv-stat-info {
  border-left-color: #72aee6;
}

.goalv-stat-info .goalv-stat-icon {
  color: #72aee6;
}

/* Responsive tweak */
@media (max-width: 480px) {
  .goalv-stat-card {
    padding: 16px;
  }

  .goalv-stat-value {
    font-size: 22px;
  }
}

</style>

<script>
jQuery(document).ready(function($) {
    const nonce = '<?php echo wp_create_nonce('goalv_admin_nonce'); ?>';
    
    // Select all checkbox
    $('#select-all-competitions').on('change', function() {
        $('.competition-checkbox').prop('checked', $(this).prop('checked'));
        updateBulkButtons();
    });
    
    // Individual checkboxes
    $('.competition-checkbox').on('change', function() {
        const allChecked = $('.competition-checkbox').length === $('.competition-checkbox:checked').length;
        $('#select-all-competitions').prop('checked', allChecked);
        updateBulkButtons();
    });
    
    // Fetch competitions from API
    $('#fetch-competitions-btn').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm('Fetch all available competitions from API-Football? This may take a moment.')) {
            return;
        }
        
        const $button = $(this);
        const $spinner = $('#competitions-spinner');
        const $message = $('#competitions-message');
        
        $button.prop('disabled', true);
        $spinner.addClass('is-active');
        $message.html('');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'goalv_fetch_competitions',
                nonce: nonce
            },
            success: function(response) {
                if (response.success) {
                    $message.html('<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>');
                    setTimeout(() => location.reload(), 2000);
                } else {
                    $message.html('<div class="notice notice-error inline"><p>' + response.data + '</p></div>');
                }
            },
            error: function() {
                $message.html('<div class="notice notice-error inline"><p><?php _e('Network error.', 'goalv'); ?></p></div>');
            },
            complete: function() {
                $button.prop('disabled', false);
                $spinner.removeClass('is-active');
            }
        });
    });
    
    // Refresh competitions list
    $('#refresh-competitions-btn').on('click', function(e) {
        e.preventDefault();
        location.reload();
    });
    
    // Toggle active status
    $('.toggle-active-btn').on('click', function(e) {
        e.preventDefault();
        const $btn = $(this);
        const competitionId = $btn.data('competition-id');
        const currentStatus = parseInt($btn.data('current-status'));
        const newStatus = currentStatus ? 0 : 1;
        
        $btn.prop('disabled', true);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'goalv_toggle_competition',
                nonce: nonce,
                competition_id: competitionId,
                type: 'active',
                status: newStatus
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                    $btn.prop('disabled', false);
                }
            },
            error: function() {
                alert('Network error');
                $btn.prop('disabled', false);
            }
        });
    });
    
    // Toggle sync
    $('.toggle-sync').on('change', function() {
        const competitionId = $(this).data('competition-id');
        const isEnabled = $(this).prop('checked');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'goalv_toggle_competition',
                nonce: nonce,
                competition_id: competitionId,
                type: 'sync',
                status: isEnabled ? 1 : 0
            },
            success: function(response) {
                if (!response.success) {
                    $(this).prop('checked', !isEnabled);
                }
            }
        });
    });
    
    // Sync single
    $('.sync-single-btn').on('click', function(e) {
        e.preventDefault();
        const $btn = $(this);
        const competitionId = $btn.data('competition-id');
        
        $btn.prop('disabled', true);
        $btn.find('.dashicons').addClass('dashicons-update-spin');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'goalv_sync_single_competition',
                nonce: nonce,
                competition_id: competitionId
            },
            success: function(response) {
                if (response.success) {
                    alert('Sync completed!');
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                alert('Network error');
            },
            complete: function() {
                $btn.prop('disabled', false);
                $btn.find('.dashicons').removeClass('dashicons-update-spin');
            }
        });
    });
    
    // Bulk actions
    $('#bulk-enable-btn, #bulk-disable-btn').on('click', function(e) {
        e.preventDefault();
        const $btn = $(this);
        const action = $btn.attr('id') === 'bulk-enable-btn' ? 'enable' : 'disable';
        const selectedIds = $('.competition-checkbox:checked').map(function() { return $(this).val(); }).get();
        
        if (selectedIds.length === 0) {
            alert('Please select at least one competition');
            return;
        }
        
        if (!confirm('Are you sure? This will ' + action + ' ' + selectedIds.length + ' competition(s)')) {
            return;
        }
        
        $btn.prop('disabled', true);
        
        // Bulk update via AJAX
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'goalv_bulk_toggle_competitions',
                nonce: nonce,
                competition_ids: selectedIds,
                bulk_action: action
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                alert('Network error');
                $btn.prop('disabled', false);
            }
        });
    });
    
    function updateBulkButtons() {
        const hasSelection = $('.competition-checkbox:checked').length > 0;
        $('#bulk-enable-btn, #bulk-disable-btn').prop('disabled', !hasSelection);
    }
});
</script>