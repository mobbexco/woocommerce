jQuery(function ($) {
    // Can submit from either checkout or order review forms
    var form = $('form.checkout, form#order_review');
    // Checks if the wallet is alredy rendered
    var rendered = false
    // Data from the wallet
    var walletData = mobbex_data.wallet || []
    // Return url from the data
    var walletReturn = mobbex_data.return_url;

    // Intercept form button (Bind to click instead of WC trigger to avoid popup) 
    $('form.checkout').on('click', ':submit', function (event) {
        return !invokeOverlayCheckout(event);
    });

    // Intercept submit for order review
    $('form#order_review').on('submit', function (event) {
        return !invokeOverlayCheckout(event);
    });

    // Some customers (Inky) have themes where the button is outside the form
    $('#checkout_buttons button').on('click', function (event) {
        $('form#order_review').submit();

        return !invokeOverlayCheckout(event); // Don't fire the submit event twice if the buttons ARE in the form
    });

    // Initial checkout and when it is updated (shipping)
    $('body').on('updated_checkout', function() {
        // Only execute when wallet is available
        if(mobbex_data.is_wallet === "1"){

            $('.payment_method_mobbex').on('click', function() {
                // Only if is the first render
                if (!rendered){
                    return renderOptions()
                }
            })
            // Only if mobbex is the only payment method and is the first render
            if($('#payment_method_mobbex').is(':checked') && !rendered) return renderOptions()
        }
    })

    if (mobbex_data.is_pay_for_order && mobbex_data.is_wallet === "1" && !rendered) {
        window.addEventListener('load', function () {
            renderOptions();
        });
    }

    // Starts the overlay checkout process (returns false if we can't)
    function invokeOverlayCheckout() {
        // Check payment method etc
        if (isMobbexPaymentMethodSelected()) {

            lockForm()

            if (mobbex_data.is_wallet === "1") {
                getUpdatedWallet(response => executeWallet(response));
            } else {
                $("body").append('<div id="mbbx-container"></div>');
                getSignedCheckoutUrlViaAjax();
            }

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
        $.ajax({
            dataType: "json",
            method: "POST",
            url: mobbex_data.is_pay_for_order ? form[0].action : mobbex_data.order_url,
            data: form.serializeArray(),
            success: function (response) {
                // WC will send the error contents in a normal request
                if (response.result === "success") {
                    // Send data object to start checkout
                    startMobbexCheckoutModal(response.data.id, response.return_url);
                } else {
                    handleErrorResponse(response);
                }
            },
            error: function () {
                // We got a 500 or something if we hit here. Shouldn't normally happen
                // alert("We were unable to process your order, please try again in a few minutes.");
                handleErrorResponse({
                    result: 'errors',
                    reload: false,
                    messages: ['Se produjo un error al procesar la transacción. Intente nuevamente']
                });
            }
        });
    }

    // Starts the Mobbex overlay once we have the checkout url.
    function startMobbexCheckoutModal(id, returnUrl) {
        var mbbxEmbed = window.MobbexEmbed.init({
            id: id,
            sid: 'none',
            type: 'checkout',

            onResult: (data) => {
                location.href = returnUrl + '&status=' + data.status.code;
            },
            onClose: (cancelled) => {
                // Only if cancelled
                if (cancelled === true) {
                    location.reload();
                }
            },
            onError: () => {
                // location.href = returnUrl + '&status=0';
                handleErrorResponse({
                    result: 'errors',
                    reload: false,
                    messages: ['Se produjo un error al procesar la transacción. Intente nuevamente']
                });
            }
        });

        mbbxEmbed.open();
    }


    // WALLET
    
    // Renders the Mobbex option (Add card and render the wallet)
    function renderOptions(){
        rendered = true
        
        var display = !isMobbexPaymentMethodSelected() ? 'display: none;' : '';
        $('.payment_method_mobbex').append(`<div class="payment_box payment_method_mobbex" id="walletCardsContainer" style="${display}"><ul class="wc_payment_methods payment_methods methods" id="walletCards"></ul></div>`)
        
        var walletDiv = $('#walletCards')
        walletDiv.append(`
            <li class="wc_payment_method payment_method_card" style="margin-bottom:1.5rem">
                <input name="walletOpt" class="input-radio" id="new_card" type="radio" value="new_card">
                <label class="payment_method_cod" for="new_card">Utilizar otra tarjeta / Medio de pago</label>
            </li>`)
        
        // Automatic check new card
        $('input[name=walletOpt][value="new_card"]').prop("checked", true)
        // The wallet is empty
        if (walletData.length < 1) renderNoCardsMessage()
        // Renders the credit cards
        else renderWallet(walletData)
    }
    
    // Process the selected credit card with Mobbex SDK
    function executeWallet(response) {
        var card = $('input[name=walletOpt]:checked').val()
        // Executes this if a credit card is selected
        if (card !== 'new_card') {
            var cardNumber = $(`#wallet-${card} input[name=cardNumber]`).val()
            var installment = $(`#wallet-${card} select`).val()
            var securityCode = $(`#wallet-${card} input[name=securityCode]`).val()
            var intentToken = response.data.wallet.find(card => card.card.card_number == cardNumber).it
    
            window.MobbexJS.operation.process({
                intentToken: intentToken,
                installment: installment,
                securityCode: securityCode
            })
            .then(data => {
                if (data.result === true) {
                    location.href = response.return_url + '&status=' + data.data.status.code
                }
                else handleErrorResponse({
                    result: 'errors',
                    reload: false,
                    messages: ['Se produjo un error al procesar la transacción. Intente nuevamente']
                });
            })
            .catch(error => {alert(error)})
        }
        else {
            // New card is selected so the modal is executed
            $("body").append('<div id="mbbx-container"></div>');
            startMobbexCheckoutModal(response.data.id, response.return_url)
        }

        return true
    }

    // Get the new wallet with updated installments from server
    function getUpdatedWallet(callback){
        lockForm()
        $.ajax({
            dataType: "json",
            method: "POST",
            url: mobbex_data.is_pay_for_order ? form[0].action : mobbex_data.order_url,
            data: form.serializeArray(),
            success: function (response) {
                if (response.result === 'success') {
                    callback(response);
                } else {
                    handleErrorResponse(response);
                }
            },
            error: function () {
                // We got a 500 or something if we hit here. Shouldn't normally happen
                // alert("We were unable to process your order, please try again in a few minutes.");
                handleErrorResponse({
                    result: 'errors',
                    reload: false,
                    messages: ['Se produjo un error al cargar las tarjetas. Intentelo nuevamente']
                });
            }
        });
    }

    // Renders the wallet with the info from server
    function renderWallet(wallet) {
        // Creates the input radio and form for each card
        wallet.forEach((card, index) => {
            var installments
            installments = card.installments ? card.installments : [];
            var i = index
            var listCard = `
                <li class="wc_payment_method payment_method_card" style="margin-bottom:1.5rem">
                    <input name="walletOpt" class="input-radio" id="card${index}" type="radio" value="card_${index}">
                    <label class="payment_method_cod" for="card${index}"><img width="30" style="border-radius: 1rem;margin: 0px 4px 0px 0px;" src="${card.source.card.product.logo}"> ${card.card.card_number}</label>
                    <div id="wallet-card_${index}" class="payment_box walletForm" style="display: none;">
                        <select class="select2-selection__rendered" name="installment"></select>
                        <input style="margin-top:1rem" class="input-text" type="password" maxlength="${card.source.card.product.code.length}" name="securityCode" placeholder="${card.source.card.product.code.name}" required>
                        <input type="hidden" name="intentToken" value="${card.it}">
                        <input type="hidden" name="cardNumber" value="${card.card.card_number}">
                    </div>
                </li>`
            $('#walletCards').append(listCard)

            // Adds the available installments for each card into the select
            installments.forEach(installment => {
                $(`#wallet-card_${i} select`).append(`<option value="${installment.reference}">${installment.name}</option>`)
            })
        })

        // Shows the card form if selected
        $('input[name=walletOpt]').on("click", function() {
            $('.walletForm').hide();
            var card = $('input[name=walletOpt]:checked').val();
            $(`#wallet-${card}`).show();
        });
    }

    // Renders the message when no cards are available
    function renderNoCardsMessage() {
        $("#walletCards").hide()
        $("#walletCardsContainer").append(`
        <p>Paga con tarjeta, efectivo y otros medios de pago</p>
        `)
    }

    // Utils
    function lockForm() {
        form.addClass('processing').block();

        $('.blockMsg').hide();
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