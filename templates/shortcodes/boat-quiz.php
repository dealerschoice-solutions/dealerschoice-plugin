<?php
/**
 * Step-by-step boat finder quiz form.
 *
 * Override by copying to your-theme/dealerschoice/shortcodes/boat-quiz.php.
 *
 * @var string $title           Quiz heading.
 * @var string $subtitle        Subheading below the title.
 * @var string $submit_label    Label on the final submit button.
 * @var array  $questions       Questions from DC\BoatQuiz::get_questions(). Each question has
 *                              'key', 'number', 'text', 'cols', 'options' and optional 'conditional';
 *                              each option has 'value', 'label', 'description', 'svg' or 'dollars',
 *                              and optional 'group'.
 * @var string $nonce           Nonce for the 'dealerschoice_quiz_nonce' action.
 * @var int    $total_steps     Number of question steps.
 * @var int    $gravity_form_id Gravity Forms lead form ID, or 0 for none.
 * @var int    $boat_count      Number of matching boats to show in the result slider.
 *
 * @package DealersChoice
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div
    class="dc-quiz-wrap"
    id="dc-boat-quiz"
    data-nonce="<?php echo esc_attr( $nonce ); ?>"
    data-total-steps="<?php echo esc_attr( $total_steps ); ?>"
    data-gravity-form-id="<?php echo esc_attr( $gravity_form_id ); ?>"
    data-boat-count="<?php echo esc_attr( $boat_count ); ?>"
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
                    // Group slug keeps option IDs unique when values repeat across groups.
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
