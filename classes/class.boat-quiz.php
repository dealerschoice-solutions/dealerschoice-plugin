<?php
/**
 * Boat quiz: config loading, questions, boat_type scoring and result inventory lookups.
 *
 * @package DealersChoice
 */

namespace DC;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Builds the "find your boat" quiz and matches answers to boat types and boats.
 *
 * Config comes from config/boat-quiz-defaults.php, deep-merged with an optional
 * {theme}/dealerschoice/boat-quiz-config.php override.
 */
class BoatQuiz {

    /**
     * Merged config, cached after the first load.
     *
     * @var array|null
     */
    private static $config = null;

    // ── Config ──────────────────────────────────────────────────────────────

    /**
     * Loads plugin defaults merged with the optional theme override.
     *
     * @return array Merged config.
     */
    public static function load_config(): array {
        if ( self::$config !== null ) {
            return self::$config;
        }

        $defaults = require plugin_dir_path( __DIR__ ) . 'config/boat-quiz-defaults.php';

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
     * Recursively merges two arrays; override values win on scalar conflicts.
     *
     * @param array $base     Base array.
     * @param array $override Overriding array.
     * @return array Merged array.
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
     * Returns the ordered question definitions for the quiz form.
     *
     * The budget step is built from the site's price_range terms.
     *
     * @return array[] Each element: [ 'key', 'number', 'text', 'cols', 'options' ].
     *                 Each option:  [ 'value', 'label', 'description', 'svg'|'dollars' ].
     */
    public static function get_questions(): array {
        return [
            self::question_activity(),
            self::question_crew(),
            self::question_priorities(),
            self::question_budget(),
        ];
    }

    /**
     * Builds question 1: activity.
     *
     * @return array Question definition.
     */
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

    /**
     * Builds question 2: crew size.
     *
     * @return array Question definition.
     */
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
     * Builds question 3: what matters most on the water.
     *
     * When the config has 'conditional_priorities', every activity group's options
     * are flattened into one list, each tagged with a 'group' key (its activity slug)
     * so the front end shows only the options for the chosen activity.
     *
     * @return array Question definition.
     */
    private static function question_priorities(): array {
        $config      = self::load_config();
        $conditional = $config['conditional_priorities'] ?? null;

        if ( ! empty( $conditional ) && is_array( $conditional ) ) {
            // Flatten per-activity options and tag each with its activity group.
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
                'conditional'  => true,
                'options'      => $all_options,
            ];
        }

        // Static options when no conditional_priorities are configured.
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
     * Builds question 4: budget, from price_range terms that have boats.
     *
     * Terms are ordered cheapest-first and shown as $ to $$$$$ tiers.
     *
     * @return array Question definition.
     */
    private static function question_budget(): array {
        $terms = self::get_ordered_price_ranges();

        $dollar_signs = [ '$', '$$', '$$$', '$$$$', '$$$$$' ];

        $options = [];
        foreach ( $terms as $index => $item ) {
            if ( empty( $item['count'] ) ) {
                continue;
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

        // Single "No Preference" option when no price ranges have boats.
        if ( empty( $options ) ) {
            $options = [
                [ 'value' => 'any', 'label' => 'No Preference', 'description' => '', 'dollars' => '$' ],
            ];
        }

        $cols = min( 4, count( $options ) );
        $cols = max( 2, $cols );

        return [
            'key'     => 'budget',
            'number'  => '04',
            'text'    => "What's your budget?",
            'cols'    => $cols,
            'options' => $options,
        ];
    }

    /**
     * Returns price_range terms with counts, ordered cheapest-first.
     *
     * Sorts on the first dollar figure in each term name ("$100,000 - $200,000",
     * "Under $50k", "$1M+"). Unparseable terms go last, ordered by term_id.
     *
     * @return array[] Each element: [ 'term' => WP_Term, 'count' => int ].
     */
    private static function get_ordered_price_ranges(): array {
        $raw   = Inventory::getTaxonomyValuesForPriceRange( [] );
        $terms = $raw['terms'] ?? [];

        usort( $terms, function( $a, $b ) {
            $a_price = self::parse_price( $a['term']->name );
            $b_price = self::parse_price( $b['term']->name );

            $a_key = $a_price ?? PHP_INT_MAX;
            $b_key = $b_price ?? PHP_INT_MAX;

            if ( $a_key !== $b_key ) {
                return $a_key <=> $b_key;
            }
            return $a['term']->term_id <=> $b['term']->term_id;
        } );

        return $terms;
    }

    /**
     * Extracts the first dollar figure from a price_range term name.
     *
     * Handles thousands separators and k/M suffixes, so "$100,000", "$100k" and
     * "0.1M" all resolve to 100000.
     *
     * @param string $label Term name.
     * @return int|null Amount in dollars, or null when no number is present.
     */
    private static function parse_price( string $label ): ?int {
        if ( ! preg_match( '/([0-9][0-9,]*(?:\.[0-9]+)?)\s*([kKmM])?/', $label, $m ) ) {
            return null;
        }

        $value = (float) str_replace( ',', '', $m[1] );

        if ( ! empty( $m[2] ) ) {
            $value *= ( strtolower( $m[2] ) === 'm' ) ? 1000000 : 1000;
        }

        return (int) round( $value );
    }

    // ── Scoring ──────────────────────────────────────────────────────────────

    /**
     * Questions that contribute to a boat_type's match score.
     *
     * @var string[]
     */
    private const SCORED_QUESTIONS = [ 'activity', 'crew', 'priorities', 'budget' ];

    /**
     * Calculates the best-matching boat_type terms for a set of quiz answers.
     *
     * Considers non-empty boat_type terms, drops non-boat types (see
     * 'excluded_keywords'), and ranks the rest by answer fit plus whether the
     * dealer stocks that type inside the chosen budget.
     *
     * @param array $answers Keys: activity, crew, priorities, budget (sanitized strings).
     * @return array {
     *     @type \WP_Term|null $top    Best-matching boat type term.
     *     @type \WP_Term[]    $alts   Up to 2 runner-up terms.
     *     @type int[]         $scores Scores keyed by term slug.
     * }
     */
    public static function calculate_match( array $answers ): array {
        $config   = self::load_config();
        $scoring  = $config['scoring'] ?? [];
        $keywords = $config['smart_keywords'] ?? [];

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

        // Drop non-boat types, keeping all terms if every one is excluded.
        $candidates = array_values( array_filter( $terms, fn( $t ) => ! self::is_excluded_type( $t ) ) );
        if ( empty( $candidates ) ) {
            $candidates = $terms;
        }

        // In-budget inventory count per boat type.
        $budget       = (string) ( $answers['budget'] ?? '' );
        $price_counts = self::get_price_fit_counts( $budget );
        $price_active = array_sum( $price_counts ) > 0;
        $hard_filter  = (bool) ( $config['price_fit']['hard_filter'] ?? true );

        $scores = [];

        foreach ( $candidates as $term ) {
            $slug = $term->slug;

            // Explicit scoring table, else smart keyword matching.
            $base = isset( $scoring[ $slug ] )
                ? self::score_from_table( $scoring[ $slug ], $answers )
                : self::score_from_keywords( $term, $keywords, $answers );

            // Price fit.
            $in_budget = ! $price_active || ! empty( $price_counts[ $slug ] );
            $price     = $price_active
                ? self::price_fit_adjustment( (int) ( $price_counts[ $slug ] ?? 0 ), $config )
                : 0;

            $scores[ $slug ] = [
                'term'      => $term,
                'score'     => $base + $price,
                'base'      => $base,
                'price'     => $price,
                'in_budget' => $in_budget,
            ];
        }

        /**
         * Filter the scored boat types before they are ranked.
         *
         * @param array $scores  slug => [ term, score, base, price, in_budget ]
         * @param array $answers The submitted quiz answers.
         * @param array $config  The merged quiz config.
         */
        $scores = apply_filters( 'dc_boat_quiz_scores', $scores, $answers, $config );

        // Rank in-budget types first (hard filter), then by score, then by term count.
        uasort( $scores, function( $a, $b ) use ( $price_active, $hard_filter ) {
            if ( $price_active && $hard_filter && $a['in_budget'] !== $b['in_budget'] ) {
                return $a['in_budget'] ? -1 : 1;
            }
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

    /**
     * Scores a boat type from its entry in the scoring matrix.
     *
     * @param array $table   Per-question weights for one boat type.
     * @param array $answers Quiz answers.
     * @return int Score.
     */
    private static function score_from_table( array $table, array $answers ): int {
        $score = 0;

        foreach ( self::SCORED_QUESTIONS as $q ) {
            $answer = $answers[ $q ] ?? '';
            if ( $answer !== '' && isset( $table[ $q ][ $answer ] ) ) {
                $score += (int) $table[ $q ][ $answer ];
            }
        }

        return $score;
    }

    /**
     * Scores a boat type with no matrix entry by keyword matching.
     *
     * Each question contributes the highest boost among the matching keywords.
     *
     * @param \WP_Term $term     Boat type term.
     * @param array    $keywords Smart keyword config.
     * @param array    $answers  Quiz answers.
     * @return int Score.
     */
    private static function score_from_keywords( \WP_Term $term, array $keywords, array $answers ): int {
        $matches = self::match_keywords( $term, $keywords );

        if ( empty( $matches ) ) {
            return 0;
        }

        $score = 0;

        foreach ( self::SCORED_QUESTIONS as $q ) {
            $answer = $answers[ $q ] ?? '';
            if ( $answer === '' ) {
                continue;
            }

            $best = 0;
            foreach ( $matches as $boosts ) {
                if ( isset( $boosts[ $q ][ $answer ] ) ) {
                    $best = max( $best, (int) $boosts[ $q ][ $answer ] );
                }
            }
            $score += $best;
        }

        return $score;
    }

    /**
     * Finds the keywords that describe a boat_type term.
     *
     * Whole-word matches in the slug or name (including simple plurals) are used
     * first. Only when there are none does it fall back to prefix matches, so
     * compound words like "bowriders" still resolve. A keyword with
     * 'match' => 'exact' skips the prefix tier.
     *
     * @param \WP_Term $term     Boat type term.
     * @param array    $keywords Smart keyword config.
     * @return array[] Boosts keyed by keyword.
     */
    private static function match_keywords( \WP_Term $term, array $keywords ): array {
        $tokens   = self::tokenize( $term );
        $haystack = self::normalize( $term->slug . ' ' . $term->name );

        $exact = [];
        $prefix = [];

        foreach ( $keywords as $keyword => $boosts ) {
            $kw = strtolower( trim( (string) $keyword ) );

            if ( $kw === '' || ! is_array( $boosts ) ) {
                continue;
            }

            // Multi-word keywords ("center console") are matched as a phrase.
            if ( strpbrk( $kw, ' -_' ) !== false ) {
                if ( str_contains( $haystack, self::normalize( $kw ) ) ) {
                    $exact[ $kw ] = $boosts;
                }
                continue;
            }

            if ( in_array( $kw, $tokens, true )
                || in_array( $kw . 's', $tokens, true )
                || in_array( $kw . 'es', $tokens, true ) ) {
                $exact[ $kw ] = $boosts;
                continue;
            }

            if ( ( $boosts['match'] ?? '' ) === 'exact' ) {
                continue;
            }

            foreach ( $tokens as $token ) {
                if ( strlen( $token ) > strlen( $kw ) && str_starts_with( $token, $kw ) ) {
                    $prefix[ $kw ] = $boosts;
                    break;
                }
            }
        }

        return $exact ?: $prefix;
    }

    /**
     * Determines whether a boat_type term is something other than a boat.
     *
     * Matches the term's head noun and tokens against 'excluded_keywords'. A term
     * whose head noun is a vessel keyword ("Wakeboard Boats") is kept.
     *
     * @param \WP_Term $term Boat type term.
     * @return bool True when the term is excluded from quiz results.
     */
    private static function is_excluded_type( \WP_Term $term ): bool {
        $config  = self::load_config();
        $blocked = array_map( 'strtolower', (array) ( $config['excluded_keywords'] ?? [] ) );

        $excluded = false;

        if ( ! empty( $blocked ) ) {
            $vessel   = array_map( 'strtolower', (array) ( $config['vessel_keywords'] ?? [] ) );
            $tokens   = self::tokenize( $term );
            $haystack = self::normalize( $term->slug . ' ' . $term->name );

            // The last word decides: "Wakeboard Boats" is a boat, "Boat Trailers" is not.
            $head = self::head_noun( $term );

            if ( $head !== '' && self::matches_word( $head, $blocked ) ) {
                $excluded = true;
            } elseif ( $head !== '' && in_array( $head, $vessel, true ) ) {
                $excluded = false;
            } else {
                foreach ( $blocked as $needle ) {
                    $needle = trim( $needle );
                    if ( $needle === '' ) {
                        continue;
                    }
                    if ( self::matches_word( $needle, $tokens ) ) {
                        $excluded = true;
                        break;
                    }
                    // Keywords of 6+ characters also match inside compound tokens.
                    if ( strlen( $needle ) >= 6 && str_contains( $haystack, self::normalize( $needle ) ) ) {
                        $excluded = true;
                        break;
                    }
                }
            }
        }

        /**
         * Filters whether a boat_type term is excluded from quiz results.
         *
         * @param bool     $excluded Whether the term is excluded.
         * @param \WP_Term $term     Boat type term.
         */
        return (bool) apply_filters( 'dc_boat_quiz_exclude_type', $excluded, $term );
    }

    /**
     * Returns the last significant word of a term's display name.
     *
     * @param \WP_Term $term Boat type term.
     * @return string Head noun, or empty when the name has no usable words.
     */
    private static function head_noun( \WP_Term $term ): string {
        $words = preg_split(
            '/[^a-z0-9]+/',
            strtolower( wp_strip_all_tags( html_entity_decode( $term->name ) ) ),
            -1,
            PREG_SPLIT_NO_EMPTY
        ) ?: [];

        // Drop trailing filler words ("Boats for Sale", "Boats & More").
        $filler = [ 'for', 'sale', 'and', 'or', 'more', 'other', 'new', 'used', 'all' ];
        while ( ! empty( $words ) && in_array( end( $words ), $filler, true ) ) {
            array_pop( $words );
        }

        return empty( $words ) ? '' : (string) end( $words );
    }

    /**
     * Checks whether a word appears in a list, tolerating simple plurals.
     *
     * @param string   $word       Word to find.
     * @param string[] $candidates Words to search.
     * @return bool True on a match.
     */
    private static function matches_word( string $word, array $candidates ): bool {
        foreach ( $candidates as $candidate ) {
            $candidate = strtolower( trim( (string) $candidate ) );
            if ( $candidate === '' ) {
                continue;
            }
            if ( $word === $candidate
                || $word === $candidate . 's'
                || $word === $candidate . 'es'
                || $candidate === $word . 's'
                || $candidate === $word . 'es' ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Splits a term's slug and name into unique lowercase word tokens.
     *
     * @param \WP_Term $term Boat type term.
     * @return string[] Tokens.
     */
    private static function tokenize( \WP_Term $term ): array {
        $parts = preg_split(
            '/[^a-z0-9]+/',
            strtolower( $term->slug . ' ' . wp_strip_all_tags( html_entity_decode( $term->name ) ) ),
            -1,
            PREG_SPLIT_NO_EMPTY
        );

        return array_values( array_unique( $parts ?: [] ) );
    }

    /**
     * Lowercases a string and strips everything but letters and digits.
     *
     * @param string $value Input string.
     * @return string Normalized string.
     */
    private static function normalize( string $value ): string {
        return preg_replace( '/[^a-z0-9]+/', '', strtolower( $value ) ) ?? '';
    }

    /**
     * Counts, per boat_type, the visible boats inside a price range.
     *
     * @param string $price_range_slug Price range term slug, '' or 'any'.
     * @return array<string,int> Counts keyed by boat_type slug; empty when no budget is given.
     */
    private static function get_price_fit_counts( string $price_range_slug ): array {
        if ( $price_range_slug === '' || $price_range_slug === 'any' ) {
            return [];
        }

        $raw    = Inventory::getTaxonomyValuesForBoatType( [ 'price_range' => [ $price_range_slug ] ] );
        $counts = [];

        foreach ( $raw['terms'] ?? [] as $item ) {
            $counts[ $item['term']->slug ] = (int) $item['count'];
        }

        return $counts;
    }

    /**
     * Converts an in-budget inventory count into a score adjustment.
     *
     * Zero boats returns the out-of-budget penalty; otherwise the in-budget bonus
     * plus a capped depth bonus.
     *
     * @param int   $count  Visible boats of this type inside the budget.
     * @param array $config Merged quiz config.
     * @return int Score adjustment.
     */
    private static function price_fit_adjustment( int $count, array $config ): int {
        $settings = $config['price_fit'] ?? [];

        $bonus     = (int) ( $settings['in_budget_bonus'] ?? 4 );
        $penalty   = (int) ( $settings['out_of_budget_penalty'] ?? 6 );
        $depth_cap = (int) ( $settings['depth_bonus_cap'] ?? 2 );
        $per_depth = max( 1, (int) ( $settings['boats_per_depth_point'] ?? 3 ) );

        if ( $count <= 0 ) {
            return -$penalty;
        }

        return $bonus + min( $depth_cap, (int) floor( $count / $per_depth ) );
    }

    // ── Inventory lookup ─────────────────────────────────────────────────────

    /**
     * Fetches matching boats to display on the result screen.
     *
     * Boats inside the chosen budget come first. If there are not enough, it widens
     * to neighboring price ranges (up to 'widen_tiers'), then to any boat of the type.
     *
     * @param string     $boat_type_slug   Slug of the recommended boat_type term.
     * @param string     $price_range_slug Slug of the chosen price_range term, '' or 'any'.
     * @param int        $count            Optional. Max number of boats. Default 3.
     * @param array|null $meta             Optional. Receives [ 'budget_respected' => bool, 'widened' => bool ].
     * @return int[] Post IDs.
     */
    public static function get_matching_boats( string $boat_type_slug, string $price_range_slug, int $count = 3, ?array &$meta = null ): array {
        $has_budget = $price_range_slug && $price_range_slug !== 'any';
        $meta       = [ 'budget_respected' => true, 'widened' => false ];

        $ids = self::query_boats( $boat_type_slug, $has_budget ? [ $price_range_slug ] : [], $count );

        if ( count( $ids ) >= $count || ! $has_budget ) {
            return array_slice( $ids, 0, $count );
        }

        $config = self::load_config();
        $tiers  = max( 0, (int) ( $config['price_fit']['widen_tiers'] ?? 1 ) );

        // Widen through neighboring price tiers, closest first.
        foreach ( self::get_adjacent_price_ranges( $price_range_slug, $tiers ) as $slug ) {
            $found = self::query_boats( $boat_type_slug, [ $slug ], $count - count( $ids ), $ids );

            if ( $found ) {
                $ids            = array_merge( $ids, $found );
                $meta['widened'] = true;
            }

            if ( count( $ids ) >= $count ) {
                return array_slice( $ids, 0, $count );
            }
        }

        // Fall back to any boat of the type and flag the budget as not respected.
        $found = self::query_boats( $boat_type_slug, [], $count - count( $ids ), $ids );

        if ( $found ) {
            $ids                        = array_merge( $ids, $found );
            $meta['budget_respected']   = false;
        }

        return array_slice( $ids, 0, $count );
    }

    /**
     * Queries visible boats of a type, optionally constrained by price, highest price first.
     *
     * @param string   $boat_type_slug    Boat type term slug.
     * @param string[] $price_range_slugs Price range slugs; empty for no price constraint.
     * @param int      $count             Max number of boats.
     * @param int[]    $exclude           Optional. Post IDs to exclude. Default empty.
     * @return int[] Post IDs.
     */
    private static function query_boats( string $boat_type_slug, array $price_range_slugs, int $count, array $exclude = [] ): array {
        if ( $count < 1 ) {
            return [];
        }

        $tax_query = [
            'relation' => 'AND',
            [
                'taxonomy' => 'boat_type',
                'field'    => 'slug',
                'terms'    => [ $boat_type_slug ],
            ],
        ];

        if ( ! empty( $price_range_slugs ) ) {
            $tax_query[] = [
                'taxonomy' => 'price_range',
                'field'    => 'slug',
                'terms'    => $price_range_slugs,
            ];
        }

        $args = [
            'post_type'      => 'boat',
            'post_status'    => 'publish',
            'posts_per_page' => $count,
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

        if ( ! empty( $exclude ) ) {
            $args['post__not_in'] = $exclude;
        }

        $query = new \WP_Query( $args );
        $ids   = $query->posts;
        wp_reset_postdata();

        return $ids;
    }

    /**
     * Returns the price range slugs nearest to the given one, closest first.
     *
     * @param string $price_range_slug Price range term slug.
     * @param int    $max_offset       Optional. Tiers to travel in each direction. Default 1.
     * @return string[] Price range slugs.
     */
    private static function get_adjacent_price_ranges( string $price_range_slug, int $max_offset = 1 ): array {
        $slugs = array_column( array_column( self::get_ordered_price_ranges(), 'term' ), 'slug' );

        $index = array_search( $price_range_slug, $slugs, true );

        if ( $index === false || $max_offset < 1 ) {
            return [];
        }

        $neighbours = [];
        $limit      = min( $max_offset, count( $slugs ) - 1 );

        for ( $offset = 1; $offset <= $limit; $offset++ ) {
            foreach ( [ $index - $offset, $index + $offset ] as $i ) {
                if ( isset( $slugs[ $i ] ) ) {
                    $neighbours[] = $slugs[ $i ];
                }
            }
        }

        return $neighbours;
    }

    /**
     * Builds the inventory page URL with quiz result filters applied.
     *
     * The page comes from the dealers_choice_inventory_page_id option, then the
     * page at /inventory/, then home_url( '/inventory/' ).
     *
     * @param string $boat_type_slug   Boat type term slug.
     * @param string $price_range_slug Price range term slug, '' or 'any'.
     * @return string Inventory URL.
     */
    public static function build_inventory_url( string $boat_type_slug, string $price_range_slug ): string {
        $inventory_page_id = (int) get_option( 'dealers_choice_inventory_page_id', 0 );

        if ( ! $inventory_page_id ) {
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
     * Returns the result explanation copy for a boat type term.
     *
     * Uses config result_copy, then the term description, then a generic sentence.
     *
     * @param \WP_Term $term Boat type term.
     * @return string Unescaped copy.
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

    /** Returns the cruising (pontoon at sunset) illustration SVG. */
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

    /** Returns the fishing boat illustration SVG. */
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

    /** Returns the watersports tow boat illustration SVG. */
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

    /** Returns the adventure cruiser illustration SVG. */
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

    /** Returns the small crew (2 people) illustration SVG. */
    private static function svg_crew_small(): string {
        return '<svg viewBox="0 0 120 80" fill="none" aria-hidden="true">
            <circle cx="48" cy="28" r="10" fill="#bae6fd" opacity="0.8"/>
            <path d="M36 52 Q48 44 60 52 L63 68 L33 68Z" fill="#7dd3fc" opacity="0.6"/>
            <circle cx="74" cy="30" r="10" fill="#bae6fd" opacity="0.7"/>
            <path d="M62 54 Q74 46 86 54 L89 68 L59 68Z" fill="#7dd3fc" opacity="0.5"/>
        </svg>';
    }

    /** Returns the medium crew (4 people) illustration SVG. */
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

    /** Returns the large crew (6 people) illustration SVG. */
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

    /** Returns the Comfort & Space priority illustration SVG. */
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

    /** Returns the Speed & Performance priority illustration SVG. */
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

    /** Returns the Fishing Features priority illustration SVG. */
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

    /** Returns the Range & Exploration priority illustration SVG. */
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
