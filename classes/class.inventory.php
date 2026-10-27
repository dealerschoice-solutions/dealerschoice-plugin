<?php
/**
 * Taxonomy term counts for the inventory filters.
 *
 * @package DealersChoice
 */
namespace DC;

if (!defined('ABSPATH')) {
    exit;
}

class Inventory {

    static public function getTotalInventory($filters = []){
        $posts = wp_count_posts( 'boat' );
        return isset($posts->publish) ? $posts->publish : 0;
    }

    static public function getTaxonomyValuesForBoatType($filters = []){
        $taxArgs = [];
        foreach($filters as $filter => $value){
            if($filter != 'boat_type' && $value !== false){
                $fArgs['taxonomy'] = $filter;
                $fArgs['field'] = 'slug';
                $fArgs['terms'] = $value;
                $fArgs['operator'] = 'IN';
                array_push($taxArgs, $fArgs);
            }
        }
        return self::getFilteredTaxonomyValues([
            'taxonomy' => 'boat_type',
            'hide_empty' => false
        ], $taxArgs);
    }
    static public function getTaxonomyValuesForCondition($filters = []){
        $taxArgs = [];
        foreach($filters as $filter => $value){
            if($filter != 'condition' && $value !== false){
                $fArgs['taxonomy'] = $filter;
                $fArgs['field'] = 'slug';
                $fArgs['terms'] = $value;
                $fArgs['operator'] = 'IN';
                array_push($taxArgs, $fArgs);
            }
        }
        return self::getFilteredTaxonomyValues([
            'taxonomy' => 'condition',
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'DESC'
        ], $taxArgs);
    }
    static public function getTaxonomyValuesForStatus($filters = []){
        $taxArgs = [];
        foreach($filters as $filter => $value){
            if($filter != 'boat_status' && $value !== false){
                $fArgs['taxonomy'] = $filter;
                $fArgs['field'] = 'slug';
                $fArgs['terms'] = $value;
                $fArgs['operator'] = 'IN';
                array_push($taxArgs, $fArgs);
            }
        }
        return self::getFilteredTaxonomyValues([
            'taxonomy' => 'boat_status',
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC'
        ], $taxArgs);
    }
    static public function getTaxonomyValuesForLocation($filters = []){
        $taxArgs = [];
        foreach($filters as $filter => $value){
            if($filter != 'location' && $value !== false){
                $fArgs['taxonomy'] = $filter;
                $fArgs['field'] = 'slug';
                $fArgs['terms'] = $value;
                $fArgs['operator'] = 'IN';
                array_push($taxArgs, $fArgs);
            }
        }
        return self::getFilteredTaxonomyValues([
            'taxonomy' => 'location',
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'DESC'
        ], $taxArgs);
    }
    static public function getTaxonomyValuesForYear($filters = []){
        $taxArgs = [];
        foreach($filters as $filter => $value){
            if($filter != 'boat_year' && $value !== false){
                $fArgs['taxonomy'] = $filter;
                $fArgs['field'] = 'slug';
                $fArgs['terms'] = $value;
                $fArgs['operator'] = 'IN';
                array_push($taxArgs, $fArgs);
            }
        }
        return self::getFilteredTaxonomyValues([
            'taxonomy' => 'boat_year',
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'DESC'
        ], $taxArgs);
    }
    static public function getTaxonomyValuesForMake($filters = []){
        $taxArgs = [];
        foreach($filters as $filter => $value){
            if($filter != 'make' && $value !== false){
                $fArgs['taxonomy'] = $filter;
                $fArgs['field'] = 'slug';
                $fArgs['terms'] = $value;
                $fArgs['operator'] = 'IN';
                array_push($taxArgs, $fArgs);
            }
        }
        return self::getFilteredTaxonomyValues([
            'taxonomy' => 'make',
            'hide_empty' => false
        ], $taxArgs);
    }
    static public function getTaxonomyValuesForModel($filters = []){
        $taxArgs = [];
        foreach($filters as $filter => $value){
            if($filter != 'model' && $value !== false){
                $fArgs['taxonomy'] = $filter;
                $fArgs['field'] = 'slug';
                $fArgs['terms'] = $value;
                $fArgs['operator'] = 'IN';
                array_push($taxArgs, $fArgs);
            }
        }
        return self::getFilteredTaxonomyValues([
            'taxonomy' => 'model',
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'DESC'
        ], $taxArgs);
    }
    static public function getTaxonomyValuesForPriceRange($filters = []){
        $taxArgs = [];
        foreach($filters as $filter => $value){
            if($filter != 'price_range' && $value !== false){
                $fArgs['taxonomy'] = $filter;
                $fArgs['field'] = 'slug';
                $fArgs['terms'] = $value;
                $fArgs['operator'] = 'IN';
                array_push($taxArgs, $fArgs);
            }
        }
        return self::getFilteredTaxonomyValues([
            'taxonomy' => 'price_range',
            'hide_empty' => false,
            'orderby'    => 'id',
            'order'      => 'ASC'
        ], $taxArgs);
    }
    static public function getTaxonomyValuesForLength($filters = []){
        $taxArgs = [];
        foreach($filters as $filter => $value){
            if($filter != 'length_range' && $value !== false){
                $fArgs['taxonomy'] = $filter;
                $fArgs['field'] = 'slug';
                $fArgs['terms'] = $value;
                $fArgs['operator'] = 'IN';
                array_push($taxArgs, $fArgs);
            }
        }
        return self::getFilteredTaxonomyValues([
            'taxonomy' => 'length_range',
            'hide_empty' => false,
            'orderby'    => 'id',
            'order'      => 'ASC'
        ], $taxArgs);
    }
    static public function getTaxonomyValuesForHorsepower($filters = []){
        $taxArgs = [];
        foreach($filters as $filter => $value){
            if($filter != 'horsepower' && $value !== false){
                $fArgs['taxonomy'] = $filter;
                $fArgs['field'] = 'slug';
                $fArgs['terms'] = $value;
                $fArgs['operator'] = 'IN';
                array_push($taxArgs, $fArgs);
            }
        }
        return self::getFilteredTaxonomyValues([
            'taxonomy' => 'horsepower',
            'hide_empty' => false,
            'orderby'    => 'id',
            'order'      => 'ASC'
        ], $taxArgs);
    }
    static public function getTaxonomyValuesForPersonCapacity($filters = []){
        $taxArgs = [];
        foreach($filters as $filter => $value){
            if($filter != 'person_capacity' && $value !== false){
                $fArgs['taxonomy'] = $filter;
                $fArgs['field'] = 'slug';
                $fArgs['terms'] = $value;
                $fArgs['operator'] = 'IN';
                array_push($taxArgs, $fArgs);
            }
        }
        return self::getFilteredTaxonomyValues([
            'taxonomy' => 'person_capacity',
            'hide_empty' => false,
            'orderby'    => 'id',
            'order'      => 'ASC'
        ], $taxArgs);
    }

