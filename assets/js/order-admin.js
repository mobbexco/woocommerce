/**
 * Toggle an element display depending the value of a select.
 */
 function toggleElementDependigSelect(elementToToggle, select, valueToShow) {
    var value = select.options[select.selectedIndex].value;

    // If current value is the value to show
    if (value && value == valueToShow) {
        elementToToggle.style.display = 'inline-block';
    } else {
        elementToToggle.style.display = 'none';
    }
}

function createCaptureField() {
    // Get woocommerce actions panel
    var actionsPanel = document.getElementById('actions');

    // Create field
    var input = document.createElement('input');
    input.setAttribute('type', 'text');
    input.setAttribute('name', 'mbbx_capture_payment');
    input.setAttribute('id', 'mbbx_capture_payment');
    input.setAttribute('value', mobbex_data.order_total);

    var label = document.createElement('label');
    label.appendChild(document.createTextNode('Capture total'));

    var container = document.createElement('div');
    container.setAttribute('class', 'mbbx_capture_field mbbx_action_field');
    container.appendChild(label);
    container.appendChild(input);

    // Add to actions panel
    actionsPanel.appendChild(container);
}

jQuery(function ($) {
    window.addEventListener('load', function () {
        // Create Capture Payment' field
        createCaptureField();

        // Get actions select and 'Capture Payment' field container
        var actionsSelect  = document.getElementsByName('wc_order_action')[0];
        var fieldContainer = document.querySelector('.mbbx_capture_field');

        // Only show while its action is selected
        toggleElementDependigSelect(fieldContainer, actionsSelect, 'mbbx_capture_payment');
        actionsSelect.onchange = function() {
            toggleElementDependigSelect(fieldContainer, actionsSelect, 'mbbx_capture_payment');
        };
    });
})