<?php
/**
 * Boat Quiz Template
 *
 * Renders the step-by-step boat finder quiz form.
 *
 * ── THEME OVERRIDE ───────────────────────────────────────────────────────────
 * Copy this file to {your-theme}/dealerschoice/shortcodes/boat-quiz.php
 * and modify to customise the quiz layout or question presentation for a
 * specific client without editing the plugin.
 *
 * ── AVAILABLE VARIABLES ──────────────────────────────────────────────────────
 * $title        (string) Quiz heading — from shortcode attribute or default.
 * $subtitle     (string) Sub-heading below title.
 * $submit_label (string) Label on the final submit button.
 * $questions    (array)  Question definitions from DC\BoatQuiz::get_questions().
 *               Each question: [ 'key', 'number', 'text', 'cols', 'options' ]
 *               Each option:   [ 'value', 'label', 'description', 'svg'|'dollars' ]
 * $nonce        (string) wp_create_nonce('dealerschoice_quiz_nonce')
 * $total_steps  (int)    Total number of question steps (for JS).
 *
 * @package DealersChoice
 * @subpackage Templates
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** @var string $title */
/** @var string $subtitle */
/** @var string $submit_label */
/** @var array  $questions */
/** @var string $nonce */
/** @var int    $total_steps */
/** @var int    $gravity_form_id */
?>
<div
    class="dc-quiz-wrap"
    id="dc-boat-quiz"
    data-nonce="<?php echo esc_attr( $nonce ); ?>"
    data-total-steps="<?php echo esc_attr( $total_steps ); ?>"
    data-gravity-form-id="<?php echo esc_attr( $gravity_form_id ); ?>"
