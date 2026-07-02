<?php
/**
 * Template: Boat Content Inventory Card
 * 
 * Displays a single boat as a block in the inventory grid.
 * This template can be overridden by copying it to:
 * your-theme/dealerschoice/inventory-block.php
 * 
 * @package DealersChoice
 * @subpackage Templates
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

global $post;
$boat = new \DC\Boat($post);

// Get CTA button
$quote_button = get_option('dealers_choice_get_a_quote_page_id', '');

// Get boat details
$condition = $boat->getCondition();
$status = $boat->getStatus();
$year = $boat->getYear();
$make = $boat->getMake();
$model = $boat->getModel();
$length = $boat->getFormattedLength();
$type = $boat->getType();
$horsepower = $boat->getHorsepower();
$price = $boat->getSaleprice();
$msrp = $boat->getMSRP();
$location = $boat->getLocation();
$stock_number = $boat->getStockNumber();

// Price Display settings
$show_price = get_option('dealers_choice_always_show_price', '0') === '1';
$popup_form_id = get_option('dealers_choice_price_popup_form_id', '');
$price_display_override = get_field('hide_sale_price');

$show_favorites = get_option('dealers_choice_show_favorites', '1') === '1';

do_action('dealerschoice_before_boat_card', $post->ID);
?>

<article class="inventory-section dc-p-25 dc-mb">
    <div class="dc-flexed">
        <div class="inventory-photo dc-flex-40">
            <a href="<?php the_permalink(); ?>" aria-hidden="true" tabindex="-1">
                <?php if (has_post_thumbnail()):
                    the_post_thumbnail('large');
                else: ?>
                    <img src="<?php echo esc_url(DC_PLUGIN_URL . 'public/images/no-photo.jpg'); ?>" alt="<?php the_title_attribute(); ?>" loading="lazy" />
                <?php
                endif; ?>
            </a>
            <?php if ($show_favorites): ?>
            <!-- Favorite Button -->
            <div class="favorite-button-wrapper">
                <button type="button" class="dc-button dc-favorite-btn" data-boat-id="<?php echo esc_attr(get_the_ID()); ?>">
                    <span class="dc-favorite-icon" aria-hidden="true"><i class="fa-light fa-heart-circle-plus"></i></span> <span class="dc-favorite-label dc-screen-reader-text" data-boat-title="<?php the_title(); ?>">Add <?php the_title(); ?> to Favorites</span>
                </button>
            </div>
            <?php endif; ?>
            <?php
            $banner = $boat->getField('boat_banner');
            if ($banner):
            ?>
                <div class="status-badge-wrapper">
                    <span class="status-badge status-banner"><?php echo esc_html($banner); ?></span>
                </div>
            <?php elseif ($status && $status !== 'In Stock'): ?>
                <div class="status-badge-wrapper">
                    <span class="status-badge status-<?php echo esc_attr(strtolower(str_replace(' ', '-', $status))); ?>"><?php echo esc_html($status); ?></span>
                </div>
            <?php endif; ?>
        </div>

        <div class="inventory-details dc-flex-55">
            <div class="inventory-header dc-clear">
                <div class="dc-flexed dc-flexed-vertical-top">
                    <div class="dc-flex-70">
                        <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                    </div>
                    <div class="dc-flex-30 dc-text-right boat-id">
                        <?php if ($stock_number): ?>
                            <p class="boat-stock-number">Stock #<?php echo esc_html($stock_number); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="inventory-specs dc-p-15">
                <div class="dc-flexed">
                    <div class="spec">
                        <div class="dc-flexed">
                            <div class="spec-icon"><i class="fa-light fa-ship"></i></div>
                            <div class="spec-detail">Condition <strong><?php echo esc_html($condition); ?></strong></div>
                        </div>
                    </div>
                    <div class="spec">
                        <div class="dc-flexed">
                            <div class="spec-icon"><i class="fa-light fa-ruler"></i></i></div>
                            <div class="spec-detail">Length <strong><?php echo esc_html($length); ?></strong></div>
                        </div>
                    </div>
                    <div class="spec">
                        <div class="dc-flexed">
                            <div class="spec-icon"><i class="fa-light fa-table-list"></i></div>
                            <div class="spec-detail">Type <strong><?php echo esc_html($type); ?></strong></div>
                        </div>
                    </div>
                    <div class="spec">
                        <div class="dc-flexed">
                            <div class="spec-icon"><i class="fa-light fa-map-marker-alt"></i></div>
                            <div class="spec-detail">Location <strong><?php echo esc_html($location); ?></strong></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="pricing-detail">
                <?php if (!$price_display_override): ?>
                    <?php if($show_price): ?>
                        <?php if ($price && $price !== '0.00'): ?>
                            <p class="boat-price"><strong>Price:</strong> $<?php echo esc_html(number_format($price)); ?></p>
                        <?php else: ?>
                            <p><strong>Contact Us for Our Price</strong></p>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php if ($price && $price !== '0.00'): ?>
                            <p class="boat-price"><button class="boat-price-popup-button" data-inventory-id="<?php echo esc_attr( get_the_ID() ); ?>" data-boat-name="<?php the_title_attribute(); ?>" data-stock-number="<?php echo esc_attr( $stock_number ); ?>">Reveal Price</button></p>
                        <?php else: ?>
                            <p><strong>Contact Us for Our Price</strong></p>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php else: ?>
                     <p><strong>Contact Us for Our Price</strong></p>
                <?php endif; ?>

                <?php if($msrp && $msrp > $price): ?>
                    <p class="boat-msrp"><strong>MSRP:</strong> <s>$<?php echo esc_html(number_format($msrp)); ?></s></p>
                    <?php if(!$price_display_override && $show_price && $price && $msrp < $price): ?>
                        <p class="boat-savings"><strong>Savings:</strong> $<?php echo esc_html(number_format($price - $msrp)); ?></p>
                    <?php endif; ?>
                <?php endif; ?>

            </div>

            <div class="inventory-buttons dc-text-right">
                <div class="dc-flexed dc-flexed-vertical-center dc-flexed-justify-end">
                    <?php if($quote_button): ?>
                        <a href="<?php echo esc_url(get_permalink($quote_button)); ?>?inventoryID=<?php echo esc_attr($post->ID); ?>" class="dc-button">Get a Quote</a>
                    <?php endif; ?>

                    <a href="<?php the_permalink(); ?>" class="dc-button" aria-label="View Details for <?php the_title_attribute(); ?>">View Details</a>
                </div>
            </div>
        </div>
    </div>
</article>

<?php
do_action('dealerschoice_after_boat_card', $post->ID);
