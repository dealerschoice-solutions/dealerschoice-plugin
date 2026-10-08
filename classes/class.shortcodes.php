<?php
/**
 * Shortcodes Handler
 * 
 * Registers and handles all plugin shortcodes for embedding inventory functionality
 * into posts, pages, and widgets.
 * 
 * @package DealersChoice
 * @subpackage Classes
 * @since 1.0.0
 * 
 * Available Shortcodes:
 * 
 * [dealerschoice_inventory]
 * Displays the full inventory listing with filters
 * Attributes:
 * - posts_per_page: Number of boats per page (default: 12)
 * - show_filters: Show filter sidebar (default: true)
 * - show_search: Show search box (default: true)
 * - show_sort: Show sort dropdown (default: true)
 * - category: Constrain to one or more boat_type slugs, comma separated
 * - condition: Constrain to one or more condition slugs, comma separated
 * - status: Constrain to one or more boat_status slugs, comma separated
 * - location: Constrain to one or more location slugs, comma separated
 * - make: Constrain to one or more make slugs, comma separated
 * - promotion: Constrain to one or more dc_promotions slugs, comma separated:
 *           promotion="clearance"
 *           promotion="featured,clearance"
 *         Term slugs are whatever the dealer created under Inventory >
 *         Promotions; the plugin ships no vocabulary of its own. Unlike the
 *         attributes above, promotions never render as a filter widget.
 * - lock: Constrain a taxonomy that has no dedicated attribute above, as
 *         "key:slug" pairs separated by "|" (slugs within a key separated
 *         by ","):
 *           lock="price:50000-100000"
 *           lock="year:2026|length:20-24"
 *         Keys come from AJAX_Handlers::get_lockable_taxonomy_map(). Setting a
 *         key that also has its own attribute is allowed and merges with it.
 * - lock_notice: Show the "Showing: ..." text for the active constraints
 *         (default: false). Set lock_notice="true" on the rare page where the
 *         constraint isn't already obvious from the page title or heading, so
 *         visitors aren't left wondering why the list is short.
 *
 * ALL of the constraint attributes above are enforced server-side on every
 * AJAX request, so they survive "Clear All Filters", pagination, sorting, the
 * header location selector, URL parameters and show_filters="false". Any
 * constrained taxonomy has its sidebar widget hidden, since there is nothing
 * left for the visitor to change.
 *
 * For a filter the visitor SHOULD be able to change, do not set an attribute.
 * Link to the page with a URL parameter instead (?condition=new), which
 * pre-checks the filter while leaving the widget visible and adjustable.
 *
 * [dealerschoice_slider]
 * Displays a horizontal slider/carousel of boats
 * Attributes:
 * - limit: Number of boats to show (default: 6)
 * - category: Filter by category slug
 * - condition: Filter by condition slug
 * - location: Filter by location slug
 * - promotion: Scope the slider to one or more dc_promotions term slugs,
 *        comma separated, e.g. promotion="featured"
 * - tax: Additional taxonomy constraint, same "key:slug" syntax as 'lock'
 *        above, e.g. tax="condition:new"
 * - orderby: Sort by (date, price, year, length, rand) (default: date)
 * - order: Sort direction (ASC, DESC) (default: DESC)
 * - slides_to_show: Number of slides visible at once (default: 3)
 * - autoplay: Automatically advance slides (default: false)
 * - autoplay_speed: Milliseconds between auto-advances, only used when
 *        autoplay is enabled (default: 3000)
 *
 * [dealerschoice_filters]
 * Displays just the filter sidebar (useful for custom layouts)
 * 
 * [dealerschoice_favorites]
 * Displays the user's favorite boats using localStorage and AJAX.
 * 
 * [dealerschoice_location_list]
 * Displays the list of locations as buttons for the location selector popup in the header.
 * 
 * [dealerschoice_boat_quiz]
 * Displays the step-by-step boat finder quiz.
 * Attributes:
 * - title: Quiz heading (default: 'Find Your Perfect Boat')
 * - subtitle: Sub-heading (default: descriptive tagline)
 * - submit_label: Label on the final submit button (default: 'Find My Perfect Boat')
 *
 * [dealerschoice_finance_calculator]
 * Displays the generic client-side loan payment calculator, with a full
 * monetization summary and an expandable amortization schedule.
 * Attributes:
 * - title: Heading above the form (default: 'Estimate Your Payment')
 * - default_amount: Pre-filled amount financed (default: '', blank)
 *
 * The "quick" calculator shown automatically on single-boat.php is rendered
 * via DC\Shortcodes::render_quick_finance_calculator() and is not a
 * registered shortcode (it isn't user-insertable).
 *
 * Dependencies:
 * - WordPress shortcode API
 * - DC\Template_Loader
 * - DC\Inventory class
 */

