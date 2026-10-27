/**
 * DealersChoice favorites: stores favorite boat IDs in localStorage and records add/remove analytics.
 */
(function($) {
    'use strict';

    var FAVORITES_KEY = 'dealerschoice_favorites';

    /**
     * Reads the favorites list from localStorage.
     *
     * @returns {string[]} Favorite boat IDs, or an empty array when missing or invalid.
     */
    function getFavorites() {
        var favs = localStorage.getItem(FAVORITES_KEY);
        if (!favs) return [];
        try {
            return JSON.parse(favs);
        } catch (e) {
            return [];
        }
    }

    /**
     * Writes the favorites list to localStorage.
     *
     * @param {string[]} favs Favorite boat IDs.
     */
    function setFavorites(favs) {
        localStorage.setItem(FAVORITES_KEY, JSON.stringify(favs));
    }

    /**
     * Adds the boat to favorites, or removes it if already present.
     *
     * @param {string} boatId Boat post ID.
     * @returns {boolean} True when the boat is a favorite after toggling.
     */
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

    /**
     * Checks whether a boat is in the favorites list.
     *
     * @param {string} boatId Boat post ID.
     * @returns {boolean} True when the boat is a favorite.
     */
    function isFavorite(boatId) {
        return getFavorites().indexOf(boatId) !== -1;
    }

    /**
     * Sets a favorite button's icon, active class and label for its state.
     *
     * @param {jQuery}  $btn  Favorite button.
     * @param {boolean} isFav Whether the boat is a favorite.
     */
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


    /**
     * Syncs every favorite button in the context with stored favorites.
     *
     * @param {Element|string} [context] Container to search. Defaults to the document.
     */
    function initFavoriteButtons(context) {
        var $context = context ? $(context) : $(document);
        $context.find('.dc-favorite-btn').each(function() {
            var $btn = $(this);
            var boatId = $btn.data('boat-id').toString();
            updateButton($btn, isFavorite(boatId));
        });
    }

    $(document).ready(function() {
        initFavoriteButtons();
    });

    // Toggle a favorite and record the analytics event.
    $(document).on('click', '.dc-favorite-btn', function(e) {
        var $btn = $(this);
        var boatId = $btn.data('boat-id').toString();
        var nowFav = toggleFavorite(boatId);
        updateButton($btn, nowFav);

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

    // Public API for the favorites page and AJAX-rendered results.
    window.DealersChoiceFavorites = {
        getFavorites: getFavorites,
        setFavorites: setFavorites,
        isFavorite: isFavorite,
        initFavoriteButtons: initFavoriteButtons
    };
})(jQuery);
