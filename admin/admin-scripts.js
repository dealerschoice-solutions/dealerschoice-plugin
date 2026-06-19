/**
 * Admin Scripts for DealersChoice Plugin
 * 
 * JavaScript functionality for admin interface including AJAX sync trigger,
 * status updates, and user feedback.
 * 
 * @package DealersChoice
 * @subpackage Admin
 * @since 1.0.0
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        // Ensure dealersChoiceAdmin is defined before proceeding
        if (typeof dealersChoiceAdmin === 'undefined') {
            // console.error('dealersChoiceAdmin is not defined. AJAX functionality will not work.');
            return;
        }
        
        // Handle Dealers Choice Inventory Sync button click
        if($('#dealers-choice-sync-btn').length) {
            $('#dealers-choice-sync-btn').on('click', function() {
                var $btn = $(this);
                var $status = $('#sync-status');
                var $forceCheckbox = $('#force-sync-checkbox');
                var forceSync = $forceCheckbox.is(':checked');
                
                // Disable button and show loading
                $btn.prop('disabled', true);
                $status.removeClass('success error').addClass('loading');
                
                var loadingMessage = forceSync ? 
                    '<span class="dashicons dashicons-update-alt rotating"></span> Force syncing inventory...' :
                    '<span class="dashicons dashicons-update-alt rotating"></span> Syncing inventory...';
                
                $status.html(loadingMessage);
                
                // Make AJAX request
                $.ajax({
                    url: dealersChoiceAdmin.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'dealers_choice_trigger_sync',
                        nonce: dealersChoiceAdmin.syncNonce,
                        force_sync: forceSync ? 'true' : 'false'
                    },
                    success: function(response) {
                        $btn.prop('disabled', false);
                        
                        if (response.success) {
                            $status.removeClass('loading').addClass('success');
                            var message = response.data.message;
                            if (response.data.forced) {
                                message += ' (Force sync completed)';
                            }
                            $status.html('<span class="dashicons dashicons-yes"></span> ' + message);
                            
                            // Reload page after 2 seconds to update last sync time
                            setTimeout(function() {
                                location.reload();
                            }, 2000);
                        } else {
                            $status.removeClass('loading').addClass('error');
                            $status.html('<span class="dashicons dashicons-no"></span> ' + response.data.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        $btn.prop('disabled', false);
                        $status.removeClass('loading').addClass('error');
                        $status.html('<span class="dashicons dashicons-no"></span> An error occurred. Please try again.');
                        
                        // Log error to console for debugging
                        console.error('Dealers Choice Sync Error:', {
                            status: status,
                            error: error,
                            response: xhr.responseText
                        });
                    }
                });
            });
        }

        // Show/hide Popup Form ID row based on Always Show Price toggle
        if($('#dealers_choice_always_show_price').length) {
            var $priceToggle = $('#dealers_choice_always_show_price');
            var $revealPriceRows = $('#popup-form-id-row, #gravity-form-id-row, #allowed-zips-row, #location-request-message-row, #location-verified-message-row, #location-failed-message-row, #location-denied-message-row, #price-unavailable-message-row');

            function toggleRevealPriceFields() {
                if ($priceToggle.is(':checked')) {
                    $revealPriceRows.hide();
                } else {
                    $revealPriceRows.show();
                }
            }
            $priceToggle.on('change', toggleRevealPriceFields);
            toggleRevealPriceFields();
        }
    });

})(jQuery);
