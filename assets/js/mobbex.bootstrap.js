jQuery(function ($) {
    
    // Can submit from either checkout or order review forms
    var form = jQuery('form.checkout, form#order_review');

    // Intercept form button (Bind to click instead of WC trigger to avoid popup) 
    jQuery('form.checkout').on('click', ':submit', function (event) {
        return !invokeOverlayCheckout();
    });
    // Intercept submit for order review
    jQuery('form#order_review').on('submit', function (event) {
        return !invokeOverlayCheckout();
    });

    // Some customers (Inky) have themes where the button is outside the form
    jQuery('#checkout_buttons button').on('click', function (event) {
        jQuery('form#order_review').submit();

        return false; // Don't fire the submit event twice if the buttons ARE in the form
    });

    // Starts the overlay checkout process (returns false if we can't)
    function invokeOverlayCheckout() {
        // Check payment method etc
        if (isMobbexPaymentMethodSelected()) {
            // Need to show spinner before standard checkout as we have to spend time getting the pay URL
            // TODO: Add some loading spinner ( show )

            getSignedCheckoutUrlViaAjax();

            // Make sure we don't submit the form normally
            return true;
        }

        // We didn't fire
        return false;
    }

    // Gets if the payment method is set to Mobbex (in case multiple methods are available)
    function isMobbexPaymentMethodSelected() {
        if ($('#payment_method_mobbex').is(':checked')) {
            return true;
        }
    }

    // Requests the signed checkout link via the Mobbex WC plugin
    function getSignedCheckoutUrlViaAjax() {
        jQuery.ajax({
            dataType: "json",
            method: "POST",
            url: mobbex_data.order_url,
            data: form.serializeArray(),
            success: function (response) {
                // WC will send the error contents in a normal request
                if (response.result == "success") {
                    // Send data object to start checkout
                    startMobbexCheckoutModal(response.data, response.return_url);
                } else {
                    handleErrorResponse(response);
                }
            },
            error: function (jqxhr, status) {
                // We got a 500 or something if we hit here. Shouldn't normally happen
                alert("We were unable to process your order, please try again in a few minutes.");
            }
        });
    };

    // Starts the Mobbex overlay once we have the checkout url.
    function startMobbexCheckoutModal(checkoutData, returnUrl) {
        var mbbxButton = window.MobbexButton.init({
            checkout: checkoutData.id,
            onPayment: (data) => {
                location.href = returnUrl + '&status=' + data.status.code;
            },
            onClose: (cancelled) => {
                // Only if cancelled
                if (cancelled === true) {
                    location.reload();
                }
            },
            onError: (error) => {
                location.href = returnUrl + '&status=0';
            }
        });

        mbbxButton.open();
    };

    // Shows any errors we encountered
    function handleErrorResponse(response) {
        // Note: This error handling code is copied from the woocommerce checkout.js file
        if (response.reload === 'true') {
            window.location.reload();
            return;
        }

        // Remove old errors
        jQuery('.woocommerce-error, .woocommerce-message').remove();
        // Add new errors
        if (response.messages) {
            form.prepend(response.messages);
        }

        // Cancel processing
        form.removeClass('processing').unblock();

        // Lose focus for all fields
        form.find('.input-text, select').blur();

        // Scroll to top
        jQuery('html, body').animate({
            scrollTop: (form.offset().top - 100)
        }, 1000);

        if (response.nonce) {
            form.find('#_wpnonce').val(response.nonce);
        }

        // Trigger update in case we need a fresh nonce
        if (response.refresh === 'true') {
            jQuery('body').trigger('update_checkout');
        }

        // Clear the Mobbex spinner manually as we didn't start checkout
        // TODO: Add some loading spinner ( hide )
    };

});