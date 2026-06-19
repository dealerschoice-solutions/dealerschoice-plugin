<?php
/**
 * Template: Single Boat Detail Page
 * 
 * Displays the full detail page for a single boat.
 * This template can be overridden by copying it to:
 * your-theme/dealerschoice/single-boat.php
 * 
 * @package DealersChoice
 * @subpackage Templates
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get CTA settings
$quote_button = get_option('dealers_choice_get_a_quote_page_id', '');
$finance_button = get_option('dealers_choice_financing_page_id', '');
$trade_button = get_option('dealers_choice_value_your_trade_page_id', '');
$test_drive_button = get_option('dealers_choice_schedule_test_drive_page_id', '');

// Price Display settings
$show_price = get_option('dealers_choice_always_show_price', '0') === '1';
$popup_form_id = get_option('dealers_choice_price_popup_form_id', '');
$price_display_override = get_field('hide_sale_price');

$show_favorites = get_option('dealers_choice_show_favorites', '1') === '1'; 

// Enqueue Slick slider scripts and styles
wp_enqueue_style( 'dealerschoice-slick' );
wp_enqueue_script( 'dealerschoice-slick' );
wp_enqueue_script( 'dealerschoice-slider' );

if(get_field('gallery')):
    wp_enqueue_style('dealerschoice-modaal', get_stylesheet_directory_uri().'/js/modaal/css/modaal.min.css');
    wp_enqueue_script('dealerschoice-modaal-js', get_stylesheet_directory_uri() . '/js/modaal/js/modaal.js', array('jquery'), false, true );
    wp_enqueue_script( 'dealerschoice-gallery' );
endif;

if($show_favorites):
    // Enqueue favorites JS
    wp_enqueue_script( 'dealerschoice-favorites' );
endif;

get_header();

while (have_posts()) : the_post();
    global $post;
    $boat = new \DC\Boat($post);

    // Get boat details
    $gallery_images = $boat->getGalleryImages();
    $main_specs = $boat->getMainSpecs();
    $tech_specs = $boat->getTechSpecs();
    $condition = $boat->getCondition();
    $status = $boat->getStatus();
    $year = $boat->getYear();
    $make = $boat->getMake();
    $model = $boat->getModel();
    $length = $boat->getFormattedLength();
    $price = $boat->getSaleprice();
    $msrp = $boat->getMSRP();
    $location = $boat->getLocation();
    $stock_number = $boat->getStockNumber();
    $hin = $boat->getHIN();
    $description = get_field('boat_description');
    $type = $boat->getType();

    // add schema output for boat
    $boat->outputBoatSchema($boat);

    do_action('dealerschoice_before_single_boat', $post->ID);
    ?>

    <div id="primary" class="dealerschoice-single content-area dc-row">
        <div class="dealerschoice-container dc-clear">
            <section id="main" class="site-main" aria-labelledby="page-title">

                <article id="boat-<?php the_ID(); ?>" <?php post_class('boat-detail'); ?>>

                    <?php do_action('dealerschoice_single_boat_header_before'); ?>

                    <header class="boat-header dc-clear">
                        <div class="dc-flexed dc-flexed-vertical-end dc-mb">
                            <div class="title-wrapper">
                                <h1 class="boat-title" id="page-title"><?php the_title(); ?></h1>

                                <div class="boat-header-meta">
                                    <?php if($status): ?>
                                        <span class="meta-item boat-status <?php echo esc_attr(str_replace(' ', '-', strtolower($status))); ?>">
                                            <?php echo esc_html($status); ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php $banner = $boat->getField('boat_banner'); if ($banner): ?>
                                        <span class="meta-item boat-banner">
                                            <?php echo esc_html($banner); ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($condition): ?>
                                        <span class="meta-item boat-condition <?php echo esc_attr(strtolower($condition)); ?>">
                                            <?php echo esc_html($condition); ?>
                                        </span>
                                    <?php endif; ?>

                                    <?php if ($stock_number): ?>
                                        <span class="meta-item boat-stock">
                                            Stock #<?php echo esc_html($stock_number); ?>
                                        </span>
                                    <?php endif; ?>

                                    <?php if ($location): ?>
                                        <span class="meta-item boat-location">
                                            <i class="fa-light fa-location-dot"></i>
                                            <?php echo esc_html($location); ?>
                                        </span>
                                    <?php endif; ?>

                                    <?php if($show_favorites): ?>
                                        <!-- Favorite Button -->
                                        <button type="button" class="dc-button dc-favorite-btn" data-boat-id="<?php echo esc_attr($post->ID); ?>">
                                            <span class="dc-favorite-icon" aria-hidden="true"><i class="fa-light fa-heart-circle-plus"></i></span> <span class="dc-favorite-label dc-screen-reader-text" data-boat-title="<?php the_title(); ?>">Add <?php the_title(); ?> to Favorites</span>
                                        </button>
                                    <?php endif; ?>

                                </div>
                            </div>
                            <!-- Boat CTAs -->
                            <div class="boat-contact-cta">
                                <?php do_action('dealerschoice_contact_cta', $post->ID); ?>

                                <?php if($quote_button): ?>
                                    <a href="<?php echo esc_url(get_permalink($quote_button)); ?>?inventoryID=<?php echo esc_attr($post->ID); ?>" class="dc-button">Get a Quote</a>
                                <?php endif; ?>
                                <?php if($finance_button): ?>
                                    <a href="<?php echo esc_url(get_permalink($finance_button)); ?>" class="dc-button">Financing</a>
                                <?php endif; ?>
                                <?php if($trade_button): ?>
                                    <a href="<?php echo esc_url(get_permalink($trade_button)); ?>" class="dc-button">Value Your Trade</a>
                                <?php endif; ?>
                                <?php if($test_drive_button): ?>
                                    <a href="<?php echo esc_url(get_permalink($test_drive_button)); ?>?inventoryID=<?php echo esc_attr($post->ID); ?>" class="dc-button">Schedule a Test Drive</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </header>

                    <?php do_action('dealerschoice_single_boat_header_after'); ?>

                    <div class="boat-content-wrapper dc-clear">
                        <div class="dc-flexed dc-mb">
                            <!-- Gallery Section -->
                            <div class="boat-gallery-wrapper dc-flex-55">
                                <?php do_action('dealerschoice_before_gallery'); ?>

                                <?php if(!has_post_thumbnail() && empty($gallery_images)): ?>
                                    <img src="<?php echo esc_url(DC_PLUGIN_URL . 'public/images/no-photo.jpg'); ?>" alt="<?php the_title_attribute(); ?>" width="1200" height="800" loading="lazy" />
                                <?php endif; ?>

                                <?php if (!empty($gallery_images)): ?>
                                    <div class="boat-gallery" id="boat-<?php echo get_the_ID(); ?>-gallery">
                                        <?php foreach ($gallery_images as $image):
                                            echo '<div class="gallery-image-item">';
                                                echo '<a href="'.wp_get_attachment_image_url($image,'full').'" class="gallery-modal" data-group="boat-gallery">';
                                                    echo wp_get_attachment_image($image, 'large');
                                                    echo '<span class="magnify"><i class="fa-light fa-magnifying-glass-plus"></i></span>';
                                                echo '</a>';
                                            echo '</div>';
                                        endforeach; ?>
                                    </div>
                                    <div class="boat-gallery-buttons button-wrapper" id="boat-<?php echo get_the_ID(); ?>-gallery-buttons"></div>
                                <?php endif; ?>

                                <?php do_action('dealerschoice_after_gallery'); ?>
                            </div>

                            <!-- Info Section -->
                            <div class="boat-info dc-flex-42">

                                <?php do_action('dealerschoice_before_boat_info'); ?>

                                <!-- Price -->
                                <div class="boat-price-section dc-mb dc-p-25">
                                    <?php if (!$price_display_override): ?>
                                        <?php if($show_price): ?>
                                            <?php if ($price && $price !== '0.00'): ?>
                                                <p class="boat-price"><strong>Price:</strong> $<?php echo esc_html(number_format($price)); ?></p>
                                            <?php else: ?>
                                                <p class="boat-price">Contact Us for Our Price</p>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <?php if ($price && $price !== '0.00'): ?>
                                                <p class="boat-price"><button class="boat-price-popup-button" data-inventory-id="<?php echo esc_attr( get_the_ID() ); ?>">Reveal Price</button></p>
                                            <?php else: ?>
                                                <p class="boat-price">Contact Us for Our Price</p>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <p class="boat-price"><strong>Contact Us for Our Price</strong></p>
                                    <?php endif; ?>

                                    <?php if($msrp && $msrp > $price): ?>
                                        <p class="boat-msrp"><strong>MSRP:</strong> <s>$<?php echo esc_html(number_format($msrp)); ?></s></p>
                                        <?php if(!$price_display_override && $show_price && $price && $msrp < $price): ?>
                                            <p class="boat-savings"><strong>Savings:</strong> $<?php echo esc_html(number_format($price - $msrp)); ?></p>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>

                                <!-- Key Specs -->
                                <?php if ($main_specs): ?>
                                <div class="boat-key-specs">
                                    <h2>Key Specifications</h2>
                                    <dl class="specs-list">
                                        <?php foreach ($main_specs as $label => $value): ?>
                                            <div class="spec-item">
                                                <dt><?php echo esc_html($label); ?></dt>
                                                <dd><?php echo esc_html($value); ?></dd>
                                            </div>
                                        <?php endforeach; ?>
                                    </dl>
                                </div>
                                <?php endif; ?>

                                <?php do_action('dealerschoice_after_boat_info'); ?>
                            </div>
                        </div>
                    </div>

                    <!-- Additional Details Sections -->
                    <?php if ($description || $tech_specs || have_rows('boat_options') || have_rows('boat_model_features') || have_rows('boat_specifications') || have_rows('boat_custom_fields')): ?>
                        <div class="boat-additional-details dc-row dc-clear">
                            <!-- Description -->
                            <?php if ($description): ?>
                                <div class="boat-description">
                                    <h2><?php the_title(); ?> Overview</h2>
                                    <?php echo wp_kses_post($description); ?>
                                </div>
                            <?php endif; ?>

                            <?php if($tech_specs): ?>
                                <div class="boat-tech-specs dc-clear dc-mb">
                                    <h2><?php the_title(); ?> Tech Specs</h2>
                                    <div class="dc-flexed">
                                    <?php foreach ($tech_specs as $label => $value): ?>
                                        <div class="tech-spec-item">
                                            <?php if(!empty($value) && $value != ''): ?>
                                                <strong><?php echo esc_html($label); ?></strong> <?php echo esc_html($value); ?>
                                            <?php else: ?>
                                                <?php echo esc_html($label); ?>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (have_rows('boat_options')): ?>
                                <div class="installed-options accordion-item dc-clear">
                                    <details class="accordion">
                                        <summary class="accordion-title"><h3>Installed Options</h3></summary>
                                        <div class="accordion-content">
                                            <div class="dc-flexed">
                                            <?php while(have_rows('boat_options')): the_row(); 
                                                $option_name = get_sub_field('label');
                                                $option_value = get_sub_field('value');
                                                ?>
                                                <div class="tech-spec-item">
                                                    <?php if(!empty($option_value)): ?>
                                                        <strong><?php echo esc_html($option_name); ?></strong> <?php echo esc_html($option_value); ?>
                                                    <?php else: ?>
                                                        <?php echo esc_html($option_name); ?>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endwhile; ?>
                                            </div>
                                        </div>
                                    </details>
                                </div>
                            <?php endif; ?>

                            <?php if (have_rows('boat_model_features')): ?>
                                <div class="standard-features accordion-item dc-clear">
                                    <details class="accordion">
                                        <summary class="accordion-title"><h3>Standard Features</h3></summary>
                                        <div class="accordion-content">
                                            <div class="dc-flexed">
                                            <?php while(have_rows('boat_model_features')): the_row(); 
                                                $feature_name = get_sub_field('label');
                                                $feature_value = get_sub_field('value');
                                                ?>
                                                <div class="tech-spec-item">
                                                    <?php if(!empty($feature_value)): ?>
                                                        <strong><?php echo esc_html($feature_name); ?></strong> <?php echo esc_html($feature_value); ?>
                                                    <?php else: ?>
                                                        <?php echo esc_html($feature_name); ?>
                                                    <?php endif; ?>
                                                </div>
                                                <?php endwhile; ?>
                                            </div>
                                        </div>
                                    </details>
                                </div>
                            <?php endif; ?>

                            <?php if (have_rows('boat_specifications')): ?>
                                <div class="full-specs accordion-item dc-clear">
                                    <details class="accordion">
                                        <summary class="accordion-title"><h3>Full Specifications</h3></summary>
                                        <div class="accordion-content">
                                            <div class="dc-flexed">
                                            <?php while(have_rows('boat_specifications')): the_row(); 
                                                $spec_name = get_sub_field('label');
                                                $spec_value = get_sub_field('value');
                                                ?>
                                                <div class="tech-spec-item">
                                                    <?php if(!empty($spec_value)): ?>
                                                        <strong><?php echo esc_html($spec_name); ?></strong> <?php echo esc_html($spec_value); ?>
                                                    <?php else: ?>
                                                        <?php echo esc_html($spec_name); ?>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endwhile; ?>
                                            </div>
                                        </div>
                                    </details>
                                </div>
                            <?php endif; ?>

                            <?php if (have_rows('boat_custom_fields')): ?>
                                <div class="other-features accordion-item dc-clear">
                                    <details class="accordion">
                                        <summary class="accordion-title"><h3>Other Features</h3></summary>
                                        <div class="accordion-content">
                                            <div class="dc-flexed">
                                            <?php while(have_rows('boat_custom_fields')): the_row(); 
                                                $spec_name = get_sub_field('label');
                                                $spec_value = get_sub_field('value');
                                                ?>
                                                <div class="tech-spec-item">
                                                <strong><?php echo esc_html($spec_name); ?></strong> <?php echo esc_html($spec_value); ?>
                                                </div>
                                            <?php endwhile; ?>
                                            </div>
                                        </div>
                                    </details>
                                </div>
                            <?php endif; ?>

                            <?php if ($boat->hasVideos()): ?>
                                <div class="videos-wrapper accordion-item dc-clear">
                                    <details class="accordion" open>
                                        <summary class="accordion-title"><h3><?php the_title(); ?> Videos</h3></summary>
                                        <div class="accordion-content videos-container">
                                            <div class="dc-flexed">
                                                <?php foreach ($boat->getVideos() as $video_url): ?>
                                                    <div class="boat-video-item dc-flex-50">
                                                        <div class="video-wrapper">
                                                            <?php
                                                            // Check if it's a YouTube or Vimeo URL and embed accordingly
                                                            if (strpos($video_url, 'youtube.com') !== false || strpos($video_url, 'youtu.be') !== false) {
                                                                // Extract YouTube video ID
                                                                preg_match('/(youtu\.be\/|youtube\.com\/(watch\?(.*&)?v=|(embed|v)\/))([^\?&"\'<>]+)/', $video_url, $matches);
                                                                $youtube_id = $matches[5] ?? '';
                                                                if ($youtube_id) {
                                                                    echo '<iframe width="100%" height="315" src="https://www.youtube.com/embed/' . esc_attr($youtube_id) . '" frameborder="0" allowfullscreen></iframe>';
                                                                }
                                                            } elseif (strpos($video_url, 'vimeo.com') !== false) {
                                                                // Extract Vimeo video ID
                                                                preg_match('/vimeo\.com\/(\d+)/', $video_url, $matches);
                                                                $vimeo_id = $matches[1] ?? '';
                                                                if ($vimeo_id) {
                                                                    echo '<iframe width="100%" height="315" src="https://player.vimeo.com/video/' . esc_attr($vimeo_id) . '" frameborder="0" allowfullscreen></iframe>';
                                                                }
                                                            } else {
                                                                // For other URLs, just display the link
                                                                echo '<a href="' . esc_url($video_url) . '" target="_blank">' . esc_html($video_url) . '</a>';
                                                            }
                                                            ?>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </details>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </article>

                <?php do_action('dealerschoice_after_single_boat', $post->ID); ?>

            </section>
        </div>
    </div>

    <?php

    // Similar Boats Section (stepped queries: model, make, type)
    $similar_ids = [];
    $current_id = get_the_ID();

    // 1. By same model (meta)
    if ($model) {
        $model_query = new WP_Query([
            'post_type' => 'boat',
            'posts_per_page' => 6,
            'post__not_in' => [$current_id],
            'meta_query' => [
                [
                    'key' => 'boat_model',
                    'value' => $model,
                    'compare' => '='
                ]
            ],
            'fields' => 'ids',
        ]);
        $similar_ids = $model_query->posts;
    }

    // 2. By same make (meta), fill up to 6
    if (count($similar_ids) < 6 && $make) {
        $make_query = new WP_Query([
            'post_type' => 'boat',
            'posts_per_page' => 6 - count($similar_ids),
            'post__not_in' => array_merge([$current_id], $similar_ids),
            'meta_query' => [
                [
                    'key' => 'boat_make',
                    'value' => $make,
                    'compare' => '='
                ]
            ],
            'fields' => 'ids',
        ]);
        $similar_ids = array_merge($similar_ids, $make_query->posts);
    }

    // 3. By same type (taxonomy), fill up to 6
    if (count($similar_ids) < 6 && !empty($type)) {
        $type_query = new WP_Query([
            'post_type' => 'boat',
            'posts_per_page' => 6 - count($similar_ids),
            'post__not_in' => array_merge([$current_id], $similar_ids),
            'tax_query' => [
                [
                    'taxonomy' => 'boat_type',
                    'field' => 'slug',
                    'terms' => $type,
                ]
            ],
            'fields' => 'ids',
        ]);
        $similar_ids = array_merge($similar_ids, $type_query->posts);
    }

    // 4. If still not enough, fill with any boats (excluding current and already found)
    if (count($similar_ids) < 6) {
        $any_query = new WP_Query([
            'post_type' => 'boat',
            'posts_per_page' => 6 - count($similar_ids),
            'post__not_in' => array_merge([$current_id], $similar_ids),
            'fields' => 'ids',
        ]);
        $similar_ids = array_merge($similar_ids, $any_query->posts);
    }

    // Now get the boats in the order of $similar_ids
    if (!empty($similar_ids)) {
        $final_query = new WP_Query([
            'post_type' => 'boat',
            'post__in' => $similar_ids,
            'orderby' => 'post__in',
            'posts_per_page' => 6,
        ]);
        ?>
        <section class="similar-boats-section dc-row" aria-label="Similar Boats">
            <div class="dealerschoice-container dc-clear">
                <h2>Shop Similar Boats</h2>
                <div class="dealerschoice-shortcode dealerschoice-slider">
                    <div class="boat-slider" id="similar-boats-slider" data-slick='{"slidesToShow": 3}'>
                        <?php while ($final_query->have_posts()): $final_query->the_post(); ?>
                            <?php \DC\Template_Loader::get_template_part('inventory', 'slide'); ?>
                        <?php endwhile; ?>
                    </div>
                    <div class="button-wrapper" id="similar-boats-slider-buttons"></div>
                </div>
            </div>
        </section>
        <?php
        wp_reset_postdata();
    }

endwhile;

get_footer();