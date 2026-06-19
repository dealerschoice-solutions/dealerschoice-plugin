<?php
/**
 * Plugin Name: DealersChoice Solutions
 * Plugin URI: https://www.dealerschoicesolutions.com
 * Description: A comprehensive dealership inventory management plugin that syncs boat listings from your DMS to WordPress with automatic categorization, image management, and advanced filtering.
 * Version: 1.0.0
 * Author: DealersChoice, by Mannix Marketing
 * Author URI: https://www.dealerschoicesolutions.com
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: dealers-choice
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * 
 * @package DealersChoice
 * @author Mannix Marketing
 * @copyright 2026 Mannix Marketing
 * 
 * This plugin provides:
 * - Automatic inventory sync from DealersChoice API
 * - Custom 'boat' post type
 * - Smart range taxonomies (price, length, horsepower) for filtering
 * - Image download and caching to prevent duplicates
 * - Local listing support for manual inventory additions
 * - Draft management with automatic cleanup
 * - Manual sync trigger (Admin UI and WP-CLI)
 * 
 * Dependencies:
 * - Advanced Custom Fields (ACF) - Required
 * - DealersChoice API credentials - Required for sync
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Activation and Deactivation hooks
register_activation_hook(__FILE__, 'dealers_choice_activate');
register_deactivation_hook(__FILE__, 'dealers_choice_deactivate');

/**
 * Plugin activation callback
 */
function dealers_choice_activate() {
    registerCustomPostTypeAndTaxonomies();
    flush_rewrite_rules();
    
    // Set default options
    add_option('dealers_choice_log_enabled', '1');
    
    // Add database indexes for better query performance
    dealers_choice_add_database_indexes();

    // Create favorites analytics table
    dealers_choice_create_favorites_table();
    
    // Schedule automatic inventory sync (twice daily)
    if (!wp_next_scheduled('dealerschoice_inventory_sync_cron')) {
        wp_schedule_event(time(), 'twicedaily', 'dealerschoice_inventory_sync_cron');
    }
    
    // Log activation
    dealers_choice_log('Plugin activated', 'info');
}

/**
 * Create the favorites analytics table if it does not exist.
 * Safe to call on every load — uses dbDelta which is idempotent.
 */
function dealers_choice_create_favorites_table() {
    global $wpdb;

    $table_name      = $wpdb->prefix . 'dc_favorite_events';
    $db_version      = '1.0';
    $installed_ver   = get_option( 'dealers_choice_favorites_db_version', '' );

    if ( $installed_ver === $db_version ) {
        return;
    }

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table_name} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        boat_id BIGINT UNSIGNED NOT NULL,
        action VARCHAR(10) NOT NULL,
        event_time DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY boat_id (boat_id),
        KEY event_time (event_time)
    ) {$charset_collate};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );

    update_option( 'dealers_choice_favorites_db_version', $db_version );
    dealers_choice_log( 'Favorites analytics table created/updated', 'info' );
}

/**
 * Add database indexes for optimized queries
 */
function dealers_choice_add_database_indexes() {
    global $wpdb;
    
    // Check if indexes already exist before adding
    $indexes = $wpdb->get_results("SHOW INDEX FROM {$wpdb->postmeta} WHERE Key_name = 'meta_key_value'");
    
    if (empty($indexes)) {
        // Add composite index on meta_key and meta_value for faster ACF field queries
        $wpdb->query("ALTER TABLE {$wpdb->postmeta} ADD INDEX meta_key_value (meta_key(191), meta_value(100))");
        dealers_choice_log('Added database indexes for optimization', 'info');
    }
}

/**
 * Plugin deactivation callback
 */
function dealers_choice_deactivate() {
    // Clear scheduled cron jobs
    $timestamp = wp_next_scheduled('delete_old_draft_boats_cron');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'delete_old_draft_boats_cron');
    }
    
    $sync_timestamp = wp_next_scheduled('dealerschoice_inventory_sync_cron');
    if ($sync_timestamp) {
        wp_unschedule_event($sync_timestamp, 'dealerschoice_inventory_sync_cron');
    }
    
    flush_rewrite_rules();
    
    // Log deactivation
    dealers_choice_log('Plugin deactivated', 'info');
}

/**
 * Logging function for the plugin
 * 
 * @param string $message Log message
 * @param string $level Log level: info, warning, error
 * @param array $context Additional context
 */
function dealers_choice_log($message, $level = 'info', $context = []) {
    // Check if logging is enabled
    if (!get_option('dealers_choice_log_enabled', '1')) {
        return;
    }
    
    // Format the log message
    $formatted_message = sprintf(
        '[DealersChoice] [%s] %s',
        strtoupper($level),
        $message
    );

    if (!empty($context)) {
        $formatted_message .= ' | Context: ' . json_encode($context);
    }

    // Log to WordPress debug.log if WP_DEBUG_LOG is enabled
    if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
        error_log($formatted_message);
    }

    // Also store recent logs in option for admin display
    $recent_logs = get_option('dealers_choice_recent_logs', []);
    $recent_logs[] = [
        'timestamp' => current_time('mysql'),
        'level' => $level,
        'message' => $message,
        'context' => $context
    ];

    // Keep only last 100 logs
    if (count($recent_logs) > 100) {
        $recent_logs = array_slice($recent_logs, -100);
    }

    update_option('dealers_choice_recent_logs', $recent_logs);
}

/**
 * Check if ACF plugin is active
 */
function dealers_choice_check_acf() {
    if (!function_exists('acf_add_local_field_group')) {
        add_action('admin_notices', 'dealers_choice_acf_admin_notice');
        return false;
    }
    return true;
}
add_action('plugins_loaded', 'dealers_choice_check_acf');

/**
 * Display admin notice if ACF is not active
 */
function dealers_choice_acf_admin_notice() {
    ?>
    <div class="notice notice-error">
        <p>
            <strong><?php _e('DealersChoice Plugin:', 'dealers-choice'); ?></strong>
            <?php _e('This plugin requires Advanced Custom Fields (ACF) to be installed and activated.', 'dealers-choice'); ?>
            <a href="<?php echo admin_url('plugin-install.php?s=advanced+custom+fields&tab=search&type=term'); ?>">
                <?php _e('Install ACF Now', 'dealers-choice'); ?>
            </a>
        </p>
    </div>
    <?php
}

