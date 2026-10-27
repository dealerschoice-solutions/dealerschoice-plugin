<?php
/**
 * Inventory sync from the DealersChoice IMS API.
 *
 * @package DealersChoice
 */
namespace DC;

/**
 * Fetches inventory from the IMS and syncs it into boat posts.
 *
 * Creates or updates boat posts, ACF fields, taxonomies and gallery images,
 * and unpublishes synced boats that are no longer in the feed.
 */
class InventorySync {

    /**
     * IMS inventory sync endpoint.
     *
     * @var string
     */
    private $inventory_url = 'https://dealerschoiceims.securem2.com/api/v1/inventory/sync';

    public function __construct() {
    }

    /**
     * Syncs the boat inventory from the API.
     *
     * Skips the sync when another sync holds the lock or when the API's
     * last_sync timestamp is not newer than the stored one.
     *
     * @param bool $debug Optional. Dump the API response and exit. Default false.
     * @param bool $force Optional. Sync regardless of the last_sync timestamp. Default false.
     * @return void
     */
    public function sync_inventory($debug = false, $force = false) {
        if (get_transient('dealers_choice_inventory_sync_lock')) {
            dealers_choice_log('Sync already in progress. Aborting.', 'warning');
            return;
        }

        try {
            set_transient('dealers_choice_inventory_sync_lock', true, 15 * MINUTE_IN_SECONDS);

            $client_id = get_option('dealers_choice_client_id');
            $api_key = get_option('dealers_choice_api_key');

            if (empty($client_id) || empty($api_key)) {
                throw new \Exception('Client ID or API Key is not configured.');
            }

            $response = wp_remote_post($this->inventory_url, [
                'body' => [
                    'client_id' => $client_id,
                    'api_key'   => $api_key,
                ],
                'timeout' => 120,
            ]);

            if (is_wp_error($response)) {
                throw new \Exception($response->get_error_message());
            }

            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Failed to decode JSON response: ' . json_last_error_msg());
            }

            $last_sync_from_api = isset($data->last_sync) ? strtotime($data->last_sync) : 0;
            $last_sync_stored = get_option('dealers_choice_last_sync', 0);

            if (!$force && $last_sync_from_api <= $last_sync_stored) {
                dealers_choice_log('Inventory is already up to date', 'info', [
                    'last_sync_api' => date('Y-m-d H:i:s', $last_sync_from_api),
                    'last_sync_stored' => date('Y-m-d H:i:s', $last_sync_stored)
                ]);
                return;
            }
            
            dealers_choice_log('Starting inventory sync', 'info', [
                'last_sync_api' => date('Y-m-d H:i:s', $last_sync_from_api),
                'inventory_count' => count($data->inventory),
                'forced' => $force
            ]);

            if ($debug) {
                echo "<pre>";
                var_dump($data);
                echo "</pre>";
                exit;
            }

            if (isset($data->status) && $data->status === 'success' && !empty($data->inventory)) {
                $this->process_boat_inventory($data->inventory, $last_sync_from_api);
                update_option('dealers_choice_last_sync', $last_sync_from_api);
                dealers_choice_log('Inventory sync completed successfully', 'info', [
                    'boats_processed' => count($data->inventory),
                    'sync_timestamp' => date('Y-m-d H:i:s', $last_sync_from_api)
                ]);
            } else {
                $message = isset($data->message) ? $data->message : 'Unknown error or empty inventory.';
                throw new \Exception('API Error: ' . $message);
            }

        } catch (\Exception $e) {
            dealers_choice_log('Inventory sync error: ' . $e->getMessage(), 'error', [
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString()
            ]);
        } finally {
            delete_transient('dealers_choice_inventory_sync_lock');
        }
    }

    /**
     * Creates or updates a boat post for each inventory item, then unpublishes missing boats.
     *
     * @param array $inventory          Inventory items (objects) from the API.
     * @param int   $last_sync_from_api Optional. API last_sync timestamp, used when an item has no
     *                                  last_updated value. Default 0.
     * @return void
     */
    public function process_boat_inventory($inventory, $last_sync_from_api = 0) {
        $synced_post_ids = [];

        foreach ($inventory as $boat_data) {
            $boat_id = (string) $boat_data->UniqueInventoryID;
            $name = trim($boat_data->Year . ' ' . $boat_data->Make . ' ' . $boat_data->Model);
            $slug = sanitize_title($name . ' ' . $boat_data->StockNumber);

            $args = [
                'post_type' => 'boat',
                'meta_key' => 'field_boat_id',
                'meta_value' => $boat_id,
                'posts_per_page' => 1,
                'post_status' => ['publish', 'draft'],
            ];
            $existing_post = new \WP_Query($args);

            $post_id = null;
            if ($existing_post->have_posts()) {
                $post_id = $existing_post->posts[0]->ID;
                wp_update_post([
                    'ID'          => $post_id,
                    'post_title'  => $name,
                    'post_name'   => $slug,
                    'post_status' => 'publish',
                ]);
                dealers_choice_log('Updated boat', 'info', [
                    'boat_id' => $boat_id,
                    'post_id' => $post_id,
                    'title' => $name
                ]);
            } else {
                $post_id = wp_insert_post([
                    'post_title' => $name,
                    'post_name' => $slug,
                    'post_type' => 'boat',
                    'post_status' => 'publish',
                ]);
                if ($post_id && !is_wp_error($post_id)) {
                    update_post_meta($post_id, 'field_boat_id', $boat_id);
                }
                dealers_choice_log('Inserted new boat', 'info', [
                    'boat_id' => $boat_id,
                    'post_id' => $post_id,
                    'title' => $name
                ]);
            }

            if ($post_id && !is_wp_error($post_id)) {
                $synced_post_ids[] = $post_id;

                // Images are processed for new boats, boats never photo-synced, and boats whose
                // update time (per-boat last_updated, else the API last_sync) is newer than the last photo sync.
                $per_boat_update = isset($boat_data->last_updated) ? strtotime($boat_data->last_updated) : 0;
                $effective_last_update = $per_boat_update > 0 ? $per_boat_update : $last_sync_from_api;

                $last_photo_sync = (int) get_post_meta($post_id, '_last_photo_sync_ts', true);
                $is_new_boat = $existing_post->post_count === 0;

                $should_process_images = $is_new_boat || $last_photo_sync === 0 || ($effective_last_update > $last_photo_sync);

                if ($should_process_images) {
                    dealers_choice_log('Processing images for boat', 'info', [
                        'boat_id' => $boat_id,
                        'post_id' => $post_id,
                        'reason' => $is_new_boat ? 'New boat' : ($last_photo_sync === 0 ? 'Never synced' : 'Images updated in API'),
                        'effective_last_update' => $effective_last_update,
                        'last_photo_sync' => $last_photo_sync,
                    ]);
                }

                $update_result = $this->update_acf_fields($post_id, $boat_data, $should_process_images);
                $this->update_taxonomies($post_id, $boat_data);

                // Stamp the photo sync time only when every image synced.
                if ($should_process_images && !empty($update_result['images_synced'])) {
                    update_post_meta($post_id, '_last_photo_sync_ts', $effective_last_update > 0 ? $effective_last_update : time());
                }
            }
        }

        $this->unpublish_old_boats($synced_post_ids);
    }

    /**
     * Writes the boat's ACF fields, gallery and featured image from an inventory item.
     *
     * When images are processed, the existing manual gallery order and featured image
     * are kept for images still in the feed, and new images are appended.
     *
     * @param int    $post_id        Boat post ID.
     * @param object $data           Inventory item from the API.
     * @param bool   $process_images Optional. Download and attach the item's images. Default false.
     * @return array {
     *     @type bool $images_synced True when all images synced or images were skipped.
     * }
     */
    public function update_acf_fields($post_id, $data, $process_images = false) {
        // Returns the value, or an empty string when unset or empty.
        $get_value = function($value) {
            return isset($value) && $value !== null && $value !== '' ? $value : '';
        };

        // Strips non-numeric characters and formats to the given decimals.
        $get_numeric = function($value, $decimals = 2) {
            if (!isset($value) || $value === null || $value === '') {
                return '';
            }
            $numeric = preg_replace("/[^0-9.]/", "", (string) $value);
            return $numeric !== '' ? number_format((float) $numeric, $decimals, '.', '') : '';
        };
        
        // Converts a JSON string, array or object into label/value rows for an ACF repeater.
        $process_repeater_field = function($value) {
            $data = [];

            if (!isset($value) || $value === null || $value === '') {
                return $data;
            }

            // Strings that are not valid JSON become a single value row.
            if (is_string($value)) {
                $trimmed = trim($value);
                if (strpos($trimmed, '[') === 0 || strpos($trimmed, '{') === 0) {
                    $decoded = json_decode($value, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $value = $decoded;
                    } else {
                         return [['label' => '', 'value' => $value]];
                    }
                } else {
                     return [['label' => '', 'value' => $value]];
                }
            }

            if (is_array($value) || is_object($value)) {
                $value_arr = (array) $value;

                $is_assoc = array_keys($value_arr) !== range(0, count($value_arr) - 1);
                
                if ($is_assoc) {
                    // Key-Value pairs: {"Beam": "8ft", "Weight": "2000lbs"}
                    foreach ($value_arr as $k => $v) {
                        $data[] = [
                            'label' => $k,
                            'value' => is_array($v) || is_object($v) ? json_encode($v) : $v
                        ];
                    }
                } else {
                    // Indexed Array: ["Option 1", "Option 2"] OR [{"label": "A", "value": "B"}]
                    foreach ($value_arr as $item) {
                        if (is_string($item) || is_numeric($item)) {
                            $data[] = [
                                'label' => '', 
                                'value' => $item
                            ];
                        } elseif (is_array($item) || is_object($item)) {
                            $item_arr = (array) $item;
                            $label = '';
                            $val = '';

                            // Match common label and value key names.
                            $label_keys = ['label', 'name', 'key', 'Label', 'Name', 'Key'];
                            foreach ($label_keys as $k) {
                                if (isset($item_arr[$k])) {
                                    $label = $item_arr[$k];
                                    break;
                                }
                            }

                            $val_keys = ['value', 'data', 'val', 'Value', 'Data', 'Val'];
                            foreach ($val_keys as $k) {
                                if (isset($item_arr[$k])) {
                                    $val = $item_arr[$k];
                                    break;
                                }
                            }
                            
                            // Without known keys, use the first two properties as label/value, or one as value.
                            if (empty($label) && empty($val) && count($item_arr) == 2) {
                                $keys = array_keys($item_arr);
                                $label = $item_arr[$keys[0]];
                                $val = $item_arr[$keys[1]];
                            } elseif (empty($label) && empty($val) && count($item_arr) == 1) {
                                $keys = array_keys($item_arr);
                                $val = $item_arr[$keys[0]];
                            }
                            
                            // Otherwise store the item as JSON in the value.
                            if (empty($label) && empty($val)) {
                                $val = json_encode($item_arr);
                            }
                            
                            $data[] = [
                                'label' => $label,
                                'value' => $val
                            ];
                        }
                    }
                }
            }
            
            return $data;
        };
        
        // Images arrive as an array or a JSON string.
        $images_array = [];
        if (isset($data->Images) && !empty($data->Images)) {
            if (is_array($data->Images)) {
                $images_array = $data->Images;
            } elseif (is_string($data->Images)) {
                $decoded_images = json_decode($data->Images, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_images)) {
                    $images_array = $decoded_images;
                }
            }
        }
        
        $gallery_ids = [];
        $images_synced_successfully = true;
        if ($process_images) {
            $images_result = $this->process_images($images_array);
            $new_gallery_ids = array_map('intval', $images_result['ids']);
            $images_synced_successfully = $images_result['success'];

            // Keep the existing gallery order for images still in the feed and append new ones in API order.
            $existing_gallery_ids = array_map('intval', (array) get_field('gallery', $post_id, false));
            if (!empty($existing_gallery_ids)) {
                $preserved_order = array_values(array_intersect($existing_gallery_ids, $new_gallery_ids));
                $added_images = array_values(array_diff($new_gallery_ids, $existing_gallery_ids));
                $gallery_ids = array_merge($preserved_order, $added_images);
            } else {
                $gallery_ids = $new_gallery_ids;
            }

            // Keep the current featured image if it is still in the gallery; otherwise use the first gallery image.
            $current_thumbnail_id = (int) get_post_thumbnail_id($post_id);
            if ($current_thumbnail_id && in_array($current_thumbnail_id, $gallery_ids, true)) {
                $first_image_id = $current_thumbnail_id;
            } elseif (!empty($gallery_ids)) {
                $first_image_id = $gallery_ids[0];
            } else {
                $first_image_id = null;
            }

            if ($first_image_id && get_post_thumbnail_id($post_id) != $first_image_id) {
                set_post_thumbnail($post_id, $first_image_id);
            }
        } else {
            $gallery_ids = get_field('gallery', $post_id, false);
        }

        // Map Videos (keys Video1-Video4, or a list) to the four video URL fields by position.
        $video_fields = ['field_boat_video1', 'field_boat_video2', 'field_boat_video3', 'field_boat_video4'];
        $video_urls = [];
        if (isset($data->Videos) && !empty($data->Videos)) {
            $videos_array = [];
            if (is_array($data->Videos) || is_object($data->Videos)) {
                $videos_array = (array) $data->Videos;
            } elseif (is_string($data->Videos)) {
                $decoded_videos = json_decode($data->Videos, true);
                if (json_last_error() === JSON_ERROR_NONE && (is_array($decoded_videos) || is_object($decoded_videos))) {
                    $videos_array = (array) $decoded_videos;
                }
            }

            foreach ($video_fields as $index => $field_name) {
                $url = '';
                $video_key = 'Video' . ($index + 1);

                if (isset($videos_array[$video_key]) && !empty($videos_array[$video_key])) {
                    $url = $videos_array[$video_key];
                }
                elseif (isset($videos_array[$index]) && !empty($videos_array[$index])) {
                    $url = $videos_array[$index];
                }

                $video_urls[$index] = $url;
            }
        }
        
        $acf_fields = [
            'field_boat_id' => (string) $get_value($data->UniqueInventoryID),
            'field_boat_make' => ucwords((string) $get_value($data->Make)),
            'field_boat_model' => (string) $get_value($data->Model),
            'field_boat_year' => (string) $get_value($data->Year),
            'field_boat_status' => ((string) $get_value($data->Status) === 'Available') ? 'In Stock' : (string) $get_value($data->Status),
            'field_boat_banner' => isset($data->Banner) ? substr(sanitize_text_field((string) $data->Banner), 0, 255) : '',
            'field_boat_stock_number' => (string) $get_value($data->StockNumber),
            'field_boat_hin'  => (string) $get_value($data->HIN),
            'field_boat_description' => wp_kses_post($get_value($data->Description)),
            'field_boat_saleprice' => $get_numeric($data->SalePrice, 2),
            'field_boat_msrp' => $get_numeric($data->MSRP, 2),
            'field_boat_monthly_payment' => $get_numeric($data->MonthlyPayment, 2),
            'field_boat_interest_rate' => $get_numeric($data->InterestRate, 3),
            'field_boat_down_payment' => $get_numeric($data->DownPayment, 2),
            'field_boat_loan_term' => $get_value($data->LoanTerm),
            'field_boat_length' => (string) $get_value($data->Length),
            'field_boat_length_inches' => convertLengthToInches($get_value($data->Length)),
            'field_boat_weight' => (string) $get_value($data->DryWeight),
            'field_boat_beam' => $get_numeric($data->Beam ?? null, 3), // Decimal feet.
            'field_engine_hours' => $get_numeric($data->EngineHours, 1),
            'field_engine_make' => (string) $get_value($data->EngineMake),
            'field_engine_hp' => $get_value($data->EngineHP),
            'field_boat_options' => $process_repeater_field($data->InstalledOptions),
            'field_boat_specifications' => $process_repeater_field($data->Specs),
            'field_boat_model_features' => $process_repeater_field($data->StandardFeatures),
            'field_boat_custom_fields' => $process_repeater_field($data->CustomFields ?? null),
            'field_boat_main_color' => (string) $get_value($data->MainColor),
            'field_boat_accent_color' => (string) $get_value($data->AccentColor),
            'field_boat_person_capacity' => (isset($data->PassengerCapacity) && $data->PassengerCapacity !== null) ? (int) $data->PassengerCapacity : '',
            'gallery' => $gallery_ids,
            'is_local' => 0,
        ];

        foreach ($video_fields as $index => $field_name) {
            if (isset($video_urls[$index])) {
                $acf_fields[$field_name] = $video_urls[$index];
            }
        }

        foreach ($acf_fields as $key => $value) {
            update_field($key, $value, $post_id);
        }

        return ['images_synced' => $images_synced_successfully];
    }

    /**
     * Sets the boat's taxonomy terms and range terms from an inventory item.
     *
     * Location keeps only the text before the first comma; status "Available" becomes "In Stock".
     *
     * @param int    $post_id Boat post ID.
     * @param object $data    Inventory item from the API.
     * @return void
     */
    public function update_taxonomies($post_id, $data) {
        $location_raw = (string) $data->Location;
        $location_term = $location_raw;
        if (strpos($location_raw, ',') !== false) {
            $location_term = trim(explode(',', $location_raw)[0]);
        }
        $taxonomies = [
            'location' => $location_term,
            'condition' => (string) $data->Condition,
            'boat_type' => (string) $data->BoatType,
            'boat_status' => ((string) $data->Status === 'Available') ? 'In Stock' : (string) $data->Status,
            'boat_year' => (string) $data->Year,
            'make' => (string) $data->Make,
            'model' => (string) $data->Model,
        ];

        foreach ($taxonomies as $tax_slug => $term_name) {
            if (!empty($term_name)) {
                wp_set_object_terms($post_id, $term_name, $tax_slug, false);
            }
        }

        $this->assign_range_taxonomies($post_id, $data);
    }

    /**
     * Assigns price, length, horsepower and person capacity range terms from numeric values.
     *
     * @param int    $post_id Boat post ID.
     * @param object $data    Inventory item from the API.
     * @return void
     */
    private function assign_range_taxonomies($post_id, $data) {
        if (isset($data->SalePrice) && !empty($data->SalePrice)) {
            $price = (float) preg_replace("/[^0-9.]/", "", (string) $data->SalePrice);
            if ($price > 0) {
                $price_range_term = dealers_choice_get_price_range_term($price);
                if ($price_range_term) {
                    wp_set_object_terms($post_id, $price_range_term, 'price_range', false);
                }
            }
        }

        if (isset($data->Length) && !empty($data->Length)) {
            $length_feet = (float) $data->Length;
            if ($length_feet > 0) {
                $length_range_term = dealers_choice_get_length_range_term($length_feet);
                if ($length_range_term) {
                    wp_set_object_terms($post_id, $length_range_term, 'length_range', false);
                }
            }
        }

        if (isset($data->EngineHP) && !empty($data->EngineHP)) {
            $hp = (float) $data->EngineHP;
            if ($hp > 0) {
                $hp_range_term = dealers_choice_get_horsepower_range_term($hp);
                if ($hp_range_term) {
                    wp_set_object_terms($post_id, $hp_range_term, 'horsepower', false);
                }
            }
        }

        if (isset($data->PassengerCapacity) && $data->PassengerCapacity !== null && $data->PassengerCapacity !== '') {
            $capacity = (int) $data->PassengerCapacity;
            if ($capacity > 0) {
                $capacity_range_term = dealers_choice_get_capacity_range_term($capacity);
                if ($capacity_range_term) {
                    wp_set_object_terms($post_id, $capacity_range_term, 'person_capacity', false);
                }
            }
        }
    }

    /**
     * Sets published boats that were not in this sync to draft and deletes their favorites events.
     *
     * Boats with is_local = 1 are skipped.
     *
     * @param int[] $synced_post_ids Post IDs created or updated in this sync.
     * @return void
     */
    public function unpublish_old_boats($synced_post_ids) {
        global $wpdb;

        $placeholders = implode(',', array_fill(0, count($synced_post_ids), '%d'));

        // Published, non-local boats not in this sync.
        $query = $wpdb->prepare(
            "SELECT p.ID 
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'is_local'
            WHERE p.post_type = 'boat'
            AND p.post_status = 'publish'
            AND p.ID NOT IN ($placeholders)
            AND (pm.meta_value IS NULL OR pm.meta_value != '1')",
            ...$synced_post_ids
        );
        
        $posts_to_unpublish = $wpdb->get_col($query);
        
        if (!empty($posts_to_unpublish)) {
            $unpublished_count = 0;
            
            foreach ($posts_to_unpublish as $post_id) {
                $result = wp_update_post([
                    'ID' => $post_id,
                    'post_status' => 'draft'
                ], true);
                
                if (!is_wp_error($result)) {
                    $unpublished_count++;
                    dealers_choice_log('Unpublished boat', 'info', ['post_id' => $post_id]);
                } else {
                    dealers_choice_log('Failed to unpublish boat', 'warning', [
                        'post_id' => $post_id,
                        'error' => $result->get_error_message()
                    ]);
                }
            }
            
            dealers_choice_log('Unpublished old boats', 'info', ['count' => $unpublished_count]);

            if ( ! empty($posts_to_unpublish) ) {
                $id_placeholders = implode( ',', array_fill(0, count($posts_to_unpublish), '%d') );
                $deleted_rows    = $wpdb->query(
                    $wpdb->prepare(
                        "DELETE FROM {$wpdb->prefix}dc_favorite_events WHERE boat_id IN ({$id_placeholders})",
                        ...$posts_to_unpublish
                    )
                );
                dealers_choice_log('Removed favorites analytics for unpublished boats', 'info', ['rows_deleted' => (int) $deleted_rows]);
            }
        }
    }

    /**
     * Resolves image URLs to attachment IDs, downloading images not already in the media library.
     *
     * @param array $image_urls Image URLs from the API.
     * @return array {
     *     @type int[] $ids     Attachment IDs in API order.
     *     @type bool  $success False when any image failed to download or attach.
     * }
     */
    private function process_images($image_urls) {
        if (!is_array($image_urls) || empty($image_urls)) {
            return ['ids' => [], 'success' => true];
        }

        $attachment_ids = [];
        $has_failure = false;

        foreach ($image_urls as $image_url) {
            if (empty($image_url)) {
                continue;
            }

            $existing_attachment = $this->get_attachment_by_url($image_url);

            if ($existing_attachment) {
                $attachment_ids[] = $existing_attachment;
                continue;
            }

            $attachment_id = $this->download_and_attach_image($image_url);
            if ($attachment_id) {
                $attachment_ids[] = $attachment_id;
            } else {
                $has_failure = true;
            }
        }

        return ['ids' => $attachment_ids, 'success' => !$has_failure];
    }

    /**
     * Finds an existing attachment for a source image URL.
     *
     * Checks the transient cache, then the _source_url attachment meta, then the attachment GUID.
     * Matches are cached for one week.
     *
     * @param string $url Image URL.
     * @return int|false Attachment ID, or false if not found.
     */
    private function get_attachment_by_url($url) {
        $cache_key = 'dc_img_' . md5($url);
        $cached_id = get_transient($cache_key);

        if ($cached_id !== false) {
            if (get_post($cached_id)) {
                return (int) $cached_id;
            } else {
                delete_transient($cache_key);
            }
        }

        global $wpdb;

        $attachment_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} 
            WHERE meta_key = '_source_url' 
            AND meta_value = %s 
            LIMIT 1",
            $url
        ));

        if (!$attachment_id) {
            $attachment = $wpdb->get_col($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE guid=%s;", 
                $url
            ));
            $attachment_id = !empty($attachment) ? $attachment[0] : false;
        }
        
        if ($attachment_id) {
            set_transient($cache_key, $attachment_id, WEEK_IN_SECONDS);
            return (int) $attachment_id;
        }
        
        return false;
    }

    /**
     * Downloads an image and adds it to the media library.
     *
     * The filename comes from the URL's "name" query parameter, else the URL path.
     * The source URL is stored in _source_url meta and the attachment ID is cached.
     *
     * @param string $url Image URL.
     * @return int|false Attachment ID, or false on failure.
     */
    private function download_and_attach_image($url) {
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        $parsed_url = parse_url($url);
        $path = $parsed_url['path'] ?? '';

        $filename = $this->get_filename_from_url($url);
        if (empty($filename)) {
            $filename = basename($path);
        }
        
        $tmp = download_url($url);
        
        if (is_wp_error($tmp)) {
            dealers_choice_log('Image download failed', 'error', [
                'url' => $url,
                'error' => $tmp->get_error_message()
            ]);
            return false;
        }

        $file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        // Default to .jpg when the filename has no extension.
        if (empty($file_ext)) {
            $file_ext = 'jpg';
            $filename .= '.' . $file_ext;
        }

        // Normalize .jpeg to .jpg.
        if ($file_ext === 'jpeg') {
            $filename = substr($filename, 0, -4) . 'jpg';
        }

        $file_array = [
            'name' => sanitize_file_name($filename),
            'tmp_name' => $tmp
        ];

        $overrides = [
            'test_form' => false,
            'test_type' => true,
        ];

        // Expose the URL's "name" query parameter in $_GET for the duration of the sideload.
        if (!empty($parsed_url['query'])) {
            parse_str($parsed_url['query'], $query_args);
            if (isset($query_args['name'])) {
                $_GET['name'] = $query_args['name'];
            }
        }

        $file = wp_handle_sideload($file_array, $overrides);

        if (isset($_GET['name'])) {
            unset($_GET['name']);
        }
        
        if (isset($file['error'])) {
            dealers_choice_log('Image sideload failed', 'error', [
                'url' => $url,
                'error' => $file['error']
            ]);
            @unlink($tmp);
            return false;
        }

        $attachment = [
            'guid'           => $file['url'],
            'post_mime_type' => $file['type'],
            'post_title'     => sanitize_file_name(basename($file['file'])),
            'post_content'   => '',
            'post_status'    => 'inherit'
        ];

        $id = wp_insert_attachment($attachment, $file['file']);
        
        if (is_wp_error($id)) {
            @unlink($file['file']);
            dealers_choice_log('Image attachment failed', 'error', [
                'url' => $url,
                'error' => $id->get_error_message()
            ]);
            return false;
        }

        $attach_data = wp_generate_attachment_metadata($id, $file['file']);
        wp_update_attachment_metadata($id, $attach_data);

        update_post_meta($id, '_source_url', $url);

        $cache_key = 'dc_img_' . md5($url);
        set_transient($cache_key, $id, WEEK_IN_SECONDS);
        
        return $id;
    }
    
    /**
     * Returns the sanitized "name" query parameter from a URL.
     *
     * @param string $url URL to parse.
     * @return string Filename, or an empty string when absent.
     */
    private function get_filename_from_url($url) {
        $parts = wp_parse_url($url);
        if (empty($parts['query'])) {
            return '';
        }
        parse_str($parts['query'], $queryArgs);
        return isset($queryArgs['name']) ? sanitize_file_name($queryArgs['name']) : '';
    }
    
    /**
     * Returns the MIME type for a common image extension.
     *
     * @param string $ext File extension.
     * @return string MIME type. Default 'image/jpeg'.
     */
    private function get_mime_type($ext) {
        $mime_types = [
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'gif'  => 'image/gif',
            'webp' => 'image/webp',
        ];
        
        return isset($mime_types[$ext]) ? $mime_types[$ext] : 'image/jpeg';
    }
}
