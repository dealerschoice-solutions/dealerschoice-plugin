<?php
/**
 * Gravity Forms Integration
 *
 * Hooks into GF notifications so that when a lead form is embedded inside
 * a boat-quiz result screen, the quiz answers are automatically appended to
 * every outgoing notification email — giving the sales agent instant context
 * before they call the prospect.
 *
 * How it works
 * ─────────────
 * 1. The quiz JS injects hidden <input> fields into the GF <form> before
 *    submission (dc_quiz_context, dc_quiz_activity, dc_quiz_crew, etc.).
 * 2. Those values arrive in $_POST alongside the normal GF field data.
 * 3. The gform_notification filter below detects dc_quiz_context=1 and
 *    appends a formatted "Boat Quiz Results" block to the notification body.
 * 4. Optionally, dealers can add Hidden fields to their GF form with
 *    "Allow field to be populated dynamically" enabled and parameter names
 *    matching dc_quiz_activity, dc_quiz_crew, dc_quiz_priorities,
 *    dc_quiz_budget, dc_quiz_result — GF will store them in the entry and
 *    they'll appear via {all_fields} merge tags.
 *
 * @package DealersChoice
 * @subpackage Classes
 * @since 1.0.0
 */

namespace DC;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GravityForms {

    /**
     * Register WordPress / Gravity Forms hooks.
     * Safe to call even when GF is not active — the hook just never fires.
     */
    public static function init(): void {
        add_filter( 'gform_notification', [ __CLASS__, 'append_quiz_data_to_notification' ], 20, 3 );
    }

    /**
     * Append a "Boat Quiz Results" summary block to GF notification emails
     * when the submission originated from the embedded quiz lead-capture form.
     *
     * @param array $notification  GF notification array (to, subject, message, …).
     * @param array $form          GF form definition.
     * @param array $entry         GF entry array.
     * @return array               Modified notification.
     */
    public static function append_quiz_data_to_notification( array $notification, array $form, array $entry ): array {

        // Only act when the quiz JS has flagged this as a quiz submission.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- extra context field only; no sensitive action taken
        if ( empty( $_POST['dc_quiz_context'] ) || '1' !== (string) $_POST['dc_quiz_context'] ) {
            return $notification;
        }

        // Sanitise each quiz field from POST.
        $activity   = sanitize_text_field( wp_unslash( $_POST['dc_quiz_activity']   ?? '' ) );
        $crew       = sanitize_text_field( wp_unslash( $_POST['dc_quiz_crew']       ?? '' ) );
        $priorities = sanitize_text_field( wp_unslash( $_POST['dc_quiz_priorities'] ?? '' ) );
        $budget     = sanitize_text_field( wp_unslash( $_POST['dc_quiz_budget']     ?? '' ) );
        $result     = sanitize_text_field( wp_unslash( $_POST['dc_quiz_result']     ?? '' ) );

        // Nothing useful to append if answers are missing.
        if ( ! $activity ) {
            return $notification;
        }

        // ── Human-readable labels ──────────────────────────────────────────
        $activity_labels = [
            'cruising'    => 'Relaxing &amp; Cruising',
            'fishing'     => 'Fishing',
            'watersports' => 'Water Sports',
            'adventure'   => 'Exploring',
        ];

        $crew_labels = [
            'small'  => 'Just Us (1–4)',
            'medium' => 'Family (5–8)',
            'large'  => 'Big Group (9+)',
        ];

        $priorities_labels = [
            'comfort'     => 'Comfort &amp; Space',
            'performance' => 'Speed &amp; Performance',
            'fishing'     => 'Fishing Features',
            'adventure'   => 'Range &amp; Exploration',
        ];

        $activity_label   = $activity_labels[ $activity ]     ?? ucfirst( $activity );
        $crew_label       = $crew_labels[ $crew ]              ?? ucfirst( $crew );
        $priorities_label = $priorities_labels[ $priorities ]  ?? ucfirst( $priorities );
        $budget_label     = ( $budget && 'any' !== $budget ) ? $budget : 'No preference';
        $result_label     = $result ?: 'Not determined';

        // ── Build the summary block ────────────────────────────────────────
        $message = $notification['message'] ?? '';
        $is_html = (bool) preg_match( '/<[a-zA-Z][\s\S]*>/i', $message );

        if ( $is_html ) {
            $block  = '<br><br>';
            $block .= '<table style="border-collapse:collapse;font-family:sans-serif;font-size:14px;width:100%;max-width:560px;">';
            $block .= '<tr><td colspan="2" style="background:#062055;color:#ffffff;font-weight:bold;padding:9px 14px;font-size:15px;">&#9989; Boat Quiz Results</td></tr>';
            $block .= self::tr( 'Recommended Boat', $result_label );
            $block .= self::tr( 'Activity',          $activity_label,   true );
            $block .= self::tr( 'Crew Size',         $crew_label );
            $block .= self::tr( 'Priority',          $priorities_label, true );
            $block .= self::tr( 'Budget Range',      $budget_label,     false, true );
            $block .= '</table>';
        } else {
            $col = 18; // column width for alignment
            $block  = "\n\n";
            $block .= "=== Boat Quiz Results ===\n";
            $block .= str_pad( 'Recommended Boat:', $col ) . $result_label . "\n";
            $block .= str_pad( 'Activity:',         $col ) . html_entity_decode( $activity_label )   . "\n";
            $block .= str_pad( 'Crew Size:',        $col ) . $crew_label       . "\n";
            $block .= str_pad( 'Priority:',         $col ) . html_entity_decode( $priorities_label ) . "\n";
            $block .= str_pad( 'Budget Range:',     $col ) . $budget_label     . "\n";
            $block .= "=========================\n";
        }

        $notification['message'] = $message . $block;

        return $notification;
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Build one <tr> for the HTML notification table.
     *
     * @param string $label
     * @param string $value
     * @param bool   $shade      Apply light background stripe.
     * @param bool   $last_row   Omit bottom border on last row.
     * @return string
     */
    private static function tr( string $label, string $value, bool $shade = false, bool $last_row = false ): string {
        $bg     = $shade ? 'background:#f8f8f8;' : '';
        $border = $last_row ? '' : 'border-bottom:1px solid #ddd;';
        return sprintf(
            '<tr style="%s">
                <td style="padding:7px 14px;%sfont-weight:600;width:42%%;">%s</td>
                <td style="padding:7px 14px;%s">%s</td>
            </tr>',
            $bg,
            $border,
            esc_html( $label ),
            $border,
            esc_html( $value )
        );
    }
}
