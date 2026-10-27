<?php
/**
 * Plugin Name: DealersChoice Solutions
 * Plugin URI: https://www.dealerschoicesolutions.com
 * Description: A comprehensive dealership inventory management plugin that syncs boat listings from your DMS to WordPress with automatic categorization, image management, and advanced filtering.
 * Version: 1.0.9
 * Author: DealersChoice, by Mannix Marketing
 * Author URI: https://www.dealerschoicesolutions.com
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: dealers-choice
 * Requires at least: 6.0
 * Requires PHP: 8.1
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
    exit;
}

register_activation_hook(__FILE__, 'dealers_choice_activate');
register_deactivation_hook(__FILE__, 'dealers_choice_deactivate');

/**
 * Activates the plugin.
 *
 * Registers the post type and taxonomies, flushes rewrites, adds the postmeta
 * index, creates the favorites table and schedules the twice-daily sync.
 */
function dealers_choice_activate() {
    registerCustomPostTypeAndTaxonomies();
    flush_rewrite_rules();

    add_option('dealers_choice_log_enabled', '1');

    dealers_choice_add_database_indexes();

    dealers_choice_create_favorites_table();

    if (!wp_next_scheduled('dealerschoice_inventory_sync_cron')) {
        wp_schedule_event(time(), 'twicedaily', 'dealerschoice_inventory_sync_cron');
    }

    dealers_choice_log('Plugin activated', 'info');
}

/**
 * Creates or updates the favorites analytics table.
 *
 * Runs dbDelta only when the stored table version differs from the current one.
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
 * Adds a composite meta_key/meta_value index to the postmeta table if missing.
 */
function dealers_choice_add_database_indexes() {
    global $wpdb;

    $indexes = $wpdb->get_results("SHOW INDEX FROM {$wpdb->postmeta} WHERE Key_name = 'meta_key_value'");

    if (empty($indexes)) {
        $wpdb->query("ALTER TABLE {$wpdb->postmeta} ADD INDEX meta_key_value (meta_key(191), meta_value(100))");
        dealers_choice_log('Added database indexes for optimization', 'info');
    }
}

/**
 * Deactivates the plugin: clears scheduled cron events and flushes rewrites.
 */
function dealers_choice_deactivate() {
    $timestamp = wp_next_scheduled('delete_old_draft_boats_cron');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'delete_old_draft_boats_cron');
    }

    $sync_timestamp = wp_next_scheduled('dealerschoice_inventory_sync_cron');
    if ($sync_timestamp) {
        wp_unschedule_event($sync_timestamp, 'dealerschoice_inventory_sync_cron');
    }

    flush_rewrite_rules();

    dealers_choice_log('Plugin deactivated', 'info');
}

/**
 * Writes a plugin log entry.
 *
 * Writes to error_log when WP_DEBUG_LOG is enabled and keeps the most recent
 * 100 entries in the `dealers_choice_recent_logs` option. Does nothing when
 * logging is disabled.
 *
 * @param string $message Log message.
 * @param string $level   Optional. Log level: info, warning or error. Default 'info'.
 * @param array  $context Optional. Additional context data. Default empty array.
 */
function dealers_choice_log($message, $level = 'info', $context = []) {
    if (!get_option('dealers_choice_log_enabled', '1')) {
        return;
    }

    $formatted_message = sprintf(
        '[DealersChoice] [%s] %s',
        strtoupper($level),
        $message
    );

    if (!empty($context)) {
        $formatted_message .= ' | Context: ' . json_encode($context);
    }

    if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
        error_log($formatted_message);
    }

    $recent_logs = get_option('dealers_choice_recent_logs', []);
    $recent_logs[] = [
        'timestamp' => current_time('mysql'),
        'level' => $level,
        'message' => $message,
        'context' => $context
    ];

    if (count($recent_logs) > 100) {
        $recent_logs = array_slice($recent_logs, -100);
    }

    update_option('dealers_choice_recent_logs', $recent_logs);
}

