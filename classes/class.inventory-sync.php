<?php
/**
 * Inventory Sync Class
 * 
 * Core synchronization engine that handles fetching boat inventory from the
 * DealersChoice API and syncing it to WordPress as custom post types with
 * associated taxonomies and ACF fields.
 * 
 * @package DealersChoice
 * @subpackage Classes
 * @since 1.0.0
 * 
 * Functionality:
 * - Fetches inventory data from DealersChoice API
 * - Creates/updates boat posts based on unique inventory ID
 * - Processes and stores ACF field data with proper formatting
 * - Assigns taxonomies including automatic range categorization
 * - Downloads and caches images to prevent duplicates
 * - Unpublishes boats no longer in API feed (except local listings)
 * - Comprehensive logging of all operations
 * 
 * API Integration:
 * - Endpoint: https://dealerschoiceims.securem2.com/api/v1/inventory/sync
 * - Method: POST
 * - Authentication: client_id + api_key
 * - Timeout: 120 seconds
 * - Response: JSON with inventory array and last_sync timestamp
 * 
 * Timestamp Logic:
 * - Compares API last_sync with stored last_sync
 * - Skips processing if inventory hasn't changed
 * - Updates stored timestamp only on successful sync
 * 
 * Image Processing:
 * - Downloads images from API URLs
 * - Checks for existing images via URL meta lookup
 * - Caches attachment IDs for 1 week
 * - Stores source URL as post meta for future reference
 * 
 * Range Taxonomy Assignment:
 * - Price Range: Based on SalePrice field
 * - Length Range: Based on Length field (in feet)
 * - Horsepower Range: Based on EngineHP field
 * - Person Capacity Range: Based on PassengerCapacity field
 * 
 * Local Listings:
 * - Posts with is_local=1 are excluded from unpublishing
 * - Allows manual inventory additions that persist through sync
 * 
 * Dependencies:
 * - WordPress HTTP API (wp_remote_post)
 * - ACF (update_field)
 * - WordPress media functions (media_handle_sideload)
 * - Helper functions: convertLengthToInches, dealers_choice_get_*_range_term
 */
namespace DC;

class InventorySync {

    private $inventory_url = 'https://dealerschoiceims.securem2.com/api/v1/inventory/sync';

    public function __construct() {
        // Constructor can be used for setting up hooks if needed.
    }

    /**
     * Syncs the boat inventory from the API.
     *
     * @param bool $debug Whether to enable debug mode.
     * @param bool $force Whether to force sync regardless of timestamp.
     */
    public function sync_inventory($debug = false, $force = false) {
        // Check if a sync is already in progress
        if (get_transient('dealers_choice_inventory_sync_lock')) {
            dealers_choice_log('Sync already in progress. Aborting.', 'warning');
            return;
        }

        try {
            // Set a lock to prevent concurrent syncs
            set_transient('dealers_choice_inventory_sync_lock', true, 15 * MINUTE_IN_SECONDS); // Lock for 15 minutes

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
                'timeout' => 120, // 2 minutes
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
            // Always release the lock
            delete_transient('dealers_choice_inventory_sync_lock');
        }
    }

