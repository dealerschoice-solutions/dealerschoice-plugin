<?php
/**
 * Boat Object Class
 * 
 * Object-oriented wrapper for boat post type that provides convenient methods
 * for accessing boat data, ACF fields, taxonomies, and metadata. This class
 * uses magic methods to dynamically access ACF fields with a clean API.
 * 
 * @package DealersChoice
 * @subpackage Classes
 * @since 1.0.0
 * 
 * Usage:
 *   $boat = new DC\Boat($post_id);
 *   echo $boat->getMake();     // Returns boat make
 *   echo $boat->getModel();    // Returns boat model
 *   echo $boat->getSalePrice(); // Returns formatted sale price
 * 
 * Magic Method Pattern:
 * - Methods starting with 'get' automatically map to ACF fields
 * - CamelCase is converted to snake_case with 'field_boat_' prefix
 * - Example: getStockNumber() → field_boat_stock_number
 * 
 * Special Methods:
 * - getPostID(): Returns WordPress post ID
 * - getName(): Returns post title
 * - getGalleryImages(): Returns ACF gallery array
 * - getExcerpt($length): Returns trimmed excerpt
 * - getCondition(): Returns condition taxonomy term
 * - getLocation(): Returns location taxonomy term
 * - getType(): Returns boat_type taxonomy term
 * - getMainSpecs(): Returns array of main specifications
 * - getTechSpecs(): Returns array of technical specifications
 * - getPayment(): Returns array of payment information
 * 
 * Constructor Options:
 * - Accepts WP_Post object
 * - Accepts post ID (integer)
 * - Defaults to global $post if no argument provided
 * 
 * Meta Management:
 * - getMeta($key): Get post meta value
 * - setMeta($key, $value): Update post meta value
 * - getField($key): Get ACF field value
 * 
 * Dependencies:
 * - ACF (get_field function)
 * - WordPress taxonomy functions
 * - WordPress post functions
 */
namespace DC;

class Boat {
    private $post;

    // Constructor accepts either a post ID or WP_Post object
    public function __construct($post = null) {
        if ($post instanceof WP_Post) {
            $this->post = $post;
        } elseif (is_numeric($post)) {
            $this->post = get_post($post);
        } else {
            // Fallback to the current post if nothing is passed
            global $post;
            $this->post = $post;
        }
    }

    // Magic method to dynamically handle method calls
    public function __call($name, $arguments) {
        // Check if the method starts with 'get'
        if (strpos($name, 'get') === 0) {
            // Convert method name to field key, remove 'get' and convert camel case to snake case
            $field = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', substr($name, 3)));

            // Add the 'field_boat_' prefix
            $acfFieldKey = 'field_boat_' . $field;

            // Get the ACF field
            return $this->getField($acfFieldKey);
        }

        throw new \Exception("Method $name does not exist.");
    }

    public function getPostID() {
        return $this->post->ID;
    }

    public function getGalleryImages(){
        return $this->getField('gallery');
    }

    public function getName() {
        return get_the_title($this->post);
    }

    public function getMeta($key) {
        return get_post_meta($this->post->ID, $key, true);
    }

    public function setMeta($key, $value) {
        update_post_meta($this->post->ID, $key, $value);
    }

    // You can add custom methods for additional functionality
    public function getField($key) {
        return get_field($key, $this->post->ID); // Using ACF
    }

    public function getExcerpt($length = 20) {
        $excerpt = '';
        if($this->post->post_content != ''){
            $excerpt = wp_trim_words($this->post->post_content, $length);
        } elseif ($this->getDescription() != ''){
            $excerpt = wp_trim_words($this->getDescription(), $length);
        }
        return $excerpt;
    }

    public function getCondition(){
        $terms = get_the_terms( $this->post->ID, 'condition' );
        return $terms ? $terms[0]->name : '';
    }
    public function getLocation(){
        $terms = get_the_terms( $this->post->ID, 'location' );
        return $terms ? $terms[0]->name : '';
    }
    public function getType(){
        $terms = get_the_terms( $this->post->ID, 'boat_type' );
        return $terms ? $terms[0]->name : '';
    }
    // Take decimal length and convert to feet and inches format
    public function getFormattedLength(){
        $length = $this->getLength();
        if (!$length) {
            return '';
        }

        $feet = floor($length);
        $inches = round(($length - $feet) * 12);

        return $feet . "'" . ($inches ? " " . $inches . '"' : '');
    }

