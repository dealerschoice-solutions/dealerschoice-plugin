<?php
/**
 * AJAX Handlers
 * 
 * Handles all AJAX requests for inventory filtering, searching, and pagination.
 * This class processes user interactions with the inventory filters and returns
 * filtered results dynamically without page reloads.
 * 
 * @package DealersChoice
 * @subpackage Classes
 * @since 1.0.0
 * 
 * Features:
 * - Dynamic inventory filtering (location, condition, year, make, model, etc.)
 * - Search functionality with keyword matching
 * - Multiple sort options (price, date, length, etc.)
 * - Pagination with configurable posts per page
 * - Efficient database queries with proper indexing
 * - Filter counts updated based on current selection
 * 
 * AJAX Actions:
 * - search_inventory: Main inventory search/filter handler
 * - get_filter_counts: Updates filter counts based on current selections
 * - reveal_price: Reveal the price of a specific boat
 * 
 * Usage:
 * ```javascript
 * jQuery.ajax({
 *     url: dealersChoicePublic.ajaxUrl,
 *     type: 'POST',
 *     data: {
 *         action: 'search_inventory',
 *         filters: {...},
 *         sortBy: 'price-asc',
 *         currentPage: 1
 *     }
 * });
 * ```
 * 
 * Dependencies:
 * - WordPress WP_Query
 * - DC\Inventory class for filter counts
 * - DC\Template_Loader for rendering results
 */

namespace DC;

if (!defined('ABSPATH')) {
    exit;
}

class AJAX_Handlers {
    
    /**
     * Initialize AJAX handlers
     */
    public static function init() {
        // Public AJAX actions (available to non-logged-in users)
        add_action('wp_ajax_search_inventory', [__CLASS__, 'search_inventory']);
        add_action('wp_ajax_nopriv_search_inventory', [__CLASS__, 'search_inventory']);
        
        add_action('wp_ajax_get_filter_counts', [__CLASS__, 'get_filter_counts']);
        add_action('wp_ajax_nopriv_get_filter_counts', [__CLASS__, 'get_filter_counts']);

        add_action('wp_ajax_reveal_price', [__CLASS__, 'reveal_price']);
        add_action('wp_ajax_nopriv_reveal_price', [__CLASS__, 'reveal_price']);

        add_action('wp_ajax_dealerschoice_quiz_results', [__CLASS__, 'quiz_results']);
        add_action('wp_ajax_nopriv_dealerschoice_quiz_results', [__CLASS__, 'quiz_results']);

        add_action('wp_ajax_record_favorite', [__CLASS__, 'record_favorite']);
        add_action('wp_ajax_nopriv_record_favorite', [__CLASS__, 'record_favorite']);

        // Admin AJAX actions
        add_action('wp_ajax_start_inventory_sync', [__CLASS__, 'start_inventory_sync']);
        add_action('wp_ajax_run_inventory_sync', [__CLASS__, 'run_inventory_sync']);
        add_action('wp_ajax_get_sync_logs', [__CLASS__, 'get_sync_logs']);
    }

