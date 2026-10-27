<?php
/**
 * Copies ACF field values on saved boats into their matching taxonomies.
 *
 * @package DealersChoice
 * @subpackage Classes
 * @since 1.0.0
 */

namespace DC;

/**
 * Syncs ACF field values to taxonomy terms when a boat is saved.
 *
 * Make, model, status and year are assigned as terms by value (created if
 * missing). Sale price, length, horsepower and person capacity are resolved
 * to their range terms.
 */
class ACF_Taxonomy_Sync {

    /**
     * Hooks the sync into acf/save_post after ACF saves its field values.
     */
    public function __construct() {
        add_action( 'acf/save_post', [ $this, 'sync_taxonomies_on_save' ], 20 );
    }

    /**
     * Syncs ACF field values to taxonomies for a saved boat.
     *
     * Skips autosaves, revisions and non-boat posts.
     *
     * @param int|string $post_id ID of the post being saved.
     */
    public function sync_taxonomies_on_save( $post_id ) {
        if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
            return;
        }

        if ( get_post_type( $post_id ) !== 'boat' ) {
            return;
        }

        $this->sync_direct_taxonomies( $post_id );
        $this->sync_range_taxonomies( $post_id );
    }

    /**
     * Assigns make, model, status and year terms from their ACF values.
     *
     * Creates missing terms. Clears the taxonomy when the field is empty.
     *
     * @param int|string $post_id Boat post ID.
     */
    private function sync_direct_taxonomies( $post_id ) {
        $map = [
            'boat_make'   => 'make',
            'boat_model'  => 'model',
            'boat_status' => 'boat_status',
            'boat_year'   => 'boat_year',
        ];

        foreach ( $map as $meta_key => $taxonomy ) {
            $value = trim( (string) get_post_meta( $post_id, $meta_key, true ) );

            if ( $value !== '' ) {
                wp_set_object_terms( $post_id, $value, $taxonomy, false );
            } else {
                wp_set_object_terms( $post_id, [], $taxonomy, false );
            }
        }
    }

    /**
     * Assigns price, length, horsepower and capacity range terms from ACF values.
     *
     * Clears the taxonomy when the value is zero or empty.
     *
     * @param int|string $post_id Boat post ID.
     */
    private function sync_range_taxonomies( $post_id ) {
        $price_raw = get_post_meta( $post_id, 'boat_saleprice', true );
        $price     = (float) preg_replace( '/[^0-9.]/', '', (string) $price_raw );

        if ( $price > 0 ) {
            $term = dealers_choice_get_price_range_term( $price );
            if ( $term ) {
                wp_set_object_terms( $post_id, $term, 'price_range', false );
            }
        } else {
            wp_set_object_terms( $post_id, [], 'price_range', false );
        }

        $length_feet = (float) get_post_meta( $post_id, 'boat_length', true );

        if ( $length_feet > 0 ) {
            $term = dealers_choice_get_length_range_term( $length_feet );
            if ( $term ) {
                wp_set_object_terms( $post_id, $term, 'length_range', false );
            }
        } else {
            wp_set_object_terms( $post_id, [], 'length_range', false );
        }

        $hp = (float) get_post_meta( $post_id, 'engine_hp', true );

        if ( $hp > 0 ) {
            $term = dealers_choice_get_horsepower_range_term( $hp );
            if ( $term ) {
                wp_set_object_terms( $post_id, $term, 'horsepower', false );
            }
        } else {
            wp_set_object_terms( $post_id, [], 'horsepower', false );
        }

        $capacity = (int) get_post_meta( $post_id, 'boat_person_capacity', true );

        if ( $capacity > 0 ) {
            $term = dealers_choice_get_capacity_range_term( $capacity );
            if ( $term ) {
                wp_set_object_terms( $post_id, $term, 'person_capacity', false );
            }
        } else {
            wp_set_object_terms( $post_id, [], 'person_capacity', false );
        }
    }
}
