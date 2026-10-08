/**
 * This file contains the javascript functionality for revealing boat pricing on the inventory page.
 */

var popupBoatID = '';
var popupBoatName = '';
var popupStockNumber = '';
var currentMessage = '';
var currentStatus = ''; // Short status stored in the GF hidden field: 'verified', 'out_of_area', or 'unverified'.
var gfFormSnapshot = null;
// jQuery reference to the button that opened the popup. Preferred over re-querying by
// data-inventory-id, which can match zero elements (once the button has been replaced)
// or several (if favorites and inventory cards for the same boat share a page).
var $clickedPriceButton = null;

jQuery(document).ready(function($) {
    const settings = window.revealPriceSettings || {};
    const popupId = settings.popupId || '';
    const gravityFormId = settings.gravityFormId || '';


    $('body').on('click', 'button.boat-price-popup-button', function(e){
        e.preventDefault();
        var $btn = $(this);
        var boatID = $btn.data('inventory-id');

        // Already unlocked: skip the form entirely. The visitor filled it out once, so we
        // do not ask again — but they still have to click to see a price, which is the
        // point of 'individual' scope. Location is trusted from that first verification.
        if (dcGetCookie('dc_price_unlocked') === '1') {
            if (dcGetRevealScope() === 'individual') {
                dcRevealUnit($btn);
            } else {
                // 'all' scope safety net: normally dcRevealPricesIfUnlocked() has already
                // replaced these buttons on ready, so this only fires if it did not.
                dcRevealAllUnits();
            }
            return;
        }

        $clickedPriceButton = $btn;
        popupBoatID = boatID;
        popupBoatName = $btn.data('boat-name') || '';
        popupStockNumber = $btn.data('stock-number') || '';
        currentStatus = '';
        currentMessage = '';
        const allowedZips = settings.allowedZips || [];


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
            
            var openPopup = $('#popmake-'+popupId);
            if(openPopup.length) {
                var messageContainer = openPopup.find('.dc-reveal-price-message');
                if(messageContainer.length){
                    messageContainer.html(currentMessage);
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
        var messageContainer = $popup.find('.dc-reveal-price-message');
        if(messageContainer.length){
            messageContainer.html(currentMessage);
        }
    });

    // Forget the clicked button once the popup closes, so a stray confirmation cannot
    // write a price into a stale spot.
    $(document).on('pumAfterClose', function(e){
        if ($(e.target).attr('id') !== 'popmake-' + popupId) { return; }
        $clickedPriceButton = null;
    });

    /**
     * When we load the page, we want to check if the user has already submitted the form to show price.
     * If they have already submitted the reveal price form, we want to show the price.
     *
     * Only applies to 'all' scope. In 'individual' scope nothing is ever revealed
     * automatically — the visitor has to click each unit, though the click is instant
     * once they are unlocked (see the click handler above).
     */
    function dcRevealPricesIfUnlocked() {
        if (dcGetRevealScope() === 'individual') { return; }
        if (dcGetCookie('dc_price_unlocked') !== '1') { return; }
        dcRevealAllUnits();
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


    jQuery('[data-dc-field="inventoryID"]').val(popupBoatID);
    jQuery('[data-dc-field="boatName"]').val(popupBoatName);
    jQuery('[data-dc-field="stockNumber"]').val(popupStockNumber);
    // Only populate priceStatus when zip-based verification was performed.
    if (currentStatus) {
        jQuery('[data-dc-field="priceStatus"]').val(currentStatus);
    }
    // priceValue is intentionally never set here. PHP (gform_pre_submission) fills it server-side.
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

    var boatID = popupBoatID;
    var displayMsg;
    var popupIdForClose = settings.popupId || '';

    if (currentStatus === 'Verified in sales area' || currentStatus === 'no_restriction') {
        // Set the cookie, then reveal BEFORE closing the popup.
        //
        // Invariant: the clicked button must be out of the DOM before PUM.close() runs.
        // Otherwise the browser returns focus to it as the popup tears down and the page
        // jumps on mobile. Both branches below satisfy this because the reveal writes
        // "Retrieving Price..." over the button synchronously, before any AJAX.
        dcSetCookie('dc_price_unlocked', '1', 30);
        if (dcGetRevealScope() === 'individual') {
            // Only the unit they clicked. Other units stay locked, but from now on a
            // click on any of them reveals immediately without the form.
            dcRevealUnit($clickedPriceButton);
        } else {
            // Every unit on the page. dc:inventoryRendered runs dcRevealPricesIfUnlocked().
            jQuery(document).trigger('dc:inventoryRendered');
        }
        if (popupIdForClose && typeof PUM !== 'undefined') { PUM.close(popupIdForClose); }
    } else if (currentStatus === 'Out of sales area') {
        var priceContainer = dcResolvePriceContainer($clickedPriceButton, boatID);
        displayMsg = settings.locationFailedMessage || 'We\'re sorry, but we were unable to verify that you\'re currently in our boating territory. Please call us to verify your location, and we would be delighted to provide you with quotes over the phone.';
        if (priceContainer.length) { priceContainer.html('<p>' + displayMsg + '</p>'); }
        if (popupIdForClose && typeof PUM !== 'undefined') { setTimeout(function(){ PUM.close(popupIdForClose); }, 400); }
    } else {
        // no_geolocation or unverified (denied / timeout / API error).
        var priceContainer = dcResolvePriceContainer($clickedPriceButton, boatID);
        displayMsg = settings.locationDeniedMessage || 'Geolocation is not supported by your browser. Please contact us for pricing information.';
        if (priceContainer.length) { priceContainer.html('<p>' + displayMsg + '</p>'); }
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

/**
 * 'individual' = only the clicked unit is revealed; anything else (including a missing
 * value on a site that has not saved settings since this option was added) means 'all',
 * which is the long-standing behavior.
 */
function dcGetRevealScope() {
    var settings = window.revealPriceSettings || {};
    return settings.revealScope === 'individual' ? 'individual' : 'all';
}

/**
 * Resolve the price container for a unit, preferring the live clicked button.
 *
 * Falls back to a document-wide lookup when the stashed button is no longer in the
 * document, which happens if the inventory grid re-rendered while the popup was open.
 * Without that fallback we would write the price into an orphaned node: the price would
 * silently vanish and the real button would stay on screen.
 *
 * Uses .closest('.boat-price') rather than .parent() because templates are
 * theme-overridable, so a client theme may wrap the button differently.
 */
function dcResolvePriceContainer($btn, boatID) {
    if ($btn && $btn.length && jQuery.contains(document, $btn[0])) {
        var $closest = $btn.closest('.boat-price');
        return $closest.length ? $closest : $btn.parent();
    }
    if (!boatID) { return jQuery(); }
    // Not narrowed to one match on purpose: if the same boat appears twice on a page
    // (favorites plus inventory), both instances should reveal together.
    var $live = jQuery('.boat-price-popup-button[data-inventory-id="' + boatID + '"]');
    if (!$live.length) { return jQuery(); }
    var $liveClosest = $live.closest('.boat-price');
    return $liveClosest.length ? $liveClosest : $live.parent();
}

/**
 * Replace one unit's button with its price.
 *
 * The placeholder is written synchronously, before any async work, so the button is
 * detached from the DOM by the time the caller runs PUM.close(). See the note in the
 * gform_confirmation_loaded handler for why that ordering matters on mobile.
 */
function dcRevealUnit($btn) {
    if (!$btn || !$btn.length) { return; }
    var boatID = $btn.data('inventory-id');
    var $container = dcResolvePriceContainer($btn, boatID);
    if (!$container.length) { return; }
    $container.html('Retrieving Price...');
    revealPrice(boatID).then(function(price){
        // The container we wrote the placeholder into is the target. Only re-resolve if
        // an AJAX grid re-render detached it while the request was in flight — passing
        // no button forces the document-wide lookup against the freshly rendered card.
        var $target = jQuery.contains(document, $container[0])
            ? $container
            : dcResolvePriceContainer(null, boatID);
        if ($target.length) { $target.html(price.formatted_price); }
    });
}

/**
 * Reveal every price on the page. Used by 'all' scope only.
 */
function dcRevealAllUnits() {
    jQuery('button.boat-price-popup-button').each(function(){
        dcRevealUnit(jQuery(this));
    });
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