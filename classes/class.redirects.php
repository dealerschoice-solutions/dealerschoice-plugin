<?php
/**
 * Redirects 404s on boat URLs to the inventory page, filtered by boat type.
 *
 * @package DealersChoice
 * @subpackage Classes
 * @since 1.0.0
 */

namespace DC;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Redirects requests for missing boats to the inventory page.
 */
class Redirects {
    
    /**
     * Registers the 404 redirect handler.
     */
    public static function init() {
        add_action('template_redirect', [__CLASS__, 'handle_boat_404']);
    }
    
    /**
     * Redirects a 404 on /boat/{type}/{slug}/ to the inventory page.
     *
     * Sends a 301 to the inventory page with `notfound=1` and the boat type
     * as the `category` filter.
     */
    public static function handle_boat_404() {
        if (!is_404()) {
            return;
        }

        $request_uri = $_SERVER['REQUEST_URI'];
        
        if (preg_match('#^/boat/([^/]+)/([^/]+)/?#', $request_uri, $matches)) {
            $type_slug = sanitize_text_field($matches[1]);
            
            $inventory_url = self::get_inventory_page_url($type_slug);
            
            if ($inventory_url) {
                $redirect_url = add_query_arg([
                    'notfound' => '1',
                    'category' => $type_slug
                ], $inventory_url);
                
                wp_redirect($redirect_url, 301);
                exit;
            }
        }
    }
    
    /**
     * Resolves the inventory page URL.
     *
     * Checks the `dealers_choice_inventory_page_id` option, then a page at
     * `inventory/{type}`, then a page at `inventory`, then the first published
     * page containing [dealerschoice_inventory] (cached for a day). Falls back
     * to home_url('/inventory/').
     *
     * @param string $type_slug Optional. Boat type slug. Default ''.
     * @return string|false Inventory page URL, or false if a resolved page has no permalink.
     */
    private static function get_inventory_page_url($type_slug = '') {
        $page_id = get_option('dealers_choice_inventory_page_id');
        
        if ($page_id) {
            return get_permalink($page_id);
        }
        
        if (!empty($type_slug)) {
            $page = get_page_by_path('inventory/' . $type_slug);
            if ($page) {
                return get_permalink($page);
            }
        }

        $page = get_page_by_path('inventory');
        if ($page) {
            return get_permalink($page);
        }
        
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
        
        return home_url('/inventory/');
    }
}
