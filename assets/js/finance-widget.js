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