// Define plugin constants
define('DC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('DC_VERSION', '1.0.0');

// Load core classes
require_once plugin_dir_path(__FILE__) . '/classes/class.inventory.php';
require_once plugin_dir_path(__FILE__) . '/classes/class.boat.php';
require_once plugin_dir_path(__FILE__) . '/classes/class.inventory-sync.php';
require_once plugin_dir_path(__FILE__) . '/classes/class.acf-taxonomy-sync.php';
require_once plugin_dir_path(__FILE__) . '/classes/class.wp-cli-commands.php';
require_once plugin_dir_path(__FILE__) . '/classes/class.insert-shortcodes.php';

// Load public-facing classes
require_once plugin_dir_path(__FILE__) . '/classes/class.template-loader.php';
require_once plugin_dir_path(__FILE__) . '/classes/class.ajax-handlers.php';
require_once plugin_dir_path(__FILE__) . '/classes/class.shortcodes.php';
require_once plugin_dir_path(__FILE__) . '/classes/class.redirects.php';
require_once plugin_dir_path(__FILE__) . '/classes/class.boat-quiz.php';
require_once plugin_dir_path(__FILE__) . '/classes/class.gravity-forms.php';

// Sync ACF fields to taxonomies on manual boat post save
new DC\ACF_Taxonomy_Sync();

// Initialize AJAX handlers
DC\AJAX_Handlers::init();

// Initialize shortcodes
DC\Shortcodes::init();

// Initialize redirects
DC\Redirects::init();

// Initialize Gravity Forms integration (quiz lead-capture data in notifications)
DC\GravityForms::init();

// Ensure the favorites table exists on every load (handles plugin upgrades without reactivation)
add_action('plugins_loaded', 'dealers_choice_create_favorites_table');

// Register the favorites analytics dashboard widget
add_action('wp_dashboard_setup', 'dealers_choice_register_favorites_widget');

/**
 * Register the Favorite Boats dashboard widget.
 */
function dealers_choice_register_favorites_widget() {
    if ( ! current_user_can('manage_options') ) {
        return;
    }
    wp_add_dashboard_widget(
        'dc_favorite_boats_widget',
        __('Favorite Boats', 'dealers-choice'),
        'dealers_choice_favorites_widget_callback'
    );
}

/**
 * Render the Favorite Boats dashboard widget.
 */
function dealers_choice_favorites_widget_callback() {
    if ( get_option('dealers_choice_show_favorites', '1') !== '1' ) {
        echo '<p>' . esc_html__('Favorites are currently disabled in DealersChoice Settings.', 'dealers-choice') . '</p>';
        return;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'dc_favorite_events';

    // Period filter
    $period  = isset( $_GET['dc_fav_period'] ) ? sanitize_text_field( $_GET['dc_fav_period'] ) : '30';
    $periods = [ 'today' => 'Today', '7' => 'Last 7 Days', '30' => 'Last 30 Days', 'all' => 'All Time' ];
    $where   = '';
    if ( $period === 'today' ) {
        $where = $wpdb->prepare( 'WHERE DATE(event_time) = %s', current_time('Y-m-d') );
    } elseif ( is_numeric($period) ) {
        $where = $wpdb->prepare( 'WHERE event_time >= DATE_SUB(NOW(), INTERVAL %d DAY)', (int) $period );
    }

    $rows = $wpdb->get_results(
        "SELECT boat_id,
                SUM(CASE WHEN action='add'    THEN 1 ELSE 0 END) AS adds,
                SUM(CASE WHEN action='remove' THEN 1 ELSE 0 END) AS removes
         FROM {$table}
         {$where}
         GROUP BY boat_id
         HAVING (adds - removes) > 0
         ORDER BY (adds - removes) DESC
         LIMIT 10"
    );

    // Period tabs
    $base_url = admin_url('index.php');
    echo '<div style="margin-bottom:10px;">';
    foreach ( $periods as $key => $label ) {
        $active = ( $period === $key ) ? 'font-weight:bold;text-decoration:underline;' : '';
        $url    = esc_url( add_query_arg('dc_fav_period', $key, $base_url) );
        echo '<a href="' . $url . '" style="margin-right:10px;' . $active . '">' . esc_html($label) . '</a>';
    }
    echo '</div>';

    if ( empty($rows) ) {
        echo '<p>' . esc_html__('No favorites recorded yet for this period.', 'dealers-choice') . '</p>';
        $analytics_url = esc_url( admin_url('admin.php?page=dealers-choice-favorites') );
        echo '<p><a href="' . $analytics_url . '">' . esc_html__('View full analytics', 'dealers-choice') . '</a></p>';
        return;
    }

    echo '<table class="widefat fixed striped" style="margin-top:5px;">';
    echo '<thead><tr><th>#</th><th>Boat</th><th style="text-align:right;">Favorites</th></tr></thead><tbody>';
    $rank = 1;
    foreach ( $rows as $row ) {
        $title    = get_the_title( (int) $row->boat_id );
        $edit_url = get_edit_post_link( (int) $row->boat_id );
        $net      = (int) $row->adds - (int) $row->removes;
        if ( ! $title ) {
            $title = sprintf( __('Boat #%d (removed)', 'dealers-choice'), $row->boat_id );
            $edit_url = '';
        }
        echo '<tr>';
        echo '<td>' . esc_html( $rank ) . '</td>';
        echo '<td>' . ( $edit_url ? '<a href="' . esc_url($edit_url) . '">' . esc_html($title) . '</a>' : esc_html($title) ) . '</td>';
        echo '<td style="text-align:right;">' . esc_html( $net ) . '</td>';
        echo '</tr>';
        $rank++;
    }
    echo '</tbody></table>';

    $analytics_url = esc_url( admin_url('admin.php?page=dealers-choice-favorites') );
    echo '<p style="margin-top:8px;"><a href="' . $analytics_url . '">' . esc_html__('View full analytics →', 'dealers-choice') . '</a></p>';
}

// Admin classes
if (is_admin()) {
    require_once plugin_dir_path(__FILE__) . '/admin/admin.plugin-settings.php';
    require_once plugin_dir_path(__FILE__) . '/admin/admin.sync-inventory.php';
    require_once plugin_dir_path(__FILE__) . '/admin/admin.favorites-analytics.php';

    // Register admin menu
    add_action('admin_menu', function() {
        add_menu_page(
            __('DealersChoice Settings', 'dealers-choice'),
            __('DealersChoice', 'dealers-choice'),
            'manage_options',
            'dealers-choice-settings',
            'dealers_choice_settings_page',
            DC_PLUGIN_URL . 'admin/images/dealerschoice.svg'
        );

        add_submenu_page(
            'dealers-choice-settings',
            __('Settings', 'dealers-choice'),
            __('Settings', 'dealers-choice'),
            'manage_options',
            'dealers-choice-settings',
            'dealers_choice_settings_page'
        );

        add_submenu_page(
            'dealers-choice-settings',
            'Sync Inventory',
            'Sync Inventory',
            'manage_options',
            'dealers-choice-sync',
            'dealers_choice_sync_page'
        );

        add_submenu_page(
            'dealers-choice-settings',
            __('Favorite Boats', 'dealers-choice'),
            __('Favorite Boats', 'dealers-choice'),
            'manage_options',
            'dealers-choice-favorites',
            'dealers_choice_favorites_page'
        );

        // add submenu link to DealersChoice IMS (https://dealerschoiceims.securem2.com) to DealersChoice admin menu
        global $submenu;
        $submenu['dealers-choice-settings'][] = array(
            'DealersChoice IMS <span class="dashicons dashicons-external" style="display:inline; font-size: 14px; vertical-align: -1px;"></span>', 
            'manage_options', 
            'https://dealerschoiceims.securem2.com'
        );
    });
    
    // Add inline CSS for menu icon sizing on all admin pages
    add_action('admin_head', function() {
        echo '<style>
            #adminmenu #toplevel_page_dealers-choice-settings .wp-menu-image img {
                width: 20px;
                display: inline;
            }
        </style>';

        // Force external IMS menu link to open in a new tab safely
        echo '<script>
            jQuery(document).ready(function($) {
                $("a[href=\'https://dealerschoiceims.securem2.com\']").attr("target", "_blank").attr("rel", "noopener noreferrer");
            });
        </script>';
    });
}

// Optional AI description generator
if (defined( 'DCS_AI_SERVICE' )){
    require_once plugin_dir_path(__FILE__) . '/classes/class.generate-description.php';
}

/**
 * Register custom post type and taxonomy
 * Post Type: Inventory
 * Basic Taxonomies: Type, Condition, Make, Year, Price Range, Length
 * Advanced Taxonomies: Location, Horsepower
 */
function registerCustomPostTypeAndTaxonomies() {
    // Register the Boat custom post type
    $labels = [
        'name'                  => __('Inventory', 'dealers-choice'),
        'singular_name'         => __('Inventory', 'dealers-choice'),
        'menu_name'             => __('Inventory', 'dealers-choice'),
        'add_new'               => __('Add New', 'dealers-choice'),
        'add_new_item'          => __('Add New Boat', 'dealers-choice'),
        'edit_item'             => __('Edit Boat', 'dealers-choice'),
        'new_item'              => __('New Boat', 'dealers-choice'),
        'view_item'             => __('View Inventory', 'dealers-choice'),
        'search_items'          => __('Search Inventory', 'dealers-choice'),
        'not_found'             => __('No boats found', 'dealers-choice'),
        'not_found_in_trash'    => __('No boats found in trash', 'dealers-choice'),
    ];

    $args = [
        'labels' => $labels,
        "public" => true,
        "publicly_queryable" => true,
        "show_ui" => true,
        "show_in_rest" => true,
        "rest_base" => "",
        "rest_controller_class" => "WP_REST_Posts_Controller",
        "rest_namespace" => "wp/v2",
        "has_archive" => false,
        "show_in_menu" => true,
        "show_in_nav_menus" => true,
        "delete_with_user" => false,
        "exclude_from_search" => false,
        "capability_type" => "post",
        "map_meta_cap" => true,
        "hierarchical" => false,
        "can_export" => true,
        "rewrite" => [ "slug" => "boat/%type%", "with_front" => false ],
        "query_var" => true,
        "menu_position" => 25,
        "menu_icon" => "dashicons-excerpt-view",
        "supports" => [ "title", "editor", "thumbnail", "revisions", "page-attributes" ],
        "show_in_graphql" => false,
    ];

    register_post_type('boat', $args);

    // Register Taxonomies
    $taxonomies = [
        'boat_type' => ['Boat Type', 'Boat Types', 'boat-type'],
        'condition' => ['Condition', 'Conditions', 'condition'],
        'boat_status' => ['Status', 'Statuses', 'boat-status'],
        'location' => ['Location', 'Locations', 'location'],
        'boat_year' => ['Year', 'Years', 'year'],
        'make' => ['Make', 'Makes', 'make'],
        'model' => ['Model', 'Models', 'model'],
        'price_range' => ['Price Range', 'Price Ranges', 'price-range'],
        'length_range' => ['Length', 'Lengths', 'length-range'],
        'horsepower' => ['Horsepower', 'Horsepower', 'horsepower'],
        'person_capacity' => ['Person Capacity', 'Person Capacities', 'person-capacity'],
    ];

    foreach ($taxonomies as $taxonomy => $config) {
        register_taxonomy(
            $taxonomy,
            'boat',
            [
                'labels' => [
                    'name'              => __($config[1], 'dealers-choice'),
                    'singular_name'     => __($config[0], 'dealers-choice'),
                    'search_items'      => __('Search ' . $config[1], 'dealers-choice'),
                    'all_items'         => __('All ' . $config[1], 'dealers-choice'),
                    'edit_item'         => __('Edit ' . $config[0], 'dealers-choice'),
                    'update_item'       => __('Update ' . $config[0], 'dealers-choice'),
                    'add_new_item'      => __('Add New ' . $config[0], 'dealers-choice'),
                    'new_item_name'     => __('New ' . $config[0] . ' Name', 'dealers-choice'),
                    'menu_name'         => __($config[1], 'dealers-choice'),
                ],
                "public" => true,
                "publicly_queryable" => false,
                "hierarchical" => false,
                "show_ui" => true,
                "show_in_menu" => true,
                "show_in_nav_menus" => false,
                "query_var" => true,
                "rewrite" => false,
                "show_admin_column" => false,
                "show_in_rest" => false,
                "show_tagcloud" => false,
            ]
        );
    }

    // Create range taxonomy terms
    dealers_choice_create_range_terms();
}
add_action('init', 'registerCustomPostTypeAndTaxonomies');

/**
 * Allow for the inventory "boat_type" in the URL
 * URLs should be /boat/{boat_type}/{slug}/
 **/
function inventoryChangeLinkWithType( $post_link, $post = null, $leavename = false ) {
    // Check if the post is a 'boat'
    if ( is_object( $post ) && $post->post_type == 'boat' ) {
        // Get the terms associated with the post
        $terms = wp_get_object_terms( $post->ID, array('boat_type') );

        // Check if there was an error or if terms are empty
        if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
            // Replace the placeholder with the term slug
            return str_replace( '%type%', $terms[0]->slug, $post_link );
        } else {
            // If no terms found, use 'general' as default
            return str_replace( '%type%', 'general', $post_link );
        }
    }
    return $post_link;
}
add_filter( 'post_type_link', 'inventoryChangeLinkWithType', 1, 3 );

