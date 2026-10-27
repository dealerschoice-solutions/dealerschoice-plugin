<?php
/**
 * Public and admin AJAX handlers: inventory search, filter counts, reveal price,
 * favorites analytics, the boat quiz and the legacy background sync.
 *
 * @package DealersChoice
 */

namespace DC;

if (!defined('ABSPATH')) {
    exit;
}

class AJAX_Handlers {
    
    /**
     * Registers AJAX actions and the search filter.
     *
     * @return void
     */
    public static function init() {
        // Public actions.
        add_action('wp_ajax_search_inventory', [__CLASS__, 'search_inventory']);
        add_action('wp_ajax_nopriv_search_inventory', [__CLASS__, 'search_inventory']);
        
        add_action('wp_ajax_get_filter_counts', [__CLASS__, 'get_filter_counts']);
        add_action('wp_ajax_nopriv_get_filter_counts', [__CLASS__, 'get_filter_counts']);

        // Extends opted-in searches to Stock Number, HIN and Boat Type.
        add_filter('posts_search', [__CLASS__, 'search_stock_and_hin'], 10, 2);

        add_action('wp_ajax_reveal_price', [__CLASS__, 'reveal_price']);
        add_action('wp_ajax_nopriv_reveal_price', [__CLASS__, 'reveal_price']);

        add_action('wp_ajax_dealerschoice_quiz_results', [__CLASS__, 'quiz_results']);
        add_action('wp_ajax_nopriv_dealerschoice_quiz_results', [__CLASS__, 'quiz_results']);

        add_action('wp_ajax_record_favorite', [__CLASS__, 'record_favorite']);
        add_action('wp_ajax_nopriv_record_favorite', [__CLASS__, 'record_favorite']);

        // Admin actions.
        add_action('wp_ajax_start_inventory_sync', [__CLASS__, 'start_inventory_sync']);
        add_action('wp_ajax_run_inventory_sync', [__CLASS__, 'run_inventory_sync']);
        add_action('wp_ajax_get_sync_logs', [__CLASS__, 'get_sync_logs']);
    }

