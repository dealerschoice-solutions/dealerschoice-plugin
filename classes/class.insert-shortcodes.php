<?php
/**
 * Shortcode builder modal for inserting DealersChoice shortcodes into TinyMCE editors.
 *
 * @package DealersChoice
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Returns the shortcode schema used to build the builder modal form.
 *
 * Each shortcode entry has a label, a description and a fields array of
 * attribute_name => config. Field config keys: type ('text', 'number',
 * 'toggle', 'select' or 'taxonomy'), label, default (omitted from the
 * shortcode when unchanged), options (select), and taxonomy, multiple and
 * terms (taxonomy; terms are value/label pairs of non-empty term slugs).
 *
 * @return array Shortcode tag => shortcode config.
 */
function dc_get_shortcode_schema() {
    $fetch = function( $taxonomy ) {
        $terms = get_terms( array(
            'taxonomy'   => $taxonomy,
            'hide_empty' => true,
            'orderby'    => 'name',
            'order'      => 'ASC',
            'fields'     => 'id=>name',
        ) );

        if ( is_wp_error( $terms ) || empty( $terms ) ) {
            return array();
        }

        $result = array();
        foreach ( get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => true, 'orderby' => 'name', 'fields' => 'all' ) ) as $term ) {
            $result[] = array( 'value' => $term->slug, 'label' => $term->name );
        }
        return $result;
    };

    // Lead form options from Gravity Forms, when active.
    $gf_forms_options = array( '0' => '— No Lead Form —' );
    if ( class_exists( 'GFAPI' ) ) {
        foreach ( GFAPI::get_forms( true ) as $form ) {
            $gf_forms_options[ (string) $form['id'] ] = $form['title'];
        }
    }

    return array(

        'dealerschoice_inventory' => array(
            'label'       => 'Inventory Grid',
            'description' => 'Filterable, paginated grid of boats.',
            'fields'      => array(
                'posts_per_page' => array(
                    'type'    => 'number',
                    'label'   => 'Boats Per Page',
                    'default' => 12,
                ),
                'show_filters' => array(
                    'type'    => 'toggle',
                    'label'   => 'Show Filters Sidebar',
                    'default' => 'true',
                ),
                'show_search' => array(
                    'type'    => 'toggle',
                    'label'   => 'Show Search Box',
                    'default' => 'true',
                ),
                'show_sort' => array(
                    'type'    => 'toggle',
                    'label'   => 'Show Sort Dropdown',
                    'default' => 'true',
                ),
                'category' => array(
                    'type'     => 'taxonomy',
                    'label'    => 'Boat Type',
                    'taxonomy' => 'boat_type',
                    'multiple' => true,
                    'default'  => '',
                    'terms'    => $fetch( 'boat_type' ),
                ),
                'condition' => array(
                    'type'     => 'taxonomy',
                    'label'    => 'Condition',
                    'taxonomy' => 'condition',
                    'multiple' => true,
                    'default'  => '',
                    'terms'    => $fetch( 'condition' ),
                ),
                'status' => array(
                    'type'     => 'taxonomy',
                    'label'    => 'Status',
                    'taxonomy' => 'boat_status',
                    'multiple' => true,
                    'default'  => '',
                    'terms'    => $fetch( 'boat_status' ),
                ),
                'location' => array(
                    'type'     => 'taxonomy',
                    'label'    => 'Location',
                    'taxonomy' => 'location',
                    'multiple' => true,
                    'default'  => '',
                    'terms'    => $fetch( 'location' ),
                ),
                'make' => array(
                    'type'     => 'taxonomy',
                    'label'    => 'Make',
                    'taxonomy' => 'make',
                    'multiple' => true,
                    'default'  => '',
                    'terms'    => $fetch( 'make' ),
                ),
            ),
        ),

        'dealerschoice_slider' => array(
            'label'       => 'Inventory Slider',
            'description' => 'Horizontal Slick carousel of boat listings.',
            'fields'      => array(
                'limit' => array(
                    'type'    => 'number',
                    'label'   => 'Number of Boats',
                    'default' => 6,
                ),
                'category' => array(
                    'type'     => 'taxonomy',
                    'label'    => 'Boat Type',
                    'taxonomy' => 'boat_type',
                    'multiple' => false,
                    'default'  => '',
                    'terms'    => $fetch( 'boat_type' ),
                ),
                'condition' => array(
                    'type'     => 'taxonomy',
                    'label'    => 'Condition',
                    'taxonomy' => 'condition',
                    'multiple' => false,
                    'default'  => '',
                    'terms'    => $fetch( 'condition' ),
                ),
                'location' => array(
                    'type'     => 'taxonomy',
                    'label'    => 'Location',
                    'taxonomy' => 'location',
                    'multiple' => false,
                    'default'  => '',
                    'terms'    => $fetch( 'location' ),
                ),
                'make' => array(
                    'type'     => 'taxonomy',
                    'label'    => 'Make',
                    'taxonomy' => 'make',
                    'multiple' => false,
                    'default'  => '',
                    'terms'    => $fetch( 'make' ),
                ),
                'year' => array(
                    'type'     => 'taxonomy',
                    'label'    => 'Year',
                    'taxonomy' => 'boat_year',
                    'multiple' => false,
                    'default'  => '',
                    'terms'    => $fetch( 'boat_year' ),
                ),
                'orderby' => array(
                    'type'    => 'select',
                    'label'   => 'Order By',
                    'default' => 'date',
                    'options' => array(
                        'date'   => 'Date Added',
                        'price'  => 'Price',
                        'year'   => 'Year',
                        'length' => 'Length',
                    ),
                ),
                'order' => array(
                    'type'    => 'select',
                    'label'   => 'Order Direction',
                    'default' => 'DESC',
                    'options' => array(
                        'DESC' => 'Descending',
                        'ASC'  => 'Ascending',
                    ),
                ),
                'slides_to_show' => array(
                    'type'    => 'number',
                    'label'   => 'Slides to Show',
                    'default' => 3,
                ),
                'autoplay' => array(
                    'type'    => 'toggle',
                    'label'   => 'Autoplay',
                    'default' => false,
                ),
                'autoplay_speed' => array(
                    'type'    => 'number',
                    'label'   => 'Autoplay Speed (ms)',
                    'default' => 3000,
                ),
            ),
        ),

        'dealerschoice_filters' => array(
            'label'       => 'Filters Sidebar',
            'description' => 'Standalone filter sidebar (use alongside a custom inventory loop).',
            'fields'      => array(),
        ),

        'dealerschoice_favorites' => array(
            'label'       => 'Favorites List',
            'description' => 'Displays boats the visitor has saved as favorites.',
            'fields'      => array(),
        ),

        'dealerschoice_location_list' => array(
            'label'       => 'Location List',
            'description' => 'Renders a list of location buttons for the location selector popup.',
            'fields'      => array(),
        ),

        'dealerschoice_boat_quiz' => array(
            'label'       => 'Boat Finder Quiz',
            'description' => 'Multi-step quiz that matches visitors to the right boat.',
            'fields'      => array(
                'title' => array(
                    'type'    => 'text',
                    'label'   => 'Quiz Title',
                    'default' => 'Find Your Perfect Boat',
                ),
                'subtitle' => array(
                    'type'    => 'text',
                    'label'   => 'Quiz Subtitle',
                    'default' => "Answer a few quick questions and we'll match you with the right vessel for the way you boat.",
                ),
                'submit_label' => array(
                    'type'    => 'text',
                    'label'   => 'Submit Button Label',
                    'default' => 'Find My Perfect Boat',
                ),
                'gravity_form_id' => array(
                    'type'    => 'select',
                    'label'   => 'Lead Capture Form',
                    'default' => '0',
                    'options' => $gf_forms_options,
                ),
                'boat_count' => array(
                    'type'    => 'number',
                    'label'   => 'Number of Matching Boats',
                    'default' => 3,
                ),
            ),
        ),

        'dealerschoice_finance_calculator' => array(
            'label'       => 'Finance Calculator',
            'description' => 'Client-side loan payment calculator with amortization schedule.',
            'fields'      => array(
                'title' => array(
                    'type'    => 'text',
                    'label'   => 'Heading',
                    'default' => 'Estimate Your Payment',
                ),
                'default_amount' => array(
                    'type'    => 'number',
                    'label'   => 'Pre-filled Amount Financed',
                    'default' => '',
                ),
            ),
        ),

    );
}

