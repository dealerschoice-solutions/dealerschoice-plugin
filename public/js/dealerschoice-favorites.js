/**
 * DealersChoice Favorites JS
 * Handles add/remove favorites using localStorage.
 */
(function($) {
    'use strict';

    // Key for localStorage
    var FAVORITES_KEY = 'dealerschoice_favorites';

    // Get favorites array from localStorage
    function getFavorites() {
        var favs = localStorage.getItem(FAVORITES_KEY);
        if (!favs) return [];
        try {
            return JSON.parse(favs);
        } catch (e) {
            return [];
        }
    }

    // Save favorites array to localStorage
    function setFavorites(favs) {
        localStorage.setItem(FAVORITES_KEY, JSON.stringify(favs));
    }

    // Add or remove a boat ID
    function toggleFavorite(boatId) {
        var favs = getFavorites();
        var idx = favs.indexOf(boatId);
        if (idx === -1) {
            favs.push(boatId);
        } else {
            favs.splice(idx, 1);
        }
        setFavorites(favs);
        return favs.indexOf(boatId) !== -1;
    }

    // Check if a boat is a favorite
    function isFavorite(boatId) {
        return getFavorites().indexOf(boatId) !== -1;
    }

    // Update button UI
    function updateButton($btn, isFav) {
        var $icon = $btn.find('.dc-favorite-icon');
        var $label = $btn.find('.dc-favorite-label');
        if (isFav) {
            $icon.html('<i class="fa-solid fa-heart-circle-minus"></i>');
            $btn.addClass('active');
            var boatTitle = $label.data('boat-title') || '';
            $label.text('Remove ' + boatTitle + ' from Favorites');
        } else {
            $icon.html('<i class="fa-light fa-heart-circle-plus"></i>');
            $btn.removeClass('active');
            var boatTitle = $label.data('boat-title') || '';
            $label.text('Add ' + boatTitle + ' to Favorites');
        }
    }


    // Function to (re)initialize all favorite buttons
    function initFavoriteButtons(context) {
        var $context = context ? $(context) : $(document);
        $context.find('.dc-favorite-btn').each(function() {
            var $btn = $(this);
            var boatId = $btn.data('boat-id').toString();
            updateButton($btn, isFavorite(boatId));
        });
    }

    // Initial setup on page load
    $(document).ready(function() {
        // Init on page load
        initFavoriteButtons();
    });

    // Event delegation for dynamically loaded buttons
    $(document).on('click', '.dc-favorite-btn', function(e) {
        var $btn = $(this);
        var boatId = $btn.data('boat-id').toString();
        var nowFav = toggleFavorite(boatId);
        updateButton($btn, nowFav);

        // Fire-and-forget analytics event — does not affect UI if it fails
        if (typeof dealersChoicePublic !== 'undefined' && dealersChoicePublic.ajaxUrl) {
            $.ajax({
                url: dealersChoicePublic.ajaxUrl,
                type: 'POST',
                data: {
                    action:          'record_favorite',
                    nonce:           dealersChoicePublic.recordFavoriteNonce,
                    boat_id:         boatId,
                    favorite_action: nowFav ? 'add' : 'remove'
                }
            });
        }
    });

    // Expose for other scripts (e.g., for shortcode page, or after AJAX load)
    window.DealersChoiceFavorites = {
        getFavorites: getFavorites,
        setFavorites: setFavorites,
        isFavorite: isFavorite,
        initFavoriteButtons: initFavoriteButtons
    };
})(jQuery);
