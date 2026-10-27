jQuery(document).ready(function($) {
    // Requests a generated description and inserts it into the main content editor.
    $('#dc-generate-description-button').on('click', function() {
        var $button = $(this);
        $button.prop('disabled', true);
        
        var data = {
            'action': 'dc_generate_boat_description',
            'post_id': dc_generate_description.post_id,
            'nonce': dc_generate_description.nonce
        };

        $.post(dc_generate_description.ajaxURL, data, function(response) {
            if (response.success) {
                var editor = tinymce.get('content');

                // Visual tab uses TinyMCE; Text tab uses the textarea.
                if (editor && !editor.isHidden()) {
                    editor.setContent(response.data);
                } else {
                    $('#content').val(response.data);
                }
            } else {
                alert('Failed to generate description: ' + (response.data || 'Unknown error'));
            }
        }).fail(function(xhr, status, error) {
            alert('AJAX error: ' + error);
        }).always(function() {
            $button.prop('disabled', false);
        });
    });
});