/**
 * Checks whether ACF is active and queues an admin notice if it is not.
 *
 * @return bool True if ACF is active.
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
 * Outputs the admin notice shown when ACF is not active.
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

define('DC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('DC_VERSION', '1.0.9');

// Plugin Update Checker: checks the DealersChoice update server for new releases.
require_once DC_PLUGIN_DIR . 'lib/plugin-update-checker/load-v5p7.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$dc_update_checker = PucFactory::buildUpdateChecker(
    'https://dealerschoiceims.securem2.com/api/plugin/update',
    __FILE__,
    'dealerschoice'
);

require_once plugin_dir_path(__FILE__) . '/classes/class.inventory.php';
require_once plugin_dir_path(__FILE__) . '/classes/class.boat.php';
require_once plugin_dir_path(__FILE__) . '/classes/class.inventory-sync.php';
require_once plugin_dir_path(__FILE__) . '/classes/class.acf-taxonomy-sync.php';
require_once plugin_dir_path(__FILE__) . '/classes/class.wp-cli-commands.php';
require_once plugin_dir_path(__FILE__) . '/classes/class.insert-shortcodes.php';

require_once plugin_dir_path(__FILE__) . '/classes/class.template-loader.php';
require_once plugin_dir_path(__FILE__) . '/classes/class.ajax-handlers.php';
require_once plugin_dir_path(__FILE__) . '/classes/class.shortcodes.php';
require_once plugin_dir_path(__FILE__) . '/classes/class.redirects.php';
require_once plugin_dir_path(__FILE__) . '/classes/class.boat-quiz.php';
require_once plugin_dir_path(__FILE__) . '/classes/class.gravity-forms.php';

new DC\ACF_Taxonomy_Sync();

DC\AJAX_Handlers::init();

DC\Shortcodes::init();

DC\Redirects::init();

DC\GravityForms::init();

add_action('plugins_loaded', 'dealers_choice_create_favorites_table');

/**
 * Checks whether the favorites feature is enabled.
 *
 * Defaults to enabled when the option has never been saved.
 *
 * @return bool True if favorites are enabled.
 */
function dealerschoiceIsFavoritesEnabled() {
    return get_option('dealerschoice_show_favorites', '1') === '1';
}

/**
 * Migrates 'dealers_choice_show_favorites' to 'dealerschoice_show_favorites'.
 *
 * Copies the legacy value once, deletes the legacy option, and filters reads
 * of the legacy name to return the current setting.
 */
function dealerschoiceMigrateFavoritesOption() {
    if (get_option('dealerschoice_show_favorites', null) === null) {
        $legacy_value = get_option('dealers_choice_show_favorites', null);
        if ($legacy_value !== null) {
            update_option('dealerschoice_show_favorites', $legacy_value === '1' ? '1' : '0');
            delete_option('dealers_choice_show_favorites');
        }
    }

    add_filter('pre_option_dealers_choice_show_favorites', function() {
        return dealerschoiceIsFavoritesEnabled() ? '1' : '0';
    });
}
add_action('plugins_loaded', 'dealerschoiceMigrateFavoritesOption', 5);

add_action('wp_dashboard_setup', 'dealers_choice_register_favorites_widget');

/**
 * Registers the Favorite Boats dashboard widget for administrators.
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
 * Renders the Favorite Boats dashboard widget.
 *
 * Lists the ten boats with the highest net favorites for the selected period.
 */
function dealers_choice_favorites_widget_callback() {
    if ( ! dealerschoiceIsFavoritesEnabled() ) {
        echo '<p>' . esc_html__('Favorites are currently disabled in DealersChoice Settings.', 'dealers-choice') . '</p>';
        return;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'dc_favorite_events';

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

if (is_admin()) {
    require_once plugin_dir_path(__FILE__) . '/admin/admin.plugin-settings.php';
    require_once plugin_dir_path(__FILE__) . '/admin/admin.sync-inventory.php';
    require_once plugin_dir_path(__FILE__) . '/admin/admin.favorites-analytics.php';

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

        // External link to the DealersChoice IMS.
        global $submenu;
        $submenu['dealers-choice-settings'][] = array(
            'DealersChoice IMS <span class="dashicons dashicons-external" style="display:inline; font-size: 14px; vertical-align: -1px;"></span>',
            'manage_options',
            'https://dealerschoiceims.securem2.com'
        );
    });

    // Admin menu icon sizing, and opens the IMS menu link in a new tab.
    add_action('admin_head', function() {
        echo '<style>
            #adminmenu #toplevel_page_dealers-choice-settings .wp-menu-image img {
                width: 20px;
                display: inline;
            }
        </style>';

        echo '<script>
            jQuery(document).ready(function($) {
                $("a[href=\'https://dealerschoiceims.securem2.com\']").attr("target", "_blank").attr("rel", "noopener noreferrer");
            });
        </script>';
    });
}

// Optional AI description generator, loaded only when DCS_AI_SERVICE is defined.
if (defined( 'DCS_AI_SERVICE' )){
    require_once plugin_dir_path(__FILE__) . '/classes/class.generate-description.php';
}

/**
 * Registers the `boat` post type, its taxonomies and the range terms.
 *
 * Registers the public inventory taxonomies, the private `dc_promotions`
 * taxonomy, and creates the price, length, horsepower and capacity range terms.
 */
