<?php
/**
 * Template loader with theme override support.
 *
 * @package DealersChoice
 */

namespace DC;

if (!defined('ABSPATH')) {
    exit;
}

class Template_Loader {
    
    /**
     * Locates a template file.
     *
     * Checks {theme}/dealerschoice/{template_name} in the child and parent theme,
     * then falls back to the plugin's templates directory.
     *
     * @param string $template_name Template file name, e.g. 'single-boat.php'.
     * @return string Full path to the template, or an empty string if not found.
     */
    public static function locate_template($template_name) {
        $theme_template = locate_template(['dealerschoice/' . $template_name]);
        
        if ($theme_template) {
            return $theme_template;
        }
        
        $plugin_template = DC_PLUGIN_DIR . 'templates/' . $template_name;
        
        if (file_exists($plugin_template)) {
            return $plugin_template;
        }
        
        return '';
    }
    
    /**
     * Loads a template file, extracting $args into its scope.
     *
     * @param string $template_name Template file name.
     * @param array  $args          Optional. Variables made available to the template. Default empty.
     * @param bool   $return        Optional. Whether to return the output instead of echoing it. Default false.
     * @return string|void Template output when $return is true or the template is missing.
     */
    public static function get_template($template_name, $args = [], $return = false) {
        $template_path = self::locate_template($template_name);
        
        if (empty($template_path)) {
            return '';
        }
        
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
     * Loads a template part with theme override support.
     *
     * Tries "{slug}-{name}.php" then "{slug}.php", and fires
     * dealerschoice_before_template_part / dealerschoice_after_template_part around the include.
     *
     * @param string $slug Template slug, e.g. 'content'.
     * @param string $name Optional. Template name, e.g. 'boat'. Default empty.
     * @param array  $args Optional. Variables made available to the template. Default empty.
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
                if (!empty($args) && is_array($args)) {
                    extract($args);
                }
                
                do_action('dealerschoice_before_template_part', $slug, $name, $template_path, $args);
                
                include $template_path;
                
                do_action('dealerschoice_after_template_part', $slug, $name, $template_path, $args);
                
                return;
            }
        }
    }
    
    /**
     * Uses the single-boat template for singular boat pages.
     *
     * Hooked to template_include. Other requests keep their original template.
     *
     * @param string $template Current template path.
     * @return string Template path to load.
     */
    public static function template_include($template) {
        $post_type = get_query_var('post_type');

        if (is_singular('boat')) {
            $new_template = self::locate_template('single-boat.php');
            if ($new_template) {
                return $new_template;
            }
        }
        
        return $template;
    }
    
    /**
     * Checks whether a template exists in the theme or plugin.
     *
     * @param string $template_name Template file name.
     * @return bool True if the template exists.
     */
    public static function template_exists($template_name) {
        return !empty(self::locate_template($template_name));
    }
}