/**
 * Outputs the "Insert DealersChoice" button in the media buttons area above editors.
 *
 * @return void
 */
function dc_add_shortcode_button() {
    ?>
    <button type="button" class="button button-secondary dc-generate-shortcode-button">
        <svg data-name="DealersChoice Solutions" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 371.5 102.94" style="height:6px;margin-right:4px;display:inline-block;vertical-align:middle;fill:#2271b1;" aria-hidden="true" focusable="false">
            <path d="M131.04,11.79c83.3,0,106.87,36.93,106.87,36.93h133.59C332.2-.78,165.61,0,165.61,0h-97.28c-.34,0-.67.17-.86.46l-3.61,5.44-3.2,4.83c-.3.45.02,1.07.57,1.07h69.81,0Z"/>
            <path d="M131.04,91.15H7.79c-.34,0-.67.17-.86.46L.11,101.88c-.3.45.02,1.07.57,1.07h164.92s166.59.78,205.88-48.72h-133.59s-23.58,36.93-106.87,36.93h0Z"/>
        </svg>
        Insert DealersChoice
    </button>
    <?php
}
add_action( 'media_buttons', 'dc_add_shortcode_button' );

/**
 * Registers the DealersChoice TinyMCE plugin for users who can edit posts.
 *
 * @param array $plugins TinyMCE plugin name => script URL.
 * @return array Filtered plugins.
 */
function dc_register_tinymce_plugin( $plugins ) {
    if ( ! current_user_can( 'edit_posts' ) ) {
        return $plugins;
    }
    $plugins['dc_shortcode'] = DC_PLUGIN_URL . 'admin/dc-tinymce-plugin.js';
    return $plugins;
}
add_filter( 'mce_external_plugins', 'dc_register_tinymce_plugin' );

function dc_add_tinymce_button( $buttons ) {
    $buttons[] = 'dc_shortcode';
    return $buttons;
}
add_filter( 'mce_buttons', 'dc_add_tinymce_button' );

/**
 * Enqueues the shortcode builder script, styles and schema on post edit screens.
 *
 * @param string $hook Current admin page hook suffix.
 * @return void
 */
function dc_enqueue_generate_shortcode_script( $hook ) {
    if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
        return;
    }

    wp_enqueue_style(
        'dc-shortcode-builder',
        DC_PLUGIN_URL . 'admin/dc-shortcode-builder.css',
        array(),
        DC_VERSION
    );

    wp_enqueue_script(
        'dc-generate-shortcode',
        DC_PLUGIN_URL . 'admin/generate-shortcode.js',
        array( 'jquery' ),
        DC_VERSION,
        true
    );

    wp_localize_script(
        'dc-generate-shortcode',
        'dcShortcodeBuilder',
        array( 'schema' => dc_get_shortcode_schema() )
    );
}
add_action( 'admin_enqueue_scripts', 'dc_enqueue_generate_shortcode_script' );
