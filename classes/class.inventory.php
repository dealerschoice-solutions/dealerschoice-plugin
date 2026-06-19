<?php
/**
 * Inventory Helper Class
 * 
 * Provides static helper methods for retrieving and filtering boat inventory
 * data. This class is primarily used for frontend displays and filtering
 * interfaces to get taxonomy term counts with active filters applied.
 * 
 * @package DealersChoice
 * @subpackage Classes
 * @since 1.0.0
 * 
 * Functionality:
 * - Get total inventory count
 * - Retrieve taxonomy values with post counts
 * - Apply filters across multiple taxonomies
 * - Respect do_not_show_on_public_website meta field
 * - Optimized queries using direct wpdb calls
 * 
 * Available Taxonomy Methods:
 * - getTaxonomyValuesForBoatType()
 * - getTaxonomyValuesForCondition()
 * - getTaxonomyValuesForLocation()
 * - getTaxonomyValuesForYear()
 * - getTaxonomyValuesForMake()
 * - getTaxonomyValuesForModel()
 * - getTaxonomyValuesForPriceRange()
 * - getTaxonomyValuesForLength()
 * - getTaxonomyValuesForHorsepower()
 * - getTaxonomyValuesForPersonCapacity()
 * 
 * Filter Format:
 * Each method accepts $filters array with taxonomy => term_id pairs
 * Example: ['boat_type' => 123, 'condition' => 456]
 * 
 * Return Format:
 * Returns array with:
 * - 'terms': Array of term objects with counts
 * - 'total': Total count across all terms
 * 
 * Performance Optimization:
 * - Single database query per taxonomy (vs N queries)
 * - Uses JOIN for multi-taxonomy filtering
 * - Prepared statements for security
 * - Efficient post counting with GROUP BY
 * 
 * Public Website Filtering:
 * - Automatically excludes boats with do_not_show_on_public_website = 1
 * - Applied in all counting queries
 * 
 * Dependencies:
 * - WordPress taxonomy functions (get_terms)
 * - Direct wpdb queries for performance
 */
namespace DC;

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

        // Build optimized query to get all counts at once
        $taxonomy = $args['taxonomy'];
        $term_ids = wp_list_pluck($terms, 'term_id');
        $term_id_placeholders = implode(',', array_fill(0, count($term_ids), '%d'));
        
        // Build the JOIN clauses for additional taxonomy filters
        $tax_joins = '';
        $join_counter = 1;
        $join_params = [];
        
        if (!empty($taxArgs)) {
            foreach ($taxArgs as $tArg) {
                $alias = 'tr' . $join_counter;
                $tax_taxonomy = $tArg['taxonomy'];
                $tax_terms = is_array($tArg['terms']) ? $tArg['terms'] : [$tArg['terms']];
                
                $tax_joins .= " INNER JOIN {$wpdb->term_relationships} AS {$alias} ON p.ID = {$alias}.object_id";
                
                // Determine if we are querying by slug or term_id
                $field = isset($tArg['field']) ? $tArg['field'] : 'term_id';
                
                if ($field === 'slug') {
                    $tax_term_placeholders = implode(',', array_fill(0, count($tax_terms), '%s'));
                    // For slug, we need to join term_taxonomy AND terms
                    $tax_joins .= " INNER JOIN {$wpdb->term_taxonomy} AS tt{$join_counter} ON {$alias}.term_taxonomy_id = tt{$join_counter}.term_taxonomy_id";
                    $tax_joins .= " INNER JOIN {$wpdb->terms} AS t{$join_counter} ON tt{$join_counter}.term_id = t{$join_counter}.term_id";
                    $tax_joins .= " AND tt{$join_counter}.taxonomy = '{$tax_taxonomy}' AND t{$join_counter}.slug IN ({$tax_term_placeholders})";
                    
                    $join_params = array_merge($join_params, $tax_terms);
                } else {
                    // Default to ID
                    $tax_term_placeholders = implode(',', array_fill(0, count($tax_terms), '%d'));
                    $tax_joins .= " INNER JOIN {$wpdb->term_taxonomy} AS tt{$join_counter} ON {$alias}.term_taxonomy_id = tt{$join_counter}.term_taxonomy_id AND tt{$join_counter}.taxonomy = '{$tax_taxonomy}' AND tt{$join_counter}.term_id IN ({$tax_term_placeholders})";
                    
                    $join_params = array_merge($join_params, $tax_terms);
                }
                
                $join_counter++;
            }
        }
        
        // Query to get counts for all terms at once
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
        
        // Prepare query parameters: Joins first, then WHERE clause
        $query_params = array_merge(
            $join_params,
            [$taxonomy],
            $term_ids
        );
        
        $results = $wpdb->get_results($wpdb->prepare($query, $query_params), OBJECT_K);
        
        // Map counts to terms
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
}