/**
 * Load the template on the new generated URL
 * otherwise you will get 404's the page
 **/
function inventoryGeneratedRewriteRules() {
    add_rewrite_rule(
        '^boat/(.*)/(.*)/?$',
        'index.php?post_type=boat&name=$matches[2]',
        'top'
        );
}
add_action( 'init', 'inventoryGeneratedRewriteRules' );

/**
 * Get default price range key
 */
function dealers_choice_get_default_price_range_key() {
    return "$0 - $20k";
}

/**
 * Get price ranges with min/max values
 *
 * @return array Array of price ranges with [min, max] values
 */
function dealers_choice_get_price_ranges() {
    return [
        dealers_choice_get_default_price_range_key() => [0, 20000],
        "$20k - $50k" => [20001, 50000],
        "$50k - $100k" => [50001, 100000],
        "$100k - $200k" => [100001, 200000],
        "$200k - $500k" => [200001, 500000],
        "$500k+" => [500001, 999999999]
    ];
}

/**
 * Get default length key
 */
function dealers_choice_get_default_length_key() {
    return "Under 20 ft.";
}

/**
 * Get length ranges with min/max values (in feet)
 *
 * @return array Array of length ranges with [min, max] values
 */
function dealers_choice_get_lengths() {
    return [
        dealers_choice_get_default_length_key() => [0, 20],
        "21 ft. - 24 ft." => [21, 24],
        "25 ft. - 29 ft." => [25, 29],
        "30 ft. - 34 ft." => [30, 34],
        "35 ft. - 39 ft." => [35, 39],
        "40 ft. and above" => [40, 9999]
    ];
}

/**
 * Get default horsepower key
 */
function dealers_choice_get_default_horsepower_key() {
    return "Up to 90";
}

/**
 * Get horsepower ranges with min/max values
 *
 * @return array Array of horsepower ranges with [min, max] values
 */
function dealers_choice_get_horsepower() {
    return [
        dealers_choice_get_default_horsepower_key() => [0, 90],
        "91 - 140" => [91, 140],
        "141 - 200" => [141, 200],
        "201 - 300" => [201, 300],
        "301 - 400" => [301, 400],
        "401 and up" => [401, 99999]
    ];
}

/**
 * Create taxonomy terms for price ranges, lengths, and horsepower
 */
function dealers_choice_create_range_terms() {
    // Create price range terms
    if ($price_ranges = dealers_choice_get_price_ranges()) {
        foreach ($price_ranges as $key => $min_max) {
            if (!term_exists($key, 'price_range')) {
                wp_insert_term($key, 'price_range');
            }
        }
    }

    // Create length terms
    if ($lengths = dealers_choice_get_lengths()) {
        foreach ($lengths as $key => $min_max) {
            if (!term_exists($key, 'length_range')) {
                wp_insert_term($key, 'length_range');
            }
        }
    }

    // Create horsepower terms
    if ($horsepower = dealers_choice_get_horsepower()) {
        foreach ($horsepower as $key => $min_max) {
            if (!term_exists($key, 'horsepower')) {
                wp_insert_term($key, 'horsepower');
            }
        }
    }

    // Create person capacity terms
    if ($capacities = dealers_choice_get_capacity_ranges()) {
        foreach ($capacities as $key => $min_max) {
            if (!term_exists($key, 'person_capacity')) {
                wp_insert_term($key, 'person_capacity');
            }
        }
    }
}

/**
 * Determine which price range a boat belongs to
 *
 * @param float $price The boat price
 * @return string|false The price range term name or false
 */
function dealers_choice_get_price_range_term($price) {
    $price = (float) $price;
    $ranges = dealers_choice_get_price_ranges();

    foreach ($ranges as $term_name => $min_max) {
        if ($price >= $min_max[0] && $price <= $min_max[1]) {
            return $term_name;
        }
    }

    return dealers_choice_get_default_price_range_key();
}

/**
 * Determine which length range a boat belongs to
 *
 * @param float $length_feet The boat length in feet
 * @return string|false The length range term name or false
 */
function dealers_choice_get_length_range_term($length_feet) {
    $length_feet = (float) $length_feet;
    $ranges = dealers_choice_get_lengths();

    foreach ($ranges as $term_name => $min_max) {
        if ($length_feet >= $min_max[0] && $length_feet <= $min_max[1]) {
            return $term_name;
        }
    }

    return dealers_choice_get_default_length_key();
}

/**
 * Determine which horsepower range a boat belongs to
 *
 * @param float $hp The boat horsepower
 * @return string|false The horsepower range term name or false
 */
function dealers_choice_get_horsepower_range_term($hp) {
    $hp = (float) $hp;
    $ranges = dealers_choice_get_horsepower();

    foreach ($ranges as $term_name => $min_max) {
        if ($hp >= $min_max[0] && $hp <= $min_max[1]) {
            return $term_name;
        }
    }

    return dealers_choice_get_default_horsepower_key();
}

/**
 * Get default person capacity key
 */
function dealers_choice_get_default_capacity_key() {
    return "1 - 4 People";
}

/**
 * Get person capacity ranges with min/max values
 *
 * @return array Array of capacity ranges with [min, max] values
 */
function dealers_choice_get_capacity_ranges() {
    return [
        dealers_choice_get_default_capacity_key() => [1, 4],
        "5 - 8 People"  => [5, 8],
        "9 - 12 People" => [9, 12],
        "13+ People"    => [13, 9999],
    ];
}

/**
 * Determine which person capacity range a boat belongs to
 *
 * @param int $capacity The boat's person capacity
 * @return string The capacity range term name
 */
function dealers_choice_get_capacity_range_term($capacity) {
    $capacity = (int) $capacity;
    $ranges = dealers_choice_get_capacity_ranges();

    foreach ($ranges as $term_name => $min_max) {
        if ($capacity >= $min_max[0] && $capacity <= $min_max[1]) {
            return $term_name;
        }
    }

    return dealers_choice_get_default_capacity_key();
}

