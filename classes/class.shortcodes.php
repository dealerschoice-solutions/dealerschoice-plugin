<?php
/**
 * Registers and renders the plugin's front-end shortcodes.
 *
 * @package DealersChoice
 */

namespace DC;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Front-end shortcodes: inventory listing, slider, filters, favorites, location list,
 * boat quiz and finance calculators.
 */
class Shortcodes {
    
    /**
     * Registers all shortcodes.
     */
    public static function init() {
        add_shortcode('dealerschoice_inventory', [__CLASS__, 'inventory_shortcode']);
        add_shortcode('dealerschoice_slider', [__CLASS__, 'slider_shortcode']);
        add_shortcode('dealerschoice_filters', [__CLASS__, 'filters_shortcode']);
        add_shortcode('dealerschoice_favorites', [__CLASS__, 'favorites_shortcode']);
        add_shortcode('dealerschoice_location_list', [__CLASS__, 'location_list_shortcode']);
        add_shortcode('dealerschoice_boat_quiz', [__CLASS__, 'boat_quiz_shortcode']);
        add_shortcode('dealerschoice_finance_calculator', [__CLASS__, 'finance_calculator_shortcode']);
    }

    /**
     * Enqueues and localizes the Reveal Price script when prices are gated.
     *
     * Called by shortcodes that render inventory cards.
     */
    public static function enqueue_reveal_price_assets() {
        if (get_option('dealers_choice_always_show_price', '0') === '1') {
            return;
        }
        wp_enqueue_script('dealerschoice-reveal-price');
        wp_localize_script('dealerschoice-reveal-price', 'revealPriceSettings', dealers_choice_reveal_price_settings());
    }

        /**
         * Renders [dealerschoice_favorites]: boats saved in localStorage, loaded via AJAX.
         *
         * @param array|string $atts Shortcode attributes (unused).
         * @return string HTML output.
         */
        public static function favorites_shortcode($atts) {
            wp_enqueue_style('dealerschoice-public');
            wp_enqueue_script('dealerschoice-favorites');
            wp_enqueue_script('dealerschoice-public');
            self::enqueue_reveal_price_assets();

            ob_start();
            ?>
            <div class="dealerschoice-shortcode dealerschoice-favorites-shortcode">
                <div id="dealerschoice-favorites-list" class="inventory-results">
                    <div class="loading-spinner"><i class="fa-light fa-arrows-rotate-reverse"></i> Loading favorites...</div>
                </div>
            </div>
            <script>
            jQuery(function($) {
                function renderFavorites(ids) {
                    var $list = $('#dealerschoice-favorites-list');
                    if (!ids || !ids.length) {
                        var message = '<p>Your favorites list is currently empty. Whether you\'re looking for a weekend cruiser or a hardcore fishing rig, your dream boat is waiting.</p>';
                        message += '<p>Start building your personalized boat list by browsing our <a href="/inventory/">inventory</a>. When you find a boat you like, simply click the heart icon to add it to your favorites. You can access your favorites anytime from this page, making it easy to compare boats and find the perfect match for your next adventure on the water.</p>';
                        message += '<p style="text-align:center;"><a href="/inventory/" class="dc-button">Browse Inventory</a></p>';
                        $list.html(message);
                        return;
                    }
                    $list.html('<div class="loading-spinner"><i class="fa-light fa-arrows-rotate-reverse"></i> Loading favorites...</div>');
                    $.ajax({
                        url: (typeof dealersChoicePublic !== 'undefined' ? dealersChoicePublic.ajaxUrl : ''),
                        type: 'POST',
                        data: {
                            action: 'search_inventory',
                            filters: { id: ids },
                            sortBy: 'date-desc',
                            query: '',
                            currentPage: 1,
                            favorites_only: true
                        },
                        success: function(response) {
                            if (response.success && response.data && response.data.results) {
                                $list.html(response.data.results);
                                // Re-init favorite buttons in new content
                                if (window.DealersChoiceFavorites && typeof window.DealersChoiceFavorites.initFavoriteButtons === 'function') {
                                    window.DealersChoiceFavorites.initFavoriteButtons('#dealerschoice-favorites-list');
                                }
                                // Let reveal-price restore already-unlocked prices in the new cards.
                                $(document).trigger('dc:inventoryRendered');
                            } else {
                                $list.html('<p>No favorites found.</p>');
                            }
                        },
                        error: function() {
                            $list.html('<p>Error loading favorites. Please try again.</p>');
                        }
                    });
                }
                var favs = (window.DealersChoiceFavorites && window.DealersChoiceFavorites.getFavorites) ? window.DealersChoiceFavorites.getFavorites() : [];
                renderFavorites(favs);
            });
            </script>
            <?php
            return ob_get_clean();
        }

