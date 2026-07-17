<?php
/**
 * Favorite Boats Analytics Admin Page
 *
 * Displays analytics for the favorites system including summary cards,
 * a daily trend chart, and a ranked table of most-favorited boats.
 *
 * @package DealersChoice
 * @subpackage Admin
 */

if ( ! defined('ABSPATH') ) {
    exit;
}

/**
 * Enqueue Chart.js on the Favorite Boats admin page.
 */
function dealers_choice_favorites_enqueue_assets( $hook ) {
    if ( $hook !== 'dealerschoice_page_dealers-choice-favorites' ) {
        return;
    }
    wp_enqueue_script(
        'chartjs',
        'https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js',
        [],
        '4.4.2',
        true
    );
}
add_action( 'admin_enqueue_scripts', 'dealers_choice_favorites_enqueue_assets' );

/**
 * Main callback for the Favorite Boats admin page.
 */
function dealers_choice_favorites_page() {
    if ( ! current_user_can('manage_options') ) {
        wp_die( esc_html__('You do not have permission to view this page.', 'dealers-choice') );
    }

    // ── Favorites-enabled guard ───────────────────────────────────────────
    if ( get_option('dealers_choice_show_favorites', '1') !== '1' ) {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Favorite Boats Analytics', 'dealers-choice') . '</h1>';
        echo '<div class="notice notice-warning inline"><p>';
        echo esc_html__('Favorites are currently disabled. Enable them in ', 'dealers-choice');
        echo '<a href="' . esc_url( admin_url('admin.php?page=dealers-choice-settings') ) . '">';
        echo esc_html__('DealersChoice Settings', 'dealers-choice');
        echo '</a>.';
        echo '</p></div>';
        echo '</div>';
        return;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'dc_favorite_events';

    // ── Date range resolution ─────────────────────────────────────────────
    $preset = isset($_GET['dc_period']) ? sanitize_text_field($_GET['dc_period']) : '30';

    $date_from = '';
    $date_to   = date('Y-m-d');

    $custom_from = isset($_GET['dc_from']) ? sanitize_text_field($_GET['dc_from']) : '';
    $custom_to   = isset($_GET['dc_to'])   ? sanitize_text_field($_GET['dc_to'])   : '';

    // Validate custom dates
    $using_custom = false;
    if ( $preset === 'custom' && $custom_from && $custom_to && strtotime($custom_from) && strtotime($custom_to) ) {
        $date_from    = date('Y-m-d', strtotime($custom_from));
        $date_to      = date('Y-m-d', strtotime($custom_to));
        $using_custom = true;
    } else {
        switch ( $preset ) {
            case 'today':
                $date_from = current_time('Y-m-d');
                break;
            case '7':
                $date_from = date('Y-m-d', strtotime('-6 days'));
                break;
            case '90':
                $date_from = date('Y-m-d', strtotime('-89 days'));
                break;
            case 'all':
                $date_from = '';
                break;
            default: // 30
                $preset    = '30';
                $date_from = date('Y-m-d', strtotime('-29 days'));
        }
    }

    // Build SQL WHERE clause
    $where_parts = [];
    if ( $date_from ) {
        $where_parts[] = $wpdb->prepare('DATE(event_time) >= %s', $date_from);
    }
    if ( $date_to ) {
        $where_parts[] = $wpdb->prepare('DATE(event_time) <= %s', $date_to);
    }
    $where = $where_parts ? 'WHERE ' . implode(' AND ', $where_parts) : '';

    // ── Summary metrics ───────────────────────────────────────────────────
    $totals = $wpdb->get_row(
        "SELECT
            SUM(CASE WHEN action='add'    THEN 1 ELSE 0 END) AS total_adds,
            SUM(CASE WHEN action='remove' THEN 1 ELSE 0 END) AS total_removes,
            COUNT(DISTINCT boat_id) AS unique_boats
         FROM {$table}
         {$where}"
    );
    $total_adds    = $totals ? (int) $totals->total_adds    : 0;
    $total_removes = $totals ? (int) $totals->total_removes : 0;
    $net_favorites = $total_adds - $total_removes;
    $unique_boats  = $totals ? (int) $totals->unique_boats  : 0;

    // ── Daily chart data ─────────────────────────────────────────────────
    $daily_rows = $wpdb->get_results(
        "SELECT DATE(event_time) AS day,
                SUM(CASE WHEN action='add'    THEN 1 ELSE 0 END) AS adds,
                SUM(CASE WHEN action='remove' THEN 1 ELSE 0 END) AS removes
         FROM {$table}
         {$where}
         GROUP BY DATE(event_time)
         ORDER BY DATE(event_time) ASC"
    );

    // Fill in zero days for continuous x-axis
    $chart_labels = [];
    $chart_adds   = [];
    $chart_removes = [];

    if ( $date_from && $date_to ) {
        $current_day = strtotime($date_from);
        $end_day     = strtotime($date_to);
        $day_map     = [];
        foreach ( $daily_rows as $row ) {
            $day_map[$row->day] = $row;
        }
        while ( $current_day <= $end_day ) {
            $key             = date('Y-m-d', $current_day);
            $chart_labels[]  = date('M j', $current_day);
            $chart_adds[]    = isset($day_map[$key]) ? (int) $day_map[$key]->adds    : 0;
            $chart_removes[] = isset($day_map[$key]) ? (int) $day_map[$key]->removes : 0;
            $current_day     = strtotime('+1 day', $current_day);
        }
    } else {
        // "All time" — just use what we have
        foreach ( $daily_rows as $row ) {
            $chart_labels[]  = date('M j', strtotime($row->day));
            $chart_adds[]    = (int) $row->adds;
            $chart_removes[] = (int) $row->removes;
        }
    }

    // ── Top boats table ───────────────────────────────────────────────────
    $per_page    = 25;
    $current_page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
    $offset      = ($current_page - 1) * $per_page;

    $top_boats = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT boat_id,
                    SUM(CASE WHEN action='add'    THEN 1 ELSE 0 END) AS adds,
                    SUM(CASE WHEN action='remove' THEN 1 ELSE 0 END) AS removes,
                    (SUM(CASE WHEN action='add' THEN 1 ELSE 0 END) - SUM(CASE WHEN action='remove' THEN 1 ELSE 0 END)) AS net,
                    MAX(event_time) AS last_favorited
             FROM {$table}
             {$where}
             GROUP BY boat_id
             HAVING net > 0
             ORDER BY net DESC
             LIMIT %d OFFSET %d",
            $per_page,
            $offset
        )
    );

    $total_rows_result = $wpdb->get_var(
        "SELECT COUNT(DISTINCT boat_id)
         FROM {$table}
         {$where}"
    );
    $total_rows  = (int) $total_rows_result;
    $total_pages = ceil($total_rows / $per_page);

    // ── Build period URL helper ───────────────────────────────────────────
    $base_url = admin_url('admin.php?page=dealers-choice-favorites');

    function dc_fav_period_url( $preset_key, $base ) {
        return esc_url( add_query_arg('dc_period', $preset_key, $base) );
    }

    ?>
    <div class="wrap dc-fav-analytics">
        <h1><?php esc_html_e('Favorite Boats Analytics', 'dealers-choice'); ?></h1>

        <!-- Period Selector -->
        <div class="dc-fav-periods" style="margin: 15px 0; display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
            <?php
            $presets = [
                'today' => 'Today',
                '7'     => 'Last 7 Days',
                '30'    => 'Last 30 Days',
                '90'    => 'Last 90 Days',
                'all'   => 'All Time',
            ];
            foreach ( $presets as $key => $label ) {
                $active = ( ! $using_custom && $preset === $key ) ? 'button-primary' : 'button-secondary';
                echo '<a href="' . dc_fav_period_url($key, $base_url) . '" class="button ' . esc_attr($active) . '">' . esc_html($label) . '</a>';
            }
            ?>
            &nbsp;|&nbsp;
            <!-- Custom range -->
            <form method="get" style="display:inline-flex; gap:6px; align-items:center;">
                <input type="hidden" name="page"      value="dealers-choice-favorites" />
                <input type="hidden" name="dc_period" value="custom" />
                <label for="dc_from" style="font-weight:600;"><?php esc_html_e('From:', 'dealers-choice'); ?></label>
                <input type="date" id="dc_from" name="dc_from" value="<?php echo esc_attr($custom_from ?: $date_from); ?>" style="padding:4px 6px;" />
                <label for="dc_to" style="font-weight:600;"><?php esc_html_e('To:', 'dealers-choice'); ?></label>
                <input type="date" id="dc_to"   name="dc_to"   value="<?php echo esc_attr($custom_to   ?: $date_to);   ?>" style="padding:4px 6px;" />
                <button type="submit" class="button button-<?php echo $using_custom ? 'primary' : 'secondary'; ?>"><?php esc_html_e('Apply', 'dealers-choice'); ?></button>
            </form>
        </div>

        <!-- Summary Cards -->
        <div class="dc-fav-cards" style="display:flex; gap:16px; flex-wrap:wrap; margin:20px 0;">
            <?php
            $cards = [
                [ 'label' => 'Total Adds',        'value' => number_format($total_adds),    'color' => '#46b450' ],
                [ 'label' => 'Total Removes',      'value' => number_format($total_removes), 'color' => '#dc3232' ],
                [ 'label' => 'Net Favorites',      'value' => number_format($net_favorites), 'color' => '#0073aa' ],
                [ 'label' => 'Unique Boats',       'value' => number_format($unique_boats),  'color' => '#826eb4' ],
            ];
            foreach ( $cards as $card ) :
            ?>
            <div style="background:#fff; border-left:4px solid <?php echo esc_attr($card['color']); ?>; padding:16px 20px; border-radius:3px; box-shadow:0 1px 3px rgba(0,0,0,.08); min-width:150px; flex:1;">
                <div style="font-size:28px; font-weight:700; color:<?php echo esc_attr($card['color']); ?>; line-height:1;"><?php echo esc_html($card['value']); ?></div>
                <div style="font-size:13px; color:#555; margin-top:5px;"><?php echo esc_html($card['label']); ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Trend Chart -->
        <?php if ( ! empty($chart_labels) ) : ?>
        <div style="background:#fff; padding:20px; border-radius:3px; box-shadow:0 1px 3px rgba(0,0,0,.08); margin-bottom:24px;">
            <h2 style="margin-top:0;"><?php esc_html_e('Daily Trend', 'dealers-choice'); ?></h2>
            <canvas id="dc-fav-chart" height="80"></canvas>
        </div>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var ctx = document.getElementById('dc-fav-chart');
            if (!ctx || typeof Chart === 'undefined') return;
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?php echo wp_json_encode($chart_labels); ?>,
                    datasets: [
                        {
                            label: 'Adds',
                            data:  <?php echo wp_json_encode($chart_adds); ?>,
                            borderColor:     '#46b450',
                            backgroundColor: 'rgba(70,180,80,0.08)',
                            fill: true,
                            tension: 0.3,
                            pointRadius: 3
                        },
                        {
                            label: 'Removes',
                            data:  <?php echo wp_json_encode($chart_removes); ?>,
                            borderColor:     '#dc3232',
                            backgroundColor: 'rgba(220,50,50,0.06)',
                            fill: true,
                            tension: 0.3,
                            pointRadius: 3
                        }
                    ]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { position: 'top' } },
                    scales:  { y: { beginAtZero: true, ticks: { precision: 0 } } }
                }
            });
        });
        </script>
        <?php endif; ?>

        <!-- Top Boats Table -->
        <div style="background:#fff; padding:20px; border-radius:3px; box-shadow:0 1px 3px rgba(0,0,0,.08);">
            <h2 style="margin-top:0;"><?php esc_html_e('Most Favorited Boats', 'dealers-choice'); ?></h2>

            <?php if ( empty($top_boats) ) : ?>
                <p><?php esc_html_e('No favorites recorded for this period.', 'dealers-choice'); ?></p>
            <?php else : ?>
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:40px;"><?php esc_html_e('#', 'dealers-choice'); ?></th>
                        <th><?php esc_html_e('Boat', 'dealers-choice'); ?></th>
                        <th style="width:100px; text-align:right;"><?php esc_html_e('Net Favs', 'dealers-choice'); ?></th>
                        <th style="width:100px; text-align:right;"><?php esc_html_e('Total Adds', 'dealers-choice'); ?></th>
                        <th style="width:160px;"><?php esc_html_e('Last Favorited', 'dealers-choice'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $rank = $offset + 1;
                foreach ( $top_boats as $row ) :
                    $boat_id   = (int) $row->boat_id;
                    $title     = get_the_title($boat_id);
                    $edit_url  = get_edit_post_link($boat_id);
                    $view_url  = get_permalink($boat_id);
                    $last_fav  = $row->last_favorited ? date_i18n('M j, Y g:i a', strtotime($row->last_favorited)) : '—';

                    if ( ! $title ) {
                        $title    = sprintf( __('Boat #%d (no longer published)', 'dealers-choice'), $boat_id );
                        $edit_url = '';
                        $view_url = '';
                    }
                ?>
                    <tr>
                        <td><?php echo esc_html($rank); ?></td>
                        <td>
                            <?php if ($edit_url) : ?>
                                <a href="<?php echo esc_url($edit_url); ?>" style="font-weight:600;"><?php echo esc_html($title); ?></a>
                                <?php if ($view_url) : ?>
                                    &nbsp;<a href="<?php echo esc_url($view_url); ?>" target="_blank" rel="noopener noreferrer" style="color:#888; font-size:11px;"><?php esc_html_e('View', 'dealers-choice'); ?></a>
                                <?php endif; ?>
                            <?php else : ?>
                                <span style="color:#888;"><?php echo esc_html($title); ?></span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:right; font-weight:700; color:#0073aa;"><?php echo esc_html(number_format((int) $row->net)); ?></td>
                        <td style="text-align:right;"><?php echo esc_html(number_format((int) $row->adds)); ?></td>
                        <td><?php echo esc_html($last_fav); ?></td>
                    </tr>
                <?php
                    $rank++;
                endforeach;
                ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if ( $total_pages > 1 ) : ?>
            <div class="tablenav bottom" style="margin-top:12px;">
                <div class="tablenav-pages">
                    <span class="displaying-num"><?php echo esc_html(sprintf(_n('%s item', '%s items', $total_rows, 'dealers-choice'), number_format($total_rows))); ?></span>
                    <?php
                    $page_links = paginate_links([
                        'base'      => add_query_arg('paged', '%#%'),
                        'format'    => '',
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                        'total'     => $total_pages,
                        'current'   => $current_page,
                    ]);
                    echo $page_links;
                    ?>
                </div>
            </div>
            <?php endif; ?>

            <?php endif; ?>
        </div>
    </div>
    <?php
}
