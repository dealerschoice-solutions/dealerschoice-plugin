/**
 * This file contains the javascript functionality for revealing boat pricing on the inventory page.
 */

var popupBoatID = '';
var currentMessage = '';
var currentStatus = ''; // Short status stored in the GF hidden field: 'verified', 'out_of_area', or 'unverified'.
var gfFormSnapshot = null;

jQuery(document).ready(function($) {
    const settings = window.revealPriceSettings || {};
    const popupId = settings.popupId || '';
    const gravityFormId = settings.gravityFormId || '';

    console.log('Reveal Price Script Loaded. Settings:', settings);

    $('body').on('click', 'button.boat-price-popup-button', function(e){
        e.preventDefault();
        var boatID = $(this).data('inventory-id');
        popupBoatID = boatID;
        currentStatus = '';
        currentMessage = '';
        const allowedZips = settings.allowedZips || [];

        console.log('Button clicked. Boat ID:', boatID, 'Popup ID:', popupId);

        if (!popupId) {
            console.error('Popup ID is not set.');
            return;
        }

        // If zip codes are configured, show the location request message immediately.
        // The popup <div> is already in the DOM (just hidden), so we can pre-populate
        // the message container before PUM.open() starts its animation.
        if (allowedZips.length > 0) {
            currentMessage = settings.locationRequestMessage || 'In order to comply with manufacturer pricing policies, we need to verify your location. Please allow location access to continue.';
            $('#popmake-' + popupId).find('.dc-reveal-price-message').html(currentMessage);
        } else {
            // No zip codes configured ⇒ clean form, no messaging.
            currentMessage = '';
            currentStatus = 'no_restriction';
            $('#popmake-' + popupId).find('.dc-reveal-price-message').html('');
        }

        if (gravityFormId && gfFormSnapshot) {
            var $popupDom = $('#popmake-' + popupId);
            if ($popupDom.length && !$popupDom.find('#gform_' + gravityFormId).length) {
                $popupDom.find('#gform_confirmation_wrapper_' + gravityFormId).replaceWith(gfFormSnapshot);
            }
        }

        PUM.open(popupId);
        console.log('PUM.open called for popup ID:', popupId);

        // Get pricing; only update the message if zip codes are configured.
        getPricing(boatID).then(function(result){
            if (allowedZips.length === 0) {
                return; // No zip restrictions — no message or status needed.
            }
            if(result.canShow){
                currentMessage = settings.locationVerifiedMessage || 'Your location has been verified. Please fill out the form to reveal the price.';
                currentStatus = 'Verified in sales area';
            } else if (result.status === 'out_of_area') {
                currentMessage = settings.locationFailedMessage || 'We\'re sorry, but we were unable to verify that you\'re currently in our boating territory. A salesperson will be in touch with you to discuss pricing. Please fill out the form to continue.';
                currentStatus = 'Out of sales area';
            } else {
                // no_geolocation or unverified (user denied / timeout / API error).
                currentMessage = settings.locationDeniedMessage || 'Geolocation is not supported by your browser. Please contact us for pricing information.';
                currentStatus = result.status === 'no_geolocation' ? 'No geolocation' : 'Unverified';
            }
            // Update the hidden field now that we have a definitive status.
            // gform_post_render fired before geolocation completed, so we set it here too.
            jQuery('[data-dc-field="priceStatus"]').val(currentStatus);
            console.log('Geolocation finished. New message:', currentMessage);
            
            var openPopup = $('#popmake-'+popupId);
            if(openPopup.length) {
                var messageContainer = openPopup.find('.dc-reveal-price-message');
                if(messageContainer.length){
                    messageContainer.html(currentMessage);
                    console.log('Message container updated after geolocation.');
                } else {
                    console.error('Message container (.dc-reveal-price-message) not found inside the open popup.');
                }
            } else {
                console.error('No open popup found to update the message.');
            }
        });
    });

    // pumAfterOpen: safety net in case the popup animation starts before our pre-population runs.
    // Only injects a message when zip codes are configured (currentMessage will be empty otherwise).
    $(document).on('pumAfterOpen', function(e){
        if (!currentMessage) { return; }
        var $popup = $(e.target);
        if ($popup.attr('id') !== 'popmake-' + popupId) { return; }
        console.log('pumAfterOpen event triggered for popup:', popupId);
        var messageContainer = $popup.find('.dc-reveal-price-message');
        if(messageContainer.length){
            messageContainer.html(currentMessage);
            console.log('Message container updated on pumAfterOpen.');
        }
    });

    /**
     * When we load the page, we want to check if the user has already submitted the form to show price.
     * If they have already submitted the reveal price form, we want to show the price. 
     */
    function dcRevealPricesIfUnlocked() {
        if (dcGetCookie('dc_price_unlocked') !== '1') { return; }
        console.log('dc_price_unlocked cookie found. Auto-revealing prices.');
        $('button.boat-price-popup-button').each(function(){
            var boatID = $(this).data('inventory-id');
            var pricingContainer = $(this).parent();
            pricingContainer.html('Retrieving Price...');
            revealPrice(boatID).then(function(price){
                pricingContainer.html(price.formatted_price);
            });
        });
    }

    // Run on initial ready (covers single boat pages and server-rendered buttons).
    dcRevealPricesIfUnlocked();

    // Re-run whenever the inventory list is refreshed via AJAX (covers inventory listing page).
    $(document).on('dc:inventoryRendered', function() {
        dcRevealPricesIfUnlocked();
    });
});

