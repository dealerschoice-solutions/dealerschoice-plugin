/**
 * Reveal Price: opens the Popup Maker lead form, verifies location by ZIP, and reveals boat prices.
 */

var popupBoatID = '';
var popupBoatName = '';
var popupStockNumber = '';
var currentMessage = '';
var currentStatus = ''; // Location status written to the priceStatus hidden field.
var gfFormSnapshot = null;
// Button that opened the popup.
var $clickedPriceButton = null;

jQuery(document).ready(function($) {
    const settings = window.revealPriceSettings || {};
    const popupId = settings.popupId || '';
    const gravityFormId = settings.gravityFormId || '';


    $('body').on('click', 'button.boat-price-popup-button', function(e){
        e.preventDefault();
        var $btn = $(this);
        var boatID = $btn.data('inventory-id');

        // Already unlocked: reveal without the form.
        if (dcGetCookie('dc_price_unlocked') === '1') {
            if (dcGetRevealScope() === 'individual') {
                dcRevealUnit($btn);
            } else {
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

        // Pre-populate the popup message before it opens.
        if (allowedZips.length > 0) {
            currentMessage = settings.locationRequestMessage || 'In order to comply with manufacturer pricing policies, we need to verify your location. Please allow location access to continue.';
            $('#popmake-' + popupId).find('.dc-reveal-price-message').html(currentMessage);
        } else {
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

        // Verify location, then update the message and status when ZIP codes are configured.
        getPricing(boatID).then(function(result){
            if (allowedZips.length === 0) {
                return;
            }
            if(result.canShow){
                currentMessage = settings.locationVerifiedMessage || 'Your location has been verified. Please fill out the form to reveal the price.';
                currentStatus = 'Verified in sales area';
            } else if (result.status === 'out_of_area') {
                currentMessage = settings.locationFailedMessage || 'We\'re sorry, but we were unable to verify that you\'re currently in our boating territory. A salesperson will be in touch with you to discuss pricing. Please fill out the form to continue.';
                currentStatus = 'Out of sales area';
            } else {
                // no_geolocation or unverified (denied, timeout, or API error).
                currentMessage = settings.locationDeniedMessage || 'Geolocation is not supported by your browser. Please contact us for pricing information.';
                currentStatus = result.status === 'no_geolocation' ? 'No geolocation' : 'Unverified';
            }
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

    // Write the current message into the popup after it opens.
    $(document).on('pumAfterOpen', function(e){
        if (!currentMessage) { return; }
        var $popup = $(e.target);
        if ($popup.attr('id') !== 'popmake-' + popupId) { return; }
        var messageContainer = $popup.find('.dc-reveal-price-message');
        if(messageContainer.length){
            messageContainer.html(currentMessage);
        }
    });

    // Clear the clicked button reference when the popup closes.
    $(document).on('pumAfterClose', function(e){
        if ($(e.target).attr('id') !== 'popmake-' + popupId) { return; }
        $clickedPriceButton = null;
    });

    /**
     * Reveals every price on the page when the visitor is unlocked and the scope is 'all'.
     */
    function dcRevealPricesIfUnlocked() {
        if (dcGetRevealScope() === 'individual') { return; }
        if (dcGetCookie('dc_price_unlocked') !== '1') { return; }
        dcRevealAllUnits();
    }

    dcRevealPricesIfUnlocked();

    // Re-run after the inventory list re-renders.
    $(document).on('dc:inventoryRendered', function() {
        dcRevealPricesIfUnlocked();
    });
});

/**
 * Populates the Reveal Price form's hidden fields (identified by data-dc-field) when it renders.
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
    if (currentStatus) {
        jQuery('[data-dc-field="priceStatus"]').val(currentStatus);
    }
});

/**
 * Handles the Reveal Price form confirmation.
 *
 * Sets the unlock cookie and reveals the price when the location is verified or
 * unrestricted; otherwise shows the location message in place of the button.
 */
jQuery(document).on('gform_confirmation_loaded', function(e, form_id) {
    const settings = window.revealPriceSettings || {};
    const revealPriceFormID = settings.gravityFormId || '';

    if(form_id != revealPriceFormID) { return; }

    var boatID = popupBoatID;
    var displayMsg;
    var popupIdForClose = settings.popupId || '';

    if (currentStatus === 'Verified in sales area' || currentStatus === 'no_restriction') {
        // Reveal before closing the popup.
        dcSetCookie('dc_price_unlocked', '1', 30);
        if (dcGetRevealScope() === 'individual') {
            dcRevealUnit($clickedPriceButton);
        } else {
            // dc:inventoryRendered runs dcRevealPricesIfUnlocked().
            jQuery(document).trigger('dc:inventoryRendered');
        }
        if (popupIdForClose && typeof PUM !== 'undefined') { PUM.close(popupIdForClose); }
    } else if (currentStatus === 'Out of sales area') {
        var priceContainer = dcResolvePriceContainer($clickedPriceButton, boatID);
        displayMsg = settings.locationFailedMessage || 'We\'re sorry, but we were unable to verify that you\'re currently in our boating territory. Please call us to verify your location, and we would be delighted to provide you with quotes over the phone.';
        if (priceContainer.length) { priceContainer.html('<p>' + displayMsg + '</p>'); }
        if (popupIdForClose && typeof PUM !== 'undefined') { setTimeout(function(){ PUM.close(popupIdForClose); }, 400); }
    } else {
        // no_geolocation or unverified (denied, timeout, or API error).
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
 * Returns the reveal scope.
 *
 * @returns {string} 'individual' to reveal only the clicked unit, otherwise 'all'.
 */
function dcGetRevealScope() {
    var settings = window.revealPriceSettings || {};
    return settings.revealScope === 'individual' ? 'individual' : 'all';
}

/**
 * Resolves the price container for a unit.
 *
 * Uses the button's closest .boat-price (or its parent) when the button is still in
 * the document, otherwise looks up every button for the boat ID.
 *
 * @param {jQuery|null}   $btn   Clicked price button.
 * @param {number|string} boatID Inventory ID.
 * @returns {jQuery} Price container(s), or an empty set.
 */
function dcResolvePriceContainer($btn, boatID) {
    if ($btn && $btn.length && jQuery.contains(document, $btn[0])) {
        var $closest = $btn.closest('.boat-price');
        return $closest.length ? $closest : $btn.parent();
    }
    if (!boatID) { return jQuery(); }
    // Matches every instance of the boat on the page.
    var $live = jQuery('.boat-price-popup-button[data-inventory-id="' + boatID + '"]');
    if (!$live.length) { return jQuery(); }
    var $liveClosest = $live.closest('.boat-price');
    return $liveClosest.length ? $liveClosest : $live.parent();
}

/**
 * Replaces one unit's button with its price.
 *
 * Writes a "Retrieving Price..." placeholder synchronously, then the fetched price.
 *
 * @param {jQuery} $btn Price button.
 */
function dcRevealUnit($btn) {
    if (!$btn || !$btn.length) { return; }
    var boatID = $btn.data('inventory-id');
    var $container = dcResolvePriceContainer($btn, boatID);
    if (!$container.length) { return; }
    $container.html('Retrieving Price...');
    revealPrice(boatID).then(function(price){
        // Re-resolve the container if the grid re-rendered during the request.
        var $target = jQuery.contains(document, $container[0])
            ? $container
            : dcResolvePriceContainer(null, boatID);
        if ($target.length) { $target.html(price.formatted_price); }
    });
}

/**
 * Reveals every price on the page.
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