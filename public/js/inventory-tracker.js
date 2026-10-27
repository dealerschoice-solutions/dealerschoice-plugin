(function($) {
    $(document).ready(function() {
        if (typeof dc_settings === 'undefined' || !dc_settings.popup_id) return;

        var viewLimit = parseInt(dc_settings.view_limit, 10);
        if (!viewLimit) return;

        // The last path segment is the boat slug (year-make-model-stocknumber).
        var pathSegments = window.location.pathname.split('/').filter(Boolean);
        var boatId = pathSegments[pathSegments.length - 1];
        if (!boatId) return;

        // Per-boat view counts stored in localStorage.
        var boatLog = {};
        try {
            boatLog = JSON.parse(localStorage.getItem('boat_view_tracker')) || {};
        } catch (e) {
            boatLog = {};
        }

        boatLog[boatId] = (boatLog[boatId] || 0) + 1;
        try {
            localStorage.setItem('boat_view_tracker', JSON.stringify(boatLog));
        } catch (e) {
            return;
        }

        // Open the Popup Maker CTA on every view once the limit is reached.
        if (boatLog[boatId] >= viewLimit) {
            if (typeof PUM !== 'undefined') {
                setTimeout(function() {
                    PUM.open(dc_settings.popup_id);
                }, 1500);
            }
        }
    });
})(jQuery);