    /**
     * Processes the boat inventory data.
     *
     * @param array $inventory The inventory data from the API.
     * @param int   $last_sync_from_api The global last_sync timestamp from the API response, used as
     *                                   fallback when individual boats lack a last_updated timestamp.
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

                // Check if images should be processed.
                // $last_api_update uses the per-boat last_updated field when available; falls back to
                // the global last_sync timestamp so that image processing is not permanently skipped
                // for API feeds that do not provide per-boat update timestamps.
                $per_boat_update = isset($boat_data->last_updated) ? strtotime($boat_data->last_updated) : 0;
                $effective_last_update = $per_boat_update > 0 ? $per_boat_update : $last_sync_from_api;

                // Get the last photo sync timestamp; If it does not exist, ensure the images are processed for new boats
                // or boats that have been imported already and have never had their images synced before.
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

                $this->update_acf_fields($post_id, $boat_data, $should_process_images);
                $this->update_taxonomies($post_id, $boat_data);

                if ($should_process_images) {
                    update_post_meta($post_id, '_last_photo_sync_ts', $effective_last_update > 0 ? $effective_last_update : time());
                }
            }
        }

        $this->unpublish_old_boats($synced_post_ids);
    }

    /**
     * Updates ACF fields for a given post.
     *
     * @param int   $post_id   The ID of the post to update.
     * @param object $data The data for the boat.
     * @param bool $process_images Whether to process images for this boat.
     */
    public function update_acf_fields($post_id, $data, $process_images = false) {
        // Helper function to safely get value or empty string
        $get_value = function($value) {
            return isset($value) && $value !== null && $value !== '' ? $value : '';
        };
        
        // Helper function to safely get numeric value
        $get_numeric = function($value, $decimals = 2) {
            if (!isset($value) || $value === null || $value === '') {
                return '';
            }
            $numeric = preg_replace("/[^0-9.]/", "", (string) $value);
            return $numeric !== '' ? number_format((float) $numeric, $decimals, '.', '') : '';
        };
        
        // Helper function to process data for ACF Repeater fields
        $process_repeater_field = function($value) {
            $data = [];
            
            if (!isset($value) || $value === null || $value === '') {
                return $data;
            }
            
            // Decode JSON if it's a string
            if (is_string($value)) {
                // Check if it looks like JSON
                $trimmed = trim($value);
                if (strpos($trimmed, '[') === 0 || strpos($trimmed, '{') === 0) {
                    $decoded = json_decode($value, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $value = $decoded;
                    } else {
                        // Not valid JSON, treat as string?
                        // If it's just a plain string, assume it's a value
                         return [['label' => '', 'value' => $value]];
                    }
                } else {
                     // Plain string
                     return [['label' => '', 'value' => $value]];
                }
            }
            
            if (is_array($value) || is_object($value)) {
                // Convert to array if object
                $value_arr = (array) $value;
                
                // Check if it's indexed array or associative
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
                            // ["Option 1", "Option 2"]
                            $data[] = [
                                'label' => '', 
                                'value' => $item
                            ];
                        } elseif (is_array($item) || is_object($item)) {
                            $item_arr = (array) $item;
                            // Checking for common key names
                            $label = '';
                            $val = '';
                            
                            // Try to find label
                            $label_keys = ['label', 'name', 'key', 'Label', 'Name', 'Key'];
                            foreach ($label_keys as $k) {
                                if (isset($item_arr[$k])) {
                                    $label = $item_arr[$k];
                                    break;
                                }
                            }
                            
                            // Try to find value
                            $val_keys = ['value', 'data', 'val', 'Value', 'Data', 'Val'];
                            foreach ($val_keys as $k) {
                                if (isset($item_arr[$k])) {
                                    $val = $item_arr[$k];
                                    break;
                                }
                            }
                            
                            // Fallback if no specific keys found and it has 2 properties
                            if (empty($label) && empty($val) && count($item_arr) == 2) {
                                $keys = array_keys($item_arr);
                                $label = $item_arr[$keys[0]];
                                $val = $item_arr[$keys[1]];
                            } elseif (empty($label) && empty($val) && count($item_arr) == 1) {
                                $keys = array_keys($item_arr);
                                $val = $item_arr[$keys[0]];
                            }
                            
                            // If still empty and haven't matched, just json_encode it into value
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
        
        // Parse images - API returns JSON string or Array
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
        if ($process_images) {
            // Process images and set featured image
            $gallery_ids = $this->process_images($images_array);
            
            // Set first image as featured image if available
            if (!empty($gallery_ids) && is_array($gallery_ids)) {
                $first_image_id = $gallery_ids[0];
                // Check if the post already has this thumbnail
                if (get_post_thumbnail_id($post_id) != $first_image_id) {
                    set_post_thumbnail($post_id, $first_image_id);
                }
            }
        } else {
            // If not processing images, retain existing gallery
            $gallery_ids = get_field('gallery', $post_id, false);
        }

        // Process video JSON and store in ACF fields boat_video1, boat_video2, boat_video3 and boat_video4
        // JSON Videos returns Video1: URL, Video2: URL, etc
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
            
            // Extract video URLs based on keys like Video1, Video2, etc or indexed array
            // We map directly to indices 0, 1, 2, 3 corresponding to Video1, Video2, Video3, Video4
            foreach ($video_fields as $index => $field_name) {
                $url = '';
                $video_key = 'Video' . ($index + 1);
                
                // Check if key exists (e.g. Video1)
                if (isset($videos_array[$video_key]) && !empty($videos_array[$video_key])) {
                    $url = $videos_array[$video_key];
                }
                // Fallback to numeric index if needed
                elseif (isset($videos_array[$index]) && !empty($videos_array[$index])) {
                    $url = $videos_array[$index];
                }
                
                // Store at specific index to maintain position (even if empty)
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
            'field_boat_description' => wp_kses_post($get_value($data->Description)), // Sanitize HTML
            'field_boat_saleprice' => $get_numeric($data->SalePrice, 2),
            'field_boat_msrp' => $get_numeric($data->MSRP, 2),
            'field_boat_monthly_payment' => $get_numeric($data->MonthlyPayment, 2),
            'field_boat_interest_rate' => $get_numeric($data->InterestRate, 3),
            'field_boat_down_payment' => $get_numeric($data->DownPayment, 2),
            'field_boat_loan_term' => $get_value($data->LoanTerm),
            'field_boat_length' => (string) $get_value($data->Length),
            'field_boat_length_inches' => convertLengthToInches($get_value($data->Length)),
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
            'is_local' => 0, // This is not a local listing
        ];

        // Add video URLs to ACF fields
        foreach ($video_fields as $index => $field_name) {
            if (isset($video_urls[$index])) {
                $acf_fields[$field_name] = $video_urls[$index];
            }
        }

        foreach ($acf_fields as $key => $value) {
            update_field($key, $value, $post_id);
        }
    }

    /**
     * Updates taxonomies for a given post.
     *
     * @param int   $post_id   The ID of the post to update.
     * @param object $data The data for the boat.
     */
    public function update_taxonomies($post_id, $data) {
        // Remove everything after the first comma in location
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
        
        // Assign to range taxonomies based on actual values
        $this->assign_range_taxonomies($post_id, $data);
    }
    
    /**
     * Assign boat to range taxonomies (price_range, length, horsepower)
     *
     * @param int   $post_id   The ID of the post to update.
     * @param object $data The data for the boat.
     */
    private function assign_range_taxonomies($post_id, $data) {
        // Assign price range
        if (isset($data->SalePrice) && !empty($data->SalePrice)) {
            $price = (float) preg_replace("/[^0-9.]/", "", (string) $data->SalePrice);
            if ($price > 0) {
                $price_range_term = dealers_choice_get_price_range_term($price);
                if ($price_range_term) {
                    wp_set_object_terms($post_id, $price_range_term, 'price_range', false);
                }
            }
        }
        
        // Assign length range
        if (isset($data->Length) && !empty($data->Length)) {
            $length_feet = (float) $data->Length;
            if ($length_feet > 0) {
                $length_range_term = dealers_choice_get_length_range_term($length_feet);
                if ($length_range_term) {
                    wp_set_object_terms($post_id, $length_range_term, 'length_range', false);
                }
            }
        }
        
        // Assign horsepower range
        if (isset($data->EngineHP) && !empty($data->EngineHP)) {
            $hp = (float) $data->EngineHP;
            if ($hp > 0) {
                $hp_range_term = dealers_choice_get_horsepower_range_term($hp);
                if ($hp_range_term) {
                    wp_set_object_terms($post_id, $hp_range_term, 'horsepower', false);
                }
            }
        }

        // Assign person capacity range
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
     * Unpublishes old boat posts that are no longer in the API feed by setting their status to 'draft'.
     * Optimized to use direct database query for better performance with large datasets.
     *
     * @param array $synced_post_ids An array of post IDs that were synced.
     */
    public function unpublish_old_boats($synced_post_ids) {
        global $wpdb;
        
        // Build the query efficiently
        $placeholders = implode(',', array_fill(0, count($synced_post_ids), '%d'));
        
        // Get posts that should be unpublished (published boats not in sync, excluding local listings)
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

            // Remove analytics data for boats that are no longer published
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
     * Process image URLs and return array of attachment IDs for ACF gallery field.
     *
     * @param array $image_urls Array of image URLs from API.
     * @return array Array of attachment IDs.
     */
    private function process_images($image_urls) {
        if (!is_array($image_urls) || empty($image_urls)) {
            return [];
        }

        $attachment_ids = [];

        foreach ($image_urls as $image_url) {
            if (empty($image_url)) {
                continue;
            }

            // Check if image already exists in media library
            $existing_attachment = $this->get_attachment_by_url($image_url);
            
            if ($existing_attachment) {
                $attachment_ids[] = $existing_attachment;
                continue;
            }

            // Download and attach image
            $attachment_id = $this->download_and_attach_image($image_url);
            if ($attachment_id) {
                $attachment_ids[] = $attachment_id;
            }
        }

        return $attachment_ids;
    }

    /**
     * Get attachment ID by URL (optimized with caching and meta storage).
     *
     * @param string $url Image URL.
     * @return int|false Attachment ID or false if not found.
     */
    private function get_attachment_by_url($url) {
        // Check transient cache first
        $cache_key = 'dc_img_' . md5($url);
        $cached_id = get_transient($cache_key);
        
        if ($cached_id !== false) {
            // Verify the attachment still exists
            if (get_post($cached_id)) {
                return (int) $cached_id;
            } else {
                // Clean up invalid cache
                delete_transient($cache_key);
            }
        }
        
        global $wpdb;
        
        // First try to find by stored source URL in meta
        $attachment_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} 
            WHERE meta_key = '_source_url' 
            AND meta_value = %s 
            LIMIT 1",
            $url
        ));
        
        // Fallback to guid check (less reliable but catches old imports)
        if (!$attachment_id) {
            $attachment = $wpdb->get_col($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE guid=%s;", 
                $url
            ));
            $attachment_id = !empty($attachment) ? $attachment[0] : false;
        }
        
        if ($attachment_id) {
            // Cache for 1 week
            set_transient($cache_key, $attachment_id, WEEK_IN_SECONDS);
            return (int) $attachment_id;
        }
        
        return false;
    }

