(function($) {
    'use strict';

    $(document).ready(function() {
        /**
         * Location Selector Logic
         * Manages the location selection in the location selector dialog and syncs it with the inventory filters.
         */

        // get the initial button text to restore it back when "All Locations" is selected
        const initialBtnText = $('#location-selector-toggle > span.location-name').text();

        const preferredLocation = localStorage.getItem('dc_preferred_location') || 'all';
        const preferredLocationName = localStorage.getItem('dc_preferred_location_name') || initialBtnText;

        // Set the 'active' class on the correct button in the dialog on page load
        $('.dc-location-btn').removeClass('active');
        $('.dc-location-btn[data-slug="' + preferredLocation + '"]').addClass('active');
        $('#location-selector-toggle > span.location-name').text(preferredLocationName);

        // Handle selecting a location from the header dialog
        $(document).on('click', '.dc-location-btn', function(e) {
            e.preventDefault();
            const $btn = $(this);
            const slug = $btn.data('slug');
            const name = $btn.data('name');

            // Save to browser storage
            localStorage.setItem('dc_preferred_location', slug);
            localStorage.setItem('dc_preferred_location_name', name);

            // Update active state in the dialog immediately for visual feedback
            $('.dc-location-btn').removeClass('active');
            $btn.addClass('active');

            // Update UI Header Button text
            const btnText = slug === 'all' ? initialBtnText : name;
            $('#location-selector-toggle > span.location-name').text(btnText);

            // Trigger a global custom event so the inventory grid can catch it!
            $(document).trigger('dc_location_changed', [slug]);

            // Wait 600ms so the user sees their selection light up, then close the dialog
            setTimeout(function() {
                $('#location-selector').slideUp();
                // toggle the dropdown arrow direction
                $('.location-dropdown-indicator i').toggleClass('fa-angle-down fa-angle-up');
            }, 600); 
        });

        // Basic toggle for the dialog. The #location-selector dialog output needs to be added to the theme for this to work.
        // The dialog can use the dealerschoice_location_list shortcode to output the list of locations with the correct classes and data attributes.
        if($('#location-selector').length){
            $('#location-selector').hide();
            $('#location-selector-toggle').click(function(){
                $('#location-selector').slideToggle();
                // toggle the dropdown arrow direction
                $('.location-dropdown-indicator i').toggleClass('fa-angle-down fa-angle-up');
            });
            $('#close-location-selector').click(function(){
                $('#location-selector').slideUp();
                // reset the dropdown arrow direction
                $('.location-dropdown-indicator i').removeClass('fa-angle-up').addClass('fa-angle-down');
            });
        }
    });

})(jQuery);