function registerCustomPostTypeAndTaxonomies() {
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

    // Taxonomy slug => [singular label, plural label, slug].
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

    // Promotions: private, hierarchical taxonomy whose terms are managed in the admin.
    register_taxonomy(
        'dc_promotions',
        'boat',
        [
            'labels' => [
                'name'              => __('Promotions', 'dealers-choice'),
                'singular_name'     => __('Promotion', 'dealers-choice'),
                'search_items'      => __('Search Promotions', 'dealers-choice'),
                'all_items'         => __('All Promotions', 'dealers-choice'),
                'edit_item'         => __('Edit Promotion', 'dealers-choice'),
                'update_item'       => __('Update Promotion', 'dealers-choice'),
                'add_new_item'      => __('Add New Promotion', 'dealers-choice'),
                'new_item_name'     => __('New Promotion Name', 'dealers-choice'),
                'not_found'         => __('No promotions found', 'dealers-choice'),
                'menu_name'         => __('Promotions', 'dealers-choice'),
            ],
            "public" => false,
            "publicly_queryable" => false,
            "hierarchical" => true,
            "show_ui" => true,
            "show_in_menu" => true,
            "show_in_nav_menus" => false,
            "query_var" => false,
            "rewrite" => false,
            "show_admin_column" => true,
            "show_in_rest" => true,
            "show_tagcloud" => false,
            "capabilities" => [
                'manage_terms' => 'manage_options',
                'edit_terms'   => 'manage_options',
                'delete_terms' => 'manage_options',
                'assign_terms' => 'edit_posts',
            ],
        ]
    );

    dealers_choice_create_range_terms();
}
add_action('init', 'registerCustomPostTypeAndTaxonomies');

/**
 * Resolves the URL of the site-wide inventory page.
 *
 * Uses the 'dealers_choice_inventory_page_id' option, then a page at the
 * /inventory/ path, then home_url('/inventory/').
 *
 * @return string Absolute URL, or '' if it cannot be resolved.
 */
function dealers_choice_get_inventory_page_url() {
    $page_id = (int) get_option('dealers_choice_inventory_page_id', 0);

    if (!$page_id) {
        $page = get_page_by_path('inventory');

        if ($page) {
            $page_id = (int) $page->ID;
        }
    }

    $url = $page_id ? get_permalink($page_id) : home_url('/inventory/');

    return $url ? (string) $url : '';
}

/**
 * Replaces the %type% placeholder in boat permalinks with the boat type slug.
 *
 * Produces /boat/{boat_type}/{slug}/, using 'general' when the boat has no type.
 *
 * @param string       $post_link Post permalink.
 * @param WP_Post|null $post      Optional. Post object. Default null.
 * @param bool         $leavename Optional. Whether to keep the post name placeholder. Default false.
 * @return string Filtered permalink.
 */
function inventoryChangeLinkWithType( $post_link, $post = null, $leavename = false ) {
    if ( is_object( $post ) && $post->post_type == 'boat' ) {
        $terms = wp_get_object_terms( $post->ID, array('boat_type') );

        if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
            return str_replace( '%type%', $terms[0]->slug, $post_link );
        } else {
            return str_replace( '%type%', 'general', $post_link );
        }
    }
    return $post_link;
}
add_filter( 'post_type_link', 'inventoryChangeLinkWithType', 1, 3 );

/**
 * Adds the rewrite rule that maps /boat/{type}/{slug}/ to the boat post.
 */
function inventoryGeneratedRewriteRules() {
    add_rewrite_rule(
        '^boat/(.*)/(.*)/?$',
        'index.php?post_type=boat&name=$matches[2]',
        'top'
        );
}
add_action( 'init', 'inventoryGeneratedRewriteRules' );

/**
 * Returns the default (lowest) price range term name.
 *
 * @return string Price range term name.
 */
function dealers_choice_get_default_price_range_key() {
    return "$0 - $20k";
}

/**
 * Returns the price range buckets.
 *
 * @return array Term name => [min, max] in dollars.
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
 * Returns the default (shortest) length range term name.
 *
 * @return string Length range term name.
 */
function dealers_choice_get_default_length_key() {
    return "Under 20 ft.";
}

/**
 * Returns the length range buckets.
 *
 * @return array Term name => [min, max] in feet.
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
 * Returns the default (lowest) horsepower range term name.
 *
 * @return string Horsepower range term name.
 */
function dealers_choice_get_default_horsepower_key() {
    return "Up to 90";
}

/**
 * Returns the horsepower range buckets.
 *
 * @return array Term name => [min, max] horsepower.
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
 * Creates any missing price, length, horsepower and person capacity range terms.
 */
function dealers_choice_create_range_terms() {
    if ($price_ranges = dealers_choice_get_price_ranges()) {
        foreach ($price_ranges as $key => $min_max) {
            if (!term_exists($key, 'price_range')) {
                wp_insert_term($key, 'price_range');
            }
        }
    }

    if ($lengths = dealers_choice_get_lengths()) {
        foreach ($lengths as $key => $min_max) {
            if (!term_exists($key, 'length_range')) {
                wp_insert_term($key, 'length_range');
            }
        }
    }

    if ($horsepower = dealers_choice_get_horsepower()) {
        foreach ($horsepower as $key => $min_max) {
            if (!term_exists($key, 'horsepower')) {
                wp_insert_term($key, 'horsepower');
            }
        }
    }

    if ($capacities = dealers_choice_get_capacity_ranges()) {
        foreach ($capacities as $key => $min_max) {
            if (!term_exists($key, 'person_capacity')) {
                wp_insert_term($key, 'person_capacity');
            }
        }
    }
}

