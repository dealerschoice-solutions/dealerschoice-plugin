<?php
/**
 * WP-CLI commands for inventory sync, cleanup, logs, and stats (`wp dealers-choice`).
 *
 * @package DealersChoice
 */

namespace DC;

if (!defined('ABSPATH')) {
    exit;
}

class WP_CLI_Commands {
    
    /**
     * Syncs inventory from the IMS API.
     *
     * ## EXAMPLES
     * 
     *     wp dealers-choice sync
     * 
     * @when after_wp_load
     */
    public function sync($args, $assoc_args) {
        \WP_CLI::log('Starting inventory sync...');
        
        $client_id = get_option('dealers_choice_client_id');
        $api_key = get_option('dealers_choice_api_key');
        
        if (empty($client_id) || empty($api_key)) {
            \WP_CLI::error('API credentials not configured. Please set them in the admin panel first.');
            return;
        }
        
        try {
            $sync = new InventorySync($client_id, $api_key);
            
            $start_time = microtime(true);
            
            $sync->sync_inventory();
            
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
     * Permanently deletes draft boats not modified in the last 6 months.
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
     * Displays recent plugin log entries.
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
        
        if ($level_filter) {
            $recent_logs = array_filter($recent_logs, function($log) use ($level_filter) {
                return $log['level'] === $level_filter;
            });
        }
        
        $recent_logs = array_slice(array_reverse($recent_logs), 0, $lines);
        
        if (empty($recent_logs)) {
            \WP_CLI::log(sprintf('No logs found with level "%s".', $level_filter));
            return;
        }
        
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
     * Displays boat counts and the last sync time.
     *
     * ## EXAMPLES
     * 
     *     wp dealers-choice stats
     * 
     * @when after_wp_load
     */
    public function stats($args, $assoc_args) {
        $total_boats = wp_count_posts('boat');
        $published = $total_boats->publish ?? 0;
        $draft = $total_boats->draft ?? 0;
        
        // Published boats flagged is_local.
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

if (defined('WP_CLI') && WP_CLI) {
    \WP_CLI::add_command('dealers-choice', 'DC\WP_CLI_Commands');
}
