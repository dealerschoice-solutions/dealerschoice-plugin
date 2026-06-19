<?php
/**
 * Admin Sync Inventory Page
 * 
 * Provides an administrative interface for manually triggering inventory synchronization
 * and viewing sync logs. This page allows administrators to:
 * - Manually trigger inventory sync via AJAX
 * - View last sync timestamp
 * - Enable/disable logging
 * - View recent sync logs with context details
 * - Clear log history
 * 
 * @package DealersChoice
 * @subpackage Admin
 * @since 1.0.0
 * 
 * Features:
 * - Real-time AJAX sync with status updates
 * - Comprehensive logging viewer with expandable context
 * - Log filtering and management
 * - Visual feedback for sync status (loading/success/error)
 * 
 * Security:
 * - Requires 'manage_options' capability
 * - AJAX requests protected with nonce verification
 * - All output escaped for security
 * 
 * Dependencies:
 * - WordPress AJAX API
 * - jQuery for AJAX requests
 * - DC\InventorySync class
 */

if (!defined('ABSPATH')) {
    exit;
}

// Handle AJAX request to trigger sync
add_action('wp_ajax_dealers_choice_trigger_sync', 'dealers_choice_ajax_trigger_sync');

function dealers_choice_ajax_trigger_sync() {
    // Verify nonce
    check_ajax_referer('dealers_choice_sync_nonce', 'nonce');
    
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'You do not have permission to perform this action.']);
    }
    
    // Get API credentials
    $client_id = get_option('dealers_choice_client_id');
    $api_key = get_option('dealers_choice_api_key');
    
    if (empty($client_id) || empty($api_key)) {
        wp_send_json_error(['message' => 'Please configure API credentials first.']);
    }
    
    // Check if force sync is enabled
    $force_sync = isset($_POST['force_sync']) && $_POST['force_sync'] === 'true';
    
    try {
        // Initialize sync class and trigger sync
        $sync = new \DC\InventorySync($client_id, $api_key);
        $sync->sync_inventory(false, $force_sync);
        
        wp_send_json_success([
            'message' => 'Inventory sync completed successfully!',
            'timestamp' => current_time('mysql'),
            'forced' => $force_sync
        ]);
    } catch (Exception $e) {
        dealers_choice_log('Manual sync failed: ' . $e->getMessage(), 'error');
        wp_send_json_error(['message' => 'Sync failed: ' . $e->getMessage()]);
    }
}

/**
 * Enqueue admin styles and scripts for sync page
 */
function dealers_choice_sync_enqueue_assets($hook) {
    
    // Only load scripts on sync page
    if (strpos($hook, 'dealers-choice') === false) {
        return;
    }

    // Load admin styles
    wp_enqueue_style(
        'dealers-choice-admin-style',
        plugin_dir_url(__FILE__) . 'admin-style.css',
        [],
        '1.0.0'
    );
    
    // Enqueue admin scripts
    wp_enqueue_script(
        'dealers-choice-admin-script',
        plugin_dir_url(__FILE__) . 'admin-scripts.js',
        ['jquery'],
        '1.0.0',
        true
    );
    
    // Localize script with data
    wp_localize_script(
        'dealers-choice-admin-script',
        'dealersChoiceAdmin',
        [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'syncNonce' => wp_create_nonce('dealers_choice_sync_nonce')
        ]
    );
}
add_action('admin_enqueue_scripts', 'dealers_choice_sync_enqueue_assets');