/**
 * Returns the price range term name for a price.
 *
 * @param float|string $price Boat price.
 * @return string Price range term name, or the default range when none matches.
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
 * Returns the length range term name for a length in feet.
 *
 * @param float|string $length_feet Boat length in feet.
 * @return string Length range term name, or the default range when none matches.
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
 * Returns the horsepower range term name for an engine horsepower.
 *
 * @param float|string $hp Engine horsepower.
 * @return string Horsepower range term name, or the default range when none matches.
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
 * Returns the default (smallest) person capacity range term name.
 *
 * @return string Person capacity range term name.
 */
function dealers_choice_get_default_capacity_key() {
    return "1 - 4 People";
}

/**
 * Returns the person capacity range buckets.
 *
 * @return array Term name => [min, max] people.
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
 * Returns the person capacity range term name for a capacity.
 *
 * @param int|string $capacity Person capacity.
 * @return string Person capacity range term name, or the default range when none matches.
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
 * Registers the ACF field groups for the `boat` post type.
 */
function addACFFieldGroupForBoats() {
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
                        'type' => 'true_false',
                        'instructions' => 'Mark if you don not want to show the sale price on the front end of the website',
                        'required' => 0,
                        'default_value' => 0,
                        'ui' => 1,
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
                        'key' => 'field_boat_weight',
                        'label' => 'Weight',
                        'name' => 'boat_weight',
                        'type' => 'text',
                        'instructions' => 'Numbers Only, no symbols or commas',
                        'required' => 0,
                        'wrapper' => array(
                            'width' => '33%',
                            'class' => 'acf-responsive-field',
                        ),
                    ),
                    array(
                        'key' => 'field_boat_beam',
                        'label' => 'Beam',
                        'name' => 'boat_beam',
                        'type' => 'text',
                        'instructions' => 'Decimal feet, numbers only (e.g. 8.5 for 8\' 6")',
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
                        'type' => 'true_false',
                        'instructions' => 'Mark if the boat is set to not appear on the public search facing side of the website',
                        'required' => 0,
                        'default_value' => 0,
                        'ui' => 1,
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
                        'key' => 'field_boat_gallery',
                        'label' => 'Gallery Photos',
                        'name' => 'gallery',
                        'type' => 'gallery',
                        'instructions' => 'Add images to the boat gallery.',
                        'required' => 0,
                        'return_format' => 'id',
                        'preview_size' => 'medium',
                        'insert' => 'append',
                        'library' => 'all',
                        'min' => 0,
                        'max' => 0,
                        'min_width' => 0,
                        'min_height' => 0,
                        'min_size' => 0,
                        'max_width' => 0,
                        'max_height' => 0,
                        'max_size' => 0,
                        'mime_types' => '',
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

            if (!$existing_group) {
                acf_add_local_field_group($data);
            }
        }
    }
}
add_action('acf/init', 'addACFFieldGroupForBoats');

/**
 * Sets default Yoast SEO title and meta description templates for boats.
 *
 * Runs once, when Yoast settings exist, and only fills templates that are
 * empty or still the Yoast default. Sets the 'dealerschoice_yoast_defaults_set'
 * flag when done.
 */
function dealers_choice_set_yoast_defaults() {
    if (get_option('dealerschoice_yoast_defaults_set')) {
        return;
    }

    $wpseo_titles = get_option('wpseo_titles');
    if (!is_array($wpseo_titles)) {
        return;
    }

    $updated = false;

    if (empty($wpseo_titles['title-boat']) || $wpseo_titles['title-boat'] === '%%title%% %%page%% %%sep%% %%sitename%%') {
        $wpseo_titles['title-boat'] = '%%title%% For Sale in %%ct_location%% | %%sitename%%';
        $updated = true;
    }

    if (empty($wpseo_titles['metadesc-boat'])) {
        $wpseo_titles['metadesc-boat'] = 'View the %%title%%, currently %%ct_boat_status%% at the %%ct_location%% showroom. Learn more or request a quote today.';
        $updated = true;
    }

    if ($updated) {
        update_option('wpseo_titles', $wpseo_titles);
        dealers_choice_log('Set default Yoast SEO templates for Boat CPT', 'info');
    }

    update_option('dealerschoice_yoast_defaults_set', true);
}
add_action('admin_init', 'dealers_choice_set_yoast_defaults');


