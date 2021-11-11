jQuery(function ($) {
    // Can submit from either checkout or order review forms
    var form = $('form.checkout, form#order_review');

    // Add mobbex container to document
    $("body").append('<div id="mbbx-container"></div>');

    // Intercept form button (Bind to click instead of WC trigger to avoid popup) 
    $('form.checkout').on('click', ':submit', function (event) {
        return executePayment(event);
    });

    // Intercept submit for order review
    $('form#order_review').on('submit', function (event) {
        return executePayment(event);
    });

    // Some customers (Inky) have themes where the button is outside the form
    $('#checkout_buttons button').on('click', function (event) {
        $('form#order_review').submit();

        return executePayment(event); // Don't fire the submit event twice if the buttons ARE in the form
    });

    /**
     * Try to execute the payment.
     */
    function executePayment() {
        let methodSelected = $('[name=payment_method]:checked');

        // Check payment method selected
        if (methodSelected.val() == 'mobbex') {
            if (methodSelected.attr('method-type') == 'card') {
                processOrder(response => executeWallet(response));
            } else {
                processOrder(response => response.redirect ? redirectToCheckout(response) : openCheckoutModal(response));
            }

            return false;
        }
    }

    /**
     * Process the order and create a mobbex checkout.
     * 
     * @param {CallableFunction} callback
     */
    function processOrder(callback) {
        lockForm();

        $.ajax({
            dataType: 'json',
            method: 'POST',
            url: mobbex_data.is_pay_for_order ? form[0].action : mobbex_data.order_url,
            data: form.serializeArray(),

            success: (response) => {
                response.result == 'success' ? callback(response) && unlockForm() : handleErrorResponse(response);
            },
            error: () => {
                handleErrorResponse({
                    result: 'errors',
                    reload: false,
                    messages: ['Se produjo un error al procesar la transacción. Intente nuevamente']
                });
            }
        });
    }

    /**
     * Redirect to Mobbex checkout page.
     * 
     * @param {array} response Mobbex checkout response.
     */
    function redirectToCheckout(response) {
        let paymentMethod = $('[name=payment_method]:checked').attr('group');
        window.top.location = response.redirect + (paymentMethod ? '?paymentMethod=' + paymentMethod : '');
    }

    /**
     * Open the Mobbex checkout modal.
     * 
     * @param {array} response Mobbex checkout response.
     */
    function openCheckoutModal(response) {
        let options = {
            id: response.data.id,
            type: 'checkout',
            paymentMethod: $('[name=payment_method]:checked').attr('group') ?? null,

            onResult: (data) => {
                location.href = response.return_url + '&status=' + data.status.code;
            },
            onClose: (cancelled) => {
                if (cancelled === true)
                    location.reload();
            },
            onError: () => {
                handleErrorResponse({
                    result: 'errors',
                    reload: false,
                    messages: ['Se produjo un error al procesar la transacción. Intente nuevamente']
                });
            },
        };

        let mobbexEmbed = window.MobbexEmbed.init(options);
        mobbexEmbed.open();
    }

    /**
     * Execute wallet payment from selected card.
     * 
     * @param {array} response Mobbex checkout response.
     */
    function executeWallet(response) {
        let card        = $('[name=payment_method]:checked').attr('key') ?? null;
        let cardNumber  = $(`#wallet-${card}-number`).val();
        let updatedCard = response.data.wallet.find(card => card.card.card_number == cardNumber);

        var options = {
            intentToken: updatedCard.it,
            installment: $(`#wallet-${card}-installments`).val(),
            securityCode: $(`#wallet-${card}-code`).val()
        };

        window.MobbexJS.operation.process(options).then(data => {
            if (data.result === true) {
                location.href = response.return_url + '&status=' + data.data.status.code;
            } else {
                handleErrorResponse({
                    result: 'errors',
                    reload: false,
                    messages: ['Se produjo un error al procesar la transacción. Intente nuevamente']
                });
            }
        }).catch(error => alert(error));
    }

    // Utils
    function lockForm() {
        form.addClass('processing').block();

        $('.blockMsg').hide();
    }

    function unlockForm() {
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

            if (typeof (response.messages) === 'string') {
                noticesWrapper.append(response.messages);
            } else {
                for (var message of response.messages) {
                    noticesWrapper.append(`<ul class="woocommerce-error" role="alert"><li>${message}</li></ul>`);
                }
            }
        }

        unlockForm();

        // Lose focus for all fields
        form.find('.input-text, select').blur();

        // Scroll to top
        $('html, body').animate({
            scrollTop: (form.offset().top - 100)
        }, 1000);

        if (response.nonce) {
            form.find('#_wpnonce').val(response.nonce);
        }

        // Trigger update in case we need a fresh nonce
        if (response.refresh === 'true') {
            $('body').trigger('update_checkout');
        }
    }
});