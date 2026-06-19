<?php
/**
 * Boat Quiz Logic
 *
 * Handles config loading, question generation (with dynamic price_range terms),
 * answer scoring against boat_type taxonomy terms, and inventory lookups for
 * the quiz result screen.
 *
 * ── THEME OVERRIDE ───────────────────────────────────────────────────────────
 * Place a file at {your-theme}/dealerschoice/boat-quiz-config.php that returns
 * an array with the same structure as config/boat-quiz-defaults.php.
 * Your values are deep-merged over the plugin defaults.
 *
 * @package DealersChoice
 * @subpackage Classes
 * @since 1.0.0
 */

namespace DC;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BoatQuiz {

    /**
     * Loaded and merged config (cached after first load).
     *
     * @var array|null
     */
    private static $config = null;

    // ── Config ──────────────────────────────────────────────────────────────

    /**
     * Load and merge plugin defaults with optional theme override.
     *
     * @return array
     */
    public static function load_config(): array {
        if ( self::$config !== null ) {
            return self::$config;
        }

        $defaults = require plugin_dir_path( __DIR__ ) . 'config/boat-quiz-defaults.php';

        // Look for theme override: {theme}/dealerschoice/boat-quiz-config.php
        $theme_file = locate_template( 'dealerschoice/boat-quiz-config.php', false, false );
        if ( $theme_file && is_readable( $theme_file ) ) {
            $override = require $theme_file;
            if ( is_array( $override ) ) {
                $defaults = self::deep_merge( $defaults, $override );
            }
        }

        self::$config = $defaults;
        return self::$config;
    }

    /**
     * Recursively merge two arrays (right wins on scalar conflicts).
     *
     * @param array $base
     * @param array $override
     * @return array
     */
    private static function deep_merge( array $base, array $override ): array {
        foreach ( $override as $key => $value ) {
            if ( is_array( $value ) && isset( $base[ $key ] ) && is_array( $base[ $key ] ) ) {
                $base[ $key ] = self::deep_merge( $base[ $key ], $value );
            } else {
                $base[ $key ] = $value;
            }
        }
        return $base;
    }

    // ── Questions ────────────────────────────────────────────────────────────

    /**
     * Return the ordered question definitions for the quiz form.
     *
     * The budget step is built dynamically from the site's price_range taxonomy
     * terms so it reflects the actual inventory price structure.
     *
     * @return array[]  Each element: [ 'key', 'number', 'text', 'cols', 'options' ]
     *                  Each option:  [ 'value', 'label', 'description', 'svg'|'dollars' ]
     */
    public static function get_questions(): array {
        return [
            self::question_activity(),
            self::question_crew(),
            self::question_priorities(),
            self::question_budget(),
        ];
    }

    /** Q1 — Activity */
    private static function question_activity(): array {
        return [
            'key'     => 'activity',
            'number'  => '01',
            'text'    => "What's your perfect day on the water?",
            'cols'    => 4,
            'options' => [
                [
                    'value'       => 'cruising',
                    'label'       => 'Relaxing & Cruising',
                    'description' => 'Sandbars, sunsets, entertaining friends &amp; family.',
                    'svg'         => self::svg_cruising(),
                ],
                [
                    'value'       => 'fishing',
                    'label'       => 'Fishing',
                    'description' => 'Chasing the bite — inshore, offshore, or freshwater.',
                    'svg'         => self::svg_fishing(),
                ],
                [
                    'value'       => 'watersports',
                    'label'       => 'Water Sports',
                    'description' => 'Wakeboarding, tubing, skiing, and high-energy fun.',
                    'svg'         => self::svg_watersports(),
                ],
                [
                    'value'       => 'adventure',
                    'label'       => 'Exploring',
                    'description' => 'Overnight trips, coastal cruising, and getaways.',
                    'svg'         => self::svg_adventure(),
                ],
            ],
        ];
    }

