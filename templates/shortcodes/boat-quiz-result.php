<?php
/**
 * Boat quiz result card.
 *
 * Shows the recommended boat type, matching boats, a link to the filtered
 * inventory page, runner-up types and the optional Gravity Forms lead form.
 * Rendered server-side and returned as HTML via AJAX.
 *
 * Override by copying to your-theme/dealerschoice/shortcodes/boat-quiz-result.php.
 *
 * @var WP_Term   $top_term        Best-matching boat_type term.
 * @var string    $why_text        Explanation of the recommendation.
 * @var string    $inventory_url   Filtered inventory page URL.
 * @var int[]     $matching_boats  Post IDs of matching boats.
 * @var WP_Term[] $alt_terms       Runner-up boat_type terms.
 * @var int       $gravity_form_id Gravity Forms lead form ID, or 0 for none.
 * @var array     $answers         Quiz answers keyed by question key.
 * @var array     $boat_meta       Flags for $matching_boats: 'budget_respected' (bool), 'widened' (bool).
 *
 * @package DealersChoice
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Count boats matching the inventory URL filters for the "View All" label.
$_budget_slug   = $answers['budget'] ?? '';
$_count_tax     = [
    'relation' => 'AND',
    [
        'taxonomy' => 'boat_type',
        'field'    => 'slug',
        'terms'    => [ $top_term->slug ],
    ],
];
if ( $_budget_slug && $_budget_slug !== 'any' ) {
    $_count_tax[] = [
        'taxonomy' => 'price_range',
        'field'    => 'slug',
        'terms'    => [ $_budget_slug ],
    ];
}
$matching_count = (int) ( new \WP_Query( [
    'post_type'      => 'boat',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'fields'         => 'ids',
    'tax_query'      => $_count_tax,
    'meta_query'     => [ 'relation' => 'OR',
        [ 'key' => 'do_not_show_on_public_website', 'value' => '1', 'compare' => '!=' ],
        [ 'key' => 'do_not_show_on_public_website', 'compare' => 'NOT EXISTS' ],
    ],
    'no_found_rows'  => false,
] ) )->found_posts;
wp_reset_postdata();

// With no boats in the selected price range, count and link by boat type only.
if ( $matching_count === 0 && $_budget_slug && $_budget_slug !== 'any' ) {
    $matching_count = (int) ( new \WP_Query( [
        'post_type'      => 'boat',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'tax_query'      => [ [
            'taxonomy' => 'boat_type',
            'field'    => 'slug',
            'terms'    => [ $top_term->slug ],
        ] ],
        'meta_query'     => [ 'relation' => 'OR',
            [ 'key' => 'do_not_show_on_public_website', 'value' => '1', 'compare' => '!=' ],
            [ 'key' => 'do_not_show_on_public_website', 'compare' => 'NOT EXISTS' ],
        ],
        'no_found_rows'  => false,
    ] ) )->found_posts;
    wp_reset_postdata();
    $inventory_url = \DC\BoatQuiz::build_inventory_url( $top_term->slug, '' );
}
?>
<div class="dc-quiz-result-card">
    <div class="dc-quiz-result-body">

        <p class="dc-quiz-result-eyebrow">
            <?php esc_html_e( 'Your Perfect Match', 'dealerschoice' ); ?>
        </p>

        <h2 class="dc-quiz-result-title">
            <?php echo esc_html( $top_term->name ); ?>
        </h2>

        <?php if ( $why_text ) : ?>
            <p class="dc-quiz-result-why"><?php echo esc_html( $why_text ); ?></p>
        <?php endif; ?>

        <?php /* CTA row */ ?>
        <div class="dc-quiz-result-actions dc-mb">
            <a href="<?php echo esc_url( $inventory_url ); ?>" class="dc-quiz-btn-primary">
                <?php
                if ( $matching_count > 0 ) {
                    printf(
                        /* translators: 1: count, 2: boat type name */
                        esc_html__( 'View All %1$d %2$s', 'dealerschoice' ),
                        $matching_count,
                        esc_html( $top_term->name )
                    );
                } else {
                    esc_html_e( 'View Inventory', 'dealerschoice' );
                }
                ?>
                <i class="fa-light fa-arrow-right-long" aria-hidden="true"></i>
            </a>
            <button type="button" class="dc-quiz-btn-secondary" id="dc-quiz-retake-btn">
                <?php esc_html_e( 'Start Over', 'dealerschoice' ); ?>
            </button>
        </div>

        <?php /* ── Matching boats slider ────────────────────────── */ ?>
        <?php if ( ! empty( $matching_boats ) ) :
            $slider_id = 'dc-quiz-result-slider-' . uniqid();
        ?>
            <div class="dc-quiz-result-boats-section">
                <p class="dc-quiz-result-boats-heading">
                    <?php esc_html_e( 'Boats You Might Like', 'dealerschoice' ); ?>
                </p>
                <?php
                // Note when the slider boats fall outside the selected price range.
                if ( ! empty( $boat_meta ) && empty( $boat_meta['budget_respected'] )
                    && ! empty( $_budget_slug ) && $_budget_slug !== 'any' ) : ?>
                    <p class="dc-quiz-result-boats-note">
                        <?php esc_html_e( 'We don\'t have this type in your selected price range right now, so here are the closest options we do have.', 'dealerschoice' ); ?>
                    </p>
                <?php elseif ( ! empty( $boat_meta['widened'] ) ) : ?>
                    <p class="dc-quiz-result-boats-note">
                        <?php esc_html_e( 'Includes a few options just outside your selected price range.', 'dealerschoice' ); ?>
                    </p>
                <?php endif; ?>
                <div class="dealerschoice-shortcode dealerschoice-slider dc-mb">
                    <div class="boat-slider" id="<?php echo esc_attr( $slider_id ); ?>" data-slick='{"slidesToShow": 2}'>
                        <?php foreach ( $matching_boats as $boat_id ) :
                            $GLOBALS['post'] = get_post( $boat_id );
                            if ( ! $GLOBALS['post'] ) {
                                continue;
                            }
                            setup_postdata( $GLOBALS['post'] );
                            \DC\Template_Loader::get_template_part( 'inventory', 'slide' );
                            wp_reset_postdata();
                        endforeach; ?>
                    </div>
                    <div class="button-wrapper" id="<?php echo esc_attr( $slider_id ); ?>-buttons"></div>
                </div>
            </div>
        <?php endif; ?>

        <?php /* ── Runner-up alternatives ─────────────────────── */ ?>
        <?php if ( ! empty( $alt_terms ) ) : ?>
            <div class="dc-quiz-alts">
                <p class="dc-quiz-alts-label">
                    <?php esc_html_e( 'Also Worth Considering', 'dealerschoice' ); ?>
                </p>
                <div class="dc-quiz-alts-list">
                    <?php foreach ( $alt_terms as $alt ) :
                        $alt_url = \DC\BoatQuiz::build_inventory_url( $alt->slug, '' );
                    ?>
                        <a href="<?php echo esc_url( $alt_url ); ?>" class="dc-quiz-alt-tag">
                            <?php echo esc_html( $alt->name ); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php /* ── Gravity Forms lead capture ────────────────── */ ?>
        <?php if ( ! empty( $gravity_form_id ) && function_exists( 'gravity_form' ) ) : ?>
            <div class="dc-quiz-lead-form">
                <p class="dc-quiz-lead-form-heading">
                    <?php esc_html_e( "Ready to Take the Next Step?", 'dealerschoice' ); ?>
                </p>
                <?php
                // Quiz answers as dynamic population values for fields named dc_quiz_*.
                $gf_field_values = [
                    'dc_quiz_activity'   => $answers['activity']   ?? '',
                    'dc_quiz_crew'       => $answers['crew']        ?? '',
                    'dc_quiz_priorities' => $answers['priorities']  ?? '',
                    'dc_quiz_budget'     => $answers['budget']      ?? '',
                    'dc_quiz_result'     => $top_term->name         ?? '',
                ];
                gravity_form( $gravity_form_id, false, false, false, $gf_field_values, true, 1, true );
                ?>
            </div>
        <?php endif; ?>

    </div>
</div>
