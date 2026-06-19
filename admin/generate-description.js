jQuery(document).ready(function($) {
    // Handle the button click; send AJAX request to generate description
    $('#dc-generate-description-button').on('click', function() {
        // Show loading state
        var $button = $(this);
        $button.prop('disabled', true);
        
        var data = {
            'action': 'dc_generate_boat_description',
            'post_id': dc_generate_description.post_id,
            'nonce': dc_generate_description.nonce
        };

        $.post(dc_generate_description.ajaxURL, data, function(response) {
            if (response.success) {
                // Get the specific editor instance by its ID ('content')
                var editor = tinymce.get('content');

                // Check if the editor instance exists and is in Visual mode
                if (editor && !editor.isHidden()) {
                    editor.setContent(response.data);
                } else {
                    // Fallback for the Text tab
                    $('#content').val(response.data);
                }
            } else {
                alert('Failed to generate description: ' + (response.data || 'Unknown error'));
            }
        }).fail(function(xhr, status, error) {
            alert('AJAX error: ' + error);
        }).always(function() {
            // Reset button state 
            $button.prop('disabled', false);
        });
    });
});