    /** Q2 — Crew size */
    private static function question_crew(): array {
        return [
            'key'     => 'crew',
            'number'  => '02',
            'text'    => 'How big is your typical crew?',
            'cols'    => 3,
            'options' => [
                [
                    'value'       => 'small',
                    'label'       => 'Just Us (1–4)',
                    'description' => 'Intimate trips with a partner or small family.',
                    'svg'         => self::svg_crew_small(),
                ],
                [
                    'value'       => 'medium',
                    'label'       => 'Family (5–8)',
                    'description' => 'Room for family and a few friends too.',
                    'svg'         => self::svg_crew_medium(),
                ],
                [
                    'value'       => 'large',
                    'label'       => 'Big Group (9+)',
                    'description' => 'You love hosting — the more the merrier.',
                    'svg'         => self::svg_crew_large(),
                ],
            ],
        ];
    }

    /**
     * Q3 — Priorities
     *
     * Replaces the water-type question, which is irrelevant for dealerships
     * operating on a single body of water. Instead, we ask what matters most
     * so we can distinguish between, e.g., a pontoon vs. a runabout for
     * cruisers, or a wake boat vs. a surf-capable bowrider for water sports.
     *
     * When the loaded config includes a 'conditional_priorities' key (set via
     * a theme override), the options from every activity group are flattened
     * into one list and each option receives a 'group' key matching its
     * activity slug.  The frontend JS then shows only the subset that matches
     * the answer the user gave in Q1 (activity).
     */
    private static function question_priorities(): array {
        $config      = self::load_config();
        $conditional = $config['conditional_priorities'] ?? null;

        if ( ! empty( $conditional ) && is_array( $conditional ) ) {
            // Flatten all per-activity options into one list; attach 'group'
            // so the template can emit data-group and JS can filter visibility.
            $all_options = [];
            foreach ( $conditional as $activity_key => $group ) {
                if ( empty( $group['options'] ) || ! is_array( $group['options'] ) ) {
                    continue;
                }
                foreach ( $group['options'] as $option ) {
                    $option['group'] = $activity_key;
                    $all_options[]   = $option;
                }
            }

            return [
                'key'          => 'priorities',
                'number'       => '03',
                'text'         => "What matters most to you on the water?",
                'cols'         => 4,
                'conditional'  => true,   // hint to template / JS
                'options'      => $all_options,
            ];
        }

        // ── Static fallback (no conditional_priorities in config) ────────────
        return [
            'key'     => 'priorities',
            'number'  => '03',
            'text'    => "What matters most to you on the water?",
            'cols'    => 4,
            'options' => [
                [
                    'value'       => 'comfort',
                    'label'       => 'Comfort &amp; Space',
                    'description' => 'Lounging, entertaining, and room for everyone.',
                    'svg'         => self::svg_priority_comfort(),
                ],
                [
                    'value'       => 'performance',
                    'label'       => 'Speed &amp; Performance',
                    'description' => 'Power, handling, and an exciting ride.',
                    'svg'         => self::svg_priority_performance(),
                ],
                [
                    'value'       => 'fishing',
                    'label'       => 'Fishing Features',
                    'description' => 'Live wells, rod holders, and the right platform to fish.',
                    'svg'         => self::svg_priority_fishing(),
                ],
                [
                    'value'       => 'adventure',
                    'label'       => 'Range &amp; Exploration',
                    'description' => 'Overnights, distance, and the freedom to go further.',
                    'svg'         => self::svg_priority_adventure(),
                ],
            ],
        ];
    }

    /**
     * Q4 — Budget (built from live price_range taxonomy terms).
     * Terms are ordered by term_id (ascending) so cheaper ranges come first.
     */
    private static function question_budget(): array {
        $raw = Inventory::getTaxonomyValuesForPriceRange( [] );
        $terms = $raw['terms'] ?? [];

        // Sort by term_id ascending so price tiers appear in natural order
        usort( $terms, fn( $a, $b ) => $a['term']->term_id <=> $b['term']->term_id );

        // Build dollar-sign display for up to 5 tiers ($, $$, $$$, $$$$, $$$$$)
        $dollar_signs = [ '$', '$$', '$$$', '$$$$', '$$$$$' ];

        $options = [];
        foreach ( $terms as $index => $item ) {
            if ( empty( $item['count'] ) ) {
                continue; // Skip ranges with no boats
            }
            /** @var \WP_Term $term */
            $term      = $item['term'];
            $dollars   = $dollar_signs[ min( $index, 4 ) ] ?? '$';
            $options[] = [
                'value'       => $term->slug,
                'label'       => esc_html( $term->name ),
                'description' => '',
                'dollars'     => $dollars,
            ];
        }

        // Graceful fallback if taxonomy has no terms yet
        if ( empty( $options ) ) {
            $options = [
                [ 'value' => 'any', 'label' => 'No Preference', 'description' => '', 'dollars' => '$' ],
            ];
        }

        $cols = min( 4, count( $options ) );
        $cols = max( 2, $cols ); // at least 2 columns

        return [
            'key'     => 'budget',
            'number'  => '04',
            'text'    => "What's your budget?",
            'cols'    => $cols,
            'options' => $options,
        ];
    }