    /**
     * Download image from URL and attach to media library.
     *
     * @param string $url Image URL.
     * @return int|false Attachment ID or false on failure.
     */
    private function download_and_attach_image($url) {
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        // Parse URL to get clean filename
        $parsed_url = parse_url($url);
        $path = $parsed_url['path'] ?? '';
        
        // Get filename from 'name' query param if it exists
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

        // Get the file extension
        $file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        // Ensure we have a valid extension
        if (empty($file_ext)) {
            $file_ext = 'jpg'; // Default to jpg if no extension is found
        }

        // WordPress allows .jpg but not .jpeg — normalize before sideload
        if ($file_ext === 'jpeg') {
            $filename = substr($filename, 0, -4) . 'jpg';
        }

        $file_array = [
            'name' => sanitize_file_name($filename),
            'tmp_name' => $tmp
        ];

        // Use wp_handle_sideload for better control
        $overrides = [
            'test_form' => false,
            'test_type' => true,
        ];
        
        // Pass the original URL's query params to the sideload request so our filter can use it
        if (!empty($parsed_url['query'])) {
            parse_str($parsed_url['query'], $query_args);
            if (isset($query_args['name'])) {
                $_GET['name'] = $query_args['name'];
            }
        }

        $file = wp_handle_sideload($file_array, $overrides);
        
        // Clean up the temporary $_GET param
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

        // Prepare attachment data
        $attachment = [
            'guid'           => $file['url'],
            'post_mime_type' => $file['type'],
            'post_title'     => sanitize_file_name(basename($file['file'])),
            'post_content'   => '',
            'post_status'    => 'inherit'
        ];

        // Insert the attachment
        $id = wp_insert_attachment($attachment, $file['file']);
        
        if (is_wp_error($id)) {
            @unlink($file['file']);
            dealers_choice_log('Image attachment failed', 'error', [
                'url' => $url,
                'error' => $id->get_error_message()
            ]);
            return false;
        }

        // Generate attachment metadata
        $attach_data = wp_generate_attachment_metadata($id, $file['file']);
        wp_update_attachment_metadata($id, $attach_data);

        // Store the original URL as meta for future lookups
        update_post_meta($id, '_source_url', $url);
        
        // Cache the attachment ID
        $cache_key = 'dc_img_' . md5($url);
        set_transient($cache_key, $id, WEEK_IN_SECONDS);
        
        return $id;
    }
    
    /**
     * Get filename from URL.
     *
     * @param string $url The URL to parse.
     * @return string The filename or empty string.
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
     * Get MIME type for common image extensions.
     *
     * @param string $ext File extension.
     * @return string MIME type.
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
