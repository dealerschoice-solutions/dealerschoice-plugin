<?php
/**
 * Redirects Handler
 * 
 * Handles 404 redirects for boats that are no longer in inventory.
 * Redirects visitors to the main inventory page with a "Not Found" message
 * and filters by the requested boat type to show similar available boats.
 * 
 * @package DealersChoice
 * @subpackage Classes
 * @since 1.0.0
 */

namespace DC;

if (!defined('ABSPATH')) {
    exit;
}

class Redirects {
    
    /**
     * Initialize redirects
     */
    public static function init() {
        add_action('template_redirect', [__CLASS__, 'handle_boat_404']);
    }
    
    /**
     * Handle 404s for boat pages
     * 
     * Checks if the 404 request matches the /boat/{type}/{slug}/ pattern.
     * If so, redirects to the inventory page with the type pre-selected.
     */
    public static function handle_boat_404() {
        if (!is_404()) {
            return;
        }

        // Get the requested URI
        $request_uri = $_SERVER['REQUEST_URI'];
        
        // Check if it looks like a boat URL
        // Pattern: /boat/{type}/{slug}/
        // We use a regex that matches /boat/ followed by segment, followed by slug
        if (preg_match('#^/boat/([^/]+)/([^/]+)/?#', $request_uri, $matches)) {
            $type_slug = sanitize_text_field($matches[1]);
            // $boat_slug = $matches[2]; // We don't need the slug for the filter, just the type
            
            // Determine the inventory page URL
            // We search for the page with the inventory shortcode, or fallback to /inventory/
            $inventory_url = self::get_inventory_page_url($type_slug);
            
            if ($inventory_url) {
                // Build the redirect URL
                // We map 'type' from the URL to 'category' for the inventory filter
                // We utilize the passed 'category' param which the JS automatically picks up
                $redirect_url = add_query_arg([
                    'notfound' => '1',
                    'category' => $type_slug // Map the type from the URL to the category filter
                ], $inventory_url);
                
                wp_redirect($redirect_url, 301);
                exit;
            }
        }
        
        // Also handle the case where the URL structure might be /inventory/boat/{type}/{slug} depending on site setup
        // But the user specified /boat/{type}/{slug}
    }
    
    /**
     * Get the main inventory page URL
     * 
     * Tries to find the page containing [dealerschoice_inventory] shortcode.
     * Falls back to home_url('/inventory/') if not found.
     * 
     * @return string
     */
    private static function get_inventory_page_url($type_slug = '') {
        // First check if there's a stored option (in case we add one later)
        $page_id = get_option('dealers_choice_inventory_page_id');
        
        if ($page_id) {
            return get_permalink($page_id);
        }
        
        // Try to find page by slug 'inventory/type_slug' if type_slug is provided
        if (!empty($type_slug)) {
            $page = get_page_by_path('inventory/' . $type_slug);
            if ($page) {
                return get_permalink($page);
            }
        }

        // Try to find page by slug 'inventory'
        $page = get_page_by_path('inventory');
        if ($page) {
            return get_permalink($page);
        }
        
        // Try to find page with the shortcode (expensive query, so we cache it)
        $cached_id = get_transient('dealerschoice_inventory_page_id');
        if ($cached_id) {
            return get_permalink($cached_id);
        }
        
        global $wpdb;
        $sql = "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status = 'publish' AND post_content LIKE '%[dealerschoice_inventory%' LIMIT 1";
        $page_id = $wpdb->get_var($sql);
        
        if ($page_id) {
            set_transient('dealerschoice_inventory_page_id', $page_id, DAY_IN_SECONDS);
            return get_permalink($page_id);
        }
        
        // Fallback
        return home_url('/inventory/');
    }
}
