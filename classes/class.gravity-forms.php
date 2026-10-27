<?php
/**
 * Gravity Forms integration for the boat quiz lead form.
 *
 * @package DealersChoice
 * @subpackage Classes
 * @since 1.0.0
 */

namespace DC;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Appends boat quiz answers to Gravity Forms notification emails.
 *
 * The quiz JS adds hidden `dc_quiz_*` inputs to the lead form. When a
 * submission carries `dc_quiz_context=1`, a "Boat Quiz Results" summary is
 * added to each notification body.
 */
class GravityForms {

    /**
     * Registers the Gravity Forms notification filter.
     */
    public static function init(): void {
        add_filter( 'gform_notification', [ __CLASS__, 'append_quiz_data_to_notification' ], 20, 3 );
    }

    /**
     * Appends a "Boat Quiz Results" summary to a notification for quiz submissions.
     *
     * Uses an HTML table when the message body contains HTML, otherwise an
     * aligned plain-text block.
     *
     * @param array $notification Gravity Forms notification (to, subject, message, etc.).
     * @param array $form         Gravity Forms form definition.
     * @param array $entry        Gravity Forms entry.
     * @return array Modified notification.
     */
    public static function append_quiz_data_to_notification( array $notification, array $form, array $entry ): array {

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- extra context field only; no sensitive action taken
        if ( empty( $_POST['dc_quiz_context'] ) || '1' !== (string) $_POST['dc_quiz_context'] ) {
            return $notification;
        }

        $activity   = sanitize_text_field( wp_unslash( $_POST['dc_quiz_activity']   ?? '' ) );
        $crew       = sanitize_text_field( wp_unslash( $_POST['dc_quiz_crew']       ?? '' ) );
        $priorities = sanitize_text_field( wp_unslash( $_POST['dc_quiz_priorities'] ?? '' ) );
        $budget     = sanitize_text_field( wp_unslash( $_POST['dc_quiz_budget']     ?? '' ) );
        $result     = sanitize_text_field( wp_unslash( $_POST['dc_quiz_result']     ?? '' ) );

        if ( ! $activity ) {
            return $notification;
        }

        // Human-readable labels for quiz answer values.
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
            $col = 18;
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

    /**
     * Builds one row of the HTML notification table.
     *
     * @param string $label    Row label.
     * @param string $value    Row value.
     * @param bool   $shade    Optional. Apply a light background stripe. Default false.
     * @param bool   $last_row Optional. Omit the bottom border. Default false.
     * @return string Table row HTML.
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
