<?php
/**
 * Template Loader
 * 
 * Handles loading of template files with theme override support. This class implements
 * WordPress's template hierarchy pattern, checking the active theme first before
 * falling back to the plugin's default templates.
 * 
 * Template Override Pattern:
 * 1. Check theme directory: {theme}/dealerschoice/{template-name}
 * 2. Fall back to plugin: {plugin}/templates/{template-name}
 * 
 * This allows themes to fully customize the display while maintaining default
 * functionality when no theme override exists.
 * 
 * @package DealersChoice
 * @subpackage Classes
 * @since 1.0.0
 * 
 * Usage:
 * ```php
 * // Load a template with theme override support
 * DC\Template_Loader::get_template_part('content', 'boat');
 * 
 * // Load with variables
 * DC\Template_Loader::get_template_part('content', 'boat', ['boat' => $boat]);
 * 
 * // Get template path without loading
 * $path = DC\Template_Loader::locate_template('single-boat.php');
 * ```
 * 
 * Available Templates:
 * - single-boat.php: Single boat detail page
 * - inventory-block.php: Individual boat card/block
 * - filters.php: Filter sidebar
 * - shortcodes/inventory-slider.php: Carousel shortcode
 * 
 * Dependencies:
 * - WordPress template system
 * - Plugin constants (DC_PLUGIN_DIR)
 */

namespace DC;

if (!defined('ABSPATH')) {
    exit;
}

class Template_Loader {
    
    /**
     * Locate a template file
     * 
     * Searches for template in the theme's dealerschoice directory first,
     * then falls back to the plugin's templates directory.
     * 
     * @param string $template_name Template file name (e.g., 'single-boat.php')
     * @return string Full path to template file
     */
    public static function locate_template($template_name) {
        // Check theme directory first: theme/dealerschoice/{template}
        $theme_template = locate_template(['dealerschoice/' . $template_name]);
        
        if ($theme_template) {
            return $theme_template;
        }
        
        // Fall back to plugin template
        $plugin_template = DC_PLUGIN_DIR . 'templates/' . $template_name;
        
        if (file_exists($plugin_template)) {
            return $plugin_template;
        }
        
        return '';
    }
    
    /**
     * Load a template file
     * 
     * @param string $template_name Template file name
     * @param array $args Variables to extract and make available to template
     * @param bool $return Whether to return output instead of echoing
     * @return string|void Template output if $return is true
     */
    public static function get_template($template_name, $args = [], $return = false) {
        $template_path = self::locate_template($template_name);
        
        if (empty($template_path)) {
            return '';
        }
        
        // Extract variables for template
        if (!empty($args) && is_array($args)) {
            extract($args);
        }
        
        if ($return) {
            ob_start();
            include $template_path;
            return ob_get_clean();
        } else {
            include $template_path;
        }
    }
    
    /**
     * Load a template part (like get_template_part)
     * 
     * WordPress-style template part loader with theme override support.
     * 
     * @param string $slug Template slug (e.g., 'content')
     * @param string $name Template name (e.g., 'boat')
     * @param array $args Variables to pass to template
     * @return void
     */
    public static function get_template_part($slug, $name = '', $args = []) {
        $templates = [];
        
        if ($name) {
            $templates[] = "{$slug}-{$name}.php";
        }
        $templates[] = "{$slug}.php";
        
        foreach ($templates as $template_name) {
            $template_path = self::locate_template($template_name);
            if ($template_path) {
                // Extract variables for template
                if (!empty($args) && is_array($args)) {
                    extract($args);
                }
                
                // Allow filtering before load
                do_action('dealerschoice_before_template_part', $slug, $name, $template_path, $args);
                
                include $template_path;
                
                // Allow actions after load
                do_action('dealerschoice_after_template_part', $slug, $name, $template_path, $args);
                
                return;
            }
        }
    }
    
    /**
     * Register template with WordPress
     * 
     * This makes WordPress recognize our custom templates and use them
     * for the boat post type single pages only. All other templates pass through unchanged.
     * 
     * @param string $template Current template path
     * @return string Modified template path
     */
    public static function template_include($template) {
        $post_type = get_query_var('post_type');

        if (is_singular('boat')) {
            $new_template = self::locate_template('single-boat.php');
            if ($new_template) {
                return $new_template;
            }
        }
        
        // For all other cases, return the original template.
        return $template;
    }
    
    /**
     * Check if a template exists
     * 
     * @param string $template_name Template file name
     * @return bool True if template exists
     */
    public static function template_exists($template_name) {
        return !empty(self::locate_template($template_name));
    }
}
