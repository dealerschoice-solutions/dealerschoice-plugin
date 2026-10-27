<?php
/**
 * Boat post wrapper.
 *
 * @package DealersChoice
 */
namespace DC;

/**
 * Wraps a boat post with accessors for its ACF fields, taxonomy terms and schema data.
 *
 * Any undefined getFooBar() call maps to the ACF field key field_boat_foo_bar.
 */
class Boat {
    private $post;

    /**
     * Sets the wrapped post.
     *
     * @param \WP_Post|int|null $post Optional. Post object or ID. Default is the global post.
     */
    public function __construct($post = null) {
        if ($post instanceof WP_Post) {
            $this->post = $post;
        } elseif (is_numeric($post)) {
            $this->post = get_post($post);
        } else {
            global $post;
            $this->post = $post;
        }
    }

    /**
     * Maps getFooBar() calls to the ACF field field_boat_foo_bar.
     *
     * @param string $name      Called method name.
     * @param array  $arguments Call arguments (unused).
     * @return mixed ACF field value.
     * @throws \Exception When the method name does not start with 'get'.
     */
    public function __call($name, $arguments) {
        if (strpos($name, 'get') === 0) {
            // Strip 'get' and convert CamelCase to snake_case.
            $field = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', substr($name, 3)));

            $acfFieldKey = 'field_boat_' . $field;

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

    public function getField($key) {
        return get_field($key, $this->post->ID);
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
    /**
     * Returns the length in feet and inches, e.g. 21' 6".
     *
     * @return string
     */
    public function getFormattedLength(){
        return $this->formatFeetInches($this->getLength());
    }

    /**
     * Returns the beam in feet and inches, e.g. 8' 6".
     *
     * @return string
     */
    public function getFormattedBeam(){
        return $this->formatFeetInches($this->getBeam());
    }

    /**
     * Converts decimal feet (e.g. 8.5) to feet and inches (e.g. 8' 6").
     *
     * @param mixed $value Decimal feet.
     * @return string Formatted value, or an empty string when not a positive number.
     */
    private function formatFeetInches($value){
        if (!is_numeric($value) || (float) $value <= 0) {
            return '';
        }

        $value = (float) $value;
        $feet = floor($value);
        $inches = round(($value - $feet) * 12);

        // 12 inches rolls over to the next foot.
        if ($inches >= 12) {
            $feet++;
            $inches = 0;
        }

        return $feet . "'" . ($inches ? " " . $inches . '"' : '');
    }

    public function getMainSpecs(){
        $specs = [];
        if($this->getLocation()){$specs["Location"] = $this->getLocation();}
        if($this->getCondition()){$specs["Condition"] = $this->getCondition();}
        if($this->getStockNumber()){$specs["Stock Number"] = $this->getStockNumber();}
        if($this->getYear()){$specs["Year"] = $this->getYear();}
        if($this->getMake()){$specs["Make"] = $this->getMake();}
        if($this->getModel()){$specs["Model"] = $this->getModel();}
        if($this->getHIN()){$specs["HIN"] = $this->getHIN();}
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
        if($this->getStockNumber()){$specs["Stock Number"] = $this->getStockNumber();}
        if($this->getYear()){$specs["Year"] = $this->getYear();}
        if($this->getMake()){$specs["Make"] = $this->getMake();}
        if($this->getModel()){$specs["Model"] = $this->getModel();}
        if($this->getHIN()){$specs["HIN"] = $this->getHIN();}
        if($this->getLength()){$specs["Model Length"] = $this->getFormattedLength();}
        if($this->getFormattedBeam()){$specs["Beam"] = $this->getFormattedBeam();}
        if($this->getWeight()){$specs["Weight"] = $this->getWeight();}
        if($this->getType()){$specs["Type"] = $this->getType();}
        if($this->getMainColor()){$specs["Main Color"] = $this->getMainColor();}
        if($this->getAccentColor()){$specs["Accent Color"] = $this->getAccentColor();}
        if($this->getBottomColor()){$specs["Bottom Color"] = $this->getBottomColor();}
        if($this->getField('engine_make')){$specs["Motor Make"] = $this->getField('engine_make');}
        if($this->getField('engine_hp')){$specs["Total Horsepower"] = $this->getField('engine_hp');}
        if($this->getField('engine_hours') && !empty($this->getField('engine_hours')) && $this->getField('engine_hours') !== '0.0'){$specs["Engine Hours"] = $this->getField('engine_hours');}
        if($this->getField('boat_person_capacity')){$specs["Person Capacity"] = $this->getField('boat_person_capacity') . ' People';}
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

    /**
     * Returns the dealer-supplied monthly payment as a number.
     *
     * Strips everything but digits and the decimal point before casting.
     *
     * @return float Monthly payment, or 0.0 when absent or non-numeric.
     */
    public function getMonthlyPaymentNumeric() {
        $raw = $this->getMonthlyPayment();
        if ($raw === '' || $raw === null || $raw === false) {
            return 0.0;
        }
        $numeric = preg_replace('/[^0-9.]/', '', (string) $raw);
        return $numeric !== '' ? (float) $numeric : 0.0;
    }

    /**
     * Checks whether the dealer feed supplies a monthly payment for this boat.
     *
     * @return bool
     */
    public function hasFinancingData() {
        return $this->getMonthlyPaymentNumeric() > 0;
    }

    /**
     * Checks whether the sale price is shown without a gated action.
     *
     * Requires hide_sale_price off, the "always show price" option on, and a
     * sale price greater than zero.
     *
     * @return bool
     */
    public function isPriceVisible() {
        if ($this->getField('hide_sale_price')) {
            return false;
        }
        if (get_option('dealers_choice_always_show_price', '0') !== '1') {
            return false;
        }
        $price = $this->getSaleprice();
        return !empty($price) && (float) $price > 0;
    }

    public function getVideos(){
        $videos = [];
        if($this->getField('boat_video1')){$videos[] = $this->getField('boat_video1');}
        if($this->getField('boat_video2')){$videos[] = $this->getField('boat_video2');}
        if($this->getField('boat_video3')){$videos[] = $this->getField('boat_video3');}
        if($this->getField('boat_video4')){$videos[] = $this->getField('boat_video4');}
        return $videos;
    }

    /**
     * Returns the schema.org type for a boat.
     *
     * Types the boat as Product and Vehicle, with additionalType set to the
     * Wikidata "boat" entity.
     *
     * @return array{@type:array<int,string>,additionalType:string}
     */
    public static function getSchemaType() {
        return [
            '@type' => ['Product', 'Vehicle'],
            'additionalType' => 'https://www.wikidata.org/wiki/Q35872',
        ];
    }

    /**
     * Returns the schema.org availability URL for this boat's status.
     *
     * @return string
     */
    public function getSchemaAvailability() {
        $status = $this->getStatus();

        if ($status === 'In Stock') {
            return 'https://schema.org/InStock';
        }
        if ($status === 'Sold') {
            return 'https://schema.org/SoldOut';
        }
        return 'https://schema.org/PreOrder';
    }

    /**
     * Builds the schema.org Offer for this boat.
     *
     * Includes price only when isPriceVisible() is true. Availability is always included.
     *
     * @param bool $detailed Optional. Add itemCondition, url and priceValidUntil. Default false.
     * @return array
     */
    public function getSchemaOffer($detailed = false) {
        $offer = [
            '@type' => 'Offer',
        ];

        if ($this->isPriceVisible()) {
            $offer['priceCurrency'] = 'USD';
            $offer['price'] = $this->getSaleprice();
            if ($detailed) {
                $offer['priceValidUntil'] = date('Y-m-d', strtotime('+30 days'));
            }
        }

        $offer['availability'] = $this->getSchemaAvailability();

        if ($detailed) {
            $condition = $this->getCondition();
            $offer['itemCondition'] = $condition === 'New' ? 'https://schema.org/NewCondition' : ($condition === 'Used' ? 'https://schema.org/UsedCondition' : 'https://schema.org/RefurbishedCondition');
            $offer['url'] = get_permalink($this->getPostID());
        }

        return $offer;
    }

    /**
     * Returns brand, model, vehicleModelDate and bodyType schema properties.
     *
     * bodyType is the Boat Type term name. Empty values are omitted.
     *
     * @return array
     */
    public function getSchemaVehicleDetails() {
        $details = [];

        $make = $this->getMake();
        if ($make) {
            $details['brand'] = [
                '@type' => 'Brand',
                'name'  => $make,
            ];
        }

        $model = $this->getModel();
        if ($model) {
            $details['model'] = $model;
        }

        $year = $this->getYear();
        if ($year) {
            $details['vehicleModelDate'] = (string) $year;
        }

        $types = get_the_terms($this->getPostID(), 'boat_type');
        if ($types && !is_wp_error($types)) {
            $details['bodyType'] = html_entity_decode($types[0]->name, ENT_QUOTES, 'UTF-8');
        }

        return $details;
    }

    /**
     * Builds the schema entity for this boat as an inventory ItemList item.
     *
     * Includes name, url, featured image (when set), vehicle details and offers.
     *
     * @return array
     */
    public function getItemListEntry() {
        $post_id = $this->getPostID();

        $entry = self::getSchemaType();
        $entry['name'] = html_entity_decode(wp_strip_all_tags(get_the_title($post_id)), ENT_QUOTES, 'UTF-8');
        $entry['url'] = get_permalink($post_id);

        $image = get_the_post_thumbnail_url($post_id, 'large');
        if ($image) {
            $entry['image'] = $image;
        }

        $entry = array_merge($entry, $this->getSchemaVehicleDetails());

        $entry['offers'] = $this->getSchemaOffer();

        return $entry;
    }

    public function outputBoatSchema($boat){
        $schema = array_merge(
            [
                "@context" => "https://schema.org",
            ],
            self::getSchemaType(),
            [
                "name" => $boat->getName(),
                "description" => wp_strip_all_tags( html_entity_decode( $boat->getDescription() ) ),
                "image" => $boat->getGalleryImages() ? array_map(function($img){ return wp_get_attachment_url($img, 'full'); }, $boat->getGalleryImages()) : null,
            ],
            $boat->getSchemaVehicleDetails(),
            [
                "productID" => $boat->getHIN() ? "hin:".$boat->getHIN() : $boat->getStockNumber(),
                "sku" => $boat->getHIN() ?: $boat->getStockNumber(),
                "url" => get_permalink($boat->getPostID()),
                "offers" => $boat->getSchemaOffer(true),
            ]
        );

        echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) . '</script>';
    }

}