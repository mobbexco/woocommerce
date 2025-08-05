(function(window) {
    /**
     * Hide an element or a list of them.
     * @param {Element|NodeList} options 
     */
    function hideElements(options) {
        if (options instanceof NodeList) {
            for (const option of options)
                hideElements(option);
        } else {
            // Hide row container if exists
            var container = options.closest('tr') != null ? options.closest('tr') : options;

            container.classList.toggle('hidden');
        }
    }

    /**
     * Init mobbex config tabs.
     * @param {Array.<string>} tabNames 
     */
    function initConfigTabs(tabNames) {
        tabNames.forEach(tabName => {
            // Get tab
            var tab = document.querySelector('.mbbx-tab-' + tabName);

            // Create expand button
            tab.setAttribute('data-content', '+');

            // Hide all tab options
            hideElements(document.querySelectorAll('.mbbx-into-' + tabName));

            tab.onclick = function () {
                var dataContent = tab.getAttribute('data-content') == '+';

                // Toggle expand button
                if (dataContent) {
                    tab.setAttribute('data-content', '-');
                } else {
                    tab.setAttribute('data-content', '+');
                }

                // Display alert in advanced configuration tab
                if (tabName == 'advanced' && dataContent) {
                    window.alert('¡Cuidado! Estas opciones son avanzadas, modifiquelas sólo si sabe exactamente lo que hace.')
                }

                // Show options again
                hideElements(document.querySelectorAll('.mbbx-into-' + tabName));
            }
        });
    }

    /**
     * Add dynamic to dni fields.
     */
    function addDynamicToDniFields() {
        // Get dni fields
        var ownDni    = document.getElementById('woocommerce_mobbex_own_dni');
        var customDni = document.getElementById('woocommerce_mobbex_custom_dni');

        // If own dni option is active, hide custom dni option
        if (ownDni.checked) hideElements(customDni);
        ownDni.onchange = function () {
          hideElements(customDni);
        };
    }

    /**
     * Toggle featured installments options.
     * Manage the enabling  of the best and custom featured installments options. 
     * 
    */
    function toogleFeaturedInstallmentsOptions() {

      var showFeaturedInstallments = document.getElementById(
        "woocommerce_mobbex_show_featured_installments"
      );
      var bestFeaturedInstallments = document.getElementById(
        "woocommerce_mobbex_best_featured_installments"
      );
      var customFeaturedInstallments = document.getElementById(
        "woocommerce_mobbex_custom_featured_installments"
      );

      if (!showFeaturedInstallments.checked){
        bestFeaturedInstallments.setAttribute('disabled', 'disabled');
        customFeaturedInstallments.setAttribute('disabled', 'disabled');
      }
      
      if (showFeaturedInstallments.checked && bestFeaturedInstallments.checked)
        customFeaturedInstallments.setAttribute('disabled', 'disabled');

      showFeaturedInstallments.onchange = () => {
        if (showFeaturedInstallments.checked) {
            bestFeaturedInstallments.removeAttribute('disabled');
            customFeaturedInstallments.removeAttribute('disabled');
        } else {
            bestFeaturedInstallments.setAttribute('disabled', 'disabled');
            customFeaturedInstallments.setAttribute('disabled', 'disabled');
        }
      }

      bestFeaturedInstallments.onchange = () => {
        if (bestFeaturedInstallments.checked)
            customFeaturedInstallments.setAttribute('disabled', 'disabled');
        else
            customFeaturedInstallments.removeAttribute('disabled');
      };
    }

    window.addEventListener('load', function () {
        initConfigTabs(['appearance', 'advanced', 'orders']);
        addDynamicToDniFields();
        toogleFeaturedInstallmentsOptions();
    });
}) (window)