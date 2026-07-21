<?php
/**
 * Admin Plugin Settings Page
 * 
 * Handles the main settings form for DealersChoice plugin, including API credential
 * configuration and validation. This is the primary configuration interface for
 * connecting the plugin to the DealersChoice API.
 * 
 * @package DealersChoice
 * @subpackage Admin
 * @since 1.0.0
 * 
 * Features:
 * - API credential configuration (Client ID and API Key)
 * - Real-time credential validation against DealersChoice API
 * - Success/error messaging with detailed feedback
 * - Secure credential storage in WordPress options
 * 
 * Settings Stored:
 * - dealers_choice_client_id: Client identifier for API authentication
 * - dealers_choice_api_key: Secret key for API authentication
 * - dealers_choice_get_a_quote_page_id: Page ID for Get a Quote form
 * - dealers_choice_schedule_test_drive_page_id: Page ID for Schedule a Test Drive form
 * - dealers_choice_financing_page_id: Page ID for Financing form
 * - dealers_choice_value_your_trade_page_id: Page ID for Value Your Trade form
 * - dealers_choice_always_show_price: Toggle to always show price
 * - dealers_choice_show_favorites: Toggle to show favorites
 * - dealers_choice_show_finance_calculator: Toggle to show the quick finance calculator on single boat pages
 * - dealers_choice_finance_default_rate: Default APR (%) pre-filled in both finance calculators
 * - dealers_choice_finance_default_term: Default loan term (months) pre-filled in both finance calculators
 * - dealers_choice_finance_default_down_payment_percent: Default down payment (% of price) pre-filled in both finance calculators
 * - dealers_choice_popup_form_id: Popup form ID for price inquiries
 * - dealerschoice_sales_cta_popup_id: Popup Maker popup ID for the inventory view tracker special offer
 * - dealerschoice_inventory_view_limit: Number of views before the special offer popup is triggered
 * 
 * Validation Endpoint:
 * - URL: https://dealerschoiceims.securem2.com/api/v1/validate
 * - Method: POST
 * - Required: client_id, api_key
 * - Response: JSON with status and message
 */

/**
 * Process form submission early in WordPress request cycle
 */