/**
 * Generate Advanced Custom Fields (ACF) fields for the Inventory post type
 */
function addACFFieldGroupForBoats() {
    // Check if ACF is active
    if( function_exists('acf_add_local_field_group') ) {

        $location = array(
            array(
                array(
                    'param' => 'post_type',
                    'operator' => '==',
                    'value' => 'boat',
                ),
            ),
        );

        $existing_groups =[
            [
                "key"=>"group_boats_general_information",
                "title"=>"General Information",
                "fields"=>array(
                    array(
                        'key' => 'field_boat_id',
                        'label' => 'ID',
                        'name' => 'boat_id',
                        'type' => 'text',
                        'instructions' => 'Unique boat ID',
                        'required' => 1,
                        'wrapper' => array(
                            'width' => '33%',
                            'class' => 'acf-responsive-field',
                        ),
                    ),
                    array(
                        'key' => 'field_boat_make',
                        'label' => 'Make',
                        'name' => 'boat_make',
                        'type' => 'text',
                        'instructions' => '',
                        'required' => 0,
                        'wrapper' => array(
                            'width' => '33%',
                            'class' => 'acf-responsive-field',
                        ),
                    ),
                    array(
                        'key' => 'field_boat_model',
                        'label' => 'Model',
                        'name' => 'boat_model',
                        'type' => 'text',
                        'instructions' => '',
                        'required' => 0,
                        'wrapper' => array(
                            'width' => '33%',
                            'class' => 'acf-responsive-field',
                        ),
                    ),
                    array(
                        'key' => 'field_boat_status',
                        'label' => 'Status',
                        'name' => 'boat_status',
                        'type' => 'select',
                        'choices' => array(
                            'In Stock' => 'In Stock',
                            'Sold' => 'Sold',
                            'Pending' => 'Pending',
                        ),
                        'instructions' => '',
                        'required' => 0,
                        'wrapper' => array(
                            'width' => '33%',
                            'class' => 'acf-responsive-field',
                        ),
                    ),
                    array(
                        'key' => 'field_boat_banner',
                        'label' => 'Banner',
                        'name' => 'boat_banner',
                        'type' => 'text',
                        'instructions' => 'Short status banner shown on inventory cards (e.g. "Sale Pending", "Price Reduced", "Newly Listed"). Max 255 characters.',
                        'required' => 0,
                        'maxlength' => 255,
                        'wrapper' => array(
                            'width' => '66%',
                            'class' => 'acf-responsive-field',
                        ),
                    ),
                    array(
                        'key' => 'field_boat_year',
                        'label' => 'Year',
                        'name' => 'boat_year',
                        'type' => 'text',
                        'instructions' => '',
                        'required' => 0,
                        'wrapper' => array(
                            'width' => '33%',
                            'class' => 'acf-responsive-field',
                        ),
                    ),
                    array(
                        'key' => 'field_boat_stock_number',
                        'label' => 'Stock Number',
                        'name' => 'boat_stock_number',
                        'type' => 'text',
                        'instructions' => '',
                        'required' => 0,
                        'wrapper' => array(
                            'width' => '33%',
                            'class' => 'acf-responsive-field',
                        ),
                    ),
                    array(
                        'key' => 'field_boat_hin',
                        'label' => 'HIN',
                        'name' => 'boat_hin',
                        'type' => 'text',
                        'instructions' => '',
                        'required' => 0,
                        'wrapper' => array(
                            'width' => '33%',
                            'class' => 'acf-responsive-field',
                        ),
                    ),
                     array(
                        'key' => 'field_boat_main_color',
                        'label' => 'Main Color',
                        'name' => 'boat_main_color',
                        'type' => 'text',
                        'instructions' => '',
                        'required' => 0,
                        'wrapper' => array(
                            'width' => '33%',
                            'class' => 'acf-responsive-field',
                        ),
                    ),
                     array(
                        'key' => 'field_boat_accent_color',
                        'label' => 'Accent Color',
                        'name' => 'boat_accent_color',
                        'type' => 'text',
                        'instructions' => '',
                        'required' => 0,
                        'wrapper' => array(
                            'width' => '33%',
                            'class' => 'acf-responsive-field',
                        ),
                    ),
                    array(
                        'key' => 'field_boat_description',
                        'label' => 'Description',
                        'name' => 'boat_description',
                        'type' => 'wysiwyg',
                        'instructions' => '',
                        'required' => 0,
                        'wrapper' => array(
                            'width' => '100%',
                            'class' => 'acf-responsive-field',
                        ),
                    ),
                    array(
                        'key' => 'field_boat_saleprice',
                        'label' => 'Sale Price',
                        'name' => 'boat_saleprice',
                        'type' => 'text',
                        'instructions' => 'Numbers Only, no symbols or commas',
                        'required' => 0,
                        'wrapper' => array(
                            'width' => '33%',
                            'class' => 'acf-responsive-field',
                        ),
                    ),
                    array(
                        'key' => 'field_boat_msrp',
                        'label' => 'MSRP',
                        'name' => 'boat_msrp',
                        'type' => 'text',
                        'instructions' => '',
                        'required' => 0,
                        'wrapper' => array(
                            'width' => '33%',
                            'class' => 'acf-responsive-field',
                        ),
                    ),
                    array(
                        'key' => 'field_boat_monthly_payment',
                        'label' => 'Monthly Payment',
                        'name' => 'boat_monthly_payment',
                        'type' => 'text',
                        'instructions' => '',
                        'required' => 0,
                        'wrapper' => array(
                            'width' => '33%',
                            'class' => 'acf-responsive-field',
                        ),
                    ),
                    array(
                        'key' => 'field_boat_interest_rate',
                        'label' => 'Interest Rate',
                        'name' => 'boat_interest_rate',
                        'type' => 'text',
                        'instructions' => '',
                        'required' => 0,
                        'wrapper' => array(
                            'width' => '33%',
                            'class' => 'acf-responsive-field',
                        ),
                    ),
                    array(
                        'key' => 'field_boat_down_payment',
                        'label' => 'Down Payment',
                        'name' => 'boat_down_payment',
                        'type' => 'text',
                        'instructions' => '',
                        'required' => 0,
                        'wrapper' => array(
                            'width' => '33%',
                            'class' => 'acf-responsive-field',
                        ),
                    ),
                    array(
                        'key' => 'field_boat_loan_term',
                        'label' => 'Loan Term',
                        'name' => 'boat_loan_term',
                        'type' => 'text',
                        'instructions' => '',
                        'required' => 0,
                        'wrapper' => array(
                            'width' => '33%',
                            'class' => 'acf-responsive-field',
                        ),
                    ),
                    array(
                        'key' => 'field_boat_discount',
                        'label' => 'Discount',
                        'name' => 'boat_discount',
                        'type' => 'text',
                        'instructions' => '',
                        'required' => 0,
                        'wrapper' => array(
                            'width' => '33%',
                            'class' => 'acf-responsive-field',
                        ),
                    ),
                    array(
                        'key' => 'field_hide_sale_price',
                        'label' => 'HIDE Sale Price',
                        'name' => 'hide_sale_price',
                        'type' => 'true_false', // Use 'true_false' for a single checkbox (acts like a checkbox with yes/no or on/off)
                        'instructions' => 'Mark if you don not want to show the sale price on the front end of the website',
                        'required' => 0, // Not required
                        'default_value' => 0, // 0 means unchecked, 1 means checked
                        'ui' => 1, // Optional, adds a toggle switch UI instead of a regular checkbox
                        'wrapper' => array(
                            'width' => '66%',
                            'class' => 'acf-responsive-field',
                        ),
                    ),
                    array(
                        'key' => 'field_boat_length',
                        'label' => 'Length',
                        'name' => 'boat_length',
                        'type' => 'text',
                        'instructions' => 'Numbers Only, no symbols or commas',
                        'required' => 0,
                        'wrapper' => array(
                            'width' => '33%',
                            'class' => 'acf-responsive-field',
                        ),
                    ),
                    array(
                        'key' => 'field_boat_length_inches',
                        'label' => 'Length (inches)',
                        'name' => 'boat_length_inches',
                        'type' => 'text',
                        'instructions' => 'Enter the length of the boat in inches',
                        'required' => 0,
                        'wrapper' => array(
                            'width' => '33%',
                            'class' => 'acf-responsive-field',
                        ),
                    ),
                    array(
                        'key' => 'field_engine_make',
                        'label' => 'Engine Make',
                        'name' => 'engine_make',
                        'type' => 'text',
                        'instructions' => '',
                        'required' => 0,
                        'wrapper' => array(
                            'width' => '33%',
                            'class' => 'acf-responsive-field',
                        ),
                    ),
                    array(
                        'key' => 'field_engine_hp',
                        'label' => 'Engine Horsepower',
                        'name' => 'engine_hp',
                        'type' => 'text',
                        'instructions' => '',
                        'required' => 0,
                        'wrapper' => array(
                            'width' => '33%',
                            'class' => 'acf-responsive-field',
                        ),
                    ),
                    array(
                        'key' => 'field_boat_person_capacity',
                        'label' => 'Person Capacity',
                        'name' => 'boat_person_capacity',
                        'type' => 'number',
                        'instructions' => 'Maximum number of persons the boat can carry',
                        'required' => 0,
                        'wrapper' => array(
                            'width' => '33%',
                            'class' => 'acf-responsive-field',
                        ),
                    ),
                    array(
                        'key' => 'field_engine_hours',
                        'label' => 'Engine Hours',
                        'name' => 'engine_hours',
                        'type' => 'text',
                        'instructions' => 'Numbers Only, no symbols or commas',
                        'required' => 0,
                        'wrapper' => array(
                            'width' => '33%',
                            'class' => 'acf-responsive-field',
                        ),
                    ),
                    array(
                        'key' => 'field_boat_options',
                        'label' => 'Included Options',
                        'name' => 'boat_options',
                        'type' => 'repeater',
                        'instructions' => '',
                        'required' => 0,
                        'layout' => 'table',
                        'button_label' => 'Add Option',
                        'sub_fields' => array(
                            array(
                                'key' => 'field_boat_options_label',
                                'label' => 'Label',
                                'name' => 'label',
                                'type' => 'text',
                                'wrapper' => array('width' => '40%'),
                            ),
                            array(
                                'key' => 'field_boat_options_value',
                                'label' => 'Value',
                                'name' => 'value',
                                'type' => 'text',
                                'wrapper' => array('width' => '60%'),
                            ),
                        ),
                        'wrapper' => array(
                            'width' => '100%',
                            'class' => 'acf-responsive-field',
                        ),
                    ),
                    array(
                        'key' => 'field_boat_specifications',
                        'label' => 'Specifications',
                        'name' => 'boat_specifications',
                        'type' => 'repeater',
                        'instructions' => '',
                        'required' => 0,
                        'layout' => 'table',
                        'button_label' => 'Add Specification',
                        'sub_fields' => array(
                            array(
                                'key' => 'field_boat_specifications_label',
                                'label' => 'Label',
                                'name' => 'label',
                                'type' => 'text',
                                'wrapper' => array('width' => '40%'),
                            ),
                            array(
                                'key' => 'field_boat_specifications_value',
                                'label' => 'Value',
                                'name' => 'value',
                                'type' => 'text',
                                'wrapper' => array('width' => '60%'),
                            ),
                        ),
                        'wrapper' => array(
                            'width' => '100%',
                            'class' => 'acf-responsive-field',
                        ),
                    ),
                    array(
                        'key' => 'field_boat_model_features',
                        'label' => 'Standard Features',
                        'name' => 'boat_model_features',
                        'type' => 'repeater',
                        'instructions' => '',
                        'required' => 0,
                        'layout' => 'table',
                        'button_label' => 'Add Feature',
                        'sub_fields' => array(
                            array(
                                'key' => 'field_boat_model_features_label',
                                'label' => 'Label',
                                'name' => 'label',
                                'type' => 'text',
                                'wrapper' => array('width' => '40%'),
                            ),
                            array(
                                'key' => 'field_boat_model_features_value',
                                'label' => 'Value',
                                'name' => 'value',
                                'type' => 'text',
                                'wrapper' => array('width' => '60%'),
                            ),
                        ),
                        'wrapper' => array(
                            'width' => '100%',
                            'class' => 'acf-responsive-field',
                        ),
                    ),
                    array(
                        'key' => 'field_boat_custom_fields',
                        'label' => 'Custom Fields',
                        'name' => 'boat_custom_fields',
                        'type' => 'repeater',
                        'instructions' => '',
                        'required' => 0,
                        'layout' => 'table',
                        'button_label' => 'Add Custom Field',
                        'sub_fields' => array(
                            array(
                                'key' => 'field_boat_custom_fields_label',
                                'label' => 'Label',
                                'name' => 'label',
                                'type' => 'text',
                                'wrapper' => array('width' => '40%'),
                            ),
                            array(
                                'key' => 'field_boat_custom_fields_value',
                                'label' => 'Value',
                                'name' => 'value',
                                'type' => 'text',
                                'wrapper' => array('width' => '60%'),
                            ),
                        ),
                        'wrapper' => array(
                            'width' => '100%',
                            'class' => 'acf-responsive-field',
                        ),
                    ),
                    array(
                        'key' => 'field_boat_do_not_show_on_public_website',
                        'label' => 'Do NOT show on public facing side of the website',
                        'name' => 'do_not_show_on_public_website',
                        'type' => 'true_false', // Use 'true_false' for a single checkbox (acts like a checkbox with yes/no or on/off)
                        'instructions' => 'Mark if the boat is set to not appear on the public search facing side of the website',
                        'required' => 0, // Not required
                        'default_value' => 0, // 0 means unchecked, 1 means checked
                        'ui' => 1, // Optional, adds a toggle switch UI instead of a regular checkbox
                        'wrapper' => array(
                            'width' => '33%',
                            'class' => 'acf-responsive-field',
                        ),
                    ),
                    array(
                        'key' => 'field_is_local',
                        'label' => 'Is Local Listing',
                        'name' => 'is_local',
                        'type' => 'true_false',
                        'instructions' => 'Mark if this is a local listing that should not be affected by the inventory sync.',
                        'required' => 0,
                        'default_value' => 1,
                        'ui' => 1,
                        'wrapper' => array(
                            'width' => '33%',
                            'class' => 'acf-responsive-field',
                        ),
                    ),

                ),
                'location' => $location,
                'menu_order' => 1,
                'position' => 'normal',
                'style' => 'default',
                'label_placement' => 'top',
                'instruction_placement' => 'label',
                'active' => true,
            ],
            [
                "key"=>"group_boats_photos_videos",
                "title"=>"Photos and Videos",
                "fields"=> array(
                    array(
                        'key' => 'field_boat_gallery', // Unique key for the gallery field
                        'label' => 'Gallery Photos', // The label that will appear in the backend
                        'name' => 'gallery', // The name used to retrieve the gallery
                        'type' => 'gallery', // ACF field type for a gallery
                        'instructions' => 'Add images to the boat gallery.',
                        'required' => 0, // Optional (0), or required (1)
                        'return_format' => 'id', // Options: array, url, id
                        'preview_size' => 'medium', // Preview size in the admin area
                        'insert' => 'append', // Insert new images at the end or beginning
                        'library' => 'all', // 'all' or 'uploadedTo'
                        'min' => 0, // Minimum number of images (0 = no limit)
                        'max' => 0, // Maximum number of images (0 = no limit)
                        'min_width' => 0, // Minimum width for images (in pixels)
                        'min_height' => 0, // Minimum height for images (in pixels)
                        'min_size' => 0, // Minimum size in MB
                        'max_width' => 0, // Maximum width for images (in pixels)
                        'max_height' => 0, // Maximum height for images (in pixels)
                        'max_size' => 0, // Maximum size in MB
                        'mime_types' => '', // Leave blank or restrict to certain types, e.g. 'jpg,png'
                    ),
                    array(
                        'key' => 'field_boat_video1',
                        'label' => 'Video 1',
                        'name' => 'boat_video1',
                        'type' => 'text',
                        'instructions' => 'Youtube Video URL',
                        'required' => 0,
                        'wrapper' => array(
                            'width' => '33%',
                            'class' => 'acf-responsive-field',
                        ),
                    ),
                    array(
                        'key' => 'field_boat_video2',
                        'label' => 'Video 2',
                        'name' => 'boat_video2',
                        'type' => 'text',
                        'instructions' => 'Youtube Video URL',
                        'required' => 0,
                        'wrapper' => array(
                            'width' => '33%',
                            'class' => 'acf-responsive-field',
                        ),
                    ),
                    array(
                        'key' => 'field_boat_video3',
                        'label' => 'Video 3',
                        'name' => 'boat_video3',
                        'type' => 'text',
                        'instructions' => 'Youtube Video URL',
                        'required' => 0,
                        'wrapper' => array(
                            'width' => '33%',
                            'class' => 'acf-responsive-field',
                        ),
                    ),
                    array(
                        'key' => 'field_boat_video4',
                        'label' => 'Video 4',
                        'name' => 'boat_video4',
                        'type' => 'text',
                        'instructions' => 'Youtube Video URL',
                        'required' => 0,
                        'wrapper' => array(
                            'width' => '33%',
                            'class' => 'acf-responsive-field',
                        ),
                    ),
                ),
                'location' => $location,
                'menu_order' => 2,
                'position' => 'normal',
                'style' => 'default',
                'label_placement' => 'top',
                'instruction_placement' => 'label',
                'active' => true,
            ]
        ];

        foreach ($existing_groups AS $data){
            $existing_group = acf_get_field_group($data['key']);

            // If the group doesn't exist, create it
            if (!$existing_group) {
                acf_add_local_field_group($data);
            }
        }
    }
}
add_action('acf/init', 'addACFFieldGroupForBoats');

