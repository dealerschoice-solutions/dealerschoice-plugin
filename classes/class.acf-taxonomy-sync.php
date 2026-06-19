<?php
/**
 * ACF Taxonomy Sync Class
 *
 * Listens for `boat` post saves in the admin and ensures the seven ACF
 * fields that have a corresponding taxonomy are kept in sync automatically.
 * This prevents data-integrity issues where an editor updates an ACF text
 * field (e.g. Make) without updating the matching taxonomy term.
 *
 * Fields synced:
 *   - boat_make        → make           (direct term, auto-created if new)
 *   - boat_model       → model          (direct term, auto-created if new)
 *   - boat_status      → boat_status    (direct term, auto-created if new)
 *   - boat_year        → boat_year      (direct term, auto-created if new)
 *   - boat_saleprice   → price_range    (resolved to pre-defined range term)
 *   - boat_length      → length_range   (resolved to pre-defined range term)
 *   - engine_hp        → horsepower     (resolved to pre-defined range term)
 *   - boat_person_capacity → person_capacity (resolved to pre-defined range term)
 *
 * @package DealersChoice
 * @subpackage Classes
 * @since 1.0.0
 */

namespace DC;

class ACF_Taxonomy_Sync {

    public function __construct() {
        // Priority 20 — fires after ACF has committed its own field data (priority 10),
        // so get_post_meta() returns the freshly-saved values.
        add_action( 'acf/save_post', [ $this, 'sync_taxonomies_on_save' ], 20 );
    }

    /**
     * Sync ACF field values to their corresponding taxonomies after post save.
     *
     * @param int $post_id The ID of the post being saved.
     */
    public function sync_taxonomies_on_save( $post_id ) {
        // Skip autosaves and revisions.
        if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
            return;
        }

        // Only run for the boat post type.
        if ( get_post_type( $post_id ) !== 'boat' ) {
            return;
        }

        $this->sync_direct_taxonomies( $post_id );
        $this->sync_range_taxonomies( $post_id );
    }

    /**
     * Sync ACF fields that map directly to a taxonomy term by value.
     * WP will auto-create any term that does not yet exist.
     *
     * @param int $post_id
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
                // Field was cleared — remove any existing taxonomy association.
                wp_set_object_terms( $post_id, [], $taxonomy, false );
            }
        }
    }

    /**
     * Sync ACF fields that map to a pre-defined range taxonomy term.
     * The actual numeric value is resolved to the correct range term name
     * using the existing global helper functions.
     *
     * @param int $post_id
     */
    private function sync_range_taxonomies( $post_id ) {
        // Sale Price → price_range
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

        // Length (feet) → length_range
        $length_feet = (float) get_post_meta( $post_id, 'boat_length', true );

        if ( $length_feet > 0 ) {
            $term = dealers_choice_get_length_range_term( $length_feet );
            if ( $term ) {
                wp_set_object_terms( $post_id, $term, 'length_range', false );
            }
        } else {
            wp_set_object_terms( $post_id, [], 'length_range', false );
        }

        // Engine Horsepower → horsepower
        $hp = (float) get_post_meta( $post_id, 'engine_hp', true );

        if ( $hp > 0 ) {
            $term = dealers_choice_get_horsepower_range_term( $hp );
            if ( $term ) {
                wp_set_object_terms( $post_id, $term, 'horsepower', false );
            }
        } else {
            wp_set_object_terms( $post_id, [], 'horsepower', false );
        }

        // Person Capacity → person_capacity
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