/**
 * Gravity Forms Functionality
 * When the form renders inside the popup, populate the known hidden fields using the
 * data-dc-field attributes stamped by the PHP gform_field_content filter.
 */
jQuery(document).on('gform_post_render', function(event, form_id, current_page){
    const settings = window.revealPriceSettings || {};
    const revealPriceFormID = settings.gravityFormId || '';

    if(form_id != revealPriceFormID) { return; }

    if (!gfFormSnapshot) {
        var $wrapper = jQuery('#gform_wrapper_' + form_id);
        if ($wrapper.length) {
            gfFormSnapshot = $wrapper[0].outerHTML;
        }
    }

    console.log('gform_post_render fired for reveal price form. Populating hidden fields. Boat ID:', popupBoatID, 'Status:', currentMessage);

    jQuery('[data-dc-field="inventoryID"]').val(popupBoatID);
    // Only populate priceStatus when zip-based verification was performed.
    if (currentStatus) {
        jQuery('[data-dc-field="priceStatus"]').val(currentStatus);
    }
    // priceValue is left empty here — PHP gform_after_submission fills it from post meta.
});

/**
 * Gravity Forms Functionality
 * When the confirmation is loaded, make an AJAX call to fetch and display the price
 * on screen in place of the button. The price is also written to the entry server-side.
 */
jQuery(document).on('gform_confirmation_loaded', function(e, form_id) {
    const settings = window.revealPriceSettings || {};
    const revealPriceFormID = settings.gravityFormId || '';

    if(form_id != revealPriceFormID) { return; }

    console.log('Gravity Form confirmation loaded. Status:', currentStatus, 'Boat ID:', popupBoatID);
    var boatID = popupBoatID;
    var displayMsg;
    var popupIdForClose = settings.popupId || '';

    if (currentStatus === 'Verified in sales area' || currentStatus === 'no_restriction') {
        // Set cookie, then trigger a full-page reveal BEFORE closing the popup.
        // Triggering dc:inventoryRendered calls dcRevealPricesIfUnlocked(), which
        // synchronously replaces all "Unlock Price" buttons with "Retrieving Price..."
        // so that when PUM.close() fires and the browser tries to return focus to the
        // clicked button, the element is already gone — preventing the mobile scroll jump.
        // All boats on the page update, not just the clicked one.
        dcSetCookie('dc_price_unlocked', '1', 30);
        console.log('dc_price_unlocked cookie set.');
        jQuery(document).trigger('dc:inventoryRendered');
        if (popupIdForClose && typeof PUM !== 'undefined') { PUM.close(popupIdForClose); }
    } else if (currentStatus === 'Out of sales area') {
        var priceContainer = jQuery('.boat-price-popup-button[data-inventory-id="' + boatID + '"]').parent();
        displayMsg = settings.locationFailedMessage || 'We\'re sorry, but we were unable to verify that you\'re currently in our boating territory. Please call us to verify your location, and we would be delighted to provide you with quotes over the phone.';
        jQuery(priceContainer).html('<p>' + displayMsg + '</p>');
        if (popupIdForClose && typeof PUM !== 'undefined') { setTimeout(function(){ PUM.close(popupIdForClose); }, 400); }
    } else {
        // no_geolocation or unverified (denied / timeout / API error).
        var priceContainer = jQuery('.boat-price-popup-button[data-inventory-id="' + boatID + '"]').parent();
        displayMsg = settings.locationDeniedMessage || 'Geolocation is not supported by your browser. Please contact us for pricing information.';
        jQuery(priceContainer).html('<p>' + displayMsg + '</p>');
        if (popupIdForClose && typeof PUM !== 'undefined') { setTimeout(function(){ PUM.close(popupIdForClose); }, 400); }
    }
});