/**
 * Set default Yoast SEO titles and meta descriptions for the Boat CPT
 */
function dealers_choice_set_yoast_defaults() {
    // 1. Check our flag so we only ever run this ONCE.
    // This prevents us from overwriting a manager's intentional changes later.
    if (get_option('dealerschoice_yoast_defaults_set')) {
        return;
    }

    // 2. Make sure Yoast is actually installed and active
    $wpseo_titles = get_option('wpseo_titles');
    if (!is_array($wpseo_titles)) {
        return;
    }

    $updated = false;

    // 3. Set default Title if it hasn't been set yet
    if (empty($wpseo_titles['title-boat']) || $wpseo_titles['title-boat'] === '%%title%% %%page%% %%sep%% %%sitename%%') {
        $wpseo_titles['title-boat'] = '%%title%% For Sale in %%ct_location%% | %%sitename%%';
        $updated = true;
    }

    // 4. Set default Meta Description if it hasn't been set yet
    if (empty($wpseo_titles['metadesc-boat'])) {
        $wpseo_titles['metadesc-boat'] = 'View the %%title%%, currently %%ct_boat_status%% at the %%ct_location%% showroom. Learn more or request a quote today.';
        $updated = true;
    }

    // 5. Save back to the database
    if ($updated) {
        update_option('wpseo_titles', $wpseo_titles);
        dealers_choice_log('Set default Yoast SEO templates for Boat CPT', 'info'); // Logs the action using your custom logger
    }

    // 6. Set our flag so this function never modifies Yoast again
    update_option('dealerschoice_yoast_defaults_set', true);
}
add_action('admin_init', 'dealers_choice_set_yoast_defaults');