    // ── Scoring ──────────────────────────────────────────────────────────────

    /**
     * Calculate the best-matching boat_type term(s) for a set of quiz answers.
     *
     * Only boat_type terms that have at least one published, visible boat are
     * considered (uses get_terms with count > 0 filter).
     *
     * @param array $answers  Keys: activity, crew, water, budget (all sanitized strings).
     * @return array {
     *     'top'    => WP_Term   The best-matching boat type term.
     *     'alts'   => WP_Term[] Up to 2 runner-up terms (may be empty).
     *     'scores' => array     All slugs → scores (for debugging/logging).
     * }
     */
    public static function calculate_match( array $answers ): array {
        $config   = self::load_config();
        $scoring  = $config['scoring'] ?? [];
        $keywords = $config['smart_keywords'] ?? [];

        // Get all boat_type terms that have live posts
        $terms = get_terms( [
            'taxonomy'   => 'boat_type',
            'hide_empty' => true,
            'orderby'    => 'count',
            'order'      => 'DESC',
            'number'     => 0,
        ] );

        if ( is_wp_error( $terms ) || empty( $terms ) ) {
            return [ 'top' => null, 'alts' => [], 'scores' => [] ];
        }

        $scores = [];

        foreach ( $terms as $term ) {
            $slug  = $term->slug;
            $name  = strtolower( $term->name );
            $score = 0;

            // ── 1. Explicit scoring table ────────────────────────────────
            if ( isset( $scoring[ $slug ] ) ) {
                foreach ( [ 'activity', 'crew', 'priorities' ] as $q ) {
                    $answer = $answers[ $q ] ?? '';
                    if ( $answer && isset( $scoring[ $slug ][ $q ][ $answer ] ) ) {
                        $score += (int) $scoring[ $slug ][ $q ][ $answer ];
                    }
                }
            } else {
                // ── 2. Smart keyword fallback ────────────────────────────
                foreach ( $keywords as $keyword => $boosts ) {
                    if ( str_contains( $slug, $keyword ) || str_contains( $name, $keyword ) ) {
                        foreach ( [ 'activity', 'crew', 'priorities' ] as $q ) {
                            $answer = $answers[ $q ] ?? '';
                            if ( $answer && isset( $boosts[ $q ][ $answer ] ) ) {
                                $score += (int) $boosts[ $q ][ $answer ];
                            }
                        }
                    }
                }
            }

            $scores[ $slug ] = [ 'term' => $term, 'score' => $score ];
        }

        // Sort descending by score, then by term post count as tiebreaker
        uasort( $scores, function( $a, $b ) {
            if ( $b['score'] !== $a['score'] ) {
                return $b['score'] <=> $a['score'];
            }
            return $b['term']->count <=> $a['term']->count;
        } );

        $ranked = array_values( $scores );

        $top  = $ranked[0]['term'] ?? null;
        $alts = array_slice(
            array_column( array_slice( $ranked, 1 ), 'term' ),
            0,
            2
        );

        return [
            'top'    => $top,
            'alts'   => $alts,
            'scores' => array_map( fn( $v ) => $v['score'], $scores ),
        ];
    }

    // ── Inventory lookup ─────────────────────────────────────────────────────