    /**
     * Renders [dealerschoice_inventory]: the full listing with filters, search, sort and pagination.
     *
     * The category (alias categories), condition, status, location, make and promotion
     * attributes and the lock attribute ("key:slug,slug|key:slug") are locked
     * constraints enforced server-side; their filter widgets are hidden. lock_notice
     * shows a "Showing: ..." line for them. The first page of results and the
     * ItemList schema are rendered server-side.
     *
     * @param array|string $atts Shortcode attributes.
     * @return string HTML output.
     */
    public static function inventory_shortcode($atts) {
        $atts = shortcode_atts([
            'posts_per_page' => 12,
            'show_filters' => true,
            'show_search' => true,
            'show_sort' => true,
            'category' => '',
            'categories' => '',
            'condition' => '',
            'status' => '',
            'location' => '',
            'make' => '',
            'promotion' => '',
            'lock' => '',
            'lock_notice' => false,
        ], $atts, 'dealerschoice_inventory');

        if (empty($atts['category']) && !empty($atts['categories'])) {
            $atts['category'] = $atts['categories'];
        }

        ob_start();

        wp_enqueue_style('dealerschoice-public');
        wp_enqueue_script('dealerschoice-public');
        if (dealerschoiceIsFavoritesEnabled()) {
            wp_enqueue_script('dealerschoice-favorites');
        }
        self::enqueue_reveal_price_assets();

        // Convert comma-separated attribute values to arrays.
        if (!is_array($atts['location'])) {
            $atts['location'] = array_filter(array_map('trim', explode(',', $atts['location'])));
        }
        if (!is_array($atts['condition'])) {
            $atts['condition'] = array_filter(array_map('trim', explode(',', $atts['condition'])));
        }
        if (!is_array($atts['status'])) {
            $atts['status'] = array_filter(array_map('trim', explode(',', $atts['status'])));
        }
        if (!is_array($atts['category'])) {
            $atts['category'] = array_filter(array_map('trim', explode(',', $atts['category'])));
        }
        if (!is_array($atts['make'])) {
            $atts['make'] = array_filter(array_map('trim', explode(',', $atts['make'])));
        }

        // Filter attributes become locked constraints.
        $locked_raw = [
            'category'  => $atts['category'],
            'condition' => $atts['condition'],
            'status'    => $atts['status'],
            'location'  => $atts['location'],
            'make'      => $atts['make'],
        ];

        if (trim((string) $atts['promotion']) !== '') {
            $locked_raw['promotion'] = array_filter(
                array_map('trim', explode(',', $atts['promotion'])),
                'strlen'
            );
        }

        // Validate keys against the lockable map and sanitize slugs.
        $locked = AJAX_Handlers::sanitize_locked_constraints($locked_raw);

        // Merge the lock attribute into the attribute constraints.
        foreach (self::parse_lock_attribute($atts['lock']) as $lock_key => $lock_terms) {
            $locked[$lock_key] = isset($locked[$lock_key])
                ? array_values(array_unique(array_merge($locked[$lock_key], $lock_terms)))
                : $lock_terms;
        }

        // $filters tells templates/filters.php which widgets are already applied
        // (locked keys that have a sidebar widget). $count_filters holds every
        // locked constraint, keyed by taxonomy, to scope the term counts.
        $filters = [
            'location'         => false,
            'condition'        => false,
            'boat_status'      => false,
            'year'             => false,
            'boat_type'        => false,
            'model'            => false,
            'make'             => false,
            'price_range'      => false,
            'length_range'     => false,
            'horsepower_range' => false,
            'person_capacity'  => false,
        ];

        $filter_map    = AJAX_Handlers::get_filter_taxonomy_map();
        $lockable_map  = AJAX_Handlers::get_lockable_taxonomy_map();
        $count_filters = $filters;

        foreach ($locked as $lock_key => $lock_terms) {
            $count_filters[$lockable_map[$lock_key]] = $lock_terms;

            if (isset($filter_map[$lock_key])) {
                $filters[self::map_lock_key_to_filters_key($lock_key)] = $lock_terms;
            }
        }

        // Notice for ?notfound=1 redirects from removed boats.
        $not_found_message = '';
        if (isset($_GET['notfound']) && $_GET['notfound'] === '1') {
            $not_found_message = '<div class="dealerschoice-notification error" style="background: #fff3f3; border-left: 4px solid #d63638; padding: 15px; margin-bottom: 20px;">
                <p style="margin: 0; color: #d63638;"><strong>Notice:</strong> The boat you are looking for is no longer available. However, we have found similar boats you might be interested in below.</p>
            </div>';
        }

        // Filter term counts, scoped by the locked constraints.
        if (class_exists('\DC\Inventory')) {
            $locations = \DC\Inventory::getTaxonomyValuesForLocation($count_filters);
            $conditions = \DC\Inventory::getTaxonomyValuesForCondition($count_filters);
            $statuses = \DC\Inventory::getTaxonomyValuesForStatus($count_filters);
            $years = \DC\Inventory::getTaxonomyValuesForYear($count_filters);
            $categories = \DC\Inventory::getTaxonomyValuesForBoatType($count_filters);
            $makes = \DC\Inventory::getTaxonomyValuesForMake($count_filters);
            $models = \DC\Inventory::getTaxonomyValuesForModel($count_filters);
            $priceRanges = \DC\Inventory::getTaxonomyValuesForPriceRange($count_filters);
            $lengths = \DC\Inventory::getTaxonomyValuesForLength($count_filters);
            $horsepowers = \DC\Inventory::getTaxonomyValuesForHorsepower($count_filters);
            $capacities = \DC\Inventory::getTaxonomyValuesForPersonCapacity($count_filters);
        } else {
            $locations = $conditions = $years = $categories = $makes = $models = [];
            $priceRanges = $lengths = $horsepowers = $capacities = [];
        }

        // Server-render the first page of results, pagination and ItemList schema.
        // The JS skips its first AJAX request when its state matches data-ssr-state.
        $initial = self::get_initial_inventory_state($atts, $locked);
        $initial_render = AJAX_Handlers::render_inventory_results(
            $initial['filters'],
            $initial['query'],
            $initial['sortBy'],
            $initial['page'],
            AJAX_Handlers::get_posts_per_page($atts['posts_per_page']),
            $locked,
            $initial['pageUrl']
        );

        $wrapper_atts = ' data-posts-per-page="' . absint($atts['posts_per_page']) . '"';

        $wrapper_atts .= ' data-ssr-state="' . esc_attr(wp_json_encode([
            'filters'      => (object) $initial['filters'],
            'page'         => $initial['page'],
            'sortBy'       => $initial['sortBy'],
            'query'        => $initial['query'],
            'postsPerPage' => absint($atts['posts_per_page']),
        ])) . '"';

        if (!empty($locked)) {
            $wrapper_atts .= ' data-locked="' . esc_attr(wp_json_encode($locked)) . '"';
        }

        // With a locked location, give the JS the main inventory page URL to send
        // visitors to when they pick another location, unless this is that page.
        if (isset($locked['location']) && function_exists('dealers_choice_get_inventory_page_url')) {
            $handoff      = dealers_choice_get_inventory_page_url();
            $current_page = get_permalink();

            $handoff_path = $handoff ? untrailingslashit((string) wp_parse_url($handoff, PHP_URL_PATH)) : '';
            $current_path = $current_page ? untrailingslashit((string) wp_parse_url($current_page, PHP_URL_PATH)) : '';

            if ($handoff_path !== '' && $handoff_path !== $current_path) {
                $wrapper_atts .= ' data-location-switch-url="' . esc_url($handoff) . '"';
            }
        }
        ?>
        <div class="dealerschoice-shortcode dealerschoice-inventory-shortcode">
            <div id="inventory-wrapper" class="dealerschoice-inventory-wrapper"<?php echo $wrapper_atts; ?>>
                <?php echo $not_found_message; ?>
                <?php
                // Visible "Showing: ..." text for the locked constraints.
                if (!empty($locked) && filter_var($atts['lock_notice'], FILTER_VALIDATE_BOOLEAN)) {
                    $lock_labels = self::get_lock_labels($locked);

                    if (!empty($lock_labels)) {
                        printf(
                            '<p class="dealerschoice-lock-notice">%s <strong>%s</strong></p>',
                            esc_html__('Showing:', 'dealerschoice'),
                            esc_html(implode(', ', $lock_labels))
                        );
                    }
                }
                ?>
                <div class="dealerschoice-layout">

                    <?php if ($atts['show_filters']): ?>
                    <div id="mobile-filter-toggle" class="mobile-filter-toggle">
                        <button type="button" aria-label="Toggle Filters">
                            <i class="fa-light fa-filter"></i>
                            Filters
                            <i class="fa-light fa-angle-down"></i>
                        </button>
                    </div>

                    <aside id="inventory-filters" class="dealerschoice-filters">
                        <div id="mobile-filter-close" class="mobile-filter-close">
                            <button type="button" aria-label="Close Filters">
                                <i class="fa-light fa-xmark"></i>
                            </button>
                        </div>

                        <?php
                        Template_Loader::get_template('filters.php', [
                            'filters' => $filters,
                            'locations' => $locations,
                            'conditions' => $conditions,
                            'statuses' => $statuses,
                            'years' => $years,
                            'categories' => $categories,
                            'makes' => $makes,
                            'models' => $models,
                            'priceRanges' => $priceRanges,
                            'lengths' => $lengths,
                            'horsepowers' => $horsepowers,
                            'capacities' => $capacities,
                        ]);
                        ?>
                    </aside>
                    <?php endif; ?>

                    <div id="inventory-list" class="dealerschoice-results">

                        <?php if ($atts['show_search'] || $atts['show_sort']): ?>
                        <div class="search-sort-wrapper">
                            <div class="search-sort-inner">
                                <?php if ($atts['show_search']): ?>
                                <div class="inventory-search">
                                    <form id="inventory-search" role="search">
                                        <label for="q" class="screen-reader-text">Search Inventory</label>
                                        <input type="search" id="q" name="q" placeholder="Search by keyword..." value="<?php echo esc_attr( $_GET['q'] ?? '' ); ?>" />
                                    </form>
                                </div>
                                <?php endif; ?>

                                <?php if ($atts['show_sort']): ?>
                                <?php $default_sort = get_option('dealers_choice_default_sort', 'date-desc'); ?>
                                <div class="inventory-sort">
                                    <label for="inventory-sort">Sort by:</label>
                                    <select id="inventory-sort" name="sort">
                                        <option value="date-desc"<?php selected($default_sort, 'date-desc'); ?>>Newest First</option>
                                        <option value="date-asc"<?php selected($default_sort, 'date-asc'); ?>>Oldest First</option>
                                        <option value="price-asc"<?php selected($default_sort, 'price-asc'); ?>>Price: Low to High</option>
                                        <option value="price-desc"<?php selected($default_sort, 'price-desc'); ?>>Price: High to Low</option>
                                        <option value="year-desc"<?php selected($default_sort, 'year-desc'); ?>>Year: Newest</option>
                                        <option value="year-asc"<?php selected($default_sort, 'year-asc'); ?>>Year: Oldest</option>
                                        <option value="length-desc"<?php selected($default_sort, 'length-desc'); ?>>Length: Longest</option>
                                        <option value="length-asc"<?php selected($default_sort, 'length-asc'); ?>>Length: Shortest</option>
                                        <option value="title-asc"<?php selected($default_sort, 'title-asc'); ?>>Title: A-Z</option>
                                        <option value="title-desc"<?php selected($default_sort, 'title-desc'); ?>>Title: Z-A</option>
                                    </select>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Live region announcing AJAX result updates; empty on initial load. -->
                        <div id="inventory-results-status" class="dc-screen-reader-text" role="status" aria-live="polite"></div>

                        <div id="inventory-results" class="inventory-results">
                            <?php echo $initial_render['results']; ?>
                        </div>

                        <div class="pagination-wrapper"><?php echo $initial_render['pagination']; ?></div>

                        <?php if (isset($locked['location'])): ?>
                        <!-- Shop Other Locations results, filled by AJAX on the last page of filtered results. -->
                        <div class="dealerschoice-other-locations-wrapper"></div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if (!empty($initial_render['schema'])): ?>
                <script type="application/ld+json" id="dealerschoice-itemlist-schema"><?php echo wp_json_encode($initial_render['schema'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?></script>
                <?php endif; ?>
            </div>
        </div>
        <?php

        return ob_get_clean();
    }

    /**
     * Builds the listing's initial state from the request, matching the JS init block.
     *
     * Reads filters from ?location, condition, status, category, make, model, price,
     * length, horsepower (scalar or key[]) and ?boat_year (as 'year'), skipping locked
     * keys; ?inventory_page; ?q when search is shown; and the default sort when the
     * sort dropdown is shown. Also builds the canonical page URL from that state.
     *
     * @param array $atts   Parsed shortcode attributes.
     * @param array $locked Validated locked constraints.
     * @return array{filters:array,page:int,sortBy:string,query:string,pageUrl:string}
     */
    private static function get_initial_inventory_state($atts, $locked) {
        $url_params = [
            'location'   => 'location',
            'condition'  => 'condition',
            'status'     => 'status',
            'category'   => 'category',
            'make'       => 'make',
            'model'      => 'model',
            'price'      => 'price',
            'length'     => 'length',
            'horsepower' => 'horsepower',
            'boat_year'  => 'year',
        ];

        $raw_filters = [];
        foreach ($url_params as $param => $filter_key) {
            if (!isset($_GET[$param]) || isset($locked[$filter_key])) {
                continue;
            }
            $raw_filters[$filter_key] = (array) $_GET[$param];
        }

        // Allowlist keys and sanitize slugs.
        $filters = AJAX_Handlers::sanitize_filters($raw_filters);
        unset($filters['id']);

        $page = isset($_GET['inventory_page']) ? max(1, absint($_GET['inventory_page'])) : 1;

        $query = '';
        if ($atts['show_search'] && isset($_GET['q']) && !is_array($_GET['q'])) {
            $query = mb_substr(sanitize_text_field(wp_unslash($_GET['q'])), 0, 100);
        }

        $sort_by = $atts['show_sort']
            ? get_option('dealers_choice_default_sort', 'date-desc')
            : 'date-desc';

        // Listing URL for pagination hrefs and the ItemList 'url', rebuilt from the
        // sanitized state. Multiple values use key[]=.
        $page_url = is_singular() ? (string) get_permalink(get_queried_object_id()) : '';

        if ($page_url !== '') {
            $pairs = [];
            foreach ($url_params as $param => $filter_key) {
                if (empty($filters[$filter_key])) {
                    continue;
                }
                $name = count($filters[$filter_key]) > 1 ? $param . '[]' : $param;
                foreach ($filters[$filter_key] as $value) {
                    $pairs[] = rawurlencode($name) . '=' . rawurlencode($value);
                }
            }
            if ($query !== '') {
                $pairs[] = 'q=' . rawurlencode($query);
            }
            if ($page > 1) {
                $pairs[] = 'inventory_page=' . $page;
            }
            if (!empty($pairs)) {
                $page_url .= (strpos($page_url, '?') === false ? '?' : '&') . implode('&', $pairs);
            }
        }

        return [
            'filters' => $filters,
            'page'    => $page,
            'sortBy'  => $sort_by,
            'query'   => $query,
            'pageUrl' => $page_url,
        ];
    }

    /**
     * Parses a 'lock' or 'tax' shortcode attribute into validated constraints.
     *
     * Syntax: "key:slug" pairs separated by "|", with multiple slugs for one key
     * separated by ",", e.g. lock="promotion:featured,clearance|location:main".
     * Keys not in AJAX_Handlers::get_lockable_taxonomy_map() are dropped and the
     * result is passed through AJAX_Handlers::sanitize_locked_constraints().
     *
     * @param string $lock Raw attribute value.
     * @return array<string,array<int,string>> Term slugs keyed by lock key.
     */
    public static function parse_lock_attribute($lock) {
        if (!is_string($lock) || trim($lock) === '') {
            return [];
        }

        $parsed = [];

        foreach (explode('|', $lock) as $pair) {
            if (strpos($pair, ':') === false) {
                continue;
            }

            list($key, $terms) = explode(':', $pair, 2);

            $key   = sanitize_key(trim($key));
            $terms = array_filter(array_map('trim', explode(',', $terms)), 'strlen');

            if ($key === '' || empty($terms)) {
                continue;
            }

            $parsed[$key] = isset($parsed[$key])
                ? array_merge($parsed[$key], $terms)
                : $terms;
        }

        return AJAX_Handlers::sanitize_locked_constraints($parsed);
    }

    /**
     * Maps a lock/filter key to the key templates/filters.php expects.
     *
     * Keys not in the map are returned unchanged.
     *
     * @param string $lock_key Lock or filter key (e.g. 'status', 'category').
     * @return string Filters template key (e.g. 'boat_status', 'boat_type').
     */
    public static function map_lock_key_to_filters_key($lock_key) {
        $map = [
            'status'     => 'boat_status',
            'category'   => 'boat_type',
            'price'      => 'price_range',
            'length'     => 'length_range',
            'horsepower' => 'horsepower_range',
            'capacity'   => 'person_capacity',
        ];

        return isset($map[$lock_key]) ? $map[$lock_key] : $lock_key;
    }

    /**
     * Resolves locked constraints to term names for display.
     *
     * Uses the raw slug when no matching term exists.
     *
     * @param array $locked Validated constraints.
     * @return array<int,string> Term names.
     */
    public static function get_lock_labels($locked) {
        $map    = AJAX_Handlers::get_lockable_taxonomy_map();
        $labels = [];

        foreach ($locked as $lock_key => $terms) {
            if (!isset($map[$lock_key])) {
                continue;
            }

            foreach ($terms as $slug) {
                $term = get_term_by('slug', $slug, $map[$lock_key]);
                $labels[] = ($term && !is_wp_error($term)) ? $term->name : $slug;
            }
        }

        return $labels;
    }

    /**
     * Renders [dealerschoice_slider]: a Slick carousel of boats.
     *
     * Attributes: limit, category, condition, location, make, year, promotion,
     * tax ("key:slug" syntax as in lock), orderby (date, price, year, length, rand),
     * order, slides_to_show, autoplay, autoplay_speed.
     *
     * @param array|string $atts Shortcode attributes.
     * @return string HTML output, or '' when no boats match.
     */
    public static function slider_shortcode($atts) {
        $atts = shortcode_atts([
            'limit' => 6,
            'category' => '',
            'condition' => '',
            'location' => '',
            'make' => '',
            'year' => '',
            'promotion' => '',
            'tax' => '',
            'orderby' => 'date',
            'order' => 'DESC',
            'slides_to_show' => 3,
            'autoplay' => false,
            'autoplay_speed' => 3000,
        ], $atts, 'dealerschoice_slider');

        $args = [
            'post_type' => 'boat',
            'post_status' => 'publish',
            'posts_per_page' => absint($atts['limit']),
            'order' => $atts['order'],
        ];

        switch ($atts['orderby']) {
            case 'price':
                $args['orderby'] = 'meta_value_num';
                $args['meta_key'] = 'boat_saleprice';
                break;
            case 'year':
                $args['orderby'] = 'meta_value_num';
                $args['meta_key'] = 'boat_year';
                break;
            case 'length':
                $args['orderby'] = 'meta_value_num';
                $args['meta_key'] = 'boat_length_inches';
                break;
            case 'rand':
                $args['orderby'] = 'rand';
                break;
            default:
                $args['orderby'] = 'date';
        }

        $tax_query = ['relation' => 'AND'];

        if (!empty($atts['category'])) {
            $tax_query[] = [
                'taxonomy' => 'boat_type',
                'field' => 'slug',
                'terms' => $atts['category']
            ];
        }

        if (!empty($atts['condition'])) {
            $tax_query[] = [
                'taxonomy' => 'condition',
                'field' => 'slug',
                'terms' => $atts['condition']
            ];
        }

        if (!empty($atts['location'])) {
            $tax_query[] = [
                'taxonomy' => 'location',
                'field' => 'slug',
                'terms' => $atts['location']
            ];
        }

        if (!empty($atts['make'])) {
            $tax_query[] = [
                'taxonomy' => 'make',
                'field' => 'slug',
                'terms' => $atts['make']
            ];
        }

        if (!empty($atts['year'])) {
            $tax_query[] = [
                'taxonomy' => 'boat_year',
                'field' => 'slug',
                'terms' => $atts['year']
            ];
        }

        // Constraints from the 'tax' and 'promotion' attributes.
        $taxonomy_map = AJAX_Handlers::get_lockable_taxonomy_map();

        $slider_constraints = self::parse_lock_attribute($atts['tax']);

        foreach (self::parse_lock_attribute('promotion:' . $atts['promotion']) as $tax_key => $tax_terms) {
            $slider_constraints[$tax_key] = isset($slider_constraints[$tax_key])
                ? array_values(array_unique(array_merge($slider_constraints[$tax_key], $tax_terms)))
                : $tax_terms;
        }

        foreach ($slider_constraints as $tax_key => $tax_terms) {
            if (!isset($taxonomy_map[$tax_key])) {
                continue;
            }

            $tax_query[] = [
                'taxonomy' => $taxonomy_map[$tax_key],
                'field'    => 'slug',
                'terms'    => $tax_terms,
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

        $query = new \WP_Query($args);

        if (!$query->have_posts()) {
            return '';
        }

        wp_enqueue_style('dealerschoice-public');
        wp_enqueue_style('dealerschoice-slick');
        wp_enqueue_script('dealerschoice-slider');
        wp_enqueue_script('dealerschoice-slick');
        ob_start();
        $slider_id = 'dealerschoice-boat-slider-'.uniqid();

        // Slick settings for data-slick; autoplaySpeed only when autoplay is on.
        $slick_settings = [
            'slidesToShow' => absint($atts['slides_to_show']),
        ];

        if (filter_var($atts['autoplay'], FILTER_VALIDATE_BOOLEAN)) {
            $slick_settings['autoplay'] = true;
            $slick_settings['autoplaySpeed'] = absint($atts['autoplay_speed']);
        }
        ?>
        <div class="dealerschoice-shortcode dealerschoice-slider dc-mb">
            <div class="boat-slider" id="<?php echo esc_html($slider_id); ?>" data-slick="<?php echo esc_attr(wp_json_encode($slick_settings)); ?>">
                <?php while ($query->have_posts()): $query->the_post(); ?>
                    <?php Template_Loader::get_template_part('inventory', 'slide'); ?>
                <?php endwhile; ?>
            </div>
            <div class="button-wrapper" id="<?php echo esc_html($slider_id); ?>-buttons"></div>
        </div>
        <?php
        wp_reset_postdata();

        return ob_get_clean();
    }

    /**
     * Renders [dealerschoice_filters]: the filter sidebar on its own.
     *
     * @param array|string $atts Shortcode attributes (unused).
     * @return string HTML output.
     */
    public static function filters_shortcode($atts) {
        wp_enqueue_style('dealerschoice-public');
        wp_enqueue_script('dealerschoice-public');

        $filters = [
            'location' => false,
            'condition' => false,
            'year' => false,
            'boat_type' => false,
            'model' => false,
            'make' => false,
            'price_range' => false,
            'length_range' => false,
            'horsepower_range' => false,
            'person_capacity' => false,
        ];

        if (class_exists('\DC\Inventory')) {
            $locations = \DC\Inventory::getTaxonomyValuesForLocation($filters);
            $conditions = \DC\Inventory::getTaxonomyValuesForCondition($filters);
            $years = \DC\Inventory::getTaxonomyValuesForYear($filters);
            $categories = \DC\Inventory::getTaxonomyValuesForBoatType($filters);
            $makes = \DC\Inventory::getTaxonomyValuesForMake($filters);
            $models = \DC\Inventory::getTaxonomyValuesForModel($filters);
            $priceRanges = \DC\Inventory::getTaxonomyValuesForPriceRange($filters);
            $lengths = \DC\Inventory::getTaxonomyValuesForLength($filters);
            $horsepowers = \DC\Inventory::getTaxonomyValuesForHorsepower($filters);
            $capacities = \DC\Inventory::getTaxonomyValuesForPersonCapacity($filters);
        } else {
            return '<p>Inventory plugin not available.</p>';
        }

        ob_start();
        ?>
        <div class="dealerschoice-shortcode dealerschoice-filters-shortcode">
            <aside id="inventory-filters" class="dealerschoice-filters">
                <?php
                Template_Loader::get_template('filters.php', [
                    'filters' => $filters,
                    'locations' => $locations,
                    'conditions' => $conditions,
                    'years' => $years,
                    'categories' => $categories,
                    'makes' => $makes,
                    'models' => $models,
                    'priceRanges' => $priceRanges,
                    'lengths' => $lengths,
                    'horsepowers' => $horsepowers,
                    'capacities' => $capacities,
                ]);
                ?>
            </aside>
        </div>
        <?php

        return ob_get_clean();
    }

    /**
     * Renders [dealerschoice_location_list]: location buttons for the header location selector.
     *
     * @return string HTML output.
     */
    public static function location_list_shortcode() {
        wp_enqueue_script('dealerschoice-location-selector');

        $locations = get_terms([
            'taxonomy' => 'location',
            'hide_empty' => true,
        ]);

        ob_start();
        $html = '';

        if (empty($locations) || is_wp_error($locations)) {
            $html = '<p>No locations available.</p>';
        }

        $html = '<ul class="dc-location-selector-list">';
        $html .= '<li><button type="button" class="dc-location-btn" data-slug="all" data-name="Select Location">All Locations</button></li>';
        
        foreach ($locations as $location) {
            $html .= sprintf(
                '<li><button type="button" class="dc-location-btn" data-slug="%s" data-name="%s">%s</button></li>',
                esc_attr($location->slug),
                esc_attr($location->name),
                esc_html($location->name)
            );
        }
        
        $html .= '</ul>';

        echo $html;
        return ob_get_clean();
    }

    /**
     * Renders [dealerschoice_boat_quiz]: the step-by-step boat finder quiz.
     *
     * Attributes:
     * - title           (string) Quiz heading.
     * - subtitle        (string) Sub-heading.
     * - submit_label    (string) Label on the final submit button.
     * - gravity_form_id (int)    Gravity Form shown with the results. Default 0 (none).
     * - boat_count      (int)    Matching boats in the result slider, 1 to 12. Default 3.
     *
     * @param array|string $atts Shortcode attributes.
     * @return string HTML output.
     */
    public static function boat_quiz_shortcode( $atts ) {
        $atts = shortcode_atts(
            [
                'title'           => __( 'Find Your Perfect Boat', 'dealerschoice' ),
                'subtitle'        => __( 'Answer a few quick questions and we\'ll match you with the right vessel for the way you boat.', 'dealerschoice' ),
                'submit_label'    => __( 'Find My Perfect Boat', 'dealerschoice' ),
                'gravity_form_id' => 0,
                'boat_count'      => 3,
            ],
            $atts,
            'dealerschoice_boat_quiz'
        );

        wp_enqueue_style( 'dealerschoice-public' );
        wp_enqueue_style( 'dealerschoice-slick' );
        wp_enqueue_script( 'dealerschoice-slick' );
        wp_enqueue_script( 'dealerschoice-slider' );
        wp_enqueue_script( 'dealerschoice-boat-quiz' );

        $gravity_form_id = absint( $atts['gravity_form_id'] );
        $boat_count      = min( 12, max( 1, absint( $atts['boat_count'] ) ) );

        // Enqueue Gravity Forms assets for the form injected with the AJAX results.
        if ( $gravity_form_id > 0 && function_exists( 'gravity_form_enqueue_scripts' ) ) {
            gravity_form_enqueue_scripts( $gravity_form_id, true );
        }

        $questions   = BoatQuiz::get_questions();
        $total_steps = count( $questions );
        $nonce       = wp_create_nonce( 'dealerschoice_quiz_nonce' );

        $title        = sanitize_text_field( $atts['title'] );
        $subtitle     = sanitize_text_field( $atts['subtitle'] );
        $submit_label = sanitize_text_field( $atts['submit_label'] );

        ob_start();
        Template_Loader::get_template(
            'shortcodes/boat-quiz.php',
            compact( 'title', 'subtitle', 'submit_label', 'questions', 'total_steps', 'nonce', 'gravity_form_id', 'boat_count' )
        );
        return ob_get_clean();
    }

    /**
     * Renders [dealerschoice_finance_calculator]: a client-side loan payment calculator.
     *
     * Amount, rate, term and down payment are editable. Shows a loan summary and a
     * collapsible amortization schedule.
     *
     * Attributes:
     * - title          (string) Heading above the form. Default 'Estimate Your Payment'.
     * - default_amount (number) Pre-filled amount financed. Default empty.
     *
     * @param array|string $atts Shortcode attributes.
     * @return string HTML output.
     */
    public static function finance_calculator_shortcode( $atts ) {
        $atts = shortcode_atts(
            [
                'title'          => __( 'Estimate Your Payment', 'dealerschoice' ),
                'default_amount' => '',
            ],
            $atts,
            'dealerschoice_finance_calculator'
        );

        wp_enqueue_style( 'dealerschoice-public' );
        wp_enqueue_style( 'dealerschoice-finance-calculator' );
        wp_enqueue_script( 'dealerschoice-finance-calculator' );

        $title            = sanitize_text_field( $atts['title'] );
        $default_amount   = is_numeric( $atts['default_amount'] ) ? (float) $atts['default_amount'] : '';
        $default_rate     = self::get_default_finance_rate();
        $default_term     = self::get_default_finance_term();
        $down_payment_pct = self::get_default_finance_down_payment_percent();
        $term_options     = self::get_finance_term_options();
        $disclaimer       = self::get_finance_calculator_disclaimer();
        $instance_id      = 'dc-finance-calc-' . wp_unique_id();

        // Default down payment only when an amount is set; otherwise the JS computes it.
        $default_down_payment = ( $default_amount !== '' )
            ? round( $default_amount * $down_payment_pct / 100, 2 )
            : '';

        ob_start();
        Template_Loader::get_template(
            'shortcodes/finance-calculator.php',
            compact( 'title', 'default_amount', 'default_rate', 'default_term', 'down_payment_pct', 'default_down_payment', 'term_options', 'disclaimer', 'instance_id' )
        );
        return ob_get_clean();
    }

    /**
     * Renders the quick finance calculator for a boat on the single boat page.
     *
     * Renders only when the 'dealers_choice_show_finance_calculator' option is on,
     * the boat has no dealer-supplied financing data, and its price is visible.
     *
     * @param \DC\Boat $boat Boat being displayed.
     * @return string HTML output, or '' when not shown.
     */
    public static function render_quick_finance_calculator( \DC\Boat $boat ) {
        if ( get_option( 'dealers_choice_show_finance_calculator', '0' ) !== '1' ) {
            return '';
        }

        if ( $boat->hasFinancingData() ) {
            return '';
        }

        if ( ! $boat->isPriceVisible() ) {
            return '';
        }

        $price = (float) $boat->getSaleprice();

        wp_enqueue_style( 'dealerschoice-public' );
        wp_enqueue_style( 'dealerschoice-finance-calculator' );
        wp_enqueue_script( 'dealerschoice-finance-calculator' );

        $default_rate          = self::get_default_finance_rate();
        $default_term          = self::get_default_finance_term();
        $down_payment_pct      = self::get_default_finance_down_payment_percent();
        $term_options          = self::get_finance_term_options();
        $disclaimer            = self::get_finance_calculator_disclaimer();
        $instance_id           = 'dc-finance-calc-quick-' . $boat->getPostID();
        $default_down_payment  = round( $price * $down_payment_pct / 100, 2 );

        ob_start();
        Template_Loader::get_template(
            'shortcodes/finance-calculator-quick.php',
            compact( 'price', 'default_rate', 'default_term', 'down_payment_pct', 'default_down_payment', 'term_options', 'disclaimer', 'instance_id' )
        );
        return ob_get_clean();
    }

    /**
     * Returns the default APR (%) from Settings.
     *
     * @return float APR percentage.
     */
    public static function get_default_finance_rate() {
        return (float) get_option( 'dealers_choice_finance_default_rate', 7.99 );
    }

    /**
     * Returns the default loan term from Settings.
     *
     * @return int Term in months.
     */
    public static function get_default_finance_term() {
        return (int) get_option( 'dealers_choice_finance_default_term', 240 );
    }

    /**
     * Returns the default down payment percentage from Settings.
     *
     * @return float Percentage of the amount financed.
     */
    public static function get_default_finance_down_payment_percent() {
        return (float) get_option( 'dealers_choice_finance_default_down_payment_percent', 20 );
    }

    /**
     * Returns the finance calculator disclaimer from Settings, or the default text.
     *
     * @return string Disclaimer; may contain basic HTML.
     */
    public static function get_finance_calculator_disclaimer() {
        return get_option( 'dealers_choice_finance_calculator_disclaimer', self::get_default_finance_disclaimer() );
    }

    /**
     * Returns the default finance calculator disclaimer text.
     *
     * @return string Translated disclaimer.
     */
    public static function get_default_finance_disclaimer() {
        return __(
            'This calculator provides an estimate for informational purposes only and is not an offer of credit. Your actual payment may vary based on several factors such as down payment, credit history, final price, available promotional programs and incentives. Applicable tag, title, destination charges, taxes and other fees and incentives are not included in this estimate. Contact us for your actual rate and payment terms.',
            'dealerschoice'
        );
    }

    /**
     * Returns the loan term presets used by the calculators and the Settings dropdown.
     *
     * @return array<int,string> Labels keyed by term in months.
     */
    public static function get_finance_term_options() {
        return [
            60  => __( '60 months (5 years)', 'dealerschoice' ),
            84  => __( '84 months (7 years)', 'dealerschoice' ),
            120 => __( '120 months (10 years)', 'dealerschoice' ),
            144 => __( '144 months (12 years)', 'dealerschoice' ),
            180 => __( '180 months (15 years)', 'dealerschoice' ),
            240 => __( '240 months (20 years)', 'dealerschoice' ),
        ];
    }

}