/**
 * Convert length from feet and inches to decimal feet
 * Expected input value is in the format "X'Y"" where X is feet and Y is inches.
 */
function convertLengthToFeet($value) {
    if (empty($value) || !is_string($value)) {
        return 0;
    }

    // Split the string by the apostrophe to separate feet and inches
    if (strpos($value, "'") === false) {
        return (float) $value;
    }

    $parts = explode("'", $value);
    $feet = isset($parts[0]) && $parts[0] !== '' ? (float) $parts[0] : 0;
    $inches = isset($parts[1]) && $parts[1] !== '' ? (float) trim($parts[1], '"') : 0;

    // Convert inches to a decimal of feet
    $decimalFeet = $feet + ($inches / 12);

    // Return the result with 2 decimal places
    return round($decimalFeet, 2);
}

/**
 * Convert length from feet and inches to total inches
 * Expected input value is in the format "X'Y"" where X is feet and Y is inches.
 * Also handles decimal feet format like "23.417"
 *
 * @param mixed $value Length value in various formats
 * @return string Formatted inches as string with 2 decimals, or empty string if invalid
 */
function convertLengthToInches($value) {
    if (empty($value) || $value === null || $value === '') {
        return '';
    }

    // If value is already numeric (decimal feet), convert directly to inches
    if (is_numeric($value)) {
        return number_format((float) $value * 12, 2, '.', '');
    }

    // Handle string format like "19'6\""
    if (strpos($value, "'") === false) {
        // No apostrophe, try to parse as decimal feet
        $numeric = preg_replace("/[^0-9.]/", "", $value);
        if ($numeric !== '' && is_numeric($numeric)) {
            return number_format((float) $numeric * 12, 2, '.', '');
        }
        return '';
    }

    // Split the string by the apostrophe to separate feet and inches
    $parts = explode("'", $value);
    $feet = isset($parts[0]) && $parts[0] !== '' ? (float) $parts[0] : 0;
    $inches_part = isset($parts[1]) ? trim($parts[1], '"') : 0;
    $inches_value = $inches_part !== '' ? (float) $inches_part : 0;

    // Convert feet to inches and add the additional inches
    $total_inches = ($feet * 12) + $inches_value;
    return number_format($total_inches, 2, '.', '');
}

// Schedule cron job for deleting old draft posts
if (!wp_next_scheduled('delete_old_draft_boats_cron')) {
    wp_schedule_event(time(), 'daily', 'delete_old_draft_boats_cron');
}

// Add the function to the cron hook
add_action('delete_old_draft_boats_cron', 'delete_old_draft_boats');

/**
 * Deletes draft 'boat' posts that are older than 6 months.
 */
function delete_old_draft_boats() {
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
        'fields' => 'ids', // Only get IDs for better performance
    ];

    $old_drafts_query = new WP_Query($args);
    $deleted_count = 0;

    if (!empty($old_drafts_query->posts)) {
        foreach ($old_drafts_query->posts as $post_id) {
            $result = wp_delete_post($post_id, true); // Force delete
            if ($result) {
                $deleted_count++;
            }
        }

        dealers_choice_log('Deleted old draft boats', 'info', [
            'count' => $deleted_count,
            'total_found' => count($old_drafts_query->posts)
        ]);
    }
}

/**
 * Automatic inventory sync via cron
 * Runs twice daily to keep inventory up to date
 */
add_action('dealerschoice_inventory_sync_cron', 'dealers_choice_auto_sync_inventory');
function dealers_choice_auto_sync_inventory() {
    // Check if sync is already running
    if (get_transient('dealerschoice_sync_running')) {
        dealers_choice_log('Skipping auto-sync - sync already in progress', 'info');
        return;
    }

    // Set transient to prevent concurrent syncs (expires in 1 hour)
    set_transient('dealerschoice_sync_running', true, HOUR_IN_SECONDS);

    try {
        dealers_choice_log('Starting automatic inventory sync', 'info');

        if (class_exists('\\DC\\InventorySync')) {
            $sync = new \DC\InventorySync();
            $result = $sync->sync_inventory();

            dealers_choice_log('Automatic inventory sync completed', 'info', [
                'result' => $result
            ]);
        } else {
            dealers_choice_log('InventorySync class not found', 'error');
        }
    } catch (Exception $e) {
        dealers_choice_log('Automatic inventory sync failed', 'error', [
            'message' => $e->getMessage()
        ]);
    } finally {
        // Clear the running flag
        delete_transient('dealerschoice_sync_running');
    }
}

/**
 * Register plugin templates with WordPress
 */
add_filter('template_include', ['\DC\Template_Loader', 'template_include']);

/**
 * Enqueue public styles and scripts
 */
