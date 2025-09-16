function mbbxToggleOptions(optionToCheck, valueToShow, optionsToToggle, classUsed) {
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
    mbbxToggleOptions(isMultisite, true, allOptions, 'really-hidden');
    isMultisite.onclick = function () {
        mbbxToggleOptions(isMultisite, true, allOptions, 'really-hidden');
    }

    var storeSelect = document.querySelector('select#mbbx_store');
    var newStoreOptions = [
        document.querySelector('.mbbx_store_name_field'),
        document.querySelector('.mbbx_api_key_field'),
        document.querySelector('.mbbx_access_token_field'),
    ];

    mbbxToggleOptions(storeSelect, 'new', newStoreOptions, 'hidden');
    storeSelect.onchange = function () {
        mbbxToggleOptions(storeSelect, 'new', newStoreOptions, 'hidden');
    }

    //subscription event
    var issubscription         = document.querySelector('#mbbx_sub_enable');
    var subscriptionUidOptions = document.querySelector('.mbbx_sub_uid_field');
    var subscriptionSignUpFee  = document.querySelector('.mbbx_sub_sign_up_fee_field');
    //show subscription uid if product is type subscription
    mbbxToggleOptions(issubscription, true, [subscriptionUidOptions], 'really-hidden');
    mbbxToggleOptions(issubscription, true, [subscriptionSignUpFee], 'really-hidden');
    issubscription.onclick = function () {
        mbbxToggleOptions(issubscription, true, [subscriptionUidOptions], 'really-hidden');
        mbbxToggleOptions(issubscription, true, [subscriptionSignUpFee], 'really-hidden');
    }
});
