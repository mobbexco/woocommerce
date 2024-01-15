
jQuery(function ($) {
        
    // Can submit from either checkout or order review forms
    var form = $('form.checkout, form#order_review');
    var mbbxPaymentData = false;
    
    //Add mobbex render lock
    renderLock();
    
    // Add mobbex container to document
    $("body").append('<div id="mbbx-container"></div>');

    // Intercept wc form handler (fired on checkout.js, line 480)
    $('form.checkout').on('checkout_place_order_mobbex', executePayment);

    // Intercept submit for order review
    $('form#order_review').on('submit', executePayment);

    // Some customers (Inky) have themes where the button is outside the form
    $('#checkout_buttons button').on('click', executePayment);

    // Add mobbex banner interaction
    if ($('mbbx-banner-input').prop("checked"))
        $('.mobbex-banner').removeClass("mobbex-hidden");
    
    $(document).on('change', '.input-radio', (e) => {document.querySelector('.mbbx-banner-input')
        if (e.target == document.querySelector('.mbbx-banner-input'))
            $('.mobbex-banner').removeClass("mobbex-hidden");
        else
            $('.mobbex-banner').addClass("mobbex-hidden");
    });

    /**
     * Try to execute the payment.
     */
    function executePayment() {
        // If it is not mobbex, continue event propagation
        if ($('[name=payment_method]:checked').val() != 'mobbex')
            return;

        // If using wallet
        if ($('[name=payment_method]:checked').attr('method-type') == 'card') {
            processOrder(response => executeWallet(response));
        } else {
            processOrder(response => response.redirect ? redirectToCheckout(response) : openCheckoutModal(response));
        }

        return false;
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
            url: mobbex_data.is_pay_for_order ? form[0].action : wc_checkout_params.checkout_url,
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
                location.href = response.return_url + '&fromCallback=onResult&status=' + data.status.code;
            },

            onPayment: (data) => {
                mbbxPaymentData = data.data;
            },

            onClose: (cancelled) => {
                location.href = response.return_url + '&fromCallback=onClose&status=' + (mbbxPaymentData ? mbbxPaymentData.status.code : '500');
            },

            onError: (error) => {
                handleErrorResponse({
                    result: 'errors',
                    reload: false,
                    messages: ['Se produjo un error al procesar la transacción. Intente nuevamente']
                });
            },
        };

        let mobbexEmbed = window.MobbexEmbed.init(options);
        mobbexEmbed.open();
        unlockForm();
    }

    /**
     * Execute wallet payment from selected card.
     * 
     * @param {array} response Mobbex checkout response.
     */
    function executeWallet(response) {
        let card = $('[name=payment_method]:checked').attr('key') ?? null;
        let cardNumber = $(`#wallet-${card}-number`).val();
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
    /**
     * Render form loader element.
     */
    function renderLock() {
        let loaderModal = document.createElement("div")
        loaderModal.id = "mbbx-loader-modal"
        loaderModal.style.display = "none"

        let spinner = document.createElement("div")
        spinner.id = "mbbx-spinner"

        loaderModal.appendChild(spinner)
        document.body.appendChild(loaderModal)
    }

    function lockForm() {
        document.getElementById("mbbx-loader-modal").style.display = 'grid'
    }

    function unlockForm() {
        document.getElementById("mbbx-loader-modal").style.display = 'none'
    }

    // Shows any errors we encountered
    function handleErrorResponse(response) {
        // Note: This error handling code is copied from the woocommerce checkout.js file
        if (response.reload === 'true') {
            window.location.reload();
            return;
        }

        // Add new errors
        if (response.messages) {
            var checkout_form = $(".woocommerce-checkout"); 
            //Remove old errors
            $(".woocommerce-checkout .woocommerce-error").remove()
            //Show errors
            if (typeof (response.messages) === 'string') {
                checkout_form.prepend(response.messages);
            } else {
                for (var message of response.messages) {
                    checkout_form.prepend('<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout"><ul class="woocommerce-error" role="alert"><li>'+message+'</li></ul></div>')
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