function dealers_choice_process_settings_form() {
    // Only process on our settings page
    if (!isset($_GET['page']) || $_GET['page'] !== 'dealers-choice-settings') {
        return;
    }
    
    // Check if the form is submitted
    if (!isset($_POST['submit'])) {
        return;
    }
    
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    
    // Verify nonce for security
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'dealers_choice_settings')) {
        wp_die(__('Security check failed', 'dealers-choice'));
    }

    // Update the API key option
    $api_key = sanitize_text_field($_POST['dealers_choice_api_key']);
    $client_id = sanitize_text_field($_POST['dealers_choice_client_id']);

    // Inventory Settings: Save selected page IDs (not required)
    $cta_fields = array(
        'get_a_quote' => 'dealers_choice_get_a_quote_page_id',
        'schedule_test_drive' => 'dealers_choice_schedule_test_drive_page_id',
        'financing' => 'dealers_choice_financing_page_id',
        'value_your_trade' => 'dealers_choice_value_your_trade_page_id',
    );
    foreach ($cta_fields as $field => $option_name) {
        $page_id = isset($_POST[$option_name]) ? intval($_POST[$option_name]) : '';
        if ($page_id) {
            update_option($option_name, $page_id);
        } else {
            delete_option($option_name);
        }
    }

    // Display Settings: Save 'Always Show Price' and Popup Form ID
    $always_show_price = isset($_POST['dealers_choice_always_show_price']) ? '1' : '0';
    update_option('dealers_choice_always_show_price', $always_show_price);
    
    $popup_form_id = isset($_POST['dealers_choice_popup_form_id']) ? sanitize_text_field($_POST['dealers_choice_popup_form_id']) : '';
    if ($popup_form_id !== '') {
        update_option('dealers_choice_popup_form_id', $popup_form_id);
    } else {
        delete_option('dealers_choice_popup_form_id');
    }

    $reveal_price_gravity_form_id = isset($_POST['dealers_choice_reveal_price_gravity_form_id']) ? sanitize_text_field($_POST['dealers_choice_reveal_price_gravity_form_id']) : '';
    if ($reveal_price_gravity_form_id !== '') {
        update_option('dealers_choice_reveal_price_gravity_form_id', $reveal_price_gravity_form_id);
    } else {
        delete_option('dealers_choice_reveal_price_gravity_form_id');
    }

    $allowed_zips = isset($_POST['dealers_choice_allowed_zips']) ? sanitize_textarea_field($_POST['dealers_choice_allowed_zips']) : '';
    if($allowed_zips !== ''){
        update_option('dealers_choice_allowed_zips', $allowed_zips);
    } else {
        delete_option('dealers_choice_allowed_zips');
    }

    $location_request_message = isset($_POST['dealers_choice_location_request_message']) ? wp_kses_post($_POST['dealers_choice_location_request_message']) : '';
    if($location_request_message !== ''){
        update_option('dealers_choice_location_request_message', $location_request_message);
    } else {
        delete_option('dealers_choice_location_request_message');
    }

    $location_verified_message = isset($_POST['dealers_choice_location_verified_message']) ? wp_kses_post($_POST['dealers_choice_location_verified_message']) : '';
    if($location_verified_message !== ''){
        update_option('dealers_choice_location_verified_message', $location_verified_message);
    } else {
        delete_option('dealers_choice_location_verified_message');
    }

    $location_failed_message = isset($_POST['dealers_choice_location_failed_message']) ? wp_kses_post($_POST['dealers_choice_location_failed_message']) : '';
    if($location_failed_message !== ''){
        update_option('dealers_choice_location_failed_message', $location_failed_message);
    } else {
        delete_option('dealers_choice_location_failed_message');
    }

    $location_denied_message = isset($_POST['dealers_choice_location_denied_message']) ? wp_kses_post($_POST['dealers_choice_location_denied_message']) : '';
    if($location_denied_message !== ''){
        update_option('dealers_choice_location_denied_message', $location_denied_message);
    } else {
        delete_option('dealers_choice_location_denied_message');
    }

    $price_unavailable_message = isset($_POST['dealers_choice_price_unavailable_message']) ? wp_kses_post($_POST['dealers_choice_price_unavailable_message']) : '';
    if($price_unavailable_message !== ''){
        update_option('dealers_choice_price_unavailable_message', $price_unavailable_message);
    } else {
        delete_option('dealers_choice_price_unavailable_message');
    }

    // Display Settings: Save 'Show Favorites' option
    $show_favorites = isset($_POST['dealers_choice_show_favorites']) ? '1' : '0';
    update_option('dealers_choice_show_favorites', $show_favorites);

    // Finance Calculator: Save toggle for quick calculator on single boat pages
    $show_finance_calculator = isset($_POST['dealers_choice_show_finance_calculator']) ? '1' : '0';
    update_option('dealers_choice_show_finance_calculator', $show_finance_calculator);

    // Finance Calculator: Save default APR (%), clamped 0-30
    $finance_default_rate = isset($_POST['dealers_choice_finance_default_rate']) ? (float) $_POST['dealers_choice_finance_default_rate'] : 7.99;
    if ($finance_default_rate < 0 || $finance_default_rate > 30) {
        $finance_default_rate = 7.99;
    }
    update_option('dealers_choice_finance_default_rate', $finance_default_rate);

    // Finance Calculator: Save default loan term (months) - must be one of the valid term presets
    $valid_terms = array_keys(\DC\Shortcodes::get_finance_term_options());
    $finance_default_term = isset($_POST['dealers_choice_finance_default_term']) ? intval($_POST['dealers_choice_finance_default_term']) : 240;
    if (!in_array($finance_default_term, $valid_terms, true)) {
        $finance_default_term = 240;
    }
    update_option('dealers_choice_finance_default_term', $finance_default_term);

    // Finance Calculator: Save default down payment (% of price), clamped 0-99
    $finance_default_down_payment_percent = isset($_POST['dealers_choice_finance_default_down_payment_percent']) ? (float) $_POST['dealers_choice_finance_default_down_payment_percent'] : 20;
    if ($finance_default_down_payment_percent < 0 || $finance_default_down_payment_percent > 99) {
        $finance_default_down_payment_percent = 20;
    }
    update_option('dealers_choice_finance_default_down_payment_percent', $finance_default_down_payment_percent);

    // Inventory View Tracker: Save Popup Maker popup ID and view limit
    $sales_cta_popup_id = isset($_POST['dealerschoice_sales_cta_popup_id']) ? sanitize_text_field($_POST['dealerschoice_sales_cta_popup_id']) : '';
    if ($sales_cta_popup_id !== '') {
        update_option('dealerschoice_sales_cta_popup_id', $sales_cta_popup_id);
    } else {
        delete_option('dealerschoice_sales_cta_popup_id');
    }

    $inventory_view_limit = isset($_POST['dealerschoice_inventory_view_limit']) ? intval($_POST['dealerschoice_inventory_view_limit']) : 5;
    if ($inventory_view_limit < 1) {
        $inventory_view_limit = 5;
    }
    update_option('dealerschoice_inventory_view_limit', $inventory_view_limit);

    // Display Settings: Save default sort order
    $allowed_sorts = ['date-desc', 'date-asc', 'price-asc', 'price-desc', 'year-desc', 'year-asc', 'length-desc', 'length-asc', 'title-asc', 'title-desc'];
    $default_sort = isset($_POST['dealers_choice_default_sort']) ? sanitize_text_field($_POST['dealers_choice_default_sort']) : 'date-desc';
    if (!in_array($default_sort, $allowed_sorts, true)) {
        $default_sort = 'date-desc';
    }
    update_option('dealers_choice_default_sort', $default_sort);

    // Validate the API key and client ID
    $validation_url = 'https://dealerschoiceims.securem2.com/api/v1/validate';
    $validation_response = wp_remote_post($validation_url, array(
        'body' => array(
            'client_id' => $client_id,
            'api_key' => $api_key
        )
    ));

    if (is_wp_error($validation_response)) {
        $error_message = $validation_response->get_error_message();
        wp_redirect(add_query_arg(array('settings-updated' => 'false', 'error' => urlencode($error_message)), admin_url('admin.php?page=dealers-choice-settings')));
        exit;
    }

    $body = wp_remote_retrieve_body($validation_response);
    $data = json_decode($body);

    if (!$data || !isset($data->status)) {
        wp_redirect(add_query_arg(array('settings-updated' => 'false', 'error' => urlencode(__('Invalid API response', 'dealers-choice'))), admin_url('admin.php?page=dealers-choice-settings')));
        exit;
    }

    if ($data->status == 'success') {
        update_option('dealers_choice_api_key', $api_key);
        update_option('dealers_choice_client_id', $client_id);
        wp_redirect(add_query_arg(array('settings-updated' => 'true', 'message' => urlencode($data->message)), admin_url('admin.php?page=dealers-choice-settings')));
        exit;
    } else {
        wp_redirect(add_query_arg(array('settings-updated' => 'false', 'error' => urlencode($data->message)), admin_url('admin.php?page=dealers-choice-settings')));
        exit;
    }
}
add_action('admin_init', 'dealers_choice_process_settings_form');