    /**
     * Fetch a small set of matching boats to display on the result screen.
     *
     * @param string $boat_type_slug    Slug of the recommended boat_type term.
     * @param string $price_range_slug  Slug of the chosen price_range term (or '' / 'any').
     * @param int    $count             Max number of boats to return (default 3).
     * @return int[] Array of post IDs.
     */
    public static function get_matching_boats( string $boat_type_slug, string $price_range_slug, int $count = 3 ): array {
        $tax_query = [
            'relation' => 'AND',
            [
                'taxonomy' => 'boat_type',
                'field'    => 'slug',
                'terms'    => [ $boat_type_slug ],
            ],
        ];

        if ( $price_range_slug && $price_range_slug !== 'any' ) {
            $tax_query[] = [
                'taxonomy' => 'price_range',
                'field'    => 'slug',
                'terms'    => [ $price_range_slug ],
            ];
        }

        $args = [
            'post_type'      => 'boat',
            'post_status'    => 'publish',
            'posts_per_page' => $count,
            #'orderby'        => 'date',
            #'order'          => 'DESC',
            'meta_key'       => 'boat_saleprice',
            'orderby'        => 'meta_value_num',
            'order'          => 'DESC',
            'fields'         => 'ids',
            'tax_query'      => $tax_query,
            'meta_query'     => [
                'relation' => 'OR',
                [
                    'key'     => 'do_not_show_on_public_website',
                    'value'   => '1',
                    'compare' => '!=',
                ],
                [
                    'key'     => 'do_not_show_on_public_website',
                    'compare' => 'NOT EXISTS',
                ],
            ],
        ];

        $query = new \WP_Query( $args );
        $ids   = $query->posts;
        wp_reset_postdata();

        // If we didn't get enough boats with price filter, try without it
        if ( count( $ids ) < $count && $price_range_slug && $price_range_slug !== 'any' ) {
            $args['tax_query'] = [
                [
                    'taxonomy' => 'boat_type',
                    'field'    => 'slug',
                    'terms'    => [ $boat_type_slug ],
                ],
            ];
            $args['posts_per_page'] = $count - count( $ids );
            $args['post__not_in']   = $ids ?: [ 0 ];

            $fallback_query = new \WP_Query( $args );
            $ids            = array_merge( $ids, $fallback_query->posts );
            wp_reset_postdata();
        }

        return array_slice( $ids, 0, $count );
    }

    /**
     * Build the inventory page URL with quiz result filters pre-applied.
     *
     * @param string $boat_type_slug
     * @param string $price_range_slug
     * @return string
     */
    public static function build_inventory_url( string $boat_type_slug, string $price_range_slug ): string {
        // 1. Explicit option set by admin.
        $inventory_page_id = (int) get_option( 'dealers_choice_inventory_page_id', 0 );

        if ( ! $inventory_page_id ) {
            // 2. Find the page at the /inventory/ path via WordPress built-in.
            $inventory_page = get_page_by_path( 'inventory' );
            if ( $inventory_page ) {
                $inventory_page_id = $inventory_page->ID;
            }
        }

        $base = $inventory_page_id
            ? get_permalink( $inventory_page_id )
            : home_url( '/inventory/' );

        $params = [];
        if ( $boat_type_slug ) {
            $params['category'] = $boat_type_slug;
        }
        if ( $price_range_slug && $price_range_slug !== 'any' ) {
            $params['price'] = $price_range_slug;
        }

        return $params ? add_query_arg( $params, $base ) : (string) $base;
    }

    /**
     * Get the "why" copy for a boat type term.
     * Uses config result_copy first, then term description, then generic fallback.
     *
     * @param \WP_Term $term
     * @return string  Unescaped string (escape on output).
     */
    public static function get_result_copy( \WP_Term $term ): string {
        $config = self::load_config();
        $copy   = $config['result_copy'] ?? [];
        $slug   = $term->slug;

        if ( ! empty( $copy[ $slug ] ) ) {
            return $copy[ $slug ];
        }

        if ( ! empty( $term->description ) ) {
            return wp_strip_all_tags( $term->description );
        }

        return sprintf(
            'Based on your answers, a %s is your best match. Browse our current selection below.',
            esc_html( $term->name )
        );
    }

    // ── SVG helpers ──────────────────────────────────────────────────────────

