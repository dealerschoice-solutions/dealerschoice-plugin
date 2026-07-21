<?php
/**
 * Quick Finance Calculator Template
 *
 * Renders the compact finance calculator automatically shown on
 * single-boat.php (see DC\Shortcodes::render_quick_finance_calculator()).
 * Not user-insertable - the boat's price is fixed/display-only; only rate,
 * term, and down payment are editable. Output is a single estimated
 * monthly payment line, no loan-summary breakdown or amortization schedule.
 *
 * ── THEME OVERRIDE ───────────────────────────────────────────────────────────
 * Copy this file to {your-theme}/dealerschoice/shortcodes/finance-calculator-quick.php
 * to customise the layout without editing the plugin.
 *
 * ── AVAILABLE VARIABLES ──────────────────────────────────────────────────────
 * $price                 (float) The boat's visible sale price.
 * $default_rate           (float) Default APR (%) from Settings.
 * $default_term           (int)   Default loan term (months) from Settings.
 * $down_payment_pct       (float) Default down payment (% of price) from Settings.
 * $default_down_payment   (float) Pre-computed down payment ($price * $down_payment_pct / 100).
 * $term_options           (array) months => label loan term presets.
 * $instance_id            (string) Unique DOM id prefix for this instance.
 *
 * @package DealersChoice
 * @subpackage Templates
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** @var float  $price */
/** @var float  $default_rate */
/** @var int    $default_term */
/** @var float  $down_payment_pct */
/** @var float  $default_down_payment */
/** @var array  $term_options */
/** @var string $instance_id */
?>
<div
    class="dc-finance-calc dc-finance-calc--quick"
    id="<?php echo esc_attr( $instance_id ); ?>"
    data-calc-variant="quick"
    data-price="<?php echo esc_attr( $price ); ?>"
>
    <h2 class="dc-finance-calc-title"><?php esc_html_e( 'Estimate Your Payment', 'dealerschoice' ); ?></h2>

    <p class="dc-finance-price-display">
        <?php esc_html_e( 'Boat Price:', 'dealerschoice' ); ?>
        <strong><?php echo esc_html( '$' . number_format( $price ) ); ?></strong>
    </p>

    <form class="dc-finance-calc-form" method="get" novalidate>
        <div class="dc-finance-calc-fields">

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

        <p class="dc-finance-calc-disclaimer">
            <?php esc_html_e( 'Estimate only, for informational purposes. Contact us for your actual rate and payment terms.', 'dealerschoice' ); ?>
        </p>
    </form>

    <div
        class="dc-finance-calc-result"
        id="<?php echo esc_attr( $instance_id ); ?>-result"
        role="status"
        aria-live="polite"
        aria-atomic="true"
    ></div>

</div>