/**
 * Display admin notices for settings page
 */
function dealers_choice_settings_admin_notices() {
    // Only show on our settings page
    if (!isset($_GET['page']) || $_GET['page'] !== 'dealers-choice-settings') {
        return;
    }
    
    // Check if the settings were updated
    if (isset($_GET['settings-updated'])) {
        if ($_GET['settings-updated'] == 'true') {
            $message = isset($_GET['message']) ? urldecode($_GET['message']) : __('Settings saved.', 'dealers-choice');
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
        } else {
            $error_message = isset($_GET['error']) ? urldecode($_GET['error']) : __('An unknown error occurred.', 'dealers-choice');
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($error_message) . '</p></div>';
        }
    }
}
add_action('admin_notices', 'dealers_choice_settings_admin_notices');

/**
 * Enqueue admin styles and scripts for settings page
 */
function dealers_choice_settings_enqueue_assets($hook) {
    // Load admin styles on all Dealers Choice admin pages
    if (strpos($hook, 'dealers-choice') !== false) {
        wp_enqueue_style(
            'dealers-choice-admin-style',
            plugin_dir_url(__FILE__) . 'admin-style.css',
            [],
            '1.0.0'
        );
        
        // Enqueue admin scripts
        wp_enqueue_script(
            'dealers-choice-admin-script',
            plugin_dir_url(__FILE__) . 'admin-scripts.js',
            ['jquery'],
            '1.0.0',
            true
        );
    }
        
}
add_action('admin_enqueue_scripts', 'dealers_choice_settings_enqueue_assets');

