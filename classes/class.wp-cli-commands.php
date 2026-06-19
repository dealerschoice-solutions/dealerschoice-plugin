<?php
/**
 * WP-CLI Commands Class
 * 
 * Provides command-line interface for DealersChoice plugin operations.
 * Enables automation, scripting, and manual management of inventory sync
 * processes through WP-CLI.
 * 
 * @package DealersChoice
 * @subpackage Classes
 * @since 1.0.0
 * 
 * Available Commands:
 * 
 * wp dealers-choice sync
 *   - Triggers full inventory synchronization
 *   - Displays execution time and timestamp
 *   - Returns exit code 0 on success, 1 on error
 * 
 * wp dealers-choice cleanup
 *   - Deletes draft boats older than 6 months
 *   - Shows progress bar during deletion
 *   - Useful for database maintenance
 * 
 * wp dealers-choice logs [--lines=<number>] [--level=<level>]
 *   - Displays recent sync logs in table format
 *   - Options:
 *     --lines: Number of log entries (default: 20)
 *     --level: Filter by level (info, warning, error)
 * 
 * wp dealers-choice stats
 *   - Shows plugin statistics dashboard
 *   - Displays boat counts, last sync, local listings
 *   - Useful for monitoring and debugging
 * 
 * Usage Examples:
 *   wp dealers-choice sync
 *   wp dealers-choice cleanup
 *   wp dealers-choice logs --lines=50 --level=error
 *   wp dealers-choice stats
 * 
 * Automation:
 * Add to crontab for automatic syncing:
 *   0 2 * * * cd /path/to/wordpress && wp dealers-choice sync
 * 
 * Requirements:
 * - WP-CLI installed and accessible
 * - WordPress site with DealersChoice plugin active
 * - Proper file permissions for WordPress user
 * - API credentials configured in plugin settings
 * 
 * Dependencies:
 * - WP-CLI framework
 * - DC\InventorySync class
 * - WordPress WP_Query
 */

namespace DC;

if (!defined('ABSPATH')) {
    exit;
}

class WP_CLI_Commands {
    