    public function getMainSpecs(){
        $specs = [];
        if($this->getLocation()){$specs["Location"] = $this->getLocation();}
        if($this->getCondition()){$specs["Condition"] = $this->getCondition();}
        if($this->getHIN()){$specs["Stock Number"] = $this->getHIN();}
        if($this->getYear()){$specs["Year"] = $this->getYear();}
        if($this->getMake()){$specs["Make"] = $this->getMake();}
        if($this->getModel()){$specs["Model"] = $this->getModel();}
        if($this->getLength()){$specs["Model Length"] = $this->getFormattedLength();}
        if($this->getType()){$specs["Type"] = $this->getType();}
        if($this->getField('engine_make')){$specs["Motor Make"] = $this->getField('engine_make');}
        return $specs;
    }

    public function getTechSpecs(){
        $specs = [];
        if($this->getStatus()){$specs["Status"] = $this->getStatus();}
        if($this->getLocation()){$specs["Location"] = $this->getLocation();}
        if($this->getCondition()){$specs["Condition"] = $this->getCondition();}
        if($this->getHIN()){$specs["Stock Number"] = $this->getHIN();}
        if($this->getYear()){$specs["Year"] = $this->getYear();}
        if($this->getMake()){$specs["Make"] = $this->getMake();}
        if($this->getModel()){$specs["Model"] = $this->getModel();}
        if($this->getLength()){$specs["Model Length"] = $this->getFormattedLength();}
        if($this->getType()){$specs["Type"] = $this->getType();}
        if($this->getMainColor()){$specs["Main Color"] = $this->getMainColor();}
        if($this->getAccentColor()){$specs["Accent Color"] = $this->getAccentColor();}
        if($this->getBottomColor()){$specs["Bottom Color"] = $this->getBottomColor();}
        if($this->getField('engine_make')){$specs["Motor Make"] = $this->getField('engine_make');}
        if($this->getField('engine_hp')){$specs["Total Horsepower"] = $this->getField('engine_hp');}
        if($this->getField('engine_hours') && !empty($this->getField('engine_hours')) && $this->getField('engine_hours') !== '0.0'){$specs["Engine Hours"] = $this->getField('engine_hours');}
        if($this->getField('boat_person_capacity')){$specs["Person Capacity"] = $this->getField('boat_person_capacity') . ' People';}
        //if($this->get()){$specs["Category"] = $this->get();}
        return $specs;
    }

    public function getPayment(){
        $payment = [];
        if($this->getMSRP()){$payment["MSRP"] = $this->getMSRP();}
        if($this->getDiscount() && !$this->getField('hide_sale_price')){$payment["Savings"] = $this->getDiscount();}
        return $payment;
    }

    public function hasVideos(){
        return !empty($this->getField('boat_video1')) || !empty($this->getField('boat_video2')) || !empty($this->getField('boat_video3')) || !empty($this->getField('boat_video4'));
    }

    public function getVideos(){
        $videos = [];
        if($this->getField('boat_video1')){$videos[] = $this->getField('boat_video1');}
        if($this->getField('boat_video2')){$videos[] = $this->getField('boat_video2');}
        if($this->getField('boat_video3')){$videos[] = $this->getField('boat_video3');}
        if($this->getField('boat_video4')){$videos[] = $this->getField('boat_video4');}
        return $videos;
    }
    
    public function outputBoatSchema($boat){
        $schema = [
            "@context" => "https://schema.org",
            "@type" => "Product",
            "name" => $boat->getName(),
            "description" => wp_strip_all_tags( html_entity_decode( $boat->getDescription() ) ),
            "image" => $boat->getGalleryImages() ? array_map(function($img){ return wp_get_attachment_url($img, 'full'); }, $boat->getGalleryImages()) : null,
            "brand" => [
                "@type" => "Brand",
                "name" => $boat->getMake()
            ],
            "model" => $boat->getModel(),
            "productID" => $boat->getHIN() ? "hin:".$boat->getHIN() : $boat->getStockNumber(),
            "sku" => $boat->getHIN() ?? $boat->getStockNumber(),
            "url" => get_permalink($boat->getPostID()),
            "offers" => [
                "@type" => "Offer",
                "priceCurrency" => "USD",
                "price" => $boat->getSaleprice() ?? $boat->getMSRP(),
                "availability" => $boat->getStatus() === 'In Stock' ? 'https://schema.org/InStock' : ($boat->getStatus() === 'Sold' ? 'https://schema.org/SoldOut' : 'https://schema.org/PreOrder'),
                "itemCondition" => $boat->getCondition() === 'New' ? 'https://schema.org/NewCondition' : ($boat->getCondition() === 'Used' ? 'https://schema.org/UsedCondition' : 'https://schema.org/RefurbishedCondition'),
                "url" => get_permalink($boat->getPostID()),
                "priceValidUntil" => date('Y-m-d', strtotime('+30 days'))
            ]
        ];

        echo '<script type="application/ld+json">' . json_encode($schema) . '</script>';
    }

}