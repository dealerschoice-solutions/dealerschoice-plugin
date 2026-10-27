(function($) {
    'use strict';

    $(document).ready(function() {
        // Location selector dialog: stores the preferred location and notifies the inventory filters.

        // Default toggle text, shown when "All Locations" is selected.
        const initialBtnText = $('#location-selector-toggle > span.location-name').text();

        const preferredLocation = localStorage.getItem('dc_preferred_location') || 'all';
        const preferredLocationName = localStorage.getItem('dc_preferred_location_name') || initialBtnText;

        // Restore the saved location's active button and toggle text.
        $('.dc-location-btn').removeClass('active');
        $('.dc-location-btn[data-slug="' + preferredLocation + '"]').addClass('active');
        $('#location-selector-toggle > span.location-name').text(preferredLocationName);

        // Handle selecting a location from the header dialog
        $(document).on('click', '.dc-location-btn', function(e) {
            e.preventDefault();
            const $btn = $(this);
            const slug = $btn.data('slug');
            const name = $btn.data('name');

            localStorage.setItem('dc_preferred_location', slug);
            localStorage.setItem('dc_preferred_location_name', name);

            $('.dc-location-btn').removeClass('active');
            $btn.addClass('active');

            const btnText = slug === 'all' ? initialBtnText : name;
            $('#location-selector-toggle > span.location-name').text(btnText);

            // Notify the inventory listing of the new location.
            $(document).trigger('dc_location_changed', [slug]);

            // Close the dialog after a short delay so the selection is visible.
            setTimeout(function() {
                $('#location-selector').slideUp();
                $('.location-dropdown-indicator i').toggleClass('fa-angle-down fa-angle-up');
            }, 600); 
        });

        // Open/close the theme-provided #location-selector dialog (typically containing [dealerschoice_location_list]).
        if($('#location-selector').length){
            $('#location-selector').hide();
            $('#location-selector-toggle').click(function(){
                $('#location-selector').slideToggle();
                $('.location-dropdown-indicator i').toggleClass('fa-angle-down fa-angle-up');
            });
            $('#close-location-selector').click(function(){
                $('#location-selector').slideUp();
                $('.location-dropdown-indicator i').removeClass('fa-angle-up').addClass('fa-angle-down');
            });
        }
    });

})(jQuery);