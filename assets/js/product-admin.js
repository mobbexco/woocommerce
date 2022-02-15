function mbbxsToggleOptions(optionToCheck, valueToShow, optionsToToggle, classUsed) {
    // Works with multiple elements
    for (var option of optionsToToggle) {
        if (optionToCheck.checked === valueToShow || optionToCheck.value === valueToShow) {
            option.classList.remove(classUsed);
        } else {
            option.classList.add(classUsed);
        }
    }
}

window.addEventListener('load', function () {
    //Multisite event
    var isMultisite = document.querySelector('#mbbx_enable_multisite');
    var allOptions = [
        document.querySelector('.mbbx_store_field'),
        document.querySelector('.mbbx_store_name_field'),
        document.querySelector('.mbbx_api_key_field'),
        document.querySelector('.mbbx_access_token_field'),
    ];

    // Show all multisite options when is enabled
    mbbxsToggleOptions(isMultisite, true, allOptions, 'really-hidden');
    isMultisite.onclick = function () {
        mbbxsToggleOptions(isMultisite, true, allOptions, 'really-hidden');
    }

    var storeSelect = document.querySelector('select#mbbx_store');
    var newStoreOptions = [
        document.querySelector('.mbbx_store_name_field'),
        document.querySelector('.mbbx_api_key_field'),
        document.querySelector('.mbbx_access_token_field'),
    ];

    mbbxsToggleOptions(storeSelect, 'new', newStoreOptions, 'hidden');
    storeSelect.onchange = function () {
        mbbxsToggleOptions(storeSelect, 'new', newStoreOptions, 'hidden');
    }

    //subscription event
    var issubscription         = document.querySelector('#mbbx_enable_sus');
    var subscriptionUidOptions = document.querySelector('.mbbx_sus_uid_field');
    //show subscription uid if product is type subscription
    mbbxsToggleOptions(issubscription, true, [subscriptionUidOptions], 'really-hidden');
    issubscription.onclick = function () {
        mbbxsToggleOptions(issubscription, true, [subscriptionUidOptions], 'really-hidden');
    }
});