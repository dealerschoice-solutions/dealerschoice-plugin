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
 * - category: Filter by category slug
 * - condition: Filter by condition slug
 * - location: Filter by location slug
 * 
 * [dealerschoice_slider]
 * Displays a horizontal slider/carousel of boats
 * Attributes:
 * - limit: Number of boats to show (default: 6)
 * - category: Filter by category slug
 * - condition: Filter by condition slug
 * - location: Filter by location slug
 * - orderby: Sort by (date, price, year, length) (default: date)
 * - order: Sort direction (ASC, DESC) (default: DESC)
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

        // Build initial filters from attributes
        // Pass slugs directly to Inventory class
        $filters = [
            'location' => $atts['location'] ? $atts['location'] : false,
            'condition' => $atts['condition'] ? $atts['condition'] : false,
            'boat_status' => $atts['status'] ? $atts['status'] : false,
            'year' => false,
            'boat_type' => $atts['category'] ? $atts['category'] : false,
            'model' => false,
            'make' => $atts['make'] ? $atts['make'] : false,
            'price_range' => false,
            'length_range' => false,
            'horsepower_range' => false,
            'person_capacity' => false,
        ];

        // Check for 'notfound' parameter
        $not_found_message = '';
        if (isset($_GET['notfound']) && $_GET['notfound'] === '1') {
            $not_found_message = '<div class="dealerschoice-notification error" style="background: #fff3f3; border-left: 4px solid #d63638; padding: 15px; margin-bottom: 20px;">
                <p style="margin: 0; color: #d63638;"><strong>Notice:</strong> The boat you are looking for is no longer available. However, we have found similar boats you might be interested in below.</p>
            </div>';
        }

        // Get filter data
        if (class_exists('\DC\Inventory')) {
            $locations = \DC\Inventory::getTaxonomyValuesForLocation($filters);
            $conditions = \DC\Inventory::getTaxonomyValuesForCondition($filters);
            $statuses = \DC\Inventory::getTaxonomyValuesForStatus($filters);
            $years = \DC\Inventory::getTaxonomyValuesForYear($filters);
            $categories = \DC\Inventory::getTaxonomyValuesForBoatType($filters);
            $makes = \DC\Inventory::getTaxonomyValuesForMake($filters);
            $models = \DC\Inventory::getTaxonomyValuesForModel($filters);
            $priceRanges = \DC\Inventory::getTaxonomyValuesForPriceRange($filters);
            $lengths = \DC\Inventory::getTaxonomyValuesForLength($filters);
            $horsepowers = \DC\Inventory::getTaxonomyValuesForHorsepower($filters);
            $capacities = \DC\Inventory::getTaxonomyValuesForPersonCapacity($filters);
        } else {
            $locations = $conditions = $years = $categories = $makes = $models = [];
            $priceRanges = $lengths = $horsepowers = $capacities = [];
        }

        ?>
        <div class="dealerschoice-shortcode dealerschoice-inventory-shortcode">
            <div id="inventory-wrapper" class="dealerschoice-inventory-wrapper" data-posts-per-page="<?php echo absint($atts['posts_per_page']); ?>">
                <?php echo $not_found_message; ?>
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
     * Inventory slider/carousel shortcode
     *
     * [dealerschoice_slider limit="6" category="pontoon" orderby="price"]
     */
    public static function slider_shortcode($atts) {
        $atts = shortcode_atts([
            'limit' => 6,
            'category' => '',
            'condition' => '',
            'location' => '',
            'make' => '',
            'year' => '',
            'orderby' => 'date',
            'order' => 'DESC',
            'slides_to_show' => 3,
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
        ?>
        <div class="dealerschoice-shortcode dealerschoice-slider dc-mb">
            <div class="boat-slider" id="<?php echo esc_html($slider_id); ?>" data-slick='{"slidesToShow": <?php echo esc_html(absint($atts['slides_to_show'])); ?>}'>
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
            compact( 'title', 'subtitle', 'submit_label', 'questions', 'total_steps', 'nonce', 'gravity_form_id' )
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