/**
 * Converts a length to decimal feet.
 *
 * Accepts feet and inches (X'Y") or a plain number of feet.
 *
 * @param string $value Length value.
 * @return float|int Length in decimal feet rounded to 2 places, or 0 if empty or not a string.
 */
function convertLengthToFeet($value) {
    if (empty($value) || !is_string($value)) {
        return 0;
    }

    if (strpos($value, "'") === false) {
        return (float) $value;
    }

    $parts = explode("'", $value);
    $feet = isset($parts[0]) && $parts[0] !== '' ? (float) $parts[0] : 0;
    $inches = isset($parts[1]) && $parts[1] !== '' ? (float) trim($parts[1], '"') : 0;

    $decimalFeet = $feet + ($inches / 12);

    return round($decimalFeet, 2);
}

/**
 * Converts a length to total inches.
 *
 * Accepts decimal feet (23.417), feet and inches (19'6"), or a string
 * containing a number of feet.
 *
 * @param string|float|int $value Length value.
 * @return string Inches formatted with 2 decimals, or '' if invalid.
 */
function convertLengthToInches($value) {
    if (empty($value) || $value === null || $value === '') {
        return '';
    }

    if (is_numeric($value)) {
        return number_format((float) $value * 12, 2, '.', '');
    }

    if (strpos($value, "'") === false) {
        $numeric = preg_replace("/[^0-9.]/", "", $value);
        if ($numeric !== '' && is_numeric($numeric)) {
            return number_format((float) $numeric * 12, 2, '.', '');
        }
        return '';
    }

    $parts = explode("'", $value);
    $feet = isset($parts[0]) && $parts[0] !== '' ? (float) $parts[0] : 0;
    $inches_part = isset($parts[1]) ? trim($parts[1], '"') : 0;
    $inches_value = $inches_part !== '' ? (float) $inches_part : 0;

    $total_inches = ($feet * 12) + $inches_value;
    return number_format($total_inches, 2, '.', '');
}

// Daily cleanup of old draft boats.
if (!wp_next_scheduled('delete_old_draft_boats_cron')) {
    wp_schedule_event(time(), 'daily', 'delete_old_draft_boats_cron');
}

add_action('delete_old_draft_boats_cron', 'delete_old_draft_boats');

/**
 * Permanently deletes draft boats not modified in the last 6 months.
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
        'fields' => 'ids',
    ];

    $old_drafts_query = new WP_Query($args);
    $deleted_count = 0;

    if (!empty($old_drafts_query->posts)) {
        foreach ($old_drafts_query->posts as $post_id) {
            $result = wp_delete_post($post_id, true);
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

add_action('dealerschoice_inventory_sync_cron', 'dealers_choice_auto_sync_inventory');
/**
 * Runs the scheduled inventory sync.
 *
 * Skips the run while another one holds the 'dealerschoice_sync_running'
 * transient (1 hour), and logs the result or failure.
 */
function dealers_choice_auto_sync_inventory() {
    if (get_transient('dealerschoice_sync_running')) {
        dealers_choice_log('Skipping auto-sync - sync already in progress', 'info');
        return;
    }

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
        delete_transient('dealerschoice_sync_running');
    }
}

// Single boat template.
add_filter('template_include', ['\DC\Template_Loader', 'template_include']);

/**
 * Checks whether a post contains a shortcode in its content or post meta.
 *
 * Searches post_content, then every string meta value except keys starting
 * with an underscore (covers ACF text, WYSIWYG and repeater fields).
 *
 * @param int|string $post_id       Post ID.
 * @param string     $shortcode_tag Shortcode tag.
 * @return bool True if the shortcode is found.
 */
function dealers_choice_post_has_shortcode($post_id, $shortcode_tag) {
    $post_id = (int) $post_id;
    if (!$post_id) {
        return false;
    }

    if (has_shortcode((string) get_post_field('post_content', $post_id), $shortcode_tag)) {
        return true;
    }

    foreach (get_post_meta($post_id) as $meta_key => $meta_values) {
        if (strpos($meta_key, '_') === 0) {
            continue;
        }
        foreach ($meta_values as $meta_value) {
            if (is_string($meta_value) && has_shortcode($meta_value, $shortcode_tag)) {
                return true;
            }
        }
    }

    return false;
}

/**
 * Registers public assets and enqueues them where needed.
 *
 * Enqueues the inventory assets on single boat pages and on singular pages
 * that contain [dealerschoice_inventory], plus the theme's
 * dealerschoice-overrides.css when present. Other shortcodes enqueue the
 * registered handles when they render.
 */
