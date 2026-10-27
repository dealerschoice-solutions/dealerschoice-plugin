<?php
/**
 * Full finance calculator for the [dealerschoice_finance_calculator] shortcode.
 *
 * Renders amount, rate, term and down payment inputs, a loan summary result
 * and a collapsed amortization schedule.
 *
 * Override by copying to your-theme/dealerschoice/shortcodes/finance-calculator.php.
 *
 * @var string       $title                Heading above the form.
 * @var float|string $default_amount       Prefilled amount financed, or '' when blank.
 * @var float        $default_rate         Default APR (%).
 * @var int          $default_term         Default loan term in months.
 * @var float        $down_payment_pct     Default down payment as a percent of the amount.
 * @var float|string $default_down_payment Precomputed down payment, or '' when $default_amount is blank.
 * @var array        $term_options         Loan term presets as months => label.
 * @var string       $disclaimer           Disclaimer text; may contain basic HTML.
 * @var string       $instance_id          Unique DOM ID prefix for this instance.
 *
 * @package DealersChoice
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div
    class="dc-finance-calc"
    id="<?php echo esc_attr( $instance_id ); ?>"
    data-calc-variant="full"
    data-default-down-payment-percent="<?php echo esc_attr( $down_payment_pct ); ?>"
>
    <?php if ( $title ) : ?>
        <h2 class="dc-finance-calc-title"><?php echo esc_html( $title ); ?></h2>
    <?php endif; ?>

    <form class="dc-finance-calc-form" method="get" novalidate>
        <div class="dc-finance-calc-fields">

            <div class="dc-field">
                <label for="<?php echo esc_attr( $instance_id ); ?>-amount">
                    <?php esc_html_e( 'Amount Financed', 'dealerschoice' ); ?>
                    <span class="dc-required" aria-hidden="true">*</span>
                    <span class="dc-screen-reader-text"><?php esc_html_e( '(required)', 'dealerschoice' ); ?></span>
                </label>
                <input
                    type="number"
                    inputmode="decimal"
                    min="0"
                    step="0.01"
                    required
                    id="<?php echo esc_attr( $instance_id ); ?>-amount"
                    name="amount_financed"
                    value="<?php echo esc_attr( $default_amount ); ?>"
                    aria-describedby="<?php echo esc_attr( $instance_id ); ?>-amount-error"
                >
                <p id="<?php echo esc_attr( $instance_id ); ?>-amount-error" class="dc-field-error" role="alert" hidden></p>
            </div>

            <div class="dc-field">
                <label for="<?php echo esc_attr( $instance_id ); ?>-rate">
                    <?php esc_html_e( 'Interest Rate (APR %)', 'dealerschoice' ); ?>
                    <span class="dc-required" aria-hidden="true">*</span>
                    <span class="dc-screen-reader-text"><?php esc_html_e( '(required)', 'dealerschoice' ); ?></span>
                </label>
                <input
                    type="number"
                    inputmode="decimal"
                    min="0"
                    max="30"
                    step="0.01"
                    required
                    id="<?php echo esc_attr( $instance_id ); ?>-rate"
                    name="interest_rate"
                    value="<?php echo esc_attr( $default_rate ); ?>"
                    aria-describedby="<?php echo esc_attr( $instance_id ); ?>-rate-error"
                >
                <p id="<?php echo esc_attr( $instance_id ); ?>-rate-error" class="dc-field-error" role="alert" hidden></p>
            </div>

            <div class="dc-field">
                <label for="<?php echo esc_attr( $instance_id ); ?>-term">
                    <?php esc_html_e( 'Loan Term', 'dealerschoice' ); ?>
                </label>
                <select
                    id="<?php echo esc_attr( $instance_id ); ?>-term"
                    name="loan_term"
                    aria-describedby="<?php echo esc_attr( $instance_id ); ?>-term-error"
                >
                    <?php foreach ( $term_options as $months => $label ) : ?>
                        <option value="<?php echo esc_attr( $months ); ?>" <?php selected( (int) $default_term, $months ); ?>><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                    <option value="other"><?php esc_html_e( 'Other (enter months)', 'dealerschoice' ); ?></option>
                </select>
                <p id="<?php echo esc_attr( $instance_id ); ?>-term-error" class="dc-field-error" role="alert" hidden></p>
            </div>

            <div class="dc-field dc-finance-other-term" hidden>
                <label for="<?php echo esc_attr( $instance_id ); ?>-term-other">
                    <?php esc_html_e( 'Custom Term (months)', 'dealerschoice' ); ?>
                    <span class="dc-required" aria-hidden="true">*</span>
                    <span class="dc-screen-reader-text"><?php esc_html_e( '(required)', 'dealerschoice' ); ?></span>
                </label>
                <input
                    type="number"
                    inputmode="numeric"
                    min="1"
                    max="600"
                    step="1"
                    id="<?php echo esc_attr( $instance_id ); ?>-term-other"
                    name="loan_term_other"
                    aria-describedby="<?php echo esc_attr( $instance_id ); ?>-term-other-error"
                >
                <p id="<?php echo esc_attr( $instance_id ); ?>-term-other-error" class="dc-field-error" role="alert" hidden></p>
            </div>

            <div class="dc-field">
                <label for="<?php echo esc_attr( $instance_id ); ?>-down-payment">
                    <?php esc_html_e( 'Down Payment (optional)', 'dealerschoice' ); ?>
                </label>
                <input
                    type="number"
                    inputmode="decimal"
                    min="0"
                    step="0.01"
                    id="<?php echo esc_attr( $instance_id ); ?>-down-payment"
                    name="down_payment"
                    value="<?php echo esc_attr( $default_down_payment ); ?>"
                    aria-describedby="<?php echo esc_attr( $instance_id ); ?>-down-payment-error"
                >
                <p id="<?php echo esc_attr( $instance_id ); ?>-down-payment-error" class="dc-field-error" role="alert" hidden></p>
            </div>

        </div>

        <button type="submit" class="dc-button dc-finance-calc-submit">
            <?php esc_html_e( 'Calculate', 'dealerschoice' ); ?>
        </button>
    </form>

    <div
        class="dc-finance-calc-result"
        id="<?php echo esc_attr( $instance_id ); ?>-result"
        role="status"
        aria-live="polite"
        aria-atomic="true"
    ></div>

    <?php if ( $disclaimer ) : ?>
        <p class="dc-finance-calc-disclaimer"><?php echo wp_kses_post( $disclaimer ); ?></p>
    <?php endif; ?>

    <details class="dc-finance-schedule-details" hidden>
        <summary class="dc-finance-schedule-summary"><?php esc_html_e( 'View Full Amortization Schedule', 'dealerschoice' ); ?></summary>
        <div class="dc-finance-schedule-table-wrap"></div>
    </details>

</div>