    static private function getFilteredTaxonomyValues($args = [], $taxArgs = []) {
        global $wpdb;
        
        $terms = get_terms($args);
        $terms_with_counts = array();
        $total = 0;
        
        if (empty($terms)) {
            return ['terms' => [], 'total' => 0];
        }

        $taxonomy = $args['taxonomy'];
        $term_ids = wp_list_pluck($terms, 'term_id');
        $term_id_placeholders = implode(',', array_fill(0, count($term_ids), '%d'));
        
        // One JOIN set per active filter on another taxonomy.
        $tax_joins = '';
        $join_counter = 1;
        $join_params = [];

        $taxArgs = self::sanitizeTaxArgs($taxArgs);

        if (!empty($taxArgs)) {
            foreach ($taxArgs as $tArg) {
                $alias = 'tr' . $join_counter;
                $tax_taxonomy = $tArg['taxonomy'];
                $tax_terms = is_array($tArg['terms']) ? $tArg['terms'] : [$tArg['terms']];
                
                $tax_joins .= " INNER JOIN {$wpdb->term_relationships} AS {$alias} ON p.ID = {$alias}.object_id";
                
                $field = isset($tArg['field']) ? $tArg['field'] : 'term_id';
                
                if ($field === 'slug') {
                    $tax_term_placeholders = implode(',', array_fill(0, count($tax_terms), '%s'));
                    $tax_joins .= " INNER JOIN {$wpdb->term_taxonomy} AS tt{$join_counter} ON {$alias}.term_taxonomy_id = tt{$join_counter}.term_taxonomy_id";
                    $tax_joins .= " INNER JOIN {$wpdb->terms} AS t{$join_counter} ON tt{$join_counter}.term_id = t{$join_counter}.term_id";
                    $tax_joins .= " AND tt{$join_counter}.taxonomy = '{$tax_taxonomy}' AND t{$join_counter}.slug IN ({$tax_term_placeholders})";
                    
                    $join_params = array_merge($join_params, $tax_terms);
                } else {
                    $tax_term_placeholders = implode(',', array_fill(0, count($tax_terms), '%d'));
                    $tax_joins .= " INNER JOIN {$wpdb->term_taxonomy} AS tt{$join_counter} ON {$alias}.term_taxonomy_id = tt{$join_counter}.term_taxonomy_id AND tt{$join_counter}.taxonomy = '{$tax_taxonomy}' AND tt{$join_counter}.term_id IN ({$tax_term_placeholders})";
                    
                    $join_params = array_merge($join_params, $tax_terms);
                }
                
                $join_counter++;
            }
        }
        
        // Counts published, publicly visible boats per term in a single query.
        $query = "
            SELECT tr.term_taxonomy_id, COUNT(DISTINCT p.ID) as count
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
            INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
            {$tax_joins}
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'do_not_show_on_public_website'
            WHERE p.post_type = 'boat'
            AND p.post_status = 'publish'
            AND tt.taxonomy = %s
            AND tt.term_id IN ({$term_id_placeholders})
            AND (pm.meta_value IS NULL OR pm.meta_value = '0')
            GROUP BY tr.term_taxonomy_id
        ";
        
        // Placeholder order: JOIN params, then WHERE params.
        $query_params = array_merge(
            $join_params,
            [$taxonomy],
            $term_ids
        );
        
        $results = $wpdb->get_results($wpdb->prepare($query, $query_params), OBJECT_K);
        
        foreach ($terms as $term) {
            $count = isset($results[$term->term_taxonomy_id]) ? (int) $results[$term->term_taxonomy_id]->count : 0;
            $total += $count;
            
            $terms_with_counts[] = array(
                'term' => $term,
                'count' => $count,
            );
        }

        return [
            'terms' => $terms_with_counts,
            'total' => $total
        ];
    }

