<?php
/**
 * Generate a boat description from it's attributes by sending to AI service to create content.
 * Add a button to the edit boat admin page in the `wp-content-editor-tools` area to generate
 * the description and add it to the main Wordpress editor.
 * Required constants:
 *   DCS_CLAUDE_API_KEY
 *   DCS_OPENAI_API_KEY
 *   DCS_AI_SERVICE // Options: 'openai' or 'claude'
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Create button to generate description in boat edit admin page. Add to `wp-content-editor-tools`
 * through the `media_buttons` action hook.
 */
function dc_add_generate_description_button() {
    global $post;

    // Only add button on boat post type edit screen
    if ( 'boat' !== $post->post_type ) {
        return;
    }

    // Only add the button if the AI service has been defined
    if (!defined( 'DCS_AI_SERVICE' )){
        return;
    }

    ?>
    <button type="button" class="button button-secondary" id="dc-generate-description-button">
        <svg id="dealerschoice_icon" data-name="DealersChoice Solutions" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 371.5 102.94" style="height:6px;margin-right:4px;display:inline-block;vertical-align:middle;fill:#2271b1;">
            <g id="Layer_1-2" data-name="Layer 1">
                <path d="M131.04,11.79c83.3,0,106.87,36.93,106.87,36.93h133.59C332.2-.78,165.61,0,165.61,0h-97.28c-.34,0-.67.17-.86.46l-3.61,5.44-3.2,4.83c-.3.45.02,1.07.57,1.07h69.81,0Z"/>
                <path d="M131.04,91.15H7.79c-.34,0-.67.17-.86.46L.11,101.88c-.3.45.02,1.07.57,1.07h164.92s166.59.78,205.88-48.72h-133.59s-23.58,36.93-106.87,36.93h0Z"/>
            </g>
        </svg>
        Generate Description
    </button>
    <?php
}
add_action( 'media_buttons', 'dc_add_generate_description_button' );

/**
 * Enqueue JavaScript for handling the button click and AJAX request.
 */
function dc_enqueue_generate_description_script( $hook ) {
    global $post;

    // Only enqueue script on boat post type edit screen
    if ( !$post || 'boat' !== $post->post_type ) {
        return;
    }

    wp_enqueue_script( 'dc-generate-description', plugin_dir_url( __FILE__ ) . '/admin/generate-description.js', array( 'jquery' ), '1.0.1', true );

    // Localize script with data including AJAX URL
    wp_localize_script( 'dc-generate-description', 'dc_generate_description', array(
        'nonce'   => wp_create_nonce( 'dc_generate_description_nonce' ),
        'post_id' => $post->ID,
        'ajaxURL' => admin_url( 'admin-ajax.php' )
    ) );
}
add_action( 'admin_enqueue_scripts', 'dc_enqueue_generate_description_script' );

/**
 * AJAX handler to generate boat description.
 */
function dc_generate_boat_description() {
    // Verify user capabilities
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( 'Insufficient permissions.' );
        return;
    }

    // Check nonce for security
    if ( ! wp_verify_nonce( $_POST['nonce'], 'dc_generate_description_nonce' ) ) {
        wp_send_json_error( 'Invalid nonce.' );
        return;
    }

    $post_id = intval( $_POST['post_id'] );
    $boat = get_post( $post_id );

    if ( ! $boat || 'boat' !== $boat->post_type ) {
        wp_send_json_error( 'Invalid boat post.' );
        return;
    }

    // Check if user can edit this specific post
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        wp_send_json_error( 'Cannot edit this boat.' );
        return;
    }

    // Gather boat attributes
    $make = get_the_terms( $post_id, 'make' );
    $model = get_the_terms( $post_id, 'model' );
    $year = get_field( 'year', $post_id );
    $length = get_field( 'length', $post_id );
    $boat_type = get_the_terms( $post_id, 'boat_type' );

    // Construct prompt for AI service
    $prompt = "### ROLE ###\n Act as an expert marine copywriter and local SEO specialist. Your mission is to create a compelling boat description that ranks well on search engines for local buyers and persuades them to inquire.\n";
    $prompt .= "### TASK ###\n Generate a detailed and engaging 4-5 sentence description for a boat with the following attributes. The description must be persuasive, high-end, and optimized for local SEO.\n";
    $prompt .= "### BOAT ATTRIBUTES ###\n";
    $prompt .= "Condition: " . ( get_field( 'type', $post_id ) ? get_field( 'type', $post_id ) : 'N/A' ) . "\n";
    $prompt .= "Make: " . ( $make ? $make[0]->name : 'N/A' ) . "\n";
    $prompt .= "Model: " . ( $model ? $model[0]->name : 'N/A' ) . "\n";
    $prompt .= "Year: " . ( $year ? $year : 'N/A' ) . "\n";
    $prompt .= "Length: " . ( $length ? $length : 'N/A' ) . "\n";
    $prompt .= "Boat Type: " . ( $boat_type ? $boat_type[0]->name : 'N/A' ) . "\n";
    $prompt .= "### SEO & CONTENT INSTRUCTIONS ###\n";
    $prompt .= "1.  **Primary Keywords:** Naturally integrate the primary keywords: \"".$year." ".$make." ".$model."\" and \"".get_field( 'type', $post_id )." ".$make." ".$model." for sale\".\n";
    $prompt .= "2.  **Local SEO:** This is critical. You must naturally weave in the business name \"".get_bloginfo('name')."\" and selling points \"".get_bloginfo('description')."\". The goal is to capture local searches like \"".$make." boats near me\" or \"boat dealers in Lake George, New York\".\n";
    $prompt .= "3.  **Benefits Over Features:** Do not just list the attributes. Translate them into compelling buyer benefits.\n";
    $prompt .= "4.  **Tone:** Use persuasive, energetic, and premium marketing language.\n";
    $prompt .= "5.  **Negative Keywords:** Do NOT use generic phrases like 'vessel' when referring to the boat, or 'setting sail'.\n";
    $prompt .= "6.  **Call to Action:** Conclude with a clear call to action encouraging readers to contact ".get_bloginfo('name')." for more information or to schedule a viewing.";
    $prompt .= "\n";
    $prompt .= "### FORMATTING ###\n";
    $prompt .= "The entire response must be plain text only. Do not use any Markdown formatting like headings (#), bold text (**), or bullet points.\n";
    $prompt .= "### RESPONSE ###\n Provide only the boat description as plain text without any additional commentary.";

    // Get the AI service to use from config
    $ai_service = defined( 'DCS_AI_SERVICE' ) ? DCS_AI_SERVICE : 'openai';

    // Call AI service
    if ( 'openai' === $ai_service ) {
        $description = dc_openai_generate_description( $prompt );
    } else {
        $description = dc_claude_generate_description( $prompt );
    }

    if ( ! $description ) {
        wp_send_json_error( 'Failed to generate description.' );
        return;
    }

    // This converts the string to UTF-8, replacing any invalid characters.
    $clean_description = mb_convert_encoding($description, 'UTF-8', 'UTF-8');

    // Send success response with the cleaned description
    wp_send_json_success( $clean_description );
}
add_action( 'wp_ajax_dc_generate_boat_description', 'dc_generate_boat_description' );