function dealers_choice_enqueue_public_assets() {
    wp_register_style(
        'font-awesome-free',
        'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css',
        [],
        '6.7.2'
    );

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
    wp_register_style(
        'dealerschoice-finance-calculator',
        DC_PLUGIN_URL . 'public/css/dealerschoice-finance-calculator.css',
        ['dealerschoice-public'],
        DC_VERSION
    );
    wp_register_script(
        'dealerschoice-finance-calculator',
        DC_PLUGIN_URL . 'public/js/dealerschoice-finance-calculator.js',
        ['jquery'],
        DC_VERSION,
        true
    );
    wp_localize_script(
        'dealerschoice-finance-calculator',
        'dcFinanceCalcL10n',
        [
            'requiredField'      => __('This field is required.', 'dealerschoice'),
            'invalidNumber'      => __('Please enter a valid number.', 'dealerschoice'),
            'rateRange'          => __('Interest rate must be between 0 and 30.', 'dealerschoice'),
            'termRange'          => __('Loan term must be between 1 and 600 months.', 'dealerschoice'),
            'downPaymentTooHigh' => __('Down payment must be less than the amount financed.', 'dealerschoice'),
            'resultHeadline'     => __('Estimated Monthly Payment:', 'dealerschoice'),
            'quickResultLabel'   => __('Your estimated monthly payment:', 'dealerschoice'),
            'amountFinancedLabel'=> __('Amount Financed:', 'dealerschoice'),
            'totalInterestLabel' => __('Total Interest:', 'dealerschoice'),
            'totalCostLabel'     => __('Total Cost of Loan:', 'dealerschoice'),
            'scheduleCaption'    => __('Full amortization schedule', 'dealerschoice'),
            'columnMonth'        => __('Month', 'dealerschoice'),
            'columnPayment'      => __('Payment', 'dealerschoice'),
            'columnPrincipal'    => __('Principal', 'dealerschoice'),
            'columnInterest'     => __('Interest', 'dealerschoice'),
            'columnBalance'      => __('Remaining Balance', 'dealerschoice'),
        ]
    );

    wp_localize_script(
        'dealerschoice-public',
        'dealersChoicePublic',
        [
            'ajaxUrl'             => admin_url('admin-ajax.php'),
            'nonce'               => wp_create_nonce('dealerschoice_public_nonce'),
            'recordFavoriteNonce' => wp_create_nonce('dealerschoice_record_favorite_nonce'),
        ]
    );

    $show_favorites = dealerschoiceIsFavoritesEnabled();
    $show_price = get_option('dealers_choice_always_show_price', true);

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

        // Popup Maker CTA after N views of the same boat.
        $popup_id = absint(get_option('dealerschoice_sales_cta_popup_id', 0));
        $inventory_view_limit = absint(get_option('dealerschoice_inventory_view_limit', 5));
        if ($popup_id && $inventory_view_limit) {
            wp_enqueue_script(
                'dealerschoice-tracker',
                DC_PLUGIN_URL . 'public/js/inventory-tracker.js',
                array('jquery'),
                DC_VERSION,
                true
            );
            wp_localize_script('dealerschoice-tracker', 'dc_settings', array(
                'popup_id'   => $popup_id,
                'view_limit' => $inventory_view_limit,
            ));
        }

    } elseif (is_singular() && dealers_choice_post_has_shortcode(get_the_ID(), 'dealerschoice_inventory')) {
        $show_price = get_option('dealers_choice_always_show_price', true);
        wp_enqueue_style('font-awesome-free');
        wp_enqueue_style('dealerschoice-public');
        wp_enqueue_script('dealerschoice-public');
        wp_enqueue_script('dealerschoice-slider');
        if(!$show_price) {
            wp_enqueue_script('dealerschoice-reveal-price');
            wp_localize_script('dealerschoice-reveal-price', 'revealPriceSettings', dealers_choice_reveal_price_settings());
        }
    }

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
 * Builds the revealPriceSettings data passed to the Reveal Price script.
 *
 * @return array Reveal Price settings.
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
        'revealScope'            => get_option('dealers_choice_reveal_price_scope', 'all'),
        'allowedZips'            => $allowed_zips,
        'locationRequestMessage' => wp_unslash( get_option('dealers_choice_location_request_message', '') ),
        'locationVerifiedMessage' => wp_unslash( get_option('dealers_choice_location_verified_message', '') ),
        'locationFailedMessage'   => wp_unslash( get_option('dealers_choice_location_failed_message', '') ),
        'locationDeniedMessage'   => wp_unslash( get_option('dealers_choice_location_denied_message', 'Geolocation is not supported by your browser. Please contact us for pricing information.') ),
        'priceUnavailableMessage' => wp_unslash( get_option('dealers_choice_price_unavailable_message', 'Price unavailable. Please contact us.') ),
    ];
}