    /**
     * Starts the inventory sync process in the background.
     *
     * This function is triggered by an AJAX request from the admin sync page.
     * It initiates a non-blocking request to the server to run the actual sync,
     * allowing the admin page to remain responsive.
     */
    public static function start_inventory_sync() {
        check_ajax_referer('dealers_choice_sync_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied.'], 403);
        }

        // Clear previous logs before starting a new sync
        update_option('dealers_choice_recent_logs', []);

        $force_sync = isset($_POST['force_sync']) && $_POST['force_sync'] === 'true';

        // Use a non-blocking request to run the sync in the background
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

    public static function reveal_price() {
        check_ajax_referer('dealerschoice_reveal_price_nonce', 'nonce');
    
        $inventoryID = isset($_GET['inventoryID']) ? sanitize_text_field($_GET['inventoryID']) : '';
    
        if (empty($inventoryID)) {
            wp_send_json_error(['message' => 'No inventory ID provided.'], 400);
        }
    
        $price = get_post_meta($inventoryID, 'boat_saleprice', true);
    
        if ($price && is_numeric($price)) {
            $formatted_price = '$' . number_format($price);
            wp_send_json_success(['price' => $formatted_price]);
        } else {
            wp_send_json_error(['message' => 'Price not available.'], 404);
        }
    }

    /**
     * Record a favorite add/remove event to the analytics table.
     *
     * Fires for both logged-in and logged-out users. Silently succeeds or fails;
     * the user-facing favorites UI is unaffected by this call.
     */
    public static function record_favorite() {
        check_ajax_referer('dealerschoice_record_favorite_nonce', 'nonce');

        // Only record when favorites are enabled
        if ( get_option('dealers_choice_show_favorites', '1') !== '1' ) {
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
     * Executes the actual inventory sync.
     *
     * This is triggered by the non-blocking request from start_inventory_sync.
     * It runs the full inventory synchronization process.
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
     * Retrieves recent sync logs for the admin interface.
     *
     * This AJAX endpoint allows the admin page to poll for log updates
     * during an active sync process.
     */
    public static function get_sync_logs() {
        check_ajax_referer('dealers_choice_sync_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied.'], 403);
        }

        $logs = get_option('dealers_choice_recent_logs', []);
        $sync_lock = get_transient('dealers_choice_inventory_sync_lock');

        wp_send_json_success([
            'logs' => array_reverse($logs), // Show most recent first
            'sync_in_progress' => !empty($sync_lock),
        ]);
    }
    
    /**
     * Main inventory search/filter handler
     * 
     * Processes filter selections, search queries, sorting, and pagination
     * to return matching boats.
     */
    public static function search_inventory() {
        // Make sure we allow cross-origin requests
        $origin = get_http_origin();
        
        // Optional: You can restrict this strictly to domains, or use '*' to allow all 
        // (since it's just public inventory data, '*' is usually safe and fixes other iframe embeds too)
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With');

        // Handle preflight OPTIONS request from the browser
        if ('OPTIONS' == $_SERVER['REQUEST_METHOD']) {
            status_header(200);
            exit();
        }

        // Get filter data from AJAX request
        $filters = isset($_POST['filters']) ? $_POST['filters'] : [];
        $sort_by = isset($_POST['sortBy']) ? sanitize_text_field($_POST['sortBy']) : get_option('dealers_choice_default_sort', 'date-desc');
        $search_query = isset($_POST['query']) ? sanitize_text_field($_POST['query']) : '';
        $current_page = isset($_POST['currentPage']) ? absint($_POST['currentPage']) : 1;
        $posts_per_page = isset($_POST['postsPerPage']) && absint($_POST['postsPerPage']) > 0
            ? absint($_POST['postsPerPage'])
            : apply_filters('dealerschoice_posts_per_page', 12);
        
        // Build query args using helper
        $args = self::build_inventory_query_args($filters, $search_query, $sort_by, $current_page, $posts_per_page);
        
        // Execute query
        $query = new \WP_Query($args);
        
        // Build results HTML
        $results_html = '';
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $results_html .= Template_Loader::get_template('inventory-block.php', [], true);
            }
            wp_reset_postdata();
        } else {
            // Get no results message
            $no_results_msg = self::get_no_results_html($search_query, $filters);
            
            // Get suggestions
            $suggestions = self::get_suggestions_html($filters);
            
            $results_html = $no_results_msg . $suggestions;
        }
        
        // Build pagination HTML
        $pagination_html = self::build_pagination($query, $current_page);
        
        // Return JSON response
        wp_send_json_success([
            'results' => $results_html,
            'pagination' => $pagination_html,
            'total' => $query->found_posts,
            'pages' => $query->max_num_pages
        ]);
    }
    
    /**
     * Add sorting arguments to WP_Query args
     * 
     * @param array $args WP_Query arguments (passed by reference)
     * @param string $sort_by Sort option
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
     * Build pagination HTML
     * 
     * @param \WP_Query $query Query object
     * @param int $current_page Current page number
     * @return string Pagination HTML
     */
    private static function build_pagination($query, $current_page) {
        if ($query->max_num_pages <= 1) {
            return '';
        }
        
        $html = '<nav class="pagination" aria-label="Inventory Pagination">';
        $html .= '<ul class="pagination-list">';
        
        // Previous button
        if ($current_page > 1) {
            $html .= sprintf(
                '<li><a href="#" data-page="%d" class="pagination-prev" aria-label="Previous Page"><i class="fa-light fa-angle-left"></i>Previous</a></li>',
                $current_page - 1
            );
        }
        
        // Page numbers
        $range = 2; // Show 2 pages on each side of current page
        
        for ($i = 1; $i <= $query->max_num_pages; $i++) {
            // Always show first page, last page, and pages around current
            if ($i == 1 || $i == $query->max_num_pages || ($i >= $current_page - $range && $i <= $current_page + $range)) {
                $active_class = $i == $current_page ? ' class="active"' : '';
                $html .= sprintf(
                    '<li%s><a href="#" data-page="%d">%d</a></li>',
                    $active_class,
                    $i,
                    $i
                );
            } elseif ($i == $current_page - $range - 1 || $i == $current_page + $range + 1) {
                // Show ellipsis
                $html .= '<li class="pagination-ellipsis"><span>...</span></li>';
            }
        }
        
        // Next button
        if ($current_page < $query->max_num_pages) {
            $html .= sprintf(
                '<li><a href="#" data-page="%d" class="pagination-next" aria-label="Next Page">Next<i class="fa-light fa-angle-right"></i></a></li>',
                $current_page + 1
            );
        }
        
        $html .= '</ul>';
        $html .= '</nav>';
        
        return $html;
    }
    
    /**
     * Get no results HTML
     * 
     * @param string $search_query Current search query
     * @param array $filters Current filters
     * @return string No results HTML
     */
    private static function get_no_results_html($search_query, $filters) {
        $has_filters = false;
        foreach ($filters as $filter) {
            if (!empty($filter) && is_array($filter)) {
                $filter_values = array_filter($filter, function($val) {
                    return $val !== 'all';
                });
                if (!empty($filter_values)) {
                    $has_filters = true;
                    break;
                }
            }
        }
        
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
     * Get updated filter counts based on current selections
     * 
     * This allows dynamic updating of filter counts as user selects/deselects filters
     */
    public static function get_filter_counts() {
        $filters = isset($_POST['filters']) ? $_POST['filters'] : [];
        
        // Get updated counts
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
     * Build WP_Query arguments based on filters and search parameters
     *
     * @param array $filters Filter selections
     * @param string $search_query Search keyword
     * @param string $sort_by Sort order
     * @param int $paged Page number
     * @param int $posts_per_page Posts per page
     * @return array WP_Query arguments
     */
    private static function build_inventory_query_args($filters, $search_query = '', $sort_by = 'date-desc', $paged = 1, $posts_per_page = 12) {
        $args = [
            'post_type' => 'boat',
            'post_status' => 'publish',
            'posts_per_page' => $posts_per_page,
            'paged' => $paged,
        ];

        // If filtering by post IDs (for favorites), override other filters
        if (!empty($filters['id']) && is_array($filters['id'])) {
            // Sanitize IDs
            $ids = array_map('intval', array_filter($filters['id'], function($id) { return $id !== '' && $id !== null; }));
            if (!empty($ids)) {
                $args['post__in'] = $ids;
                // To preserve order of IDs as in favorites, use 'orderby' => 'post__in'
                $args['orderby'] = 'post__in';
                // No need for tax_query or search
            }
        } else {
            // Add search query
            if (!empty($search_query)) {
                $args['s'] = $search_query;
            }

            // Build tax query from filters
            $tax_query = ['relation' => 'AND'];

            $taxonomy_map = [
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
            ];

            foreach ($taxonomy_map as $filter_key => $taxonomy) {
                if (!empty($filters[$filter_key]) && is_array($filters[$filter_key])) {
                    // Remove 'all' option if present
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

            if (count($tax_query) > 1) {
                $args['tax_query'] = $tax_query;
            }
        }
        
        // Exclude boats marked to not show on public website
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
        
        // Add sorting
        self::add_sorting_args($args, $sort_by);
        
        return $args;
    }

    /**
     * Get suggestions query based on relaxed filters
     * 
     * @param array $filters Current filters
     * @return \WP_Query|null Query or null if no appropriate strategy found
     */
    private static function get_suggestions_query($filters) {
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
        
        // 1. Make + Model -> Drop Year/Price/etc, Keep Make+Model
        if ($has_filter('make') && $has_filter('model')) {
            $relaxed_filters['make'] = $filters['make'];
            $relaxed_filters['model'] = $filters['model'];
            $found_strategy = true;
        } 
        // 2. Make -> Drop Model/Year, Keep Make (+ Category if present)
        elseif ($has_filter('make')) {
            $relaxed_filters['make'] = $filters['make'];
            if ($has_filter('category')) {
                $relaxed_filters['category'] = $filters['category'];
            }
            $found_strategy = true;
        }
        // 3. Category -> Drop Length/Price, Keep Category
        elseif ($has_filter('category')) {
            $relaxed_filters['category'] = $filters['category'];
            $found_strategy = true;
        }
        
        if ($found_strategy) {
            $args = self::build_inventory_query_args($relaxed_filters, '', 'date-desc', 1, 3);
            $query = new \WP_Query($args);
            if ($query->have_posts()) return $query;
        }

        // If specific Make/Model/Category strategy yielded no results (or wasn't applicable), 
        // check if we can fall back to Make only (if we tried Make+Model or Make+Cat)
        if ($has_filter('make') && (isset($relaxed_filters['model']) || isset($relaxed_filters['category']))) {
             // We tried Make+Model or Make+Cat and failed. Try just Make.
            $args = self::build_inventory_query_args(['make' => $filters['make']], '', 'date-desc', 1, 3);
            $query = new \WP_Query($args);
            if ($query->have_posts()) return $query;
        }
        
        return null;
    }

    /**
     * Get suggestions HTML when no results found
     * 
     * @param array $filters Current filters
     * @return string HTML content
     */
    private static function get_suggestions_html($filters) {
        $query = self::get_suggestions_query($filters);
        
        if (!$query || !$query->have_posts()) {
            // Ultimate fallback: Newest boats (no filters)
             $args = self::build_inventory_query_args([], '', 'date-desc', 1, 3);
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

    // ── Boat Quiz ────────────────────────────────────────────────────────────

    /**
     * Handle quiz answer submission and return the rendered result HTML.
     *
     * POST params:
     *   nonce    (string) 'dealerschoice_quiz_nonce'
     *   activity (string) cruising|fishing|watersports|adventure
     *   crew     (string) small|medium|large
     *   water    (string) calm|coastal|offshore
     *   budget   (string) any price_range taxonomy slug (or 'any')
     */
    public static function quiz_results() {
        if ( ! check_ajax_referer( 'dealerschoice_quiz_nonce', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => __( 'Security check failed.', 'dealerschoice' ) ], 403 );
        }

        $allowed_activity   = [ 'cruising', 'fishing', 'watersports', 'adventure' ];
        $allowed_crew        = [ 'small', 'medium', 'large' ];

        // Build the allowed priorities list from the live config so that
        // conditional_priorities option values (theme overrides) are accepted
        // alongside the static defaults.
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

        // Always include the static fallback values so sites without a theme
        // override continue to work without any config changes.
        $allowed_priorities = array_unique( array_merge(
            [ 'comfort', 'performance', 'fishing', 'adventure' ],
            $allowed_priorities
        ) );

        $activity   = sanitize_text_field( wp_unslash( $_POST['activity']   ?? '' ) );
        $crew       = sanitize_text_field( wp_unslash( $_POST['crew']       ?? '' ) );
        $priorities = sanitize_text_field( wp_unslash( $_POST['priorities'] ?? '' ) );
        $budget     = sanitize_text_field( wp_unslash( $_POST['budget']     ?? '' ) );

        // Validate fixed-choice fields
        if ( ! in_array( $activity,   $allowed_activity,   true ) ||
             ! in_array( $crew,       $allowed_crew,       true ) ||
             ! in_array( $priorities, $allowed_priorities, true ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid quiz answers.', 'dealerschoice' ) ], 400 );
        }

        // Validate budget against actual taxonomy slugs (or 'any')
        if ( $budget !== 'any' && $budget !== '' ) {
            $price_term = get_term_by( 'slug', $budget, 'price_range' );
            if ( ! $price_term ) {
                $budget = 'any'; // Graceful fallback if slug not found
            }
        }

        $answers = compact( 'activity', 'crew', 'priorities', 'budget' );

        // Score and find top match
        $match = BoatQuiz::calculate_match( $answers );

        if ( empty( $match['top'] ) ) {
            wp_send_json_error( [ 'message' => __( 'No boat types found. Please check your inventory.', 'dealerschoice' ) ], 404 );
        }

        $top_term = $match['top'];
        $alt_terms = $match['alts'];

        // Fetch 1–3 matching boats
        $matching_boats = BoatQuiz::get_matching_boats( $top_term->slug, $budget );

        // Build inventory CTA URL
        $inventory_url = BoatQuiz::build_inventory_url( $top_term->slug, $budget );

        // Get "why" copy
        $why_text = BoatQuiz::get_result_copy( $top_term );

        // Sanitise the optional Gravity Forms ID from the quiz wrapper.
        $gravity_form_id = absint( wp_unslash( $_POST['gravity_form_id'] ?? 0 ) );

        // Render result template to string
        $html = Template_Loader::get_template(
            'shortcodes/boat-quiz-result.php',
            compact( 'top_term', 'why_text', 'inventory_url', 'matching_boats', 'alt_terms', 'gravity_form_id', 'answers' ),
            true
        );

        wp_send_json_success( [ 'html' => $html, 'result_type' => $top_term->name ] );
    }
}