/**
 * OpenAI API integration to generate boat description from prompt
 * @param string $prompt The prompt to send to OpenAI
 * @return string|false The generated description or false on failure
 * source: https://platform.openai.com/docs/
 */
function dc_openai_generate_description( $prompt ) {
    // Check if the key is defined in wp-config.php
    if ( !defined( 'DCS_OPENAI_API_KEY' ) || empty( DCS_OPENAI_API_KEY ) ) {
        error_log('OpenAI API Error: Key not defined in wp-config.php');
        return false;
    }

    $api_key = DCS_OPENAI_API_KEY;
    $response = wp_remote_post( 'https://api.openai.com/v1/responses', array(
        'headers' => array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $api_key,
        ),
        'body' => json_encode( array(
            'model' => 'gpt-5-nano-2025-08-07',
            'input' => $prompt,
        ) ),
        'timeout' => 60,
    ) );

    if ( is_wp_error( $response ) ) {
        error_log('OpenAI API WP_Error: ' . $response->get_error_message());
        return false;
    }

    $body = wp_remote_retrieve_body( $response );
    $data = json_decode( $body, true );

    // Parse the response from OpenAI Responses API
    if( isset($data['output'][1]['content'][0]['text']) ) {
        return trim( $data['output'][1]['content'][0]['text'] );
    } else {
        // Log the error response from OpenAI for debugging
        error_log('OpenAI API Response Error: ' . $body);
    }

    return false;
}


/**
 * Claude API integration to generate boat description from prompt
 * @param string $prompt The prompt to send to Claude
 * @return string|false The generated description or false on failure
 * source: https://docs.claude.com/reference/messages_post
 */
function dc_claude_generate_description( $prompt ) {
    // Check if the key is defined in wp-config.php
    if ( !defined( 'DCS_CLAUDE_API_KEY' ) || empty( DCS_CLAUDE_API_KEY ) ) {
        error_log('Claude API Error: Key not defined in wp-config.php');
        return false;
    }

    $api_key = DCS_CLAUDE_API_KEY;
    $response = wp_remote_post( 'https://api.anthropic.com/v1/messages', array(
        'headers' => array(
            'Content-Type'      => 'application/json',
            'x-api-key'         => $api_key,
            'anthropic-version' => '2023-06-01'
        ),
        'body' => json_encode( array(
            'model'      => 'claude-haiku-4-5-20251001',
            'max_tokens' => 1024,
            'messages'   => array(
                array(
                    'role'    => 'user',
                    'content' => $prompt,
                )
            )
        ) ),
        'timeout' => 30,
    ) );

    if ( is_wp_error( $response ) ) {
        error_log('Claude API WP_Error: ' . $response->get_error_message());
        return false;
    }

    $body = wp_remote_retrieve_body( $response );
    $data = json_decode( $body, true );

    // Parse the response from the Claude Messages API
    if ( isset( $data['content'][0]['text'] ) ) {
        $text = $data['content'][0]['text'];
        return trim($text);
    } else {
        // Log the error response from Claude for debugging
        error_log('Claude API Response Error: ' . $body);
    }

    return false;
}