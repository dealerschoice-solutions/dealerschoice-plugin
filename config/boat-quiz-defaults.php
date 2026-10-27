<?php
/**
 * Default Boat Quiz scoring configuration.
 *
 * Scores match quiz answers to boat_type taxonomy terms. A theme can return an
 * array with the same structure from {theme}/dealerschoice/boat-quiz-config.php;
 * it is deep-merged over these defaults.
 *
 * Questions / answer keys:
 *   activity   : cruising | fishing | watersports | adventure
 *   crew       : small (1–4) | medium (5–8) | large (9+)
 *   priorities : comfort | performance | fishing | adventure
 *   budget     : price_range term slugs. Scored from live inventory (see
 *                'price_fit'); explicit 'budget' weights are optional.
 *
 * @package DealersChoice
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

return [

    /*
    |--------------------------------------------------------------------------
    | Explicit Scoring Matrix
    |--------------------------------------------------------------------------
    | Key = boat_type taxonomy slug. Each entry maps question => answer => score
    | (integer). Missing question/answer combinations score 0.
    |--------------------------------------------------------------------------
    */
    'scoring' => [

        'pontoon' => [
            'activity'   => [ 'cruising' => 3, 'fishing' => 1, 'watersports' => 1, 'adventure' => 1 ],
            'crew'       => [ 'small' => 1, 'medium' => 3, 'large' => 4 ],
            'priorities' => [ 'comfort' => 4, 'performance' => 1, 'fishing' => 1, 'adventure' => 1 ],
        ],

        'bowrider' => [
            'activity'   => [ 'cruising' => 2, 'fishing' => 1, 'watersports' => 2, 'adventure' => 1 ],
            'crew'       => [ 'small' => 3, 'medium' => 2, 'large' => 1 ],
            'priorities' => [ 'comfort' => 2, 'performance' => 3, 'fishing' => 0, 'adventure' => 1 ],
        ],

        'center-console' => [
            'activity'   => [ 'cruising' => 1, 'fishing' => 4, 'watersports' => 0, 'adventure' => 2 ],
            'crew'       => [ 'small' => 2, 'medium' => 2, 'large' => 1 ],
            'priorities' => [ 'comfort' => 0, 'performance' => 2, 'fishing' => 4, 'adventure' => 2 ],
        ],

        'skiff' => [
            'activity'   => [ 'cruising' => 0, 'fishing' => 4, 'watersports' => 0, 'adventure' => 1 ],
            'crew'       => [ 'small' => 4, 'medium' => 1, 'large' => 0 ],
            'priorities' => [ 'comfort' => 0, 'performance' => 1, 'fishing' => 4, 'adventure' => 1 ],
        ],

        'ski-wake' => [
            'activity'   => [ 'cruising' => 0, 'fishing' => 0, 'watersports' => 5, 'adventure' => 1 ],
            'crew'       => [ 'small' => 2, 'medium' => 3, 'large' => 2 ],
            'priorities' => [ 'comfort' => 1, 'performance' => 4, 'fishing' => 0, 'adventure' => 0 ],
        ],

        'deck-boat' => [
            'activity'   => [ 'cruising' => 2, 'fishing' => 1, 'watersports' => 2, 'adventure' => 1 ],
            'crew'       => [ 'small' => 1, 'medium' => 3, 'large' => 3 ],
            'priorities' => [ 'comfort' => 3, 'performance' => 2, 'fishing' => 1, 'adventure' => 1 ],
        ],

        'cruiser' => [
            'activity'   => [ 'cruising' => 3, 'fishing' => 1, 'watersports' => 0, 'adventure' => 4 ],
            'crew'       => [ 'small' => 3, 'medium' => 2, 'large' => 1 ],
            'priorities' => [ 'comfort' => 3, 'performance' => 1, 'fishing' => 0, 'adventure' => 4 ],
        ],

        'jon-boat' => [
            'activity'   => [ 'cruising' => 0, 'fishing' => 4, 'watersports' => 0, 'adventure' => 1 ],
            'crew'       => [ 'small' => 4, 'medium' => 1, 'large' => 0 ],
            'priorities' => [ 'comfort' => 0, 'performance' => 0, 'fishing' => 4, 'adventure' => 1 ],
        ],

        'runabout' => [
            'activity'   => [ 'cruising' => 2, 'fishing' => 1, 'watersports' => 3, 'adventure' => 1 ],
            'crew'       => [ 'small' => 3, 'medium' => 2, 'large' => 0 ],
            'priorities' => [ 'comfort' => 2, 'performance' => 3, 'fishing' => 0, 'adventure' => 1 ],
        ],

        'tritoon' => [
            'activity'   => [ 'cruising' => 3, 'fishing' => 1, 'watersports' => 2, 'adventure' => 1 ],
            'crew'       => [ 'small' => 1, 'medium' => 3, 'large' => 4 ],
            'priorities' => [ 'comfort' => 4, 'performance' => 2, 'fishing' => 1, 'adventure' => 1 ],
        ],

        'bay-boat' => [
            'activity'   => [ 'cruising' => 1, 'fishing' => 4, 'watersports' => 0, 'adventure' => 2 ],
            'crew'       => [ 'small' => 3, 'medium' => 2, 'large' => 0 ],
            'priorities' => [ 'comfort' => 1, 'performance' => 2, 'fishing' => 4, 'adventure' => 2 ],
        ],

        'sport-boat' => [
            'activity'   => [ 'cruising' => 2, 'fishing' => 0, 'watersports' => 3, 'adventure' => 2 ],
            'crew'       => [ 'small' => 3, 'medium' => 2, 'large' => 0 ],
            'priorities' => [ 'comfort' => 1, 'performance' => 4, 'fishing' => 0, 'adventure' => 1 ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Smart Keyword Fallback
    |--------------------------------------------------------------------------
    | Applies to boat_type terms with no 'scoring' entry. A keyword matches a
    | whole word in the term slug or name (case-insensitive), or a word prefix
    | when the term has no whole-word matches. 'match' => 'exact' disables
    | prefix matching for that keyword. Only the best keyword score per
    | question is used.
    |
    | Format: 'keyword' => [ 'question' => [ 'answer' => score, ... ], ... ]
    |--------------------------------------------------------------------------
    */
    'smart_keywords' => [

        'pontoon' => [
            'activity'   => [ 'cruising' => 3, 'watersports' => 1 ],
            'crew'       => [ 'large' => 4, 'medium' => 3 ],
            'priorities' => [ 'comfort' => 4 ],
        ],

        'tritoon' => [
            'activity'   => [ 'cruising' => 3, 'watersports' => 2 ],
            'crew'       => [ 'large' => 4, 'medium' => 3 ],
            'priorities' => [ 'comfort' => 4 ],
        ],

        'fish' => [
            'activity'   => [ 'fishing' => 3 ],
            'priorities' => [ 'fishing' => 3 ],
        ],

        'fishing' => [
            'activity'   => [ 'fishing' => 3 ],
            'priorities' => [ 'fishing' => 3 ],
        ],

        'bass' => [
            'match'      => 'exact',
            'activity'   => [ 'fishing' => 4 ],
            'crew'       => [ 'small' => 3 ],
            'priorities' => [ 'fishing' => 4 ],
        ],

        'wake' => [
            'activity'   => [ 'watersports' => 5, 'cruising' => 1 ],
            'crew'       => [ 'small' => 2, 'medium' => 3, 'large' => 2 ],
            'priorities' => [ 'performance' => 4, 'comfort' => 1 ],
        ],

        'ski' => [
            'match'      => 'exact',
            'activity'   => [ 'watersports' => 5, 'cruising' => 1 ],
            'crew'       => [ 'small' => 2, 'medium' => 3, 'large' => 2 ],
            'priorities' => [ 'performance' => 4, 'comfort' => 1 ],
        ],

        'surf' => [
            'activity'   => [ 'watersports' => 5, 'cruising' => 1 ],
            'crew'       => [ 'small' => 2, 'medium' => 3, 'large' => 2 ],
            'priorities' => [ 'performance' => 4, 'comfort' => 1 ],
        ],

        'tow' => [
            'activity'   => [ 'watersports' => 4 ],
            'crew'       => [ 'small' => 2, 'medium' => 3, 'large' => 2 ],
            'priorities' => [ 'performance' => 4 ],
        ],

        'cruiser' => [
            'activity'   => [ 'adventure' => 3, 'cruising' => 3 ],
            'priorities' => [ 'adventure' => 3, 'comfort' => 2 ],
        ],

        'cabin' => [
            'activity'   => [ 'adventure' => 3, 'cruising' => 2 ],
            'priorities' => [ 'adventure' => 3, 'comfort' => 2 ],
        ],

        'center' => [
            'match'      => 'exact',
            'activity'   => [ 'fishing' => 3 ],
            'priorities' => [ 'fishing' => 3 ],
        ],

        'console' => [
            'match'      => 'exact',
            'activity'   => [ 'fishing' => 2 ],
            'priorities' => [ 'fishing' => 2 ],
        ],

        'offshore' => [
            'activity'   => [ 'fishing' => 2, 'adventure' => 2 ],
            'priorities' => [ 'fishing' => 2, 'adventure' => 2 ],
        ],

        'inshore' => [
            'activity'   => [ 'fishing' => 3 ],
            'priorities' => [ 'fishing' => 3 ],
        ],

        'deck' => [
            'activity'   => [ 'cruising' => 2, 'watersports' => 2 ],
            'crew'       => [ 'medium' => 3, 'large' => 2 ],
            'priorities' => [ 'comfort' => 3 ],
        ],

        'bow' => [
            'activity'   => [ 'cruising' => 2, 'watersports' => 2 ],
            'crew'       => [ 'small' => 3, 'medium' => 2 ],
            'priorities' => [ 'performance' => 2, 'comfort' => 2 ],
        ],

        'runabout' => [
            'activity'   => [ 'cruising' => 2, 'watersports' => 3 ],
            'crew'       => [ 'small' => 3, 'medium' => 2 ],
            'priorities' => [ 'performance' => 3, 'comfort' => 2 ],
        ],

        'skiff' => [
            'activity'   => [ 'fishing' => 3 ],
            'crew'       => [ 'small' => 3 ],
            'priorities' => [ 'fishing' => 3 ],
        ],

        'jon' => [
            'match'      => 'exact',
            'activity'   => [ 'fishing' => 3 ],
            'crew'       => [ 'small' => 4 ],
            'priorities' => [ 'fishing' => 4 ],
        ],

        'bay' => [
            'match'      => 'exact',
            'activity'   => [ 'fishing' => 3 ],
            'priorities' => [ 'fishing' => 3 ],
        ],

        'sport' => [
            'match'      => 'exact',
            'activity'   => [ 'watersports' => 2, 'cruising' => 2 ],
            'crew'       => [ 'small' => 2 ],
            'priorities' => [ 'performance' => 3 ],
        ],

        'sail' => [
            'activity'   => [ 'adventure' => 3, 'cruising' => 3 ],
            'priorities' => [ 'adventure' => 3 ],
        ],

        'catamaran' => [
            'activity'   => [ 'adventure' => 3, 'cruising' => 3 ],
            'crew'       => [ 'medium' => 2, 'large' => 3 ],
            'priorities' => [ 'adventure' => 3, 'comfort' => 2 ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Non-Boat Categories
    |--------------------------------------------------------------------------
    | boat_type terms matching any of these words are excluded from quiz
    | results, unless the term also matches a 'vessel_keywords' word.
    | An empty array includes every boat_type term. Filter per term with
    | 'dc_boat_quiz_exclude_type'.
    |--------------------------------------------------------------------------
    */
    'excluded_keywords' => [
        'surfboard',
        'kneeboard',
        'paddleboard',
        'efoil',
        'hydrofoil',
        'foilboard',
        'towable',
        'tube',
        'trailer',
        'accessory',
        'accessories',
        'apparel',
        'clothing',
        'merchandise',
        'part',
        'parts',
        'gear',
        'electronics',
        'lift',
        'dock',
        'cover',
        'prop',
        'propeller',
        'motor',
        'engine',
        'lifejacket',
        'rental',
        'service',
    ],

    /*
    |--------------------------------------------------------------------------
    | Vessel Keywords
    |--------------------------------------------------------------------------
    | Words that mark a term as an actual boat, overriding 'excluded_keywords'.
    |--------------------------------------------------------------------------
    */
    'vessel_keywords' => [
        'boat', 'boats', 'pontoon', 'pontoons', 'tritoon', 'tritoons',
        'yacht', 'yachts', 'vessel', 'cruiser', 'cruisers', 'skiff',
        'runabout', 'bowrider', 'catamaran', 'sailboat',
    ],

    /*
    |--------------------------------------------------------------------------
    | Price Fit
    |--------------------------------------------------------------------------
    | Scores each boat type by how many visible boats of that type fall inside
    | the chosen price range.
    |
    |   hard_filter            When true, types with inventory in the budget
    |                          always outrank types without. Set false to make
    |                          price a weighted signal instead of a gate.
    |   in_budget_bonus        Added when the type has any boats in budget.
    |   out_of_budget_penalty  Subtracted when it has none.
    |   depth_bonus_cap        Max extra points for depth of selection.
    |   boats_per_depth_point  Boats in budget needed per depth point.
    |   widen_tiers            How many price tiers either side the result
    |                          slider may borrow from when the chosen range
    |                          can't fill it. 0 disables widening.
    |
    | Price scoring is skipped when no boat type has inventory in the chosen
    | range.
    |--------------------------------------------------------------------------
    */
    'price_fit' => [
        'hard_filter'           => true,
        'in_budget_bonus'       => 4,
        'out_of_budget_penalty' => 6,
        'depth_bonus_cap'       => 2,
        'boats_per_depth_point' => 3,
        'widen_tiers'           => 1,
    ],

    /*
    |--------------------------------------------------------------------------
    | Result Copy
    |--------------------------------------------------------------------------
    | "Why" text shown on the result card, keyed by boat type slug. Falls back
    | to the term description when a slug has no entry.
    |--------------------------------------------------------------------------
    */
    'result_copy' => [

        'pontoon' => "With your love of entertaining a larger group on calmer waters, a Pontoon is built for you. Wide, stable decks with lounge seating mean everyone's comfortable all day. Modern tritoon configurations handle larger lakes with ease.",

        'bowrider' => "A Bow Rider is the Swiss Army knife of boating — great for cruising, versatile enough for light water sports, and comfortable for families. The open bow seating makes it a social, fun ride that performs well on lakes, rivers, and moderate coastal water.",

        'center-console' => "For fishing in coastal or offshore waters, nothing beats a Center Console. The 360° fishable deck, live wells, and rod holders give you full access no matter which direction the fish are running — and they handle chop with confidence.",

        'skiff' => "For freshwater and inshore fishing on rivers, creeks, and shallow flats, a Skiff is the perfect tool. Ultra-low draft gets you into the skinny water where fish hide, and it's easy to trailer and launch solo.",

        'ski-wake' => "If water sports are your passion, a purpose-built tow boat is your answer. Engineered to produce the perfect wake for slalom courses or big aerials, with ballast systems, wake towers, and surf tabs that make every ride extraordinary.",

        'deck-boat' => "A Deck Boat gives you the best of both worlds: the wide open deck space of a pontoon with the sporty V-hull handling of a bow rider. Great for families who want to cruise and do a bit of everything — with room for everyone.",

        'cruiser' => "For overnight trips and extended coastal adventures, a Cruiser has everything you need on board — sleeping quarters, a galley, and full amenities. These boats let you explore far and wide with serious comfort, and handle offshore conditions confidently.",

        'jon-boat' => "For casual freshwater fishing and back-country exploration, a Jon Boat is unbeatable. Simple, lightweight, and capable of getting into spots no other boat can reach — they're the workhorse of calm-water angling.",

        'tritoon' => "With your crowd size and love of the water, a Tritoon is the premium choice. The extra center pontoon delivers superior stability, higher speeds, and the confidence to handle larger lakes with whitecaps — without sacrificing entertaining space.",

        'bay-boat' => "A Bay Boat is engineered for the inshore angler who needs to cover ground fast. Shallow draft lets you pole the flats, while enough hull to handle bay chop means you're never limited by conditions.",

        'sport-boat' => "A Sport Boat puts performance and fun at the top of the list. Responsive handling, a sporty profile, and enough speed to make every outing feel like an event — perfect for the boater who likes to move.",

        'runabout' => "A Runabout is the classic day-on-the-water boat — easy to handle, fun to drive, and versatile enough for everything from lazy cruises to pulling a tube. An ideal first boat or go-to for a small crew.",

    ],

];