add_filter('gform_field_content', 'dealers_choice_gf_stamp_field_attributes', 10, 5);
/**
 * Adds a data-dc-field attribute to Reveal Price form inputs.
 *
 * Applies to fields whose parameter name is inventoryID, priceStatus,
 * priceValue, boatName or stockNumber. Matches the input by id, then by name.
 *
 * @param string   $field_content Field HTML.
 * @param GF_Field $field         Gravity Forms field.
 * @param mixed    $value         Field value.
 * @param int      $entry_id      Entry ID.
 * @param int      $form_id       Form ID.
 * @return string Field HTML.
 */
function dealers_choice_gf_stamp_field_attributes($field_content, $field, $value, $entry_id, $form_id) {
    $reveal_form_id = get_option('dealers_choice_reveal_price_gravity_form_id', '');
    if (empty($reveal_form_id) || (int) $form_id !== (int) $reveal_form_id) {
        return $field_content;
    }

    $known_params = ['inventoryID', 'priceStatus', 'priceValue', 'boatName', 'stockNumber'];
    if (!in_array($field->inputName, $known_params, true)) {
        return $field_content;
    }

    $attr      = ' data-dc-field="' . esc_attr($field->inputName) . '"';
    $input_id  = preg_quote('input_' . $form_id . '_' . $field->id, '/');

    $field_content = preg_replace_callback(
        '/(<input[^>]+id=["\']' . $input_id . '["\'][^>]*)(\/?>)/i',
        function($matches) use ($attr) {
            return $matches[1] . $attr . $matches[2];
        },
        $field_content
    );

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
 * Formats a published boat's sale price as the Reveal Price button displays it.
 *
 * @param int|string $post_id Boat post ID.
 * @return string Formatted price such as "$45,999", or '' when the post is not a
 *                published boat or has no positive price.
 */
function dealers_choice_get_revealable_price($post_id) {
    $post_id = absint($post_id);
    if (!$post_id || get_post_type($post_id) !== 'boat' || get_post_status($post_id) !== 'publish') {
        return '';
    }

    $price = get_post_meta($post_id, 'boat_saleprice', true);
    if (!$price || !is_numeric($price) || (float) $price <= 0) {
        return '';
    }

    return '$' . number_format((float) $price);
}

add_action('gform_pre_submission', 'dealers_choice_populate_price_in_entry');
/**
 * Writes the boat price into the Reveal Price form's priceValue field on submission.
 *
 * Looks up the price server-side from the submitted inventoryID and replaces
 * the posted priceValue. Uses 'Not Shown' when ZIP restrictions apply and the
 * status is not 'Verified in sales area', or when no price is available.
 *
 * @param array $form Gravity Forms form.
 */
function dealers_choice_populate_price_in_entry($form) {
    $reveal_form_id = get_option('dealers_choice_reveal_price_gravity_form_id', '');
    if (empty($reveal_form_id) || (int) $form['id'] !== (int) $reveal_form_id) {
        return;
    }

    $inventory_field_id   = null;
    $status_field_id      = null;
    $price_value_field_id = null;
    foreach ($form['fields'] as $field) {
        if ($field->inputName === 'inventoryID') { $inventory_field_id   = $field->id; }
        if ($field->inputName === 'priceStatus') { $status_field_id      = $field->id; }
        if ($field->inputName === 'priceValue')  { $price_value_field_id = $field->id; }
    }

    if (!$price_value_field_id) {
        return;
    }

    $price_key = 'input_' . $price_value_field_id;

    $allowed_zips = trim((string) get_option('dealers_choice_allowed_zips', ''));
    $status       = $status_field_id ? sanitize_text_field(wp_unslash(rgpost('input_' . $status_field_id))) : '';
    $was_shown    = $allowed_zips === '' || $status === 'Verified in sales area';

    $price = '';
    if ($was_shown && $inventory_field_id) {
        $price = dealers_choice_get_revealable_price(wp_unslash(rgpost('input_' . $inventory_field_id)));
    }

    $_POST[$price_key] = $price !== '' ? $price : 'Not Shown';
}

/**
 * Adds DealersChoice body classes and removes 'blog' on boat and inventory pages.
 *
 * @param string[] $classes Body classes.
 * @return string[] Body classes.
 */
function dealers_choice_body_classes($classes) {
    if (is_singular('boat')) {
        $classes[] = 'dealerschoice-single';
        $classes = array_diff($classes, ['blog']);
    } elseif (is_singular() && dealers_choice_post_has_shortcode(get_the_ID(), 'dealerschoice_inventory')) {
        $classes[] = 'dealerschoice-inventory-page';
        $classes = array_diff($classes, ['blog']);
    } elseif (is_singular() && dealers_choice_post_has_shortcode(get_the_ID(), 'dealerschoice_favorites')) {
        $classes[] = 'dealerschoice-inventory-page';
        $classes = array_diff($classes, ['blog']);
    }
    return $classes;
}
add_filter('body_class', 'dealers_choice_body_classes');

/**
 * Renders the [dealerschoice_reveal_price_message] placeholder for Reveal Price messages.
 *
 * @return string Placeholder HTML.
 */
function dealers_choice_reveal_price_message_shortcode() {
    return '<div class="dc-reveal-price-message"></div>';
}
add_shortcode('dealerschoice_reveal_price_message', 'dealers_choice_reveal_price_message_shortcode');

add_filter('manage_edit-boat_columns', 'dealers_choice_boat_columns');
/**
 * Adds Stock #, Price, Year, Type, Condition and Location columns to the boat list table.
 *
 * Moves the Date column to the end.
 *
 * @param string[] $columns List table columns.
 * @return string[] List table columns.
 */
function dealers_choice_boat_columns($columns) {
    unset($columns['date']);

    $columns['stock_number'] = __('Stock #', 'dealers-choice');
    $columns['saleprice'] = __('Price', 'dealers-choice');
    $columns['boat_year'] = __('Year', 'dealers-choice');
    $columns['boat_type'] = __('Type', 'dealers-choice');
    $columns['condition'] = __('Condition', 'dealers-choice');
    $columns['location'] = __('Location', 'dealers-choice');
    $columns['date'] = __('Date', 'dealers-choice');

    return $columns;
}

add_action('manage_boat_posts_custom_column', 'dealers_choice_display_boat_columns', 10, 2);
/**
 * Outputs the value of a custom boat list table column.
 *
 * @param string $column  Column key.
 * @param int    $post_id Boat post ID.
 */
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

add_filter('manage_edit-boat_sortable_columns', 'dealers_choice_sortable_boat_columns');
/**
 * Makes the Stock #, Price and Year columns sortable.
 *
 * @param array $columns Sortable columns.
 * @return array Sortable columns.
 */
function dealers_choice_sortable_boat_columns($columns) {
    $columns['stock_number'] = 'stock_number';
    $columns['saleprice'] = 'saleprice';
    $columns['boat_year'] = 'boat_year';
    return $columns;
}

add_action('pre_get_posts', 'dealers_choice_sort_boats_by_column');
/**
 * Applies meta ordering for the sortable boat list table columns.
 *
 * @param WP_Query $query Current query.
 */
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

add_action('pre_get_posts', 'dealers_choice_admin_search_stock_and_hin');
/**
 * Extends the boat list table search to match Stock Number and HIN.
 *
 * Sets the dc_search_extra_meta_keys query var, which
 * DC\AJAX_Handlers::search_stock_and_hin() uses to add the meta match to the
 * search clause.
 *
 * @param WP_Query $query Current query.
 */
function dealers_choice_admin_search_stock_and_hin($query) {
    if (!is_admin() || !$query->is_main_query() || 'boat' !== $query->get('post_type')) {
        return;
    }

    if (!$query->is_search()) {
        return;
    }

    $query->set('dc_search_extra_meta_keys', ['boat_stock_number', 'boat_hin']);
}

add_action('restrict_manage_posts', 'dealers_choice_location_filter_dropdown');
/**
 * Outputs a Location filter dropdown on the boat list table.
 *
 * Lists every location term, including empty ones, and outputs nothing when no
 * locations exist. The selected slug filters the list through the `location`
 * query var.
 *
 * @param string $post_type Post type of the list table being rendered.
 */
function dealers_choice_location_filter_dropdown($post_type) {
    if ('boat' !== $post_type) {
        return;
    }

    $taxonomy = 'location';

    if (!taxonomy_exists($taxonomy)) {
        return;
    }

    wp_dropdown_categories([
        'taxonomy'        => $taxonomy,
        'name'            => $taxonomy,
        'value_field'     => 'slug',
        'show_option_all' => __('All Locations', 'dealers-choice'),
        'selected'        => isset($_GET[$taxonomy]) ? sanitize_text_field(wp_unslash($_GET[$taxonomy])) : '',
        'hide_empty'      => false,
        'hide_if_empty'   => true,
        'orderby'         => 'name',
        'order'           => 'ASC',
    ]);
}

/**
 * Registers the Inventory Stats dashboard widget.
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
 * Renders the Inventory Stats dashboard widget.
 *
 * Shows the published boat count, last sync time, the five most recently
 * modified boats, and links to the inventory list and the IMS.
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
 * Sets the file type for extensionless image uploads from the `name` query parameter.
 *
 * Applies only when the filename has no extension, the `name` parameter has a
 * jpg, jpeg, png, gif or webp extension, and WordPress did not detect a type.
 *
 * @param array         $data     File data: ext, type and proper_filename.
 * @param string        $file     Full path to the file.
 * @param string        $filename File name.
 * @param string[]|null $mimes    Allowed MIME types keyed by extension pattern.
 * @return array File data.
 */
function dealers_choice_fix_mime_types($data, $file, $filename, $mimes) {
    $file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

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