    /**
     * Sync inventory from the API
     * 
     * ## EXAMPLES
     * 
     *     wp dealers-choice sync
     * 
     * @when after_wp_load
     */
    public function sync($args, $assoc_args) {
        \WP_CLI::log('Starting inventory sync...');
        
        // Get API credentials
        $client_id = get_option('dealers_choice_client_id');
        $api_key = get_option('dealers_choice_api_key');
        
        if (empty($client_id) || empty($api_key)) {
            \WP_CLI::error('API credentials not configured. Please set them in the admin panel first.');
            return;
        }
        
        try {
            $sync = new InventorySync($client_id, $api_key);
            
            // Capture start time
            $start_time = microtime(true);
            
            $sync->sync_inventory();
            
            // Calculate execution time
            $execution_time = round(microtime(true) - $start_time, 2);
            
            $last_sync = get_option('dealers_choice_last_sync', 0);
            
            \WP_CLI::success(sprintf(
                'Inventory sync completed in %s seconds. Last sync: %s',
                $execution_time,
                date('Y-m-d H:i:s', $last_sync)
            ));
            
        } catch (\Exception $e) {
            \WP_CLI::error('Sync failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Clean up old draft boat posts
     * 
     * ## EXAMPLES
     * 
     *     wp dealers-choice cleanup
     * 
     * @when after_wp_load
     */
    public function cleanup($args, $assoc_args) {
        \WP_CLI::log('Checking for old draft boats...');
        
        $args = [
            'post_type' => 'boat',
            'post_status' => 'draft',
            'posts_per_page' => -1,
            'date_query' => [
                [
                    'column' => 'post_modified_gmt',
                    'before' => '6 months ago',
                ],
            ],
            'fields' => 'ids',
        ];
        
        $old_drafts_query = new \WP_Query($args);
        $total_found = count($old_drafts_query->posts);
        
        if ($total_found === 0) {
            \WP_CLI::success('No old draft boats found.');
            return;
        }
        
        \WP_CLI::log(sprintf('Found %d draft boats older than 6 months.', $total_found));
        
        $progress = \WP_CLI\Utils\make_progress_bar('Deleting drafts', $total_found);
        
        $deleted_count = 0;
        foreach ($old_drafts_query->posts as $post_id) {
            $result = wp_delete_post($post_id, true);
            if ($result) {
                $deleted_count++;
            }
            $progress->tick();
        }
        
        $progress->finish();
        
        \WP_CLI::success(sprintf('Deleted %d old draft boats.', $deleted_count));
    }
    
    /**
     * Display recent sync logs
     * 
     * ## OPTIONS
     * 
     * [--lines=<number>]
     * : Number of log lines to display (default: 20)
     * 
     * [--level=<level>]
     * : Filter by log level: info, warning, error
     * 
     * ## EXAMPLES
     * 
     *     wp dealers-choice logs
     *     wp dealers-choice logs --lines=50
     *     wp dealers-choice logs --level=error
     * 
     * @when after_wp_load
     */
    public function logs($args, $assoc_args) {
        $lines = isset($assoc_args['lines']) ? intval($assoc_args['lines']) : 20;
        $level_filter = isset($assoc_args['level']) ? $assoc_args['level'] : null;
        
        $recent_logs = get_option('dealers_choice_recent_logs', []);
        
        if (empty($recent_logs)) {
            \WP_CLI::log('No logs found.');
            return;
        }
        
        // Filter by level if specified
        if ($level_filter) {
            $recent_logs = array_filter($recent_logs, function($log) use ($level_filter) {
                return $log['level'] === $level_filter;
            });
        }
        
        // Get most recent logs
        $recent_logs = array_slice(array_reverse($recent_logs), 0, $lines);
        
        if (empty($recent_logs)) {
            \WP_CLI::log(sprintf('No logs found with level "%s".', $level_filter));
            return;
        }
        
        // Display logs in table format
        $rows = [];
        foreach ($recent_logs as $log) {
            $rows[] = [
                'timestamp' => $log['timestamp'],
                'level' => strtoupper($log['level']),
                'message' => $log['message'],
            ];
        }
        
        \WP_CLI\Utils\format_items('table', $rows, ['timestamp', 'level', 'message']);
    }
    
    /**
     * Display plugin statistics
     * 
     * ## EXAMPLES
     * 
     *     wp dealers-choice stats
     * 
     * @when after_wp_load
     */
    public function stats($args, $assoc_args) {
        // Count boats
        $total_boats = wp_count_posts('boat');
        $published = $total_boats->publish ?? 0;
        $draft = $total_boats->draft ?? 0;
        
        // Count local boats
        $local_boats = new \WP_Query([
            'post_type' => 'boat',
            'post_status' => 'publish',
            'meta_query' => [
                [
                    'key' => 'is_local',
                    'value' => '1',
                    'compare' => '='
                ]
            ],
            'fields' => 'ids'
        ]);
        
        $last_sync = get_option('dealers_choice_last_sync', 0);
        
        \WP_CLI::log('=== Dealers Choice Statistics ===');
        \WP_CLI::log('');
        \WP_CLI::log(sprintf('Total Boats: %d', $published + $draft));
        \WP_CLI::log(sprintf('  Published: %d', $published));
        \WP_CLI::log(sprintf('  Draft: %d', $draft));
        \WP_CLI::log(sprintf('  Local Listings: %d', $local_boats->found_posts));
        \WP_CLI::log('');
        \WP_CLI::log(sprintf('Last Sync: %s', $last_sync ? date('Y-m-d H:i:s', $last_sync) : 'Never'));
        \WP_CLI::log('');
    }
}

// Register WP-CLI commands if WP-CLI is available
if (defined('WP_CLI') && WP_CLI) {
    \WP_CLI::add_command('dealers-choice', 'DC\WP_CLI_Commands');
}