function dealers_choice_enqueue_public_assets() {
    // Register Font Awesome (Light version)
    wp_register_style(
        'font-awesome-free',
        'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css',
        [],
        '6.7.2'
    );

    // Register styles
    wp_register_style(
        'dealerschoice-public',
        DC_PLUGIN_URL . 'public/css/dealerschoice-public.css',
        ['font-awesome-free'],
        DC_VERSION
    );
    wp_register_style(
        'dealerschoice-slick',
        DC_PLUGIN_URL . 'public/js/slick/slick.min.css',
        [],
        DC_VERSION
    );

    // Register scripts
    wp_register_script(
        'dealerschoice-public',
        DC_PLUGIN_URL . 'public/js/dealerschoice-public.js',
        ['jquery'],
        DC_VERSION,
        true
    );
    wp_register_script(
        'dealerschoice-slick',
        DC_PLUGIN_URL . 'public/js/slick/slick.min.js',
        ['jquery'],
        DC_VERSION,
        true
    );
    wp_register_script(
        'dealerschoice-slider',
        DC_PLUGIN_URL . 'public/js/dealerschoice-slider.js',
        ['jquery', 'dealerschoice-slick'],
        DC_VERSION,
        true
    );
    wp_register_script(
        'dealerschoice-gallery',
        DC_PLUGIN_URL . 'public/js/dealerschoice-gallery.js',
        ['jquery', 'dealerschoice-slick'],
        DC_VERSION,
        true
    );
    wp_register_script(
        'dealerschoice-favorites',
        DC_PLUGIN_URL . 'public/js/dealerschoice-favorites.js',
        ['jquery', 'dealerschoice-public'],
        DC_VERSION,
        true
    );
    wp_register_script(
        'dealerschoice-location-selector',
        DC_PLUGIN_URL . 'public/js/dealerschoice-location-selector.js',
        ['jquery'],
        DC_VERSION,
        true
    );
    wp_register_script(
        'dealerschoice-reveal-price',
        DC_PLUGIN_URL . 'public/js/dealerschoice-reveal-price.js',
        ['jquery', 'dealerschoice-public'],
        DC_VERSION,
        true
    );
    wp_register_script(
        'dealerschoice-boat-quiz',
        DC_PLUGIN_URL . 'public/js/dealerschoice-boat-quiz.js',
        ['jquery'],
        DC_VERSION,
        true
    );
    wp_localize_script(
        'dealerschoice-boat-quiz',
        'dcBoatQuiz',
        [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('dealerschoice_quiz_nonce'),
        ]
    );
    wp_localize_script(
        'dealerschoice-boat-quiz',
        'dcBoatQuizL10n',
        [
            'loading' => __('Finding your match', 'dealerschoice'),
            'submit'  => __('Find My Perfect Boat', 'dealerschoice'),
            'error'   => __('Something went wrong. Please try again.', 'dealerschoice'),
        ]
    );

    // Localize script with AJAX URL and nonce
    wp_localize_script(
        'dealerschoice-public',
        'dealersChoicePublic',
        [
            'ajaxUrl'             => admin_url('admin-ajax.php'),
            'nonce'               => wp_create_nonce('dealerschoice_public_nonce'),
            'recordFavoriteNonce' => wp_create_nonce('dealerschoice_record_favorite_nonce'),
        ]
    );

    $show_favorites = get_option('dealerschoice_show_favorites', false);
    $show_price = get_option('dealers_choice_always_show_price', true);

    // Enqueue on boat post type pages or pages with shortcode
    if (is_singular('boat')) {
        wp_enqueue_style('font-awesome-free');
        wp_enqueue_style('dealerschoice-public');
        wp_enqueue_script('dealerschoice-public');
        wp_enqueue_script('dealerschoice-slider');
        if(!$show_price) {
            wp_enqueue_script('dealerschoice-reveal-price');
            wp_localize_script('dealerschoice-reveal-price', 'revealPriceSettings', dealers_choice_reveal_price_settings());
        }
        if($show_favorites) {
            wp_enqueue_script('dealerschoice-favorites');
        }

        // Fetch your custom option from the database
        $popup_id = get_option('dealerschoice_sales_cta_popup_id', 0);
        $inventory_view_limit = get_option('dealerschoice_inventory_view_limit', 5);
        if ($popup_id) {
            wp_enqueue_script(
                'dealerschoice-tracker',
                plugin_dir_url(__FILE__) . 'js/inventory-tracker.js',
                array('jquery'),
                '1.0.0',
                true
            );
            wp_localize_script('dealerschoice-tracker', 'dc_settings', array(
                'popup_id'   => $popup_id,
                'view_limit' => $inventory_view_limit
            ));
        }

    } elseif (is_singular() && has_shortcode(get_post()->post_content, 'dealerschoice_inventory')) {
        $show_price = get_option('dealers_choice_always_show_price', true);
        // Check for shortcode on singular pages (posts/pages)
        wp_enqueue_style('font-awesome-free');
        wp_enqueue_style('dealerschoice-public');
        wp_enqueue_script('dealerschoice-public');
        wp_enqueue_script('dealerschoice-slider');
        if(!$show_price) {
            wp_enqueue_script('dealerschoice-reveal-price');
            wp_localize_script('dealerschoice-reveal-price', 'revealPriceSettings', dealers_choice_reveal_price_settings());
        }
    }

    // Enqueue user override CSS if it exists in the theme
    $override_path = get_stylesheet_directory() . '/dealerschoice/dealerschoice-overrides.css';
    if (file_exists($override_path)) {
        wp_enqueue_style(
            'dealerschoice-overrides',
            get_stylesheet_directory_uri() . '/dealerschoice/dealerschoice-overrides.css',
            ['dealerschoice-public'],
            filemtime($override_path)
        );
    }
}
add_action('wp_enqueue_scripts', 'dealers_choice_enqueue_public_assets', 99);

/**
 * Build the revealPriceSettings array for wp_localize_script.
 */
function dealers_choice_reveal_price_settings() {
    $allowed_zips_raw = get_option('dealers_choice_allowed_zips', '');
    $allowed_zips = $allowed_zips_raw
        ? array_values(array_filter(array_map('trim', explode(',', $allowed_zips_raw))))
        : [];

    return [
        'ajaxUrl'                => admin_url('admin-ajax.php'),
        'nonce'                  => wp_create_nonce('dealerschoice_reveal_price_nonce'),
        'popupId'                => get_option('dealers_choice_popup_form_id', ''),
        'gravityFormId'          => get_option('dealers_choice_reveal_price_gravity_form_id', ''),
        'allowedZips'            => $allowed_zips,
        'locationRequestMessage' => wp_unslash( get_option('dealers_choice_location_request_message', '') ),
        'locationVerifiedMessage' => wp_unslash( get_option('dealers_choice_location_verified_message', '') ),
        'locationFailedMessage'   => wp_unslash( get_option('dealers_choice_location_failed_message', '') ),
        'locationDeniedMessage'   => wp_unslash( get_option('dealers_choice_location_denied_message', 'Geolocation is not supported by your browser. Please contact us for pricing information.') ),
        'priceUnavailableMessage' => wp_unslash( get_option('dealers_choice_price_unavailable_message', 'Price unavailable. Please contact us.') ),
    ];
}

/**
 * Stamp data-dc-field attributes on GF hidden fields that use our known parameter names,
 * so JS can find them without knowing their numeric field IDs.
 */
add_filter('gform_field_content', 'dealers_choice_gf_stamp_field_attributes', 10, 5);
function dealers_choice_gf_stamp_field_attributes($field_content, $field, $value, $entry_id, $form_id) {
    $reveal_form_id = get_option('dealers_choice_reveal_price_gravity_form_id', '');
    if (empty($reveal_form_id) || (int) $form_id !== (int) $reveal_form_id) {
        return $field_content;
    }

    $known_params = ['inventoryID', 'priceStatus', 'priceValue'];
    if (!in_array($field->inputName, $known_params, true)) {
        return $field_content;
    }

    $attr      = ' data-dc-field="' . esc_attr($field->inputName) . '"';
    $input_id  = preg_quote('input_' . $form_id . '_' . $field->id, '/');

    // Inject data-dc-field onto the <input> tag by inserting the attribute before the closing >.
    $field_content = preg_replace_callback(
        '/(<input[^>]+id=["\']' . $input_id . '["\'][^>]*)(\/?>)/i',
        function($matches) use ($attr) {
            return $matches[1] . $attr . $matches[2];
        },
        $field_content
    );

    // Fallback: match by name attribute if the id match didn't work.
    if (strpos($field_content, 'data-dc-field') === false) {
        $field_name = preg_quote('input_' . $field->id, '/');
        $field_content = preg_replace_callback(
            '/(<input[^>]+name=["\']' . $field_name . '["\'][^>]*)(\/?>)/i',
            function($matches) use ($attr) {
                return $matches[1] . $attr . $matches[2];
            },
            $field_content
        );
    }

    return $field_content;
}

/**
 * After a Reveal Price form is submitted, look up the boat price by the submitted
 * inventory ID and write it back into the priceValue entry field.
 */
add_action('gform_after_submission', 'dealers_choice_populate_price_in_entry', 10, 2);
function dealers_choice_populate_price_in_entry($entry, $form) {
    $reveal_form_id = get_option('dealers_choice_reveal_price_gravity_form_id', '');
    if (empty($reveal_form_id) || (int) $form['id'] !== (int) $reveal_form_id) {
        return;
    }

    // Find the inventoryID and priceValue fields by their inputName (parameter name).
    $inventory_field_id  = null;
    $price_value_field_id = null;
    foreach ($form['fields'] as $field) {
        if ($field->inputName === 'inventoryID')  { $inventory_field_id  = $field->id; }
        if ($field->inputName === 'priceValue')   { $price_value_field_id = $field->id; }
    }

    if (!$inventory_field_id || !$price_value_field_id) {
        return;
    }

    $inventory_id = rgar($entry, (string) $inventory_field_id);
    if (empty($inventory_id)) {
        return;
    }

    // Find the boat post by its inventory ID stored in ACF / post meta.
    $posts = get_posts([
        'post_type'      => 'boat',
        'posts_per_page' => 1,
        'meta_query'     => [[
            'key'   => 'inventory_id',
            'value' => $inventory_id,
        ]],
        'fields' => 'ids',
    ]);

    if (empty($posts)) {
        return;
    }

    $price = get_field('saleprice', $posts[0]);
    if (empty($price)) {
        return;
    }

    GFAPI::update_entry_field($entry['id'], $price_value_field_id, $price);
}

