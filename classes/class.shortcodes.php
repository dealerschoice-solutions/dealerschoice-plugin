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

}