namespace DC;

if (!defined('ABSPATH')) {
    exit;
}

class Shortcodes {
    
    /**
     * Initialize shortcodes
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
     * Enqueue the Reveal Price script for any shortcode that renders inventory cards.
     *
     * Only needed when prices are gated. Enqueuing from the shortcode (rather than from
     * wp_enqueue_scripts) means the script follows the markup, so inventory rendered from
     * a block, widget, or page-builder template gets a working handler too. The script is
     * registered with $in_footer = true, so a shortcode-time enqueue during the_content
     * still prints, and wp_localize_script still applies.
     */
    public static function enqueue_reveal_price_assets() {
        if (get_option('dealers_choice_always_show_price', '0') === '1') {
            return;
        }
        wp_enqueue_script('dealerschoice-reveal-price');
        wp_localize_script('dealerschoice-reveal-price', 'revealPriceSettings', dealers_choice_reveal_price_settings());
    }

        /**
         * Favorites Shortcode
         *
         * [dealerschoice_favorites]
         *
         * Displays the user's favorite boats using localStorage and AJAX.
         */
        public static function favorites_shortcode($atts) {
            // Enqueue public styles and favorites JS
            wp_enqueue_style('dealerschoice-public');
            wp_enqueue_script('dealerschoice-favorites');
            wp_enqueue_script('dealerschoice-public');
            // Favorites renders the same inventory-block.php cards, so it needs the
            // Reveal Price handler too or its "Reveal Price" buttons do nothing.
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
     * Full inventory listing shortcode
     *
     * [dealerschoice_inventory posts_per_page="12" category="pontoon"]
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

        // Support 'categories' attribute as alias for 'category'
        if (empty($atts['category']) && !empty($atts['categories'])) {
            $atts['category'] = $atts['categories'];
        }

        // Start output buffering
        ob_start();

        // Enqueue assets
        wp_enqueue_style('dealerschoice-public');
        wp_enqueue_script('dealerschoice-public');
        wp_enqueue_script('dealerschoice-favorites');
        self::enqueue_reveal_price_assets();

        // Ensure that location, condition, category, and make are arrays;
        // single values and comma-separated strings are converted to arrays
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

        // Every filter attribute on this shortcode is a hard constraint, not a
        // changeable default. An author writing category="bowrider" means "this
        // page shows bowriders", which is also why templates/filters.php hides
        // the corresponding widget: nobody hides the control for a value the
        // visitor is meant to change.
        //
        // Historically these attributes only pre-checked sidebar checkboxes,
        // leaving the constraint in client-side state where "Clear All Filters",
        // the header location selector and URL parameters could each escape it.
        // That was a consequence of the results being fully AJAX-driven rather
        // than a design decision, and it also disagreed with
        // slider_shortcode(), which has always built its tax_query server-side
        // from the same attribute names.
        //
        // For a filter the visitor SHOULD be able to change, link to the page
        // with a URL parameter (?condition=new) instead of setting an attribute.
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

        // Validates keys against the lockable map, drops unknown ones and
        // forces every slug through sanitize_title().
        $locked = AJAX_Handlers::sanitize_locked_constraints($locked_raw);

        // lock="" remains supported for the taxonomies with no dedicated
        // attribute (price, length, capacity, horsepower, year, model) and
        // merges with any attribute constraint on the same taxonomy rather than
        // overwriting it, so the two can be combined without one silently
        // discarding the other.
        foreach (self::parse_lock_attribute($atts['lock']) as $lock_key => $lock_terms) {
            $locked[$lock_key] = isset($locked[$lock_key])
                ? array_values(array_unique(array_merge($locked[$lock_key], $lock_terms)))
                : $lock_terms;
        }

        // $filters drives templates/filters.php: which widgets render and
        // which are hidden as already-applied. Starts empty and is populated
        // from $locked below, so the attributes and the sidebar can't drift.
        //
        // $count_filters additionally carries every locked constraint, so the
        // term counts beside each checkbox reflect the true scope of the page.
        // Without this a promotion-scoped listing would advertise "Pontoon
        // (47)" and then return three boats when clicked.
        //
        // These are separate on purpose. Locked constraints must narrow the
        // counts, but only constraints that correspond to a real sidebar
        // widget may enter $filters. dc_promotions has no widget and must
        // never appear in the filter sidebar, which is why it is absent from
        // get_filter_taxonomy_map() and present only in the lockable map.
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
            // Inventory::getTaxonomyValuesFor*() treats the array key as the
            // taxonomy name, so the count array is keyed by taxonomy.
            $count_filters[$lockable_map[$lock_key]] = $lock_terms;

            if (isset($filter_map[$lock_key])) {
                $filters[self::map_lock_key_to_filters_key($lock_key)] = $lock_terms;
            }
        }