function getPricing(boatID) {
    return new Promise(function(fulfill) {
        const settings = window.revealPriceSettings || {};
        const allowedZips = settings.allowedZips || [];

        // No zip code restrictions — skip geolocation entirely.
        if (allowedZips.length === 0) {
            fulfill({ canShow: true, status: 'no_restriction' });
            return;
        }

        if (!navigator.geolocation) {
            fulfill({ canShow: false, status: 'no_geolocation' });
            return;
        }

        navigator.geolocation.getCurrentPosition(
            function(position) {
                var lat = position.coords.latitude;
                var lon = position.coords.longitude;
                fetch('https://api.bigdatacloud.net/data/reverse-geocode-client?latitude=' + lat + '&longitude=' + lon + '&localityLanguage=en')
                    .then(function(response) { return response.json(); })
                    .then(function(data) {
                        var inArea = canShowPricing(data.postcode || '');
                        fulfill({ canShow: inArea, status: inArea ? 'verified' : 'out_of_area' });
                    })
                    .catch(function() {
                        fulfill({ canShow: false, status: 'unverified' });
                    });
            },
            function() {
                // User denied location access or geolocation timed out.
                fulfill({ canShow: false, status: 'unverified' });
            },
            { enableHighAccuracy: true, timeout: 10000 }
        );
    });
}

function canShowPricing(zip) {
    const settings = window.revealPriceSettings || {};
    const allowedZips = settings.allowedZips || [];
    if (zip.length >= 5 && allowedZips.indexOf(zip) > -1) {
        return true;
    }
    return false;
}

function revealPrice(boatID = ''){
    return new Promise(function(fulfill, reject){
        const settings = window.revealPriceSettings || {};
        jQuery.ajax({
            url: settings.ajaxUrl,
            type: 'GET',
            data: { 
                'action': 'reveal_price', 
                'inventoryID': boatID,
                'nonce': settings.nonce
            },
            dataType: 'json'
        })
        .done(function(response){
            if(response.success){
                fulfill({
                    formatted_price: '<span><strong>Price: ' + response.data.price + '</strong></span>',
                    raw_price: response.data.price
                });
            } else {
                var unavailableMsg = settings.priceUnavailableMessage || 'Price unavailable. Please contact us.';
                fulfill({
                    formatted_price: '<strong>' + unavailableMsg + '</strong>',
                    raw_price: 'N/A'
                });
            }
        })
        .fail(function(){
            var unavailableMsg = settings.priceUnavailableMessage || 'Price unavailable. Please contact us.';
            fulfill({
                formatted_price: '<strong>' + unavailableMsg + '</strong>',
                raw_price: 'Error'
            });
        });
    });
}

function dcSetCookie(name, value, days) {
    var expires = '';
    if (days) {
        var d = new Date();
        d.setTime(d.getTime() + (days * 24 * 60 * 60 * 1000));
        expires = '; expires=' + d.toUTCString();
    }
    document.cookie = name + '=' + (value || '') + expires + '; path=/';
}

function dcGetCookie(name) {
    var nameEQ = name + '=';
    var ca = document.cookie.split(';');
    for (var i = 0; i < ca.length; i++) {
        var c = ca[i];
        while (c.charAt(0) === ' ') { c = c.substring(1, c.length); }
        if (c.indexOf(nameEQ) === 0) { return c.substring(nameEQ.length, c.length); }
    }
    return null;
}