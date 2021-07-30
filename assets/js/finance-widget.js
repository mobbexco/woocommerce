(function (window) {
    /**
     * Hide/show financial modal.
     * 
     * @param bool show 
     */
    function toggleModal(show = false) {
        var modal       = document.getElementById('mbbxProductModal');
        var closeBtn    = document.getElementById('closembbxProduct');
        var selectTitle = document.getElementById('select_title');

        modal.style.display       = show ? 'grid' : 'none';
        closeBtn.style.display    = show ? ''     : 'none';
        selectTitle.style.display = show ? ''     : 'none';
    }

    window.addEventListener('load', function () {
        // Get modal action buttons
        var modal    = document.getElementById('mbbxProductModal');
        var openBtn  = document.getElementById('mbbxProductBtn');
        var closeBtn = document.getElementById('closembbxProduct');

        // When the user clicks open button
        openBtn.onclick = function() {
            toggleModal(true);
        }

        // When the user clicks close button
        closeBtn.onclick = function() {
            toggleModal();
        }

        // When the user clicks anywhere outside of the modal, close it
        modal.onclick = function(e) {
            if (!e.target.closest('#mbbxProductModalContent'))
                toggleModal();
        }

        // Get payment method selector
        var methodSelector = document.getElementById('mobbex_methods_list');

        // Filter payment methods in the modal
        methodSelector.onchange = function() {
            var sources = document.querySelectorAll('.mobbexSource');

            for (source of sources) {
                source.style.display = source.id != methodSelector.value && methodSelector.value != 0 ? 'none' : '';
            }
        }
    });
}) (window);