        // Check for 'notfound' parameter
        $not_found_message = '';
        if (isset($_GET['notfound']) && $_GET['notfound'] === '1') {
            $not_found_message = '<div class="dealerschoice-notification error" style="background: #fff3f3; border-left: 4px solid #d63638; padding: 15px; margin-bottom: 20px;">
                <p style="margin: 0; color: #d63638;"><strong>Notice:</strong> The boat you are looking for is no longer available. However, we have found similar boats you might be interested in below.</p>
            </div>';
        }

        // Get filter data. Counts use $count_filters so they respect locked
        // constraints; the widgets themselves are driven by $filters below.
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

        // Wrapper data attributes, assembled here to keep the markup readable.
        $wrapper_atts = ' data-posts-per-page="' . absint($atts['posts_per_page']) . '"';

        if (!empty($locked)) {
            $wrapper_atts .= ' data-locked="' . esc_attr(wp_json_encode($locked)) . '"';
        }

        // When location is locked, the sidebar's location widget is hidden and
        // the constraint can't be relaxed client-side. If the site has a header
        // location selector, choosing a different location on this page would
        // AND against the locked term and strand the visitor on a permanent
        // zero-results page. Hand the JS a destination to send them to instead.
        // Omitted when this page IS the site-wide inventory page, since
        // redirecting to ourselves would only reapply the same lock.
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
                // State the locked constraint in visible text. The sidebar
                // hides widgets it considers already-applied, so without this
                // the visitor (and any assistive tech) has no way to tell why
                // the listing is narrower than the full inventory.
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
                    <!-- Mobile Filter Toggle -->
                    <div id="mobile-filter-toggle" class="mobile-filter-toggle">
                        <button type="button" aria-label="Toggle Filters">
                            <i class="fa-light fa-filter"></i>
                            Filters
                            <i class="fa-light fa-angle-down"></i>
                        </button>
                    </div>

                    <!-- Filters Sidebar -->
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

                    <!-- Results Area -->
                    <div id="inventory-list" class="dealerschoice-results">

                        <!-- Search and Sort Controls -->
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

                        <!-- Results Container -->
                        <div id="inventory-results" class="inventory-results">
                            <div class="loading-spinner">
                                <i class="fa-light fa-arrows-rotate-reverse"></i>
                                Loading inventory...
                            </div>
                        </div>

                        <!-- Pagination Container -->
                        <div class="pagination-wrapper"></div>
                    </div>
                </div>
            </div>
        </div>
        <?php

