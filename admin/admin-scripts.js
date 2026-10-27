/**
 * DealersChoice admin scripts: manual inventory sync and settings field toggles.
 *
 * @package DealersChoice
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        if (typeof dealersChoiceAdmin === 'undefined') {
            return;
        }
        
        // Manual sync: posts the sync request and shows the result in #sync-status.
        if($('#dealers-choice-sync-btn').length) {
            $('#dealers-choice-sync-btn').on('click', function() {
                var $btn = $(this);
                var $status = $('#sync-status');
                var $forceCheckbox = $('#force-sync-checkbox');
                var forceSync = $forceCheckbox.is(':checked');
                
                $btn.prop('disabled', true);
                $status.removeClass('success error').addClass('loading');
                
                var loadingMessage = forceSync ? 
                    '<span class="dashicons dashicons-update-alt rotating"></span> Force syncing inventory...' :
                    '<span class="dashicons dashicons-update-alt rotating"></span> Syncing inventory...';
                
                $status.html(loadingMessage);
                
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
                            
                            // Reload after 2 seconds to show the new last sync time.
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
                        
                        console.error('Dealers Choice Sync Error:', {
                            status: status,
                            error: error,
                            response: xhr.responseText
                        });
                    }
                });
            });
        }

        // Show the Reveal Price rows only when Always Show Price is unchecked.
        if($('#dealers_choice_always_show_price').length) {
            var $priceToggle = $('#dealers_choice_always_show_price');
            var $revealPriceRows = $('#reveal-price-scope-row, #popup-form-id-row, #gravity-form-id-row, #allowed-zips-row, #location-request-message-row, #location-verified-message-row, #location-failed-message-row, #location-denied-message-row, #price-unavailable-message-row');

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

        // Show the finance calculator default rows only when Show Finance Calculator is checked.
        if ($('#dealers_choice_show_finance_calculator').length) {
            var $financeToggle = $('#dealers_choice_show_finance_calculator');
            var $financeRows = $('#finance-default-rate-row, #finance-default-term-row, #finance-default-down-payment-row, #finance-calculator-disclaimer-row');

            function toggleFinanceCalculatorFields() {
                if ($financeToggle.is(':checked')) {
                    $financeRows.show();
                } else {
                    $financeRows.hide();
                }
            }
            $financeToggle.on('change', toggleFinanceCalculatorFields);
            toggleFinanceCalculatorFields();
        }
    });

})(jQuery);