    /**
     * Starts an inventory sync in the background.
     *
     * Clears the recent logs and fires a non-blocking request to run_inventory_sync.
     *
     * @return void
     */
    public static function start_inventory_sync() {
        check_ajax_referer('dealers_choice_sync_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied.'], 403);
        }

        update_option('dealers_choice_recent_logs', []);

        $force_sync = isset($_POST['force_sync']) && $_POST['force_sync'] === 'true';

        $url = admin_url('admin-ajax.php');
        $args = [
            'method' => 'POST',
            'timeout' => 0.01,
            'blocking' => false,
            'body' => [
                'action' => 'run_inventory_sync',
                'nonce' => wp_create_nonce('dealers_choice_run_sync_nonce'),
                'force_sync' => $force_sync,
            ],
            'cookies' => $_COOKIE,
        ];

        $response = wp_remote_post($url, $args);

        if (is_wp_error($response)) {
            wp_send_json_error(['message' => 'Failed to start sync process: ' . $response->get_error_message()]);
        } else {
            wp_send_json_success(['message' => 'Inventory sync started.']);
        }
    }

    /**
     * Returns the formatted price for a published boat.
     *
     * @return void
     */
    public static function reveal_price() {
        check_ajax_referer('dealerschoice_reveal_price_nonce', 'nonce');
    
        $inventoryID = isset($_GET['inventoryID']) ? absint(wp_unslash($_GET['inventoryID'])) : 0;

        if (!$inventoryID) {
            wp_send_json_error(['message' => 'No inventory ID provided.'], 400);
        }

        $formatted_price = dealers_choice_get_revealable_price($inventoryID);

        if ($formatted_price !== '') {
            wp_send_json_success(['price' => $formatted_price]);
        } else {
            wp_send_json_error(['message' => 'Price not available.'], 404);
        }
    }

    /**
     * Records a favorite add or remove event in the analytics table.
     *
     * @return void
     */
    public static function record_favorite() {
        check_ajax_referer('dealerschoice_record_favorite_nonce', 'nonce');

        if ( ! dealerschoiceIsFavoritesEnabled() ) {
            wp_send_json_success();
        }

        $boat_id        = isset($_POST['boat_id']) ? absint($_POST['boat_id']) : 0;
        $favorite_action = isset($_POST['favorite_action']) ? sanitize_text_field($_POST['favorite_action']) : '';

        if ( $boat_id <= 0 || ! in_array($favorite_action, ['add', 'remove'], true) ) {
            wp_send_json_error(['message' => 'Invalid parameters.'], 400);
        }

        if ( get_post_type($boat_id) !== 'boat' ) {
            wp_send_json_error(['message' => 'Invalid boat ID.'], 400);
        }

        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'dc_favorite_events',
            [
                'boat_id'    => $boat_id,
                'action'     => $favorite_action,
                'event_time' => current_time('mysql'),
            ],
            ['%d', '%s', '%s']
        );

        wp_send_json_success();
    }

    /**
     * Runs the inventory sync requested by start_inventory_sync().
     *
     * @return void
     */
    public static function run_inventory_sync() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'dealers_choice_run_sync_nonce')) {
            dealers_choice_log('Background sync failed: Invalid nonce.', 'error');
            return;
        }

        $force_sync = isset($_POST['force_sync']) && $_POST['force_sync'] === 'true';

        $client_id = get_option('dealers_choice_client_id');
        $api_key = get_option('dealers_choice_api_key');

        if (empty($client_id) || empty($api_key)) {
            dealers_choice_log('Sync aborted: API credentials not configured.', 'error');
            return;
        }

        try {
            $sync = new \DC\InventorySync($client_id, $api_key);
            $sync->sync_inventory(false, $force_sync);
        } catch (\Exception $e) {
            dealers_choice_log('Background sync failed: ' . $e->getMessage(), 'error');
        }
    }

    /**
     * Returns recent sync logs and whether a sync is in progress.
     *
     * @return void
     */
    public static function get_sync_logs() {
        check_ajax_referer('dealers_choice_sync_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied.'], 403);
        }

        $logs = get_option('dealers_choice_recent_logs', []);
        $sync_lock = get_transient('dealers_choice_inventory_sync_lock');

        wp_send_json_success([
            'logs' => array_reverse($logs),
            'sync_in_progress' => !empty($sync_lock),
        ]);
    }
    
    /**
     * Handles inventory filter, search, sort and pagination requests.
     *
     * Sends the output of render_inventory_results() as JSON.
     *
     * @return void
     */
    public static function search_inventory() {
        // Allow cross-origin requests without credentials.
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

        // Preflight request.
        if ('OPTIONS' == $_SERVER['REQUEST_METHOD']) {
            status_header(200);
            exit();
        }

        $filters = self::sanitize_filters(isset($_POST['filters']) ? $_POST['filters'] : []);
        $sort_by = isset($_POST['sortBy']) ? sanitize_text_field(wp_unslash($_POST['sortBy'])) : get_option('dealers_choice_default_sort', 'date-desc');
        $search_query = isset($_POST['query']) ? sanitize_text_field(wp_unslash($_POST['query'])) : '';
        // Limit the keyword to 100 characters.
        $search_query = mb_substr($search_query, 0, 100);
        $current_page = isset($_POST['currentPage']) ? max(1, absint($_POST['currentPage'])) : 1;
        $posts_per_page = self::get_posts_per_page(isset($_POST['postsPerPage']) ? $_POST['postsPerPage'] : 0);

        // Locked taxonomy constraints from the shortcode, kept separate from the visitor's filters.
        $locked = self::sanitize_locked_constraints(isset($_POST['locked']) ? $_POST['locked'] : []);

        // Listing page URL, used for pagination hrefs and the ItemList schema url.
        $page_url = self::sanitize_page_url(isset($_POST['pageUrl']) ? $_POST['pageUrl'] : '');

        wp_send_json_success(
            self::render_inventory_results($filters, $search_query, $sort_by, $current_page, $posts_per_page, $locked, $page_url)
        );
    }

    /**
     * Resolves the page size for an inventory request.
     *
     * Applies the dealerschoice_posts_per_page default and the
     * dealerschoice_max_posts_per_page cap (60).
     *
     * @param mixed $requested Optional. Requested page size. 0 or empty uses the default. Default 0.
     * @return int Page size, at least 1.
     */
    public static function get_posts_per_page($requested = 0) {
        $posts_per_page = absint($requested) > 0
            ? absint($requested)
            : absint(apply_filters('dealerschoice_posts_per_page', 12));

        $max_posts_per_page = max(1, (int) apply_filters('dealerschoice_max_posts_per_page', 60));

        return max(1, min($posts_per_page, $max_posts_per_page));
    }

    /**
     * Validates a listing page URL sent with an AJAX request.
     *
     * Accepts only http(s) URLs on this site's host and strips any fragment.
     *
     * @param mixed $raw Requested URL.
     * @return string Sanitized URL, or an empty string when invalid.
     */
    public static function sanitize_page_url($raw) {
        if (!is_string($raw) || $raw === '') {
            return '';
        }

        $url = esc_url_raw(wp_unslash($raw), ['http', 'https']);

        if ($url === '') {
            return '';
        }

        $url_host  = wp_parse_url($url, PHP_URL_HOST);
        $home_host = wp_parse_url(home_url(), PHP_URL_HOST);

        if (!$url_host || !$home_host || strtolower($url_host) !== strtolower($home_host)) {
            return '';
        }

        $hash_pos = strpos($url, '#');

        return $hash_pos === false ? $url : substr($url, 0, $hash_pos);
    }

    /**
     * Queries and renders one page of inventory results.
     *
     * Used by search_inventory() and by the inventory shortcode's server-rendered
     * first page. Builds the result cards, pagination, the optional
     * "Shop Other Locations" block and the ItemList schema from the same query.
     *
     * @param array  $filters        Output of sanitize_filters().
     * @param string $search_query   Sanitized keyword search.
     * @param string $sort_by        Sort option (see add_sorting_args()).
     * @param int    $current_page   Page number, 1 or greater.
     * @param int    $posts_per_page Output of get_posts_per_page().
     * @param array  $locked         Output of sanitize_locked_constraints().
     * @param string $page_url       Optional. Listing page URL. Default empty.
     * @return array{results:string,pagination:string,other_locations:string,total:int,pages:int,schema:array|null}
     */
    public static function render_inventory_results($filters, $search_query, $sort_by, $current_page, $posts_per_page, $locked, $page_url = '') {
        $args = self::build_inventory_query_args($filters, $search_query, $sort_by, $current_page, $posts_per_page, $locked);

        $query = new \WP_Query($args);

        // ItemList schema for the rendered boats, skipped for the favorites request.
        $build_schema = empty($filters['id']);
        $list_items = [];

        $results_html = '';
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $results_html .= Template_Loader::get_template('inventory-block.php', [], true);

                if ($build_schema) {
                    $boat = new Boat(get_the_ID());
                    $list_items[] = [
                        '@type'    => 'ListItem',
                        'position' => count($list_items) + 1,
                        'item'     => $boat->getItemListEntry(),
                    ];
                }
            }
            wp_reset_postdata();
        } else {
            $no_results_msg = self::get_no_results_html($search_query, $filters);

            // Suggestions stay within the locked constraints.
            $suggestions = self::get_suggestions_html($filters, $locked);
            
            $results_html = $no_results_msg . $suggestions;
        }
        
        $pagination_html = self::build_pagination($query, $current_page, $page_url);

        // "Shop Other Locations": on a location-locked page, after a filter or search,
        // shows matching boats from other locations on the last page of results.
        $other_locations_html = '';
        if (
            get_option('dealerschoice_show_other_locations', '0') === '1'
            && !empty($locked['location'])
            && empty($filters['id'])
            && $query->found_posts > 0
            && $current_page >= $query->max_num_pages
            && (self::has_active_filters($filters) || $search_query !== '')
        ) {
            $other_locations_html = self::get_other_locations_html($filters, $search_query, $sort_by, $locked);
        }

        $schema = null;
        if (!empty($list_items)) {
            $schema = [
                '@context' => 'https://schema.org',
                '@type' => 'ItemList',
            ];
            if ($page_url !== '') {
                $schema['url'] = $page_url;
            }
            // Positions and numberOfItems are per page.
            $schema['numberOfItems'] = count($list_items);
            $schema['itemListElement'] = $list_items;
        }

        return [
            'results' => $results_html,
            'pagination' => $pagination_html,
            'other_locations' => $other_locations_html,
            'total' => (int) $query->found_posts,
            'pages' => (int) $query->max_num_pages,
            'schema' => $schema,
        ];
    }
    
    /**
     * Adds orderby, order and meta_key arguments for a sort option.
     *
     * @param array  $args    WP_Query arguments, modified by reference.
     * @param string $sort_by Sort option, e.g. 'price-asc'. Unknown values sort by date descending.
     * @return void
     */
    private static function add_sorting_args(&$args, $sort_by) {
        switch ($sort_by) {
            case 'price-asc':
                $args['orderby'] = 'meta_value_num';
                $args['meta_key'] = 'boat_saleprice';
                $args['order'] = 'ASC';
                break;
                
            case 'price-desc':
                $args['orderby'] = 'meta_value_num';
                $args['meta_key'] = 'boat_saleprice';
                $args['order'] = 'DESC';
                break;
                
            case 'year-asc':
                $args['orderby'] = 'meta_value_num';
                $args['meta_key'] = 'boat_year';
                $args['order'] = 'ASC';
                break;
                
            case 'year-desc':
                $args['orderby'] = 'meta_value_num';
                $args['meta_key'] = 'boat_year';
                $args['order'] = 'DESC';
                break;
                
            case 'length-asc':
                $args['orderby'] = 'meta_value_num';
                $args['meta_key'] = 'boat_length_inches';
                $args['order'] = 'ASC';
                break;
                
            case 'length-desc':
                $args['orderby'] = 'meta_value_num';
                $args['meta_key'] = 'boat_length_inches';
                $args['order'] = 'DESC';
                break;
                
            case 'title-asc':
                $args['orderby'] = 'title';
                $args['order'] = 'ASC';
                break;
                
            case 'title-desc':
                $args['orderby'] = 'title';
                $args['order'] = 'DESC';
                break;
                
            case 'date-asc':
                $args['orderby'] = 'date';
                $args['order'] = 'ASC';
                break;
                
            case 'date-desc':
            default:
                $args['orderby'] = 'date';
                $args['order'] = 'DESC';
                break;
        }
    }
    
    /**
     * Builds the pagination nav.
     *
     * Links use ?inventory_page=N on the listing page URL and carry data-page for the JS handler.
     *
     * @param \WP_Query $query        Inventory query.
     * @param int       $current_page Current page number.
     * @param string    $page_url     Optional. Listing page URL. Empty uses href="#". Default empty.
     * @return string Pagination HTML, or an empty string for a single page.
     */
    private static function build_pagination($query, $current_page, $page_url = '') {
        if ($query->max_num_pages <= 1) {
            return '';
        }

        $page_href = function($page) use ($page_url) {
            if ($page_url === '') {
                return '#';
            }
            return $page > 1
                ? add_query_arg('inventory_page', $page, $page_url)
                : remove_query_arg('inventory_page', $page_url);
        };
        
        $html = '<nav class="pagination" aria-label="Inventory Pagination">';
        $html .= '<ul class="pagination-list">';
        
        if ($current_page > 1) {
            $html .= sprintf(
                '<li><a href="%s" data-page="%d" class="pagination-prev" aria-label="Previous Page"><i class="fa-light fa-angle-left" aria-hidden="true"></i>Previous</a></li>',
                esc_url($page_href($current_page - 1)),
                $current_page - 1
            );
        }
        
        // Pages shown on each side of the current page.
        $range = 2;
        
        for ($i = 1; $i <= $query->max_num_pages; $i++) {
            // First page, last page, and pages around the current one.
            if ($i == 1 || $i == $query->max_num_pages || ($i >= $current_page - $range && $i <= $current_page + $range)) {
                $is_current = $i == $current_page;
                $html .= sprintf(
                    '<li%s><a href="%s" data-page="%d"%s>%d</a></li>',
                    $is_current ? ' class="active"' : '',
                    esc_url($page_href($i)),
                    $i,
                    $is_current ? ' aria-current="page"' : '',
                    $i
                );
            } elseif ($i == $current_page - $range - 1 || $i == $current_page + $range + 1) {
                $html .= '<li class="pagination-ellipsis"><span>...</span></li>';
            }
        }
        
        if ($current_page < $query->max_num_pages) {
            $html .= sprintf(
                '<li><a href="%s" data-page="%d" class="pagination-next" aria-label="Next Page">Next<i class="fa-light fa-angle-right" aria-hidden="true"></i></a></li>',
                esc_url($page_href($current_page + 1)),
                $current_page + 1
            );
        }
        
        $html .= '</ul>';
        $html .= '</nav>';
        
        return $html;
    }
    
    /**
     * Builds the "No Boats Found" message.
     *
     * @param string $search_query Current search query.
     * @param array  $filters      Current filters.
     * @return string No results HTML.
     */
    private static function get_no_results_html($search_query, $filters) {
        $has_filters = self::has_active_filters($filters);

        $html = '<div class="no-results">';
        $html .= '<h2>No Boats Found</h2>';
        
        if ($has_filters || !empty($search_query)) {
            $html .= '<p>No boats match your current search criteria. Try adjusting your filters or search terms.</p>';
            $html .= '<button type="button" id="clear-all-filters" class="button">Clear All Filters</button>';
        } else {
            $html .= '<p>No boats are currently available. Please check back soon!</p>';
        }
        
        $html .= '</div>';

        return $html;
    }

    /**
     * Checks whether the visitor has selected at least one filter value other than 'all'.
     *
     * @param array $filters Current filters.
     * @return bool
     */
    private static function has_active_filters($filters) {
        if (!is_array($filters)) {
            return false;
        }

        foreach ($filters as $filter) {
            if (!empty($filter) && is_array($filter)) {
                $filter_values = array_filter($filter, function($val) {
                    return $val !== 'all';
                });
                if (!empty($filter_values)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Builds the "Shop Other Locations" block.
     *
     * Runs the visitor's filters and search against every location except the
     * locked ones, keeping all other locked constraints. Returns up to 3 boats.
     *
     * @param array  $filters      Visitor filter selections.
     * @param string $search_query Search keyword.
     * @param string $sort_by      Current sort option.
     * @param array  $locked       Sanitized locked constraints, including 'location'.
     * @return string Block HTML, or an empty string when nothing matches.
     */
    private static function get_other_locations_html($filters, $search_query, $sort_by, $locked) {
        $excluded_locations = $locked['location'];

        $other_locked = $locked;
        unset($other_locked['location']);

        // Drop the visitor's location filter.
        $other_filters = is_array($filters) ? $filters : [];
        unset($other_filters['location']);

        $limit = 3;
        $args  = self::build_inventory_query_args($other_filters, $search_query, $sort_by, 1, $limit, $other_locked);

        if (empty($args['tax_query'])) {
            $args['tax_query'] = ['relation' => 'AND'];
        }

        $args['tax_query'][] = [
            'taxonomy' => 'location',
            'field'    => 'slug',
            'terms'    => $excluded_locations,
            'operator' => 'NOT IN',
        ];
        $args['no_found_rows'] = true;

        $query = new \WP_Query($args);

        if (!$query->have_posts()) {
            return '';
        }

        $view_all_url = get_option('dealerschoice_other_locations_show_button', '1') === '1'
            ? self::get_other_locations_view_all_url($other_filters, $search_query)
            : '';

        ob_start();
        ?>
        <section class="dealerschoice-other-locations" aria-labelledby="dc-other-locations-heading">
            <h2 id="dc-other-locations-heading"><?php echo esc_html(self::get_other_locations_heading()); ?></h2>
            <?php $intro = self::get_other_locations_intro(); ?>
            <?php if ($intro !== ''): ?>
            <div class="dealerschoice-other-locations-intro"><?php echo wp_kses_post(wpautop($intro)); ?></div>
            <?php endif; ?>
            <div class="suggestions-grid">
                <?php while ($query->have_posts()): $query->the_post(); ?>
                    <?php echo Template_Loader::get_template('inventory-block.php', [], true); ?>
                <?php endwhile; ?>
            </div>
            <?php if ($view_all_url !== ''): ?>
            <p class="dealerschoice-other-locations-more">
                <a href="<?php echo esc_url($view_all_url); ?>" class="dc-button"><?php echo esc_html(self::get_other_locations_button_text()); ?></a>
            </p>
            <?php endif; ?>
        </section>
        <?php
        wp_reset_postdata();
        return ob_get_clean();
    }

    /**
     * Returns the "Shop Other Locations" heading, or the default when blank.
     *
     * @return string Plain text.
     */
    public static function get_other_locations_heading() {
        $heading = (string) get_option('dealerschoice_other_locations_heading', '');

        return $heading !== '' ? $heading : self::get_default_other_locations_heading();
    }

    /**
     * Returns the "Shop Other Locations" intro text.
     *
     * Uses the default only when the option has never been saved.
     *
     * @return string Intro text that may contain basic HTML, or an empty string for no intro.
     */
    public static function get_other_locations_intro() {
        return trim((string) get_option('dealerschoice_other_locations_intro', self::get_default_other_locations_intro()));
    }

    /**
     * Returns the "Shop Other Locations" button text, or the default when blank.
     *
     * @return string Plain text.
     */
    public static function get_other_locations_button_text() {
        $text = (string) get_option('dealerschoice_other_locations_button_text', '');

        return $text !== '' ? $text : self::get_default_other_locations_button_text();
    }

    /**
     * Returns the default "Shop Other Locations" button text.
     *
     * @return string
     */
    public static function get_default_other_locations_button_text() {
        return __('View All Matching Boats at Every Location', 'dealerschoice');
    }

    /**
     * Returns the default "Shop Other Locations" heading.
     *
     * @return string
     */
    public static function get_default_other_locations_heading() {
        return __('Shop Other Locations', 'dealerschoice');
    }

    /**
     * Returns the default "Shop Other Locations" intro text.
     *
     * @return string
     */
    public static function get_default_other_locations_intro() {
        return __('These boats matching your search are available at our other locations.', 'dealerschoice');
    }

    /**
     * Builds a link to the inventory page with the visitor's filters and search preset.
     *
     * Uses the URL parameters the inventory JS reads on load (key[]=slug, boat_year
     * for year, q for search). Capacity has no URL parameter and is skipped.
     *
     * @param array  $filters      Visitor filter selections, without location.
     * @param string $search_query Search keyword.
     * @return string URL, or an empty string when no inventory page URL can be resolved.
     */
    private static function get_other_locations_view_all_url($filters, $search_query) {
        if (!function_exists('dealers_choice_get_inventory_page_url')) {
            return '';
        }

        $base = dealers_choice_get_inventory_page_url();
        if ($base === '') {
            return '';
        }

        $url_keys = [
            'condition'  => 'condition',
            'status'     => 'status',
            'category'   => 'category',
            'make'       => 'make',
            'model'      => 'model',
            'price'      => 'price',
            'length'     => 'length',
            'horsepower' => 'horsepower',
            'year'       => 'boat_year',
        ];

        $filter_map = self::get_filter_taxonomy_map();
        $params     = [];

        foreach ($url_keys as $filter_key => $param) {
            if (!isset($filter_map[$filter_key]) || empty($filters[$filter_key]) || !is_array($filters[$filter_key])) {
                continue;
            }

            $values = [];
            foreach ($filters[$filter_key] as $value) {
                if (is_array($value)) {
                    continue;
                }
                $slug = sanitize_title(wp_unslash((string) $value));
                if ($slug !== '' && $slug !== 'all') {
                    $values[] = $slug;
                }
            }

            if (!empty($values)) {
                $params[$param] = array_values(array_unique($values));
            }
        }

        if ($search_query !== '') {
            $params['q'] = $search_query;
        }

        // Build key[]=value pairs by hand.
        $query_parts = [];
        foreach ($params as $param => $value) {
            if (is_array($value)) {
                foreach ($value as $slug) {
                    $query_parts[] = rawurlencode($param . '[]') . '=' . rawurlencode($slug);
                }
            } else {
                $query_parts[] = rawurlencode($param) . '=' . rawurlencode($value);
            }
        }

        if (empty($query_parts)) {
            return $base;
        }

        return $base . (strpos($base, '?') === false ? '?' : '&') . implode('&', $query_parts);
    }

    /**
     * Returns term counts for every filter taxonomy given the current selections.
     *
     * Request keys are taxonomy names, allowlisted against get_filter_taxonomy_map().
     *
     * @return void
     */
    public static function get_filter_counts() {
        $raw_filters        = isset($_POST['filters']) && is_array($_POST['filters']) ? wp_unslash($_POST['filters']) : [];
        $allowed_taxonomies = array_values(self::get_filter_taxonomy_map());
        $filters            = [];

        foreach ($raw_filters as $taxonomy => $values) {
            $taxonomy = sanitize_key($taxonomy);

            if (!in_array($taxonomy, $allowed_taxonomies, true)) {
                continue;
            }

            $values = is_array($values) ? $values : [$values];
            $slugs  = [];

            foreach ($values as $value) {
                if (is_array($value)) {
                    continue;
                }
                $slug = sanitize_title((string) $value);
                if ($slug !== '' && $slug !== 'all') {
                    $slugs[] = $slug;
                }
            }

            if (!empty($slugs)) {
                $filters[$taxonomy] = array_values(array_unique($slugs));
            }
        }

        $counts = [
            'locations' => \DC\Inventory::getTaxonomyValuesForLocation($filters),
            'conditions' => \DC\Inventory::getTaxonomyValuesForCondition($filters),
            'statuses' => \DC\Inventory::getTaxonomyValuesForStatus($filters),
            'years' => \DC\Inventory::getTaxonomyValuesForYear($filters),
            'categories' => \DC\Inventory::getTaxonomyValuesForBoatType($filters),
            'makes' => \DC\Inventory::getTaxonomyValuesForMake($filters),
            'models' => \DC\Inventory::getTaxonomyValuesForModel($filters),
            'priceRanges' => \DC\Inventory::getTaxonomyValuesForPriceRange($filters),
            'lengths' => \DC\Inventory::getTaxonomyValuesForLength($filters),
            'horsepowers' => \DC\Inventory::getTaxonomyValuesForHorsepower($filters),
            'capacities' => \DC\Inventory::getTaxonomyValuesForPersonCapacity($filters),
        ];
        
        wp_send_json_success($counts);
    }

    /**
     * Extends the search WHERE clause to match extra meta values and term names.
     *
     * Applies only to queries that set 'dc_search_extra_meta_keys' and/or
     * 'dc_search_extra_taxonomies'. ORs EXISTS subqueries into the first
     * "AND (...)" group and keeps any trailing clauses unchanged.
     *
     * @param string    $search Search WHERE clause generated by WP_Query.
     * @param \WP_Query $query  Current query.
     * @return string Modified WHERE clause.
     */
    public static function search_stock_and_hin($search, $query) {
        global $wpdb;

        $meta_keys  = $query->get('dc_search_extra_meta_keys');
        $taxonomies = $query->get('dc_search_extra_taxonomies');
        $term = $query->get('s');

        if (empty($search) || $term === '' || (empty($meta_keys) && empty($taxonomies))) {
            return $search;
        }

        // Match the first balanced "AND (...)" group; the rest is kept as the tail.
        if (!preg_match('/^\s*AND\s*(\((?:[^()]++|(?1))*+\))\s*(.*)$/s', $search, $matches)) {
            return $search;
        }

        $inner = substr($matches[1], 1, -1);
        $tail  = $matches[2];

        $like = '%' . $wpdb->esc_like($term) . '%';
        $extra = [];

        if (!empty($meta_keys)) {
            $placeholders = implode(', ', array_fill(0, count($meta_keys), '%s'));
            $extra[] = $wpdb->prepare(
                "EXISTS (SELECT 1 FROM {$wpdb->postmeta} dc_sm WHERE dc_sm.post_id = {$wpdb->posts}.ID AND dc_sm.meta_key IN ({$placeholders}) AND dc_sm.meta_value LIKE %s)",
                array_merge($meta_keys, [$like])
            );
        }

        if (!empty($taxonomies)) {
            $placeholders = implode(', ', array_fill(0, count($taxonomies), '%s'));
            $extra[] = $wpdb->prepare(
                "EXISTS (SELECT 1 FROM {$wpdb->term_relationships} dc_tr
                    INNER JOIN {$wpdb->term_taxonomy} dc_tt ON dc_tt.term_taxonomy_id = dc_tr.term_taxonomy_id
                    INNER JOIN {$wpdb->terms} dc_t ON dc_t.term_id = dc_tt.term_id
                    WHERE dc_tr.object_id = {$wpdb->posts}.ID AND dc_tt.taxonomy IN ({$placeholders}) AND dc_t.name LIKE %s)",
                array_merge($taxonomies, [$like])
            );
        }

        if (empty($extra)) {
            return $search;
        }

        $extra_sql = 'OR ' . implode(' OR ', $extra);

        return " AND ({$inner} {$extra_sql}) " . $tail;
    }

    /**
     * Returns the map of visitor filter keys to taxonomy names.
     *
     * Keys are the tokens used in the AJAX payload and shortcode attributes.
     * Filterable via dealerschoice_filter_taxonomy_map.
     *
     * @return array<string,string> Filter key => taxonomy name.
     */
    public static function get_filter_taxonomy_map() {
        return apply_filters('dealerschoice_filter_taxonomy_map', [
            'location' => 'location',
            'condition' => 'condition',
            'status' => 'boat_status',
            'year' => 'boat_year',
            'category' => 'boat_type',
            'make' => 'make',
            'model' => 'model',
            'price' => 'price_range',
            'length' => 'length_range',
            'horsepower' => 'horsepower',
            'capacity' => 'person_capacity',
        ]);
    }

    /**
     * Returns the map of lockable constraint keys to taxonomy names.
     *
     * The filter map plus 'promotion' => 'dc_promotions'. Filterable via
     * dealerschoice_lockable_taxonomy_map.
     *
     * @return array<string,string> Lock key => taxonomy name.
     */
    public static function get_lockable_taxonomy_map() {
        $map = self::get_filter_taxonomy_map();

        $map['promotion'] = 'dc_promotions';

        return apply_filters('dealerschoice_lockable_taxonomy_map', $map);
    }

    /**
     * Validates locked taxonomy constraints.
     *
     * Keeps keys in get_lockable_taxonomy_map() whose taxonomy is registered and
     * passes each term through sanitize_title(). Empty and 'all' values are
     * dropped; slugs that match no term are kept.
     *
     * @param mixed $raw Constraints as ['lock_key' => ['slug', ...]].
     * @return array<string,array<int,string>> Sanitized constraints.
     */
    public static function sanitize_locked_constraints($raw) {
        $clean = [];

        if (!is_array($raw)) {
            return $clean;
        }

        $map = self::get_lockable_taxonomy_map();

        foreach ($raw as $filter_key => $values) {
            $filter_key = sanitize_key($filter_key);

            if (!isset($map[$filter_key]) || !taxonomy_exists($map[$filter_key])) {
                continue;
            }

            $values = is_array($values) ? $values : [$values];
            $terms  = [];

            foreach ($values as $value) {
                if (is_array($value)) {
                    continue;
                }

                $slug = sanitize_title(wp_unslash((string) $value));

                if ($slug === '' || $slug === 'all') {
                    continue;
                }

                $terms[] = $slug;
            }

            if (!empty($terms)) {
                $clean[$filter_key] = array_values(array_unique($terms));
            }
        }

        return $clean;
    }

    /**
     * Validates the visitor's filter selections.
     *
     * Keeps keys in get_filter_taxonomy_map() and passes each slug through
     * sanitize_title(). The 'id' key (favorites) is passed through absint() and
     * capped by dealerschoice_max_favorite_ids (default 100). 'all' values are kept.
     *
     * @param mixed $raw Filters as ['filter_key' => ['slug', ...]].
     * @return array<string,array<int,string|int>> Sanitized filters.
     */
    public static function sanitize_filters($raw) {
        $clean = [];

        if (!is_array($raw)) {
            return $clean;
        }

        $map = self::get_filter_taxonomy_map();

        foreach ($raw as $filter_key => $values) {
            $filter_key = sanitize_key($filter_key);
            $values     = is_array($values) ? $values : [$values];

            if ($filter_key === 'id') {
                $ids = [];
                foreach ($values as $value) {
                    if (is_array($value)) {
                        continue;
                    }
                    $id = absint($value);
                    if ($id > 0) {
                        $ids[] = $id;
                    }
                }

                $max_ids = max(1, (int) apply_filters('dealerschoice_max_favorite_ids', 100));
                $ids     = array_slice(array_values(array_unique($ids)), 0, $max_ids);

                if (!empty($ids)) {
                    $clean['id'] = $ids;
                }
                continue;
            }

            if (!isset($map[$filter_key])) {
                continue;
            }

            $terms = [];
            foreach ($values as $value) {
                if (is_array($value)) {
                    continue;
                }

                $slug = sanitize_title(wp_unslash((string) $value));

                if ($slug !== '') {
                    $terms[] = $slug;
                }
            }

            if (!empty($terms)) {
                $clean[$filter_key] = array_values(array_unique($terms));
            }
        }

        return $clean;
    }

    /**
     * Builds WP_Query arguments for the inventory listing.
     *
     * A favorites request ('id' filter) queries those IDs in order and ignores
     * other filters and search. Locked constraints are ANDed onto every query,
     * and boats flagged do_not_show_on_public_website are excluded.
     *
     * @param array  $filters        Filter selections.
     * @param string $search_query   Optional. Search keyword. Default empty.
     * @param string $sort_by        Optional. Sort option. Default 'date-desc'.
     * @param int    $paged          Optional. Page number. Default 1.
     * @param int    $posts_per_page Optional. Posts per page. Default 12.
     * @param array  $locked         Optional. Output of sanitize_locked_constraints(). Default empty.
     * @return array WP_Query arguments.
     */
    private static function build_inventory_query_args($filters, $search_query = '', $sort_by = 'date-desc', $paged = 1, $posts_per_page = 12, $locked = []) {
        $args = [
            'post_type' => 'boat',
            'post_status' => 'publish',
            'posts_per_page' => $posts_per_page,
            'paged' => $paged,
        ];

        $taxonomy_map = self::get_filter_taxonomy_map();
        $lockable_map = self::get_lockable_taxonomy_map();

        $tax_query = ['relation' => 'AND'];

        // Favorites: query by post ID, preserving the given order.
        if (!empty($filters['id']) && is_array($filters['id'])) {
            $ids = array_map('intval', array_filter($filters['id'], function($id) { return $id !== '' && $id !== null; }));
            if (!empty($ids)) {
                $args['post__in'] = $ids;
                $args['orderby'] = 'post__in';
            }
        } else {
            if (!empty($search_query)) {
                $args['s'] = $search_query;
                // Also match Stock Number, HIN and Boat Type via search_stock_and_hin().
                $args['dc_search_extra_meta_keys'] = ['boat_stock_number', 'boat_hin'];
                $args['dc_search_extra_taxonomies'] = ['boat_type'];
            }

            foreach ($taxonomy_map as $filter_key => $taxonomy) {
                if (!empty($filters[$filter_key]) && is_array($filters[$filter_key])) {
                    $filter_values = array_filter($filters[$filter_key], function($val) {
                        return $val !== 'all';
                    });

                    if (!empty($filter_values)) {
                        $tax_query[] = [
                            'taxonomy' => $taxonomy,
                            'field' => 'slug',
                            'terms' => $filter_values,
                            'operator' => 'IN'
                        ];
                    }
                }
            }
        }

        // Locked constraints apply to both branches.
        foreach ($locked as $lock_key => $terms) {
            if (!isset($lockable_map[$lock_key]) || empty($terms)) {
                continue;
            }

            $tax_query[] = [
                'taxonomy' => $lockable_map[$lock_key],
                'field'    => 'slug',
                'terms'    => $terms,
                'operator' => 'IN',
            ];
        }

        if (count($tax_query) > 1) {
            $args['tax_query'] = $tax_query;
        }

        // Exclude boats hidden from the public website.
        $args['meta_query'] = [
            'relation' => 'OR',
            [
                'key' => 'do_not_show_on_public_website',
                'compare' => 'NOT EXISTS'
            ],
            [
                'key' => 'do_not_show_on_public_website',
                'value' => '1',
                'compare' => '!='
            ]
        ];
        
        self::add_sorting_args($args, $sort_by);
        
        return $args;
    }

    /**
     * Queries up to 3 suggested boats by relaxing the visitor's filters.
     *
     * Tries Make + Model, then Make (+ Category), then Category, then Make alone.
     * Locked constraints are always kept.
     *
     * @param array $filters Current filters.
     * @param array $locked  Optional. Locked constraints. Default empty.
     * @return \WP_Query|null Query with results, or null when no strategy matches.
     */
    private static function get_suggestions_query($filters, $locked = []) {
        $has_filter = function($key) use ($filters) {
            if (empty($filters[$key])) return false;
            if (is_array($filters[$key])) {
                $vals = array_filter($filters[$key], function($v) { return $v !== 'all'; });
                return !empty($vals);
            }
            return $filters[$key] !== 'all';
        };
        
        $relaxed_filters = [];
        $found_strategy = false;
        
        // Make + Model.
        if ($has_filter('make') && $has_filter('model')) {
            $relaxed_filters['make'] = $filters['make'];
            $relaxed_filters['model'] = $filters['model'];
            $found_strategy = true;
        } 
        // Make, plus Category if set.
        elseif ($has_filter('make')) {
            $relaxed_filters['make'] = $filters['make'];
            if ($has_filter('category')) {
                $relaxed_filters['category'] = $filters['category'];
            }
            $found_strategy = true;
        }
        // Category.
        elseif ($has_filter('category')) {
            $relaxed_filters['category'] = $filters['category'];
            $found_strategy = true;
        }
        
        if ($found_strategy) {
            $args = self::build_inventory_query_args($relaxed_filters, '', 'date-desc', 1, 3, $locked);
            $query = new \WP_Query($args);
            if ($query->have_posts()) return $query;
        }

        // Fall back to Make alone after Make + Model or Make + Category found nothing.
        if ($has_filter('make') && (isset($relaxed_filters['model']) || isset($relaxed_filters['category']))) {
            $args = self::build_inventory_query_args(['make' => $filters['make']], '', 'date-desc', 1, 3, $locked);
            $query = new \WP_Query($args);
            if ($query->have_posts()) return $query;
        }
        
        return null;
    }

    /**
     * Builds the "You May Be Interested In" block shown when there are no results.
     *
     * Falls back to the newest boats within the locked constraints.
     *
     * @param array $filters Current filters.
     * @param array $locked  Optional. Locked constraints. Default empty.
     * @return string Block HTML, or an empty string when there are no boats.
     */
    private static function get_suggestions_html($filters, $locked = []) {
        $query = self::get_suggestions_query($filters, $locked);

        if (!$query || !$query->have_posts()) {
             $args = self::build_inventory_query_args([], '', 'date-desc', 1, 3, $locked);
             $query = new \WP_Query($args);
        }
        
        if (!$query->have_posts()) {
            return '';
        }
        
        ob_start();
        ?>
        <div class="dealerschoice-suggestions" style="margin-top: 40px; border-top: 1px solid #ddd; padding-top: 30px;">
             <h3 style="text-align: center; margin-bottom: 30px;">You May Be Interested In...</h3>
            <div class="suggestions-grid">
                <?php while ($query->have_posts()): $query->the_post(); ?>
                    <?php echo Template_Loader::get_template('inventory-block.php', [], true); ?>
                <?php endwhile; ?>
            </div>
        </div>
        <?php
        wp_reset_postdata();
        return ob_get_clean();
    }

    // Boat Quiz

    /**
     * Scores quiz answers and returns the rendered result HTML.
     *
     * POST params:
     *   activity        (string) cruising|fishing|watersports|adventure
     *   crew            (string) small|medium|large
     *   priorities      (string) Priority option value from BoatQuiz::load_config().
     *   budget          (string) price_range slug, or 'any'.
     *   gravity_form_id (int)    Optional. Lead form ID.
     *   boat_count      (int)    Optional. Boats to return, 1-12. Default 3.
     *
     * @return void
     */
    public static function quiz_results() {
        if ( ! check_ajax_referer( 'dealerschoice_quiz_nonce', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => __( 'Security check failed.', 'dealerschoice' ) ], 403 );
        }

        $allowed_activity   = [ 'cruising', 'fishing', 'watersports', 'adventure' ];
        $allowed_crew        = [ 'small', 'medium', 'large' ];

        // Allowed priorities: conditional_priorities values from the config plus the defaults.
        $quiz_config        = BoatQuiz::load_config();
        $allowed_priorities = [];

        if ( ! empty( $quiz_config['conditional_priorities'] ) ) {
            foreach ( $quiz_config['conditional_priorities'] as $group ) {
                foreach ( $group['options'] ?? [] as $opt ) {
                    if ( ! empty( $opt['value'] ) ) {
                        $allowed_priorities[] = $opt['value'];
                    }
                }
            }
        }

        $allowed_priorities = array_unique( array_merge(
            [ 'comfort', 'performance', 'fishing', 'adventure' ],
            $allowed_priorities
        ) );

        $activity   = sanitize_text_field( wp_unslash( $_POST['activity']   ?? '' ) );
        $crew       = sanitize_text_field( wp_unslash( $_POST['crew']       ?? '' ) );
        $priorities = sanitize_text_field( wp_unslash( $_POST['priorities'] ?? '' ) );
        $budget     = sanitize_text_field( wp_unslash( $_POST['budget']     ?? '' ) );

        if ( ! in_array( $activity,   $allowed_activity,   true ) ||
             ! in_array( $crew,       $allowed_crew,       true ) ||
             ! in_array( $priorities, $allowed_priorities, true ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid quiz answers.', 'dealerschoice' ) ], 400 );
        }

        // Unknown budget slugs fall back to 'any'.
        if ( $budget !== 'any' && $budget !== '' ) {
            $price_term = get_term_by( 'slug', $budget, 'price_range' );
            if ( ! $price_term ) {
                $budget = 'any';
            }
        }

        $answers = compact( 'activity', 'crew', 'priorities', 'budget' );

        $match = BoatQuiz::calculate_match( $answers );

        if ( empty( $match['top'] ) ) {
            wp_send_json_error( [ 'message' => __( 'No boat types found. Please check your inventory.', 'dealerschoice' ) ], 404 );
        }

        $top_term = $match['top'];
        $alt_terms = $match['alts'];

        $boat_count = absint( wp_unslash( $_POST['boat_count'] ?? 3 ) );
        $boat_count = min( 12, max( 1, $boat_count ?: 3 ) );

        // $boat_meta reports whether the chosen budget could be matched.
        $boat_meta      = null;
        $matching_boats = BoatQuiz::get_matching_boats( $top_term->slug, $budget, $boat_count, $boat_meta );

        $inventory_url = BoatQuiz::build_inventory_url( $top_term->slug, $budget );

        $why_text = BoatQuiz::get_result_copy( $top_term );

        $gravity_form_id = absint( wp_unslash( $_POST['gravity_form_id'] ?? 0 ) );

        $html = Template_Loader::get_template(
            'shortcodes/boat-quiz-result.php',
            compact( 'top_term', 'why_text', 'inventory_url', 'matching_boats', 'alt_terms', 'gravity_form_id', 'answers', 'boat_meta' ),
            true
        );

        wp_send_json_success( [ 'html' => $html, 'result_type' => $top_term->name ] );
    }
}