/**
 * Display the settings page
 */
function dealers_choice_settings_page() {
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    // Get the current API key value
    $api_key = get_option('dealers_choice_api_key', '');
    $client_id = get_option('dealers_choice_client_id', '');

    // Get all pages for dropdowns
    $pages = get_pages();

    // Get current CTA page IDs
    $cta_options = array(
        'dealers_choice_get_a_quote_page_id' => get_option('dealers_choice_get_a_quote_page_id', ''),
        'dealers_choice_schedule_test_drive_page_id' => get_option('dealers_choice_schedule_test_drive_page_id', ''),
        'dealers_choice_financing_page_id' => get_option('dealers_choice_financing_page_id', ''),
        'dealers_choice_value_your_trade_page_id' => get_option('dealers_choice_value_your_trade_page_id', ''),
    );

    // Display Settings: Get current values
    $always_show_price = get_option('dealers_choice_always_show_price', '1');
    $show_favorites = get_option('dealers_choice_show_favorites', '1');
    $show_finance_calculator = get_option('dealers_choice_show_finance_calculator', '0');
    $finance_default_rate = get_option('dealers_choice_finance_default_rate', 7.99);
    $finance_default_term = get_option('dealers_choice_finance_default_term', 240);
    $finance_default_down_payment_percent = get_option('dealers_choice_finance_default_down_payment_percent', 20);
    $finance_term_options = \DC\Shortcodes::get_finance_term_options();
    $default_sort = get_option('dealers_choice_default_sort', 'date-desc');
    $sort_options = [
        'date-desc'   => 'Newest First',
        'date-asc'    => 'Oldest First',
        'price-asc'   => 'Price: Low to High',
        'price-desc'  => 'Price: High to Low',
        'year-desc'   => 'Year: Newest',
        'year-asc'    => 'Year: Oldest',
        'length-desc' => 'Length: Longest',
        'length-asc'  => 'Length: Shortest',
        'title-asc'   => 'Title: A–Z',
        'title-desc'  => 'Title: Z–A',
    ];
    $popup_form_id = get_option('dealers_choice_popup_form_id', '');
    $reveal_price_gravity_form_id = get_option('dealers_choice_reveal_price_gravity_form_id', '');
    $sales_cta_popup_id = get_option('dealerschoice_sales_cta_popup_id', '');
    $inventory_view_limit = get_option('dealerschoice_inventory_view_limit', 5);
    $allowed_zips = get_option('dealers_choice_allowed_zips', '');
    $location_request_message = get_option('dealers_choice_location_request_message', 'In order to comply with manufacturer pricing policies, we need to verify your location. Please allow location access to continue.');
    $location_verified_message = get_option('dealers_choice_location_verified_message', 'Your location has been verified. Please fill out the form to reveal the price.');
    $location_failed_message = get_option('dealers_choice_location_failed_message', 'We\'re sorry, but we were unable to verify that you\'re currently in our boating territory. A salesperson will be in touch with you to discuss pricing. Please fill out the form to continue.');

    ?>
    <div class="wrap">
        <h1><?php _e('DealersChoice Settings', 'dealers-choice'); ?></h1>
        <form method="post" action="">
            <?php wp_nonce_field('dealers_choice_settings'); ?>
            <h2><?php _e('DealersChoice API Settings', 'dealers-choice'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="dealers_choice_client_id"><?php _e('Client ID', 'dealers-choice'); ?></label></th>
                    <td><input type="text" id="dealers_choice_client_id" name="dealers_choice_client_id" value="<?php echo esc_attr($client_id); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="dealers_choice_api_key"><?php _e('API Key', 'dealers-choice'); ?></label></th>
                    <td><input type="text" id="dealers_choice_api_key" name="dealers_choice_api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text" /></td>
                </tr>
            </table>

            <h2><?php _e('Display Settings', 'dealers-choice'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Show Favorites', 'dealers-choice'); ?></th>
                    <td>
                        <label class="dc-switch">
                            <input type="checkbox" id="dealers_choice_show_favorites" name="dealers_choice_show_favorites" value="1" <?php checked($show_favorites, '1'); ?> />
                            <span class="dc-slider"></span>
                        </label>
                        <span class="dc-switch-label"><?php _e('Show favorites button on inventory items', 'dealers-choice'); ?></span>
                        <p class="description"><?php _e('If checked, the favorites button will be displayed on inventory items.', 'dealers-choice'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Show Finance Calculator', 'dealers-choice'); ?></th>
                    <td>
                        <label class="dc-switch">
                            <input type="checkbox" id="dealers_choice_show_finance_calculator" name="dealers_choice_show_finance_calculator" value="1" <?php checked($show_finance_calculator, '1'); ?> />
                            <span class="dc-slider"></span>
                        </label>
                        <span class="dc-switch-label"><?php _e('Show quick finance calculator on single boat pages', 'dealers-choice'); ?></span>
                        <p class="description"><?php _e('Only displays when the boat has a visible price and does not already have dealer-supplied financing data from the inventory feed.', 'dealers-choice'); ?></p>
                    </td>
                </tr>
                <tr id="finance-default-rate-row">
                    <th scope="row">
                        <label for="dealers_choice_finance_default_rate"><?php _e('Default Interest Rate (APR %)', 'dealers-choice'); ?></label>
                    </th>
                    <td>
                        <input type="number" step="0.01" min="0" max="30" id="dealers_choice_finance_default_rate" name="dealers_choice_finance_default_rate" value="<?php echo esc_attr($finance_default_rate); ?>" class="small-text" />
                        <p class="description"><?php _e('Pre-fills the interest rate field on both finance calculators. Visitors can still type a custom rate. Default: 7.99.', 'dealers-choice'); ?></p>
                    </td>
                </tr>
                <tr id="finance-default-term-row">
                    <th scope="row">
                        <label for="dealers_choice_finance_default_term"><?php _e('Default Loan Term', 'dealers-choice'); ?></label>
                    </th>
                    <td>
                        <select id="dealers_choice_finance_default_term" name="dealers_choice_finance_default_term">
                            <?php foreach ($finance_term_options as $months => $label): ?>
                                <option value="<?php echo esc_attr($months); ?>" <?php selected((int) $finance_default_term, $months); ?>><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php _e('Pre-fills the loan term dropdown on both finance calculators. Default: 240 months (20 years).', 'dealers-choice'); ?></p>
                    </td>
                </tr>
                <tr id="finance-default-down-payment-row">
                    <th scope="row">
                        <label for="dealers_choice_finance_default_down_payment_percent"><?php _e('Default Down Payment (%)', 'dealers-choice'); ?></label>
                    </th>
                    <td>
                        <input type="number" step="0.01" min="0" max="99" id="dealers_choice_finance_default_down_payment_percent" name="dealers_choice_finance_default_down_payment_percent" value="<?php echo esc_attr($finance_default_down_payment_percent); ?>" class="small-text" />
                        <p class="description"><?php _e('Pre-fills the down payment field as a percentage of the amount financed on both finance calculators. Visitors can still enter a custom amount. Default: 20.', 'dealers-choice'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="dealers_choice_default_sort"><?php _e('Default Sort Order', 'dealers-choice'); ?></label></th>
                    <td>
                        <select id="dealers_choice_default_sort" name="dealers_choice_default_sort">
                            <?php foreach ($sort_options as $value => $label): ?>
                                <option value="<?php echo esc_attr($value); ?>" <?php selected($default_sort, $value); ?>><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php _e('Default sort order for the inventory listing.', 'dealers-choice'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Always Show Price', 'dealers-choice'); ?></th>
                    <td>
                        <label class="dc-switch">
                            <input type="checkbox" id="dealers_choice_always_show_price" name="dealers_choice_always_show_price" value="1" <?php checked($always_show_price, '1'); ?> />
                            <span class="dc-slider"></span>
                        </label>
                        <span class="dc-switch-label"><?php _e('Show price on all inventory items', 'dealers-choice'); ?></span>
                        <p class="description"><?php _e('If unchecked, a Reveal Price form can be shown instead of the price.', 'dealers-choice'); ?></p>
                    </td>
                </tr>
                <tr id="popup-form-id-row">
                    <th scope="row">
                        <label for="dealers_choice_popup_form_id">Reveal Price Popup Maker ID</label>
                    </th>
                    <td>
                        <input type="text" id="dealers_choice_popup_form_id" name="dealers_choice_popup_form_id" value="<?php echo esc_attr($popup_form_id); ?>" class="regular-text" />
                        <p class="description">Enter the ID of the Popup Maker popup that contains your reveal price form.</p>
                    </td>
                </tr>
                <tr id="gravity-form-id-row">
                    <th scope="row">
                        <label for="dealers_choice_reveal_price_gravity_form_id">Reveal Price Gravity Form ID</label>
                    </th>
                    <td>
                        <input type="text" name="dealers_choice_reveal_price_gravity_form_id" id="dealers_choice_reveal_price_gravity_form_id" value="<?php echo esc_attr( get_option('dealers_choice_reveal_price_gravity_form_id') ); ?>" class="regular-text">
                        <p class="description">Enter the ID of the Gravity Form used in your "Reveal Price" popup.</p>
                    </td>
                </tr>
                <tr id="allowed-zips-row">
                    <th scope="row">
                        <label for="dealers_choice_allowed_zips">Allowed Zip Codes</label>
                    </th>
                    <td>
                        <textarea id="dealers_choice_allowed_zips" name="dealers_choice_allowed_zips" class="large-text" rows="5"><?php echo esc_textarea($allowed_zips); ?></textarea>
                        <p class="description">A comma-separated list of zip codes that are allowed to see the price.</p>
                    </td>
                </tr>
                <tr id="location-request-message-row">
                    <th scope="row">
                        <label for="dealers_choice_location_request_message">Location Request Message</label>
                    </th>
                    <td>
                        <textarea name="dealers_choice_location_request_message" id="dealers_choice_location_request_message" class="large-text" rows="5"><?php echo esc_textarea( stripslashes( get_option( 'dealers_choice_location_request_message' ) ) ); ?></textarea>
                        <p class="description">The message to display while verifying the user's location. Default: "In order to comply with manufacturer pricing policies, we need to verify your location. Please allow location access to continue."</p>
                    </td>
                </tr>
                <tr id="location-verified-message-row">
                    <th scope="row">
                        <label for="dealers_choice_location_verified_message">Location Verified Message</label>
                    </th>
                    <td>
                        <textarea name="dealers_choice_location_verified_message" id="dealers_choice_location_verified_message" class="large-text" rows="5"><?php echo esc_textarea( stripslashes( get_option( 'dealers_choice_location_verified_message' ) ) ); ?></textarea>
                        <p class="description">The message to display when the user's location has been verified. Default: "Your location has been verified. Please fill out the form to reveal the price."</p>
                    </td>
                </tr>
                <tr id="location-failed-message-row">
                    <th scope="row">
                        <label for="dealers_choice_location_failed_message">Location Failed Message</label>
                    </th>
                    <td>
                        <textarea name="dealers_choice_location_failed_message" id="dealers_choice_location_failed_message" class="large-text" rows="5"><?php echo esc_textarea( stripslashes( get_option( 'dealers_choice_location_failed_message' ) ) ); ?></textarea>
                        <p class="description">The message to display when the user is outside the allowed zip code area (out of sales territory). Default: "We're sorry, but we were unable to verify that you're currently in our boating territory. A salesperson will be in touch with you to discuss pricing. Please fill out the form to continue."</p>
                    </td>
                </tr>
                <tr id="location-denied-message-row">
                    <th scope="row">
                        <label for="dealers_choice_location_denied_message">Location Denied / No Geolocation Message</label>
                    </th>
                    <td>
                        <textarea name="dealers_choice_location_denied_message" id="dealers_choice_location_denied_message" class="large-text" rows="5"><?php echo esc_textarea( stripslashes( get_option( 'dealers_choice_location_denied_message', 'Geolocation is not supported by your browser. Please contact us for pricing information.' ) ) ); ?></textarea>
                        <p class="description">The message to display when the user denies location access, or their browser does not support geolocation. Default: "Geolocation is not supported by your browser. Please contact us for pricing information."</p>
                    </td>
                </tr>
                <tr id="sales-cta-popup-id-row">
                    <th scope="row">
                        <label for="dealerschoice_sales_cta_popup_id"><?php _e('Special Offer Popup Maker ID', 'dealers-choice'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="dealerschoice_sales_cta_popup_id" name="dealerschoice_sales_cta_popup_id" value="<?php echo esc_attr($sales_cta_popup_id); ?>" class="regular-text" />
                        <p class="description"><?php _e('Enter the ID of the Popup Maker popup to show as a special offer once a visitor has viewed an inventory item enough times. Leave blank to disable the inventory view tracker.', 'dealers-choice'); ?></p>
                    </td>
                </tr>
                <tr id="inventory-view-limit-row">
                    <th scope="row">
                        <label for="dealerschoice_inventory_view_limit"><?php _e('Inventory View Limit', 'dealers-choice'); ?></label>
                    </th>
                    <td>
                        <input type="number" min="1" id="dealerschoice_inventory_view_limit" name="dealerschoice_inventory_view_limit" value="<?php echo esc_attr($inventory_view_limit); ?>" class="small-text" />
                        <p class="description"><?php _e('Number of times a visitor must view the same inventory item (tracked per-browser) before the special offer popup is triggered. Default: 5.', 'dealers-choice'); ?></p>
                    </td>
                </tr>
                <tr id="price-unavailable-message-row">
                    <th scope="row">
                        <label for="dealers_choice_price_unavailable_message">Price Unavailable Message</label>
                    </th>
                    <td>
                        <textarea name="dealers_choice_price_unavailable_message" id="dealers_choice_price_unavailable_message" class="large-text" rows="3"><?php echo esc_textarea( stripslashes( get_option( 'dealers_choice_price_unavailable_message', 'Price unavailable. Please contact us.' ) ) ); ?></textarea>
                        <p class="description">Shown in place of a $0 or missing price after the form is submitted. Default: "Price unavailable. Please contact us."</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="dealers_choice_get_a_quote_page_id"><?php _e('Get a Quote Page', 'dealers-choice'); ?></label></th>
                    <td>
                        <select id="dealers_choice_get_a_quote_page_id" name="dealers_choice_get_a_quote_page_id">
                            <option value=""><?php _e('— Select a page —', 'dealers-choice'); ?></option>
                            <?php foreach ($pages as $page): ?>
                                <option value="<?php echo esc_attr($page->ID); ?>" <?php selected($cta_options['dealers_choice_get_a_quote_page_id'], $page->ID); ?>><?php echo esc_html($page->post_title); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php _e('Select the page for the Get a Quote form (optional).', 'dealers-choice'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="dealers_choice_schedule_test_drive_page_id"><?php _e('Schedule a Test Drive Page', 'dealers-choice'); ?></label></th>
                    <td>
                        <select id="dealers_choice_schedule_test_drive_page_id" name="dealers_choice_schedule_test_drive_page_id">
                            <option value=""><?php _e('— Select a page —', 'dealers-choice'); ?></option>
                            <?php foreach ($pages as $page): ?>
                                <option value="<?php echo esc_attr($page->ID); ?>" <?php selected($cta_options['dealers_choice_schedule_test_drive_page_id'], $page->ID); ?>><?php echo esc_html($page->post_title); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php _e('Select the page for the Schedule a Test Drive form (optional).', 'dealers-choice'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="dealers_choice_financing_page_id"><?php _e('Financing Page', 'dealers-choice'); ?></label></th>
                    <td>
                        <select id="dealers_choice_financing_page_id" name="dealers_choice_financing_page_id">
                            <option value=""><?php _e('— Select a page —', 'dealers-choice'); ?></option>
                            <?php foreach ($pages as $page): ?>
                                <option value="<?php echo esc_attr($page->ID); ?>" <?php selected($cta_options['dealers_choice_financing_page_id'], $page->ID); ?>><?php echo esc_html($page->post_title); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php _e('Select the page for the Financing form (optional).', 'dealers-choice'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="dealers_choice_value_your_trade_page_id"><?php _e('Value Your Trade Page', 'dealers-choice'); ?></label></th>
                    <td>
                        <select id="dealers_choice_value_your_trade_page_id" name="dealers_choice_value_your_trade_page_id">
                            <option value=""><?php _e('— Select a page —', 'dealers-choice'); ?></option>
                            <?php foreach ($pages as $page): ?>
                                <option value="<?php echo esc_attr($page->ID); ?>" <?php selected($cta_options['dealers_choice_value_your_trade_page_id'], $page->ID); ?>><?php echo esc_html($page->post_title); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php _e('Select the page for the Value Your Trade form (optional).', 'dealers-choice'); ?></p>
                    </td>
                </tr>
            </table>

            <?php submit_button(__('Save Changes', 'dealers-choice')); ?>
        </form>
    </div>
    <?php
}