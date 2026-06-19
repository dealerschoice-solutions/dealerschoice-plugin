<?php
/**
 * Template: Boat Slide 
 * 
 * Displays a single boat as a slide in the inventory slider.
 * This template can be overridden by copying it to:
 * your-theme/dealerschoice/inventory-slide.php
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

// Get boat details
$condition = $boat->getCondition();
$length = $boat->getFormattedLength();
$type = $boat->getType();
$location = $boat->getLocation();
$stock_number = $boat->getStockNumber();

// Get CTA button
$quote_button = get_option('dealers_choice_get_a_quote_page_id', '');

?>

<article class="inventory-slide">
    <div class="inventory-slide-image dc-lh-0 dc-mb-20">
        <a href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
            <?php if(has_post_thumbnail()):
                the_post_thumbnail('large');
            else:
                echo '<img src="'.esc_url(DC_PLUGIN_URL . 'public/images/no-photo.jpg').'" alt="'.esc_attr(get_the_title()).'" loading="lazy" />';
            endif; ?>
        </a>
        <?php
        $banner = $boat->getField('boat_banner');
        $status = $boat->getStatus();
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
    <div class="inventory-slide-content dc-p-15">
        <?php the_title( sprintf( '<h3><a href="%s">', esc_url( get_permalink() ) ), '</a></h3>' ); ?>
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
        <div class="dc-text-center inventory-slide-button-wrapper">
            <?php if($quote_button): ?>
                <a href="<?php echo esc_url(get_permalink($quote_button)); ?>?inventoryID=<?php echo esc_attr($post->ID); ?>" class="dc-button">Get a Quote</a>
            <?php endif; ?>
            <a href="<?php the_permalink(); ?>" class="dc-button" aria-label="View details for <?php the_title(); ?> inventory ID <?php echo esc_html($stock_number); ?>">View Details</a>
        </div>
    </div>
</article>