    /**
     * Validates and sanitizes tax args for the counts query.
     *
     * Keeps only taxonomies registered on the boat post type and fields 'slug' or
     * 'term_id'. Slugs pass through sanitize_title() and IDs through absint(); nested,
     * empty and 'all' values are dropped, and a tax arg with no remaining terms is removed.
     *
     * @param array $taxArgs Tax args, each with 'taxonomy', 'field' and 'terms'.
     * @return array Sanitized tax args.
     */
    static private function sanitizeTaxArgs($taxArgs) {
        $clean = [];

        if (!is_array($taxArgs)) {
            return $clean;
        }

        foreach ($taxArgs as $tArg) {
            if (!is_array($tArg) || !isset($tArg['taxonomy']) || !is_string($tArg['taxonomy'])) {
                continue;
            }

            $taxonomy = $tArg['taxonomy'];

            if (!taxonomy_exists($taxonomy) || !is_object_in_taxonomy('boat', $taxonomy)) {
                continue;
            }

            $field = isset($tArg['field']) ? $tArg['field'] : 'term_id';

            if (!in_array($field, ['slug', 'term_id'], true)) {
                continue;
            }

            $raw_terms = isset($tArg['terms']) ? $tArg['terms'] : [];
            $raw_terms = is_array($raw_terms) ? $raw_terms : [$raw_terms];
            $terms     = [];

            foreach ($raw_terms as $term) {
                if (is_array($term) || is_object($term)) {
                    continue;
                }

                if ($field === 'slug') {
                    $slug = sanitize_title((string) $term);
                    if ($slug !== '' && $slug !== 'all') {
                        $terms[] = $slug;
                    }
                } else {
                    $term_id = absint($term);
                    if ($term_id > 0) {
                        $terms[] = $term_id;
                    }
                }
            }

            if (empty($terms)) {
                continue;
            }

            $tArg['taxonomy'] = $taxonomy;
            $tArg['field']    = $field;
            $tArg['terms']    = array_values(array_unique($terms));

            $clean[] = $tArg;
        }

        return $clean;
    }
}