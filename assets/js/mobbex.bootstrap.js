jQuery(function ($) {

    // Can submit from either checkout or order review forms
    var form = jQuery('form.checkout, form#order_review');

    // Intercept form button (Bind to click instead of WC trigger to avoid popup) 
    jQuery('form.checkout').on('click', ':submit', function (event) {
        event.preventDefault();
        event.stopPropagation();

        return !invokeOverlayCheckout(event);
    });

    // Intercept submit for order review
    jQuery('form#order_review').on('submit', function (event) {
        event.preventDefault();
        event.stopPropagation();

        return !invokeOverlayCheckout(event);
    });

    // Some customers (Inky) have themes where the button is outside the form
    jQuery('#checkout_buttons button').on('click', function (event) {
        event.preventDefault();
        event.stopPropagation();

        jQuery('form#order_review').submit();

        return !invokeOverlayCheckout(event); // Don't fire the submit event twice if the buttons ARE in the form
    });

    // Starts the overlay checkout process (returns false if we can't)
    function invokeOverlayCheckout(event) {
        // Check payment method etc
        if (isMobbexPaymentMethodSelected()) {
            $("body").append('<div id="mbbx-container"></div>');

            lockForm();

            getSignedCheckoutUrlViaAjax();

            // Make sure we don't submit the form normally
            return true;
        }

        try {
            // Try to dispatch the event after stop it
            event.target.dispatchEvent(event);
        } catch(e) {}

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
                // alert("We were unable to process your order, please try again in a few minutes.");
                handleErrorResponse({
                    result: 'errors',
                    reload: false,
                    messages: ['Se produjo un error al procesar la transacción. Intente nuevamente']
                });
            }
        });
    };

    // Starts the Mobbex overlay once we have the checkout url.
    function startMobbexCheckoutModal(checkoutData, returnUrl) {
        var mbbxButton = window.MobbexButton.init({
            checkout: checkoutData.id,
            inSite: true,
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
                // location.href = returnUrl + '&status=0';
                handleErrorResponse({
                    result: 'errors',
                    reload: false,
                    messages: ['Se produjo un error al procesar la transacción. Intente nuevamente']
                });
            }
        });

        mbbxButton.open();
    };

    function lockForm() {
        form.addClass('processing').block();
    }

    function unlockForm() {
        // Cancel processing
        form.removeClass('processing').unblock();
    }

    // Shows any errors we encountered
    function handleErrorResponse(response) {
        // Note: This error handling code is copied from the woocommerce checkout.js file
        if (response.reload === 'true') {
            window.location.reload();
            return;
        }

        // Notices wrapper on WooCommerce
        var noticesWrapper = $(".woocommerce-notices-wrapper");

        // Add new errors
        if (response.messages) {
            // Remove old errors
            noticesWrapper.empty();

            // form.prepend(response.messages);
            for (var message of response.messages) {
                noticesWrapper.append(`<ul class="woocommerce-error" role="alert"><li>${message}</li></ul>`);
            }
        }

        unlockForm();

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
    };

});