    /** Pontoon / sunset cruising */
    private static function svg_cruising(): string {
        return '<svg viewBox="0 0 160 100" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <circle cx="80" cy="38" r="22" fill="#fed7aa" opacity="0.6"/>
            <path d="M0 70 Q80 58 160 70" stroke="#7dd3fc" stroke-width="1.5" fill="none"/>
            <path d="M15 78 Q80 68 145 78 L160 95 L0 95Z" fill="#bae6fd" opacity="0.5"/>
            <rect x="35" y="64" width="90" height="14" rx="7" fill="#0369a1" opacity="0.7"/>
            <rect x="48" y="54" width="64" height="12" rx="4" fill="#0284c7" opacity="0.8"/>
            <line x1="65" y1="54" x2="65" y2="40" stroke="#0ea5e9" stroke-width="1.5"/>
            <line x1="95" y1="54" x2="95" y2="40" stroke="#0ea5e9" stroke-width="1.5"/>
            <line x1="65" y1="40" x2="95" y2="40" stroke="#0ea5e9" stroke-width="1.5" opacity="0.6"/>
            <circle cx="63" cy="51" r="3.5" fill="#93c5fd" opacity="0.8"/>
            <circle cx="80" cy="51" r="3.5" fill="#93c5fd" opacity="0.8"/>
            <circle cx="97" cy="51" r="3.5" fill="#93c5fd" opacity="0.8"/>
        </svg>';
    }

    /** Fishing boat */
    private static function svg_fishing(): string {
        return '<svg viewBox="0 0 160 100" fill="none" aria-hidden="true">
            <path d="M0 65 Q40 58 80 65 Q120 72 160 65 L160 95 L0 95Z" fill="#bbf7d0" opacity="0.7"/>
            <path d="M20 74 L55 62 L120 62 L140 74 Q100 82 80 82 Q50 82 20 74Z" fill="#15803d" opacity="0.7"/>
            <rect x="68" y="53" width="30" height="11" rx="3" fill="#14532d" opacity="0.8"/>
            <line x1="120" y1="62" x2="148" y2="40" stroke="#4ade80" stroke-width="1.5"/>
            <line x1="148" y1="40" x2="150" y2="62" stroke="#86efac" stroke-width="1" stroke-dasharray="2 3"/>
            <circle cx="150" cy="63" r="2.5" fill="none" stroke="#4ade80" stroke-width="1.2"/>
            <circle cx="108" cy="56" r="4.5" fill="#86efac" opacity="0.7"/>
            <path d="M106 60 L103 67" stroke="#86efac" stroke-width="2"/>
        </svg>';
    }

    /** Watersports tow boat */
    private static function svg_watersports(): string {
        return '<svg viewBox="0 0 160 100" fill="none" aria-hidden="true">
            <path d="M0 70 Q40 62 80 70 Q120 78 160 70 L160 95 L0 95Z" fill="#fed7aa" opacity="0.6"/>
            <path d="M22 72 L58 57 L120 57 L132 72 Q100 80 80 80 Q50 80 22 72Z" fill="#c2410c" opacity="0.65"/>
            <line x1="75" y1="57" x2="68" y2="35" stroke="#fb923c" stroke-width="1.5"/>
            <line x1="85" y1="57" x2="92" y2="35" stroke="#fb923c" stroke-width="1.5"/>
            <line x1="68" y1="35" x2="92" y2="35" stroke="#fb923c" stroke-width="1.5"/>
            <circle cx="136" cy="62" r="4.5" fill="#fed7aa" opacity="0.8"/>
            <path d="M133 66 L127 74" stroke="#fdba74" stroke-width="1.5"/>
            <path d="M136 66 L138 74" stroke="#fdba74" stroke-width="1.5"/>
        </svg>';
    }

    /** Adventure / cruiser */
    private static function svg_adventure(): string {
        return '<svg viewBox="0 0 160 100" fill="none" aria-hidden="true">
            <path d="M0 58 Q80 48 160 58 L160 95 L0 95Z" fill="#c4b5fd" opacity="0.4"/>
            <path d="M0 68 Q30 62 60 68 Q90 74 120 68 Q140 64 160 68" stroke="#a78bfa" stroke-width="2" fill="none"/>
            <path d="M25 70 L48 55 L112 55 L130 70 Q100 78 80 78 Q50 78 25 70Z" fill="#6d28d9" opacity="0.6"/>
            <rect x="55" y="44" width="46" height="13" rx="4" fill="#4c1d95" opacity="0.7"/>
            <circle cx="64" cy="51" r="3" fill="none" stroke="#c4b5fd" stroke-width="1.2"/>
            <circle cx="80" cy="51" r="3" fill="none" stroke="#c4b5fd" stroke-width="1.2"/>
            <circle cx="96" cy="51" r="3" fill="none" stroke="#c4b5fd" stroke-width="1.2"/>
            <line x1="98" y1="44" x2="98" y2="36" stroke="#a78bfa" stroke-width="1.2"/>
            <circle cx="98" cy="35" r="2" fill="#c4b5fd"/>
        </svg>';
    }