/**
 * Add body classes for boat pages
 */
function dealers_choice_body_classes($classes) {
    if (is_singular('boat')) {
        $classes[] = 'dealerschoice-single';
        // Remove blog class if present
        $classes = array_diff($classes, ['blog']);
    } elseif (is_singular() && has_shortcode(get_post()->post_content, 'dealerschoice_inventory')) {
        $classes[] = 'dealerschoice-inventory-page';
        // Remove blog class to prevent theme blog styles from interfering
        $classes = array_diff($classes, ['blog']);
    } elseif (is_singular() && has_shortcode(get_post()->post_content, 'dealerschoice_favorites')) {
        $classes[] = 'dealerschoice-inventory-page';
        // Remove blog class to prevent theme blog styles from interfering
        $classes = array_diff($classes, ['blog']);
    }
    return $classes;
}
add_filter('body_class', 'dealers_choice_body_classes');

/**
 * Add a shortcode to display the reveal price message.
 */
function dealers_choice_reveal_price_message_shortcode() {
    return '<div class="dc-reveal-price-message"></div>';
}
add_shortcode('dealerschoice_reveal_price_message', 'dealers_choice_reveal_price_message_shortcode');

/**
 * Customize boat admin columns
 */
add_filter('manage_edit-boat_columns', 'dealers_choice_boat_columns');
function dealers_choice_boat_columns($columns) {
    // Remove default date column
    unset($columns['date']);

    // Add custom columns
    $columns['stock_number'] = __('Stock #', 'dealers-choice');
    $columns['saleprice'] = __('Price', 'dealers-choice');
    $columns['boat_year'] = __('Year', 'dealers-choice');
    $columns['boat_type'] = __('Type', 'dealers-choice');
    $columns['condition'] = __('Condition', 'dealers-choice');
    $columns['location'] = __('Location', 'dealers-choice');
    $columns['date'] = __('Date', 'dealers-choice'); // Re-add date at the end

    return $columns;
}

/**
 * Display custom column values
 */
add_action('manage_boat_posts_custom_column', 'dealers_choice_display_boat_columns', 10, 2);
function dealers_choice_display_boat_columns($column, $post_id) {
    switch ($column) {
        case 'stock_number':
            $stock = get_field('boat_stock_number', $post_id);
            echo !empty($stock) ? esc_html($stock) : '—';
            break;

        case 'saleprice':
            $price = get_field('boat_saleprice', $post_id);
            if (!empty($price) && is_numeric($price)) {
                echo '$' . esc_html(number_format_i18n($price, 0));

            } else {
                echo '—';
            }
            break;

        case 'boat_year':
            $year = get_field('boat_year', $post_id);
            echo !empty($year) ? esc_html($year) : '—';
            break;

        case 'boat_type':
            $terms = get_the_terms($post_id, 'boat_type');
            if ($terms && !is_wp_error($terms)) {
                $names = wp_list_pluck($terms, 'name');
                echo esc_html(join(', ', $names));
            } else {
                echo '—';
            }
            break;

        case 'condition':
            $terms = get_the_terms($post_id, 'condition');
            if ($terms && !is_wp_error($terms)) {
                $names = wp_list_pluck($terms, 'name');
                echo esc_html(join(', ', $names));
            } else {
                echo '—';
            }
            break;

        case 'location':
            $terms = get_the_terms($post_id, 'location');
            if ($terms && !is_wp_error($terms)) {
                $names = wp_list_pluck($terms, 'name');
                echo esc_html(join(', ', $names));
            } else {
                echo '—';
            }
            break;
    }
}

/**
 * Make boat columns sortable
 */
add_filter('manage_edit-boat_sortable_columns', 'dealers_choice_sortable_boat_columns');
function dealers_choice_sortable_boat_columns($columns) {
    $columns['stock_number'] = 'stock_number';
    $columns['saleprice'] = 'saleprice';
    $columns['boat_year'] = 'boat_year';
    return $columns;
}

/**
 * Handle sorting for custom boat columns
 */
add_action('pre_get_posts', 'dealers_choice_sort_boats_by_column');
function dealers_choice_sort_boats_by_column($query) {
    if (!is_admin() || !$query->is_main_query() || 'boat' !== $query->get('post_type')) {
        return;
    }

    $orderby = $query->get('orderby');

    if ('saleprice' === $orderby) {
        $query->set('meta_key', 'boat_saleprice');
        $query->set('orderby', 'meta_value_num');
    } elseif ('boat_year' === $orderby) {
        $query->set('meta_key', 'boat_year');
        $query->set('orderby', 'meta_value_num');
    } elseif ('stock_number' === $orderby) {
        $query->set('meta_key', 'boat_stock_number');
        $query->set('orderby', 'meta_value');
    }
}

/**
 * Create an admin dashboard widget to show inventory stats
 */
function dealers_choice_register_dashboard_widgets() {
    wp_add_dashboard_widget(
        'dealers_choice_inventory_stats',
        __('DealersChoice Inventory Stats', 'dealers-choice'),
        'dealers_choice_display_inventory_stats'
    );
}
add_action('wp_dashboard_setup', 'dealers_choice_register_dashboard_widgets');

/**
 * Display inventory stats in the dashboard widget
 */
function dealers_choice_display_inventory_stats() {
    $total_boats = wp_count_posts('boat')->publish;
    $last_sync = get_option('dealers_choice_last_sync', 0);
    $recent_boats = new WP_Query([
        'post_type' => 'boat',
        'posts_per_page' => 5,
        'orderby' => 'modified',
        'order' => 'DESC',
    ]);
    echo '<p><span class="dashicons dashicons-excerpt-view"></span> <strong>' . sprintf(__('Total Inventory: %d', 'dealers-choice'), $total_boats) . '</strong></p>';
    echo '<p><span class="dashicons dashicons-calendar-alt"></span> <strong>' . sprintf(__('Last Sync: %s', 'dealers-choice'), $last_sync ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($last_sync)) : __('Never', 'dealers-choice')) . '</strong></p>';
    if ($recent_boats->have_posts()) {
        echo '<hr />';
        echo '<ul>';
            echo '<li><span class="dashicons dashicons-download"></span> <strong>' . __('Recently Synced Inventory:', 'dealers-choice') . '</strong></li>';
            echo '<ul>';
            while ($recent_boats->have_posts()) {
                $recent_boats->the_post();
                echo '<li><a href="' . get_edit_post_link() . '">' . get_the_title() . '</a></li>';
            }
            wp_reset_postdata();
            echo '</ul>';
        echo '</ul>';
    }
    echo '<hr />';
    echo '<p><a href="' . admin_url('edit.php?post_type=boat') . '">' . __('View All Inventory', 'dealers-choice') . '</a> | <a href="https://dealerschoiceims.securem2.com/" target="_blank">DealersChoice IMS<span class="screen-reader-text"> (opens in a new tab)</span><span aria-hidden="true" class="dashicons dashicons-external" style="text-decoration: none;"></span></a></p>';
}

/**
 * Fixes MIME type for Salesforce image downloads.
 *
 * @param array  $data     File data.
 * @param string $file     Full path to the file.
 * @param string $filename The filename.
 * @param array  $mimes    Array of MIME types.
 * @return array Modified file data.
 */
function dealers_choice_fix_mime_types($data, $file, $filename, $mimes) {
    $file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    // If the file has no extension, try to get it from the query string of the source URL.
    if (empty($file_ext) && isset($_GET['name'])) {
        $real_filename = sanitize_file_name($_GET['name']);
        $real_ext = strtolower(pathinfo($real_filename, PATHINFO_EXTENSION));
        
        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (in_array($real_ext, $allowed_exts)) {
            if (empty($data['ext']) || empty($data['type'])) {
                $data['ext'] = $real_ext;
                $data['type'] = "image/{$real_ext}";
            }
        }
    }
    return $data;
}
add_filter('wp_check_filetype_and_ext', 'dealers_choice_fix_mime_types', 10, 4);
