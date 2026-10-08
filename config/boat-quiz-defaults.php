<?php
/**
 * Boat Quiz — Default Scoring Configuration
 *
 * This file returns the default algorithm configuration for the Boat Quiz feature.
 * Scores are applied when matching quiz answers to boat_type taxonomy terms.
 *
 * ── HOW TO OVERRIDE ──────────────────────────────────────────────────────────
 * Create a file at:
 *   {your-theme}/dealerschoice/boat-quiz-config.php
 *
 * That file should return an array with the same structure as this one.
 * Your config is deep-merged over these defaults — your values win.
 *
 * The 'scoring' key maps boat_type taxonomy SLUGS (as they exist on the site)
 * to per-question weights. The higher the number, the stronger the match.
 *
 * The 'smart_keywords' key is a fallback: if a boat_type term's slug or name
 * contains one of these keyword strings, the associated score boosts are applied
 * automatically — no explicit 'scoring' entry needed. This keeps the quiz
 * site-agnostic for terms like "pontoon-boats", "offshore-fishing", etc.
 *
 * Keywords match on WORD boundaries, and only the best keyword score is taken
 * per question — never the sum. A term named "Wake Surf" therefore scores like
 * a wake boat, not like a wake boat plus a surf boat. Keep each keyword block
 * complete (activity + crew + priorities) so keyword-matched types stay
 * competitive with types listed in the explicit matrix above.
 *
 * Questions / answer keys:
 *   activity   : cruising | fishing | watersports | adventure
 *   crew       : small (1–4) | medium (5–8) | large (9+)
 *   priorities : comfort | performance | fishing | adventure
 *   budget     : pulled dynamically from price_range taxonomy (slug used as key).
 *                Price is scored from live inventory (see 'price_fit'), so you
 *                only need explicit 'budget' weights for unusual cases.
 *
 * @package DealersChoice
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

return [

    /*
    |--------------------------------------------------------------------------
    | Explicit Scoring Matrix
    |--------------------------------------------------------------------------
    | Key = boat_type taxonomy slug exactly as it exists in WordPress.
    | Each sub-key maps a question name → answer value → score (integer).
    | Missing question/answer combos score 0 (no bonus, no penalty).
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
    | If a boat_type term slug or name CONTAINS one of these keyword strings
    | (case-insensitive), the scores below are automatically applied.
    | This handles term slugs like "pontoon-boats", "offshore-fishing", etc.
    | without requiring an explicit 'scoring' entry above.
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
            'match'      => 'exact',   // avoid matching unrelated compounds
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
            'match'      => 'exact',   // 'ski' would otherwise match "skiff"
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
            'match'      => 'exact',   // avoid matching unrelated compounds
            'activity'   => [ 'fishing' => 3 ],
            'priorities' => [ 'fishing' => 3 ],
        ],

        'console' => [
            'match'      => 'exact',   // avoid matching unrelated compounds
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
            'match'      => 'exact',   // avoid matching unrelated compounds
            'activity'   => [ 'fishing' => 3 ],
            'crew'       => [ 'small' => 4 ],
            'priorities' => [ 'fishing' => 4 ],
        ],

        'bay' => [
            'match'      => 'exact',   // 'bay' would otherwise match "bayliner"
            'activity'   => [ 'fishing' => 3 ],
            'priorities' => [ 'fishing' => 3 ],
        ],

        'sport' => [
            'match'      => 'exact',   // 'sport' would otherwise match "sportsman"
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
    | Dealers commonly file accessories under the boat_type taxonomy so they
    | appear in inventory listings — surfboards, foils, trailers, apparel. Those
    | are legitimate inventory but they are never a valid answer to "which boat
    | suits me", so any boat_type term matching one of these words is dropped
    | from quiz scoring entirely (it is not shown as a match or as a runner-up).
    |
    | A term that also reads as a vessel is kept, so "Wakeboard Boats" survives
    | while "Electric Wake Surfboards" does not.
    |
    | Set this to an empty array to consider every boat_type term, or filter
    | per-term with the 'dc_boat_quiz_exclude_type' hook.
    |--------------------------------------------------------------------------
    */
    'excluded_keywords' => [
        // Deliberately conservative: only words that cannot describe a boat.
        // "Wakeboard", "Wakesurf" and "Waterski" are NOT listed, because plenty
        // of dealers use them as the name of a tow-boat category.
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
    | The quiz asks for a budget, so the budget has to change the answer. Rather
    | than hard-coding what each boat type "should" cost — impossible to do
    | site-agnostically, since price_range slugs differ per dealer — we count how
    | many visible boats of each type actually sit inside the chosen price range
    | and score on that. A type the dealer doesn't stock at that price is not a
    | sensible recommendation for that price, whatever its name suggests.
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
    |                          can't fill it. 0 disables widening. Keep this
    |                          low — travelling the whole ladder is what let a
    |                          "$200k+" answer come back with $18k stock.
    |
    | If NO boat type has inventory in the chosen range, price scoring is skipped
    | entirely so a thin inventory can't produce a nonsense result.
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
    | "why" text displayed on the result card for each boat type slug.
    | Falls back to the term description if no entry exists here.
    | Override in your theme config file.
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