    /** Small crew (2 silhouettes) */
    private static function svg_crew_small(): string {
        return '<svg viewBox="0 0 120 80" fill="none" aria-hidden="true">
            <circle cx="48" cy="28" r="10" fill="#bae6fd" opacity="0.8"/>
            <path d="M36 52 Q48 44 60 52 L63 68 L33 68Z" fill="#7dd3fc" opacity="0.6"/>
            <circle cx="74" cy="30" r="10" fill="#bae6fd" opacity="0.7"/>
            <path d="M62 54 Q74 46 86 54 L89 68 L59 68Z" fill="#7dd3fc" opacity="0.5"/>
        </svg>';
    }

    /** Medium crew (4 silhouettes) */
    private static function svg_crew_medium(): string {
        return '<svg viewBox="0 0 120 80" fill="none" aria-hidden="true">
            <circle cx="28" cy="30" r="8" fill="#bae6fd" opacity="0.8"/>
            <path d="M20 50 Q28 44 36 50 L38 65 L18 65Z" fill="#7dd3fc" opacity="0.5"/>
            <circle cx="52" cy="26" r="10" fill="#bae6fd" opacity="0.9"/>
            <path d="M42 48 Q52 42 62 48 L64 65 L40 65Z" fill="#7dd3fc" opacity="0.6"/>
            <circle cx="78" cy="29" r="9" fill="#bae6fd" opacity="0.8"/>
            <path d="M69 50 Q78 44 87 50 L89 65 L67 65Z" fill="#7dd3fc" opacity="0.55"/>
            <circle cx="100" cy="31" r="8" fill="#bae6fd" opacity="0.7"/>
            <path d="M92 51 Q100 46 108 51 L110 65 L90 65Z" fill="#7dd3fc" opacity="0.45"/>
        </svg>';
    }

    /** Large crew (6 silhouettes) */
    private static function svg_crew_large(): string {
        return '<svg viewBox="0 0 120 80" fill="none" aria-hidden="true">
            <circle cx="18" cy="34" r="7" fill="#bae6fd" opacity="0.7"/>
            <circle cx="34" cy="29" r="8" fill="#bae6fd" opacity="0.8"/>
            <circle cx="52" cy="26" r="9" fill="#bae6fd" opacity="0.9"/>
            <circle cx="70" cy="27" r="9" fill="#bae6fd" opacity="0.9"/>
            <circle cx="88" cy="29" r="8" fill="#bae6fd" opacity="0.8"/>
            <circle cx="104" cy="33" r="7" fill="#bae6fd" opacity="0.7"/>
            <path d="M8 50 Q60 42 112 50 L112 65 L8 65Z" fill="#7dd3fc" opacity="0.35"/>
        </svg>';
    }

    /** Priority: Comfort &amp; Space — wide lounge deck with canopy */
    private static function svg_priority_comfort(): string {
        return '<svg viewBox="0 0 160 100" fill="none" aria-hidden="true">
            <circle cx="138" cy="20" r="12" fill="#fed7aa" opacity="0.6"/>
            <path d="M0 76 Q80 68 160 76 L160 95 L0 95Z" fill="#bae6fd" opacity="0.5"/>
            <rect x="20" y="62" width="120" height="14" rx="7" fill="#0369a1" opacity="0.7"/>
            <rect x="28" y="52" width="104" height="12" rx="3" fill="#0284c7" opacity="0.8"/>
            <rect x="36" y="42" width="20" height="12" rx="3" fill="#bae6fd" opacity="0.85"/>
            <rect x="70" y="42" width="20" height="12" rx="3" fill="#bae6fd" opacity="0.85"/>
            <rect x="104" y="42" width="20" height="12" rx="3" fill="#bae6fd" opacity="0.85"/>
            <line x1="40" y1="42" x2="40" y2="28" stroke="#0ea5e9" stroke-width="1.5"/>
            <line x1="120" y1="42" x2="120" y2="28" stroke="#0ea5e9" stroke-width="1.5"/>
            <line x1="40" y1="28" x2="120" y2="28" stroke="#0ea5e9" stroke-width="1.5" opacity="0.7"/>
        </svg>';
    }

