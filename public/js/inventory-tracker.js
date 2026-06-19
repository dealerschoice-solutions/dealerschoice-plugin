(function($) {
    $(document).ready(function() {
        // Ensure the Popup ID exists before running logic
        if (!dc_settings.popup_id || dc_settings.popup_id == 0) return;

        // 1. Extract Stock Number from the URL
        var pathSegments = window.location.pathname.split('/').filter(Boolean);
        var stockNumber = pathSegments[pathSegments.length - 1];

        // 2. Persistent Storage Logic
        var boatLog = JSON.parse(localStorage.getItem('boat_view_tracker')) || {};

        // 3. Increment Count
        boatLog[stockNumber] = (boatLog[stockNumber] || 0) + 1;
        localStorage.setItem('boat_view_tracker', JSON.stringify(boatLog));

        // 4. Trigger Check
        if (boatLog[stockNumber] === parseInt(dc_settings.view_limit)) {
            // Check if Popup Maker (PUM) is ready
            if (typeof PUM !== 'undefined') {
                setTimeout(function() {
                    PUM.open(dc_settings.popup_id);
                    console.log('Triggering Special Offer for Stock #: ' + stockNumber);
                }, 1500);
            }
        }
    });
})(jQuery);