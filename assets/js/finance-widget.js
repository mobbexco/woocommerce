(function (window) {
    /**
     * Hide/show element using grid.
     * 
     * @param {Element} element 
     */
    function toggleElement(element) {
        element.style.display = element.style.display != 'grid' ? 'grid' : 'none';
    }

    /**
     * Try to replace the previous modal, and positions it at the top of the document.
     */
    function replaceModal() {
        var modals = document.querySelectorAll('#mbbxProductModal');

        // If there are multiple modals, remove the first
        if (modals.length > 1)
            modals[0].remove();

        // Place new modal at the top of the document
        document.body.prepend(document.getElementById('mbbxProductModal'));
    }

    /**
     * Update the financial widget with the selected variant price.
     * 
     * @param {int} variantPrice 
     * @param {string} variantId 
     * @param {string} url 
     */
    function updateWidget(variantPrice, variantId, url) {
        jQuery('#mbbxProductBtn').prop('disabled', true);
        jQuery.ajax({
            dataType: 'json',
            method: 'POST',
            url: url,
            data: {
              "variantId": variantId,
              "variantPrice": variantPrice
            },
            success: (response) => {
                jQuery('#updatedWidget').html(response);
                replaceModal();
                jQuery('#mbbxProductBtn').prop('disabled', false);
            },
            error: (error) => {
                
            }
          });
    }

    window.addEventListener('load', function () {
        var modal = document.getElementById('mbbxProductModal');

        if (!modal)
            return false;

        // Add events to toggle modal
        document.querySelector('body').addEventListener('click', function(e) {
            var openBtn = document.getElementById('mbbxProductBtn');

            if (e.target == openBtn)
                replaceModal();

            // Get new modal and close button
            var modal    = document.getElementById('mbbxProductModal');
            var closeBtn = document.getElementById('closembbxProduct');

            if (e.target == openBtn || e.target == closeBtn || e.target == modal && !e.target.closest('#mbbxProductModalContent'))
                toggleElement(modal);
        });

        //Trigger widget update when selected variation change
        jQuery(document).on('found_variation', 'form.cart', function( event, variation ) {   
            updateWidget(variation.display_price, variation.variation_id, mobbexWidget.widgetUpdateUrl);
         });

        // Get sources and payment method selector 
        var sources      = document.querySelectorAll('.mobbexSource');
        var methodSelect = document.getElementById('mbbx-method-select');

        // Filter payment methods in the modal
        methodSelect.addEventListener('change', function() {
            for (source of sources)
                source.style.display = source.id != methodSelect.value && methodSelect.value != 0 ? 'none' : '';
        });
    });
}) (window);