    /** Priority: Speed &amp; Performance — sporty hull cutting water */
    private static function svg_priority_performance(): string {
        return '<svg viewBox="0 0 160 100" fill="none" aria-hidden="true">
            <path d="M0 68 Q80 60 160 68 L160 95 L0 95Z" fill="#bae6fd" opacity="0.4"/>
            <line x1="0" y1="46" x2="28" y2="46" stroke="#fb923c" stroke-width="1.5" opacity="0.55"/>
            <line x1="0" y1="53" x2="22" y2="53" stroke="#fb923c" stroke-width="1" opacity="0.4"/>
            <line x1="0" y1="60" x2="26" y2="60" stroke="#fb923c" stroke-width="1" opacity="0.3"/>
            <path d="M28 58 L145 46 L158 58 L28 72Z" fill="#c2410c" opacity="0.72"/>
            <path d="M80 52 L130 46 L124 40 L74 46Z" fill="#fed7aa" opacity="0.55"/>
            <path d="M148 54 Q156 50 159 60 Q155 58 148 56Z" fill="#7dd3fc" opacity="0.8"/>
        </svg>';
    }

    /** Priority: Fishing Features — rod, fish, and live-well suggestion */
    private static function svg_priority_fishing(): string {
        return '<svg viewBox="0 0 160 100" fill="none" aria-hidden="true">
            <path d="M0 68 Q80 62 160 68 L160 95 L0 95Z" fill="#bbf7d0" opacity="0.5"/>
            <path d="M20 72 L55 60 L120 60 L140 72 Q100 80 80 80 Q50 80 20 72Z" fill="#15803d" opacity="0.65"/>
            <rect x="66" y="51" width="32" height="11" rx="3" fill="#14532d" opacity="0.8"/>
            <line x1="118" y1="60" x2="148" y2="34" stroke="#4ade80" stroke-width="1.5"/>
            <line x1="148" y1="34" x2="150" y2="58" stroke="#86efac" stroke-width="1" stroke-dasharray="2 3"/>
            <circle cx="150" cy="59" r="2.5" fill="none" stroke="#4ade80" stroke-width="1.2"/>
            <path d="M144 46 Q152 43 154 50 Q150 52 144 50 Q140 48 144 46Z" fill="#86efac" opacity="0.8"/>
            <rect x="36" y="60" width="14" height="8" rx="2" fill="#4ade80" opacity="0.5"/>
            <rect x="54" y="60" width="8" height="8" rx="2" fill="#4ade80" opacity="0.4"/>
        </svg>';
    }

    /** Priority: Range &amp; Exploration — cruiser hull with compass rose */
    private static function svg_priority_adventure(): string {
        return '<svg viewBox="0 0 160 100" fill="none" aria-hidden="true">
            <path d="M0 60 Q80 52 160 60 L160 95 L0 95Z" fill="#c4b5fd" opacity="0.35"/>
            <path d="M22 68 L48 52 L112 52 L132 68 Q100 77 80 77 Q50 77 22 68Z" fill="#6d28d9" opacity="0.58"/>
            <rect x="52" y="41" width="56" height="13" rx="4" fill="#4c1d95" opacity="0.72"/>
            <line x1="100" y1="41" x2="100" y2="30" stroke="#a78bfa" stroke-width="1.5"/>
            <circle cx="100" cy="29" r="2" fill="#c4b5fd"/>
            <circle cx="130" cy="32" r="12" fill="none" stroke="#a78bfa" stroke-width="1.2" opacity="0.7"/>
            <line x1="130" y1="22" x2="130" y2="27" stroke="#c4b5fd" stroke-width="1.5"/>
            <line x1="130" y1="37" x2="130" y2="42" stroke="#c4b5fd" stroke-width="1"/>
            <line x1="120" y1="32" x2="125" y2="32" stroke="#c4b5fd" stroke-width="1"/>
            <line x1="135" y1="32" x2="140" y2="32" stroke="#c4b5fd" stroke-width="1.5"/>
            <polygon points="130,24 132,31 130,30 128,31" fill="#c4b5fd" opacity="0.9"/>
        </svg>';
    }
    
}