function dealers_choice_sync_page() {
    $client_id = get_option('dealers_choice_client_id');
    $api_key = get_option('dealers_choice_api_key');
    $last_sync = get_option('dealers_choice_last_sync', 0);
    $recent_logs = get_option('dealers_choice_recent_logs', []);
    $log_enabled = get_option('dealers_choice_log_enabled', '1');
    
    // Handle settings update
    if (isset($_POST['dealers_choice_save_settings']) && check_admin_referer('dealers_choice_settings_nonce')) {
        $log_enabled = isset($_POST['dealers_choice_log_enabled']) ? '1' : '0';
        update_option('dealers_choice_log_enabled', $log_enabled);
        echo '<div class="notice notice-success"><p>Settings saved successfully.</p></div>';
    }
    
    // Handle clear logs action
    if (isset($_POST['dealers_choice_clear_logs']) && check_admin_referer('dealers_choice_clear_logs_nonce')) {
        delete_option('dealers_choice_recent_logs');
        $recent_logs = [];
        echo '<div class="notice notice-success"><p>Logs cleared successfully.</p></div>';
    }
    
    ?>
    <div class="wrap">
        <h1>Inventory Sync</h1>
        
        <?php if (empty($client_id) || empty($api_key)): ?>
            <div class="notice notice-warning">
                <p>Please <a href="<?php echo admin_url('admin.php?page=dealers-choice-settings'); ?>">configure your API credentials</a> before syncing.</p>
            </div>
        <?php else: ?>
            <div class="card" style="max-width: 600px;">
                <h2>Manual Sync</h2>
                <p>Last sync: <strong><?php echo $last_sync ? date('Y-m-d H:i:s', $last_sync) : 'Never'; ?></strong></p>
                
                <p>
                    <label style="display: inline-block; margin-bottom: 10px;">
                        <input type="checkbox" id="force-sync-checkbox" value="1">
                        <strong>Force Sync</strong> - Re-sync all inventory even if timestamp hasn't changed
                    </label>
                </p>
                
                <button type="button" id="dealers-choice-sync-btn" class="button button-primary button-hero">
                    <span class="dashicons dashicons-update" style="margin-top: -27px; vertical-align: middle;"></span>
                    Sync Inventory Now
                </button>
                
                <div id="sync-status" style="margin-top: 20px;"></div>
            </div>
        <?php endif; ?>
        
        <div class="card" style="max-width: 800px; margin-top: 20px;">
            <h2>Sync Settings</h2>
            <form method="post" action="">
                <?php wp_nonce_field('dealers_choice_settings_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">Enable Logging</th>
                        <td>
                            <label>
                                <input type="checkbox" name="dealers_choice_log_enabled" value="1" <?php checked($log_enabled, '1'); ?>>
                                Log sync activities and errors
                            </label>
                            <p class="description">Logs are stored in WordPress debug.log (if WP_DEBUG_LOG is enabled) and recent logs are displayed below.</p>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" name="dealers_choice_save_settings" class="button button-primary">Save Settings</button>
                </p>
            </form>
        </div>
        
        <?php if (!empty($recent_logs)): ?>
        <div class="card" style="max-width: 1000px; margin-top: 20px;">
            <h2>Recent Logs</h2>
            <form method="post" action="" style="margin-bottom: 10px;">
                <?php wp_nonce_field('dealers_choice_clear_logs_nonce'); ?>
                <button type="submit" name="dealers_choice_clear_logs" class="button">Clear Logs</button>
            </form>
            
            <div style="max-height: 400px; overflow-y: auto; background: #f9f9f9; padding: 10px; border: 1px solid #ddd;">
                <table class="wp-list-table widefat fixed striped" style="background: white;">
                    <thead>
                        <tr>
                            <th style="width: 150px;">Timestamp</th>
                            <th style="width: 80px;">Level</th>
                            <th>Message</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        // Show most recent logs first
                        $recent_logs = array_reverse($recent_logs);
                        foreach ($recent_logs as $log): 
                            $level_class = '';
                            switch ($log['level']) {
                                case 'error':
                                    $level_class = 'error';
                                    break;
                                case 'warning':
                                    $level_class = 'warning';
                                    break;
                                default:
                                    $level_class = 'info';
                            }
                        ?>
                        <tr>
                            <td><?php echo esc_html($log['timestamp']); ?></td>
                            <td><span class="<?php echo esc_attr($level_class); ?>"><?php echo esc_html(strtoupper($log['level'])); ?></span></td>
                            <td>
                                <?php echo esc_html($log['message']); ?>
                                <?php if (!empty($log['context'])): ?>
                                    <details style="margin-top: 5px;">
                                        <summary style="cursor: pointer; color: #0073aa;">Show context</summary>
                                        <pre style="background: #f0f0f0; padding: 5px; margin-top: 5px; font-size: 11px;"><?php echo esc_html(print_r($log['context'], true)); ?></pre>
                                    </details>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php
}