>

    <?php /* ── Quiz form ─────────────────────────────────────────── */ ?>
    <div id="dc-quiz-form">

        <?php /* Header */ ?>
        <div class="dc-quiz-header">
            <p class="dc-quiz-eyebrow"><?php esc_html_e( 'Boat Finder', 'dealerschoice' ); ?></p>
            <h2 class="dc-quiz-title"><?php echo esc_html( $title ); ?></h2>
            <?php if ( $subtitle ) : ?>
                <p class="dc-quiz-subtitle"><?php echo esc_html( $subtitle ); ?></p>
            <?php endif; ?>
        </div>

        <?php /* Progress bar */ ?>
        <div class="dc-quiz-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
            <div class="dc-quiz-progress-fill" id="dc-quiz-progress-fill"></div>
        </div>

        <?php /* Steps */ ?>
        <?php foreach ( $questions as $step_index => $question ) :
            $step_num    = $step_index + 1;
            $is_first    = $step_index === 0;
            $is_last     = $step_index === ( $total_steps - 1 );
            $step_id     = 'dc-quiz-step-' . esc_attr( $question['key'] );
            $active_class = $is_first ? ' is-active' : '';
        ?>
        <div
            class="dc-quiz-step<?php echo $active_class; ?>"
            id="<?php echo esc_attr( $step_id ); ?>"
            data-step="<?php echo esc_attr( $step_num ); ?>"
            data-question="<?php echo esc_attr( $question['key'] ); ?>"
        >

            <?php /* Question label */ ?>
            <div class="dc-quiz-question-label">
                <span class="dc-quiz-question-num"
                      aria-hidden="true"><?php echo esc_html( $question['number'] ); ?></span>
                <h3 class="dc-quiz-question-text"><?php echo wp_kses_post( $question['text'] ); ?></h3>
            </div>

            <?php /* Options grid */ ?>
            <div
                class="dc-quiz-grid cols-<?php echo esc_attr( $question['cols'] ); ?><?php echo ! empty( $question['conditional'] ) ? ' dc-quiz-grid--conditional' : ''; ?>"
                role="radiogroup"
                aria-labelledby="<?php echo esc_attr( $step_id ); ?>-label"
            >
                <?php foreach ( $question['options'] as $option ) :
                    $has_group      = ! empty( $option['group'] );
                    // Include the group slug in the id so that shared value slugs
                    // (e.g. 'performance_handling' in both cruising and adventure)
                    // never produce duplicate id/for pairs in the DOM.
                    $opt_id         = 'dc-quiz-opt-' . esc_attr( $question['key'] )
                                      . ( $has_group ? '-' . esc_attr( $option['group'] ) : '' )
                                      . '-' . esc_attr( $option['value'] );
                    $option_classes = 'dc-quiz-option' . ( $has_group ? ' dc-quiz-option--conditional' : '' );
                ?>
                <label
                    class="<?php echo esc_attr( $option_classes ); ?>"
                    for="<?php echo esc_attr( $opt_id ); ?>"
                    <?php if ( $has_group ) : ?>data-group="<?php echo esc_attr( $option['group'] ); ?>"<?php endif; ?>
                >
                    <input
                        type="radio"
                        id="<?php echo esc_attr( $opt_id ); ?>"
                        name="dc_quiz_<?php echo esc_attr( $question['key'] ); ?>"
                        value="<?php echo esc_attr( $option['value'] ); ?>"
                    >

                    <?php if ( ! empty( $option['svg'] ) ) : ?>
                        <div class="dc-quiz-svg-wrap">
                            <?php echo $option['svg']; // phpcs:ignore WordPress.Security.EscapeOutput -- trusted SVG from class ?>
                        </div>
                    <?php elseif ( isset( $option['dollars'] ) ) : ?>
                        <div class="dc-quiz-dollar-wrap">
                            <span><?php echo esc_html( $option['dollars'] ); ?></span>
                        </div>
                    <?php endif; ?>

                    <span class="dc-quiz-opt-title"><?php echo wp_kses_post( $option['label'] ); ?></span>

                    <?php if ( ! empty( $option['description'] ) ) : ?>
                        <span class="dc-quiz-opt-desc"><?php echo wp_kses_post( $option['description'] ); ?></span>
                    <?php endif; ?>

                    <span class="dc-quiz-check" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="3">
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>
                    </span>
                </label>
                <?php endforeach; ?>
            </div>

            <?php /* Navigation row */ ?>
            <div class="dc-quiz-nav">
                <button
                    type="button"
                    class="dc-quiz-btn-back"
                    data-action="back"
                    <?php echo $is_first ? 'style="visibility:hidden"' : ''; ?>
                    aria-label="<?php esc_attr_e( 'Previous question', 'dealerschoice' ); ?>"
                >
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none"
                         stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                        <path d="M19 12H5M12 19l-7-7 7-7"/>
                    </svg>
                    <?php esc_html_e( 'Back', 'dealerschoice' ); ?>
                </button>

                <span class="dc-quiz-step-counter">
                    <?php
                    printf(
                        /* translators: 1: current step, 2: total steps */
                        esc_html__( '%1$d of %2$d', 'dealerschoice' ),
                        $step_num,
                        $total_steps
                    );
                    ?>
                </span>

                <?php if ( $is_last ) : ?>
                <button
                    type="button"
                    class="dc-quiz-btn-next"
                    id="dc-quiz-submit-btn"
                    data-action="submit"
                    disabled
                >
                    <?php echo esc_html( $submit_label ); ?>
                    <i class="fa-light fa-arrow-right-long" aria-hidden="true"></i>
                </button>
                <?php else : ?>
                <button
                    type="button"
                    class="dc-quiz-btn-next"
                    data-action="next"
                    disabled
                >
                    <?php esc_html_e( 'Next', 'dealerschoice' ); ?>
                    <i class="fa-light fa-arrow-right-long" aria-hidden="true"></i>
                </button>
                <?php endif; ?>
            </div>

        </div>
        <?php endforeach; ?>

    </div>
    <?php /* /#dc-quiz-form */ ?>

    <?php /* ── Result (populated via AJAX) ─────────────────────── */ ?>
    <div id="dc-quiz-result" class="dc-quiz-result" style="display:none;"></div>

</div>
<?php /* /.dc-quiz-wrap */ ?>