        return ob_get_clean();
    }

    /**
     * Parse the 'lock' / 'tax' shortcode attribute into validated constraints.
     *
     * Syntax: "key:slug" pairs separated by "|", with multiple slugs for one
     * key separated by ",". Slugs within a key are ORed; separate keys are
     * ANDed. Examples:
     *
     *     lock="promotion:clearance"
     *     lock="promotion:featured,clearance"
     *     lock="promotion:clearance|location:north-shore"
     *
     * The key must be present in AJAX_Handlers::get_lockable_taxonomy_map(),
     * otherwise the pair is dropped. Output is always run through
     * sanitize_locked_constraints(), so the return value is safe to hand
     * straight to a tax_query.
     *
     * @param string $lock Raw attribute value.
     * @return array<string,array<int,string>>
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
     * Translate a lock/filter key into the key templates/filters.php expects.
     *
     * These layers grew separate naming schemes: the AJAX payload uses
     * 'status', filters.php reads 'boat_status', and the taxonomy is
     * 'boat_status'. Anything not listed passes through unchanged.
     *
     * Only ever called for keys that exist in get_filter_taxonomy_map(), i.e.
     * ones that actually have a sidebar widget. Scoping-only taxonomies such
     * as dc_promotions never reach this method.
     *
     * Note 'horsepower' maps to 'horsepower_range' for the widget but the
     * taxonomy is registered as 'horsepower'. That mismatch is pre-existing
     * and means a horsepower constraint zeroes the sidebar counts. Left alone
     * here rather than fixed silently as part of a promotions change.
     *
     * @param string $lock_key
     * @return string
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
     * Resolve locked constraints to human-readable term names for display.
     *
     * Falls back to the raw slug only when the term can't be found, so a
     * mistyped slug in the shortcode is visible on the page rather than
     * silently rendering an empty notice.
     *
     * @param array $locked Validated constraints.
     * @return array<int,string>
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
     * Inventory slider/carousel shortcode
     *
     * [dealerschoice_slider limit="6" category="pontoon" orderby="price"]
     * [dealerschoice_slider promotion="featured" orderby="rand" limit="8"]
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

        // Build query args
        $args = [
            'post_type' => 'boat',
            'post_status' => 'publish',
            'posts_per_page' => absint($atts['limit']),
            'order' => $atts['order'],
        ];

        // Add sorting
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
                // Useful for promotion sliders at multi-location dealers, so
                // one store's boats don't always lead. Note WP_Query rand
                // ordering is not cacheable
                // and gets expensive on large result sets, so keep 'limit'
                // modest and don't pair this with a big posts_per_page.
                $args['orderby'] = 'rand';
                break;
            default:
                $args['orderby'] = 'date';
        }

        // Add taxonomy filters
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

        // Additional taxonomy constraints via the 'promotion' and 'tax'
        // attributes, using the same "key:slug" syntax as
        // [dealerschoice_inventory lock=""]. This is how a promotion slider is
        // scoped:
        // [dealerschoice_slider promotion="featured" orderby="rand" limit="8"]
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

        // Exclude boats marked to not show
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

        // Enqueue assets
        wp_enqueue_style('dealerschoice-public');
        wp_enqueue_style('dealerschoice-slick');
        wp_enqueue_script('dealerschoice-slider');
        wp_enqueue_script('dealerschoice-slick');
        ob_start();
        $slider_id = 'dealerschoice-boat-slider-'.uniqid();

        // Slick settings passed via the data-slick attribute. autoplaySpeed
        // is only meaningful (and only included) when autoplay is on.
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
     * Standalone filters shortcode
     *
     * [dealerschoice_filters]
     */
    public static function filters_shortcode($atts) {
        // Enqueue assets
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

        // Get filter data
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
     * Location List Shortcode
     *
     * [dealerschoice_location_list]
     *
     * Displays the list of locations as buttons for the location selector popup in the header.
     */
    public static function location_list_shortcode() {
        wp_enqueue_script('dealerschoice-location-selector');

        $locations = get_terms([
            'taxonomy' => 'location',
            'hide_empty' => true, // Only show locations that actually have boats
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
     * Boat Quiz Shortcode
     *
     * [dealerschoice_boat_quiz]
     *
     * Displays the step-by-step boat finder quiz.
     *
     * Attributes:
     * - title        (string) Quiz heading. Default: 'Find Your Perfect Boat'
     * - subtitle     (string) Sub-heading. Default: descriptive tagline.
     * - submit_label (string) Label on the final submit button.
     * - boat_count   (int)    Number of matching boats to show in the result slider. Default: 3.
     *
     * @param array $atts Shortcode attributes.
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

        // Pre-load Gravity Forms scripts/styles so they are available when the
        // result HTML is injected into the page via AJAX.
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
     * Generic Finance Calculator Shortcode
     *
     * [dealerschoice_finance_calculator]
     *
     * Client-side loan payment calculator. Amount financed, rate, term, and
     * down payment are all user-editable. Shows a quick loan summary plus a
     * collapsed full amortization schedule.
     *
     * Attributes:
     * - title           (string) Heading above the form. Default: 'Estimate Your Payment'
     * - default_amount  (number) Pre-filled amount financed. Default: '' (blank, required field)
     *
     * @param array $atts Shortcode attributes.
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

        // Only pre-compute a down payment when we already know the amount;
        // otherwise the JS fills it in once the visitor types an amount.
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
     * Renders the "quick" single-line finance calculator for a boat's price
     * on single-boat.php. NOT registered as a shortcode - not user-insertable.
     *
     * Gating (all must pass):
     * 1. Admin toggle 'dealers_choice_show_finance_calculator' is enabled.
     * 2. The boat does not already have dealer-supplied financing data
     *    (Boat::hasFinancingData() is false) - a dealer-stated payment is
     *    authoritative and must not be contradicted by a generic estimate.
     * 3. The boat's price is actually visible to the current visitor
     *    (Boat::isPriceVisible() is true) - covers both the reveal-price
     *    gate (manufacturer MAP/pricing policy compliance) and the
     *    $0/empty "Contact Us for Our Price" case.
     *
     * @param \DC\Boat $boat
     * @return string HTML output, or '' if gating fails.
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
     * Global default APR (%) from Settings.
     *
     * @return float
     */
    public static function get_default_finance_rate() {
        return (float) get_option( 'dealers_choice_finance_default_rate', 7.99 );
    }

    /**
     * Global default loan term (months) from Settings.
     *
     * @return int
     */
    public static function get_default_finance_term() {
        return (int) get_option( 'dealers_choice_finance_default_term', 240 );
    }

    /**
     * Global default down payment, as a percentage of the amount financed,
     * from Settings.
     *
     * @return float
     */
    public static function get_default_finance_down_payment_percent() {
        return (float) get_option( 'dealers_choice_finance_default_down_payment_percent', 20 );
    }

    /**
     * Disclaimer text shown below both finance calculators, editable in
     * Settings. Falls back to get_default_finance_disclaimer() when the
     * dealer hasn't customised it.
     *
     * @return string May contain basic HTML (saved via wp_kses_post) - escape
     *                on output with wp_kses_post(), not esc_html().
     */
    public static function get_finance_calculator_disclaimer() {
        return get_option( 'dealers_choice_finance_calculator_disclaimer', self::get_default_finance_disclaimer() );
    }

    /**
     * Default disclaimer text for the finance calculators. Called both here
     * and from the admin settings page (as the get_option() fallback) so the
     * default copy only lives in one place.
     *
     * Explicitly calls out down payment, credit, price, and promotional
     * variability, and that tax/title/destination/other fees are excluded -
     * the calculators have no tax-rate input, so this is the only place
     * that caveat is communicated to the visitor.
     *
     * @return string
     */
    public static function get_default_finance_disclaimer() {
        return __(
            'This calculator provides an estimate for informational purposes only and is not an offer of credit. Your actual payment may vary based on several factors such as down payment, credit history, final price, available promotional programs and incentives. Applicable tag, title, destination charges, taxes and other fees and incentives are not included in this estimate. Contact us for your actual rate and payment terms.',
            'dealerschoice'
        );
    }

    /**
     * Common boat-loan term presets (months), shared by both calculator
     * variants and the admin settings page's default-term dropdown - single
     * source of truth so they never drift out of sync.
     *
     * @return array<int,string> value(months) => label
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
