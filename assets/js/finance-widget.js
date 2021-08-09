(function (window) {
    /**
     * Hide/show element using grid.
     * 
     * @param {Element} element 
     */
    function toggleElement(element) {
        element.style.display = element.style.display != 'grid' ? 'grid' : 'none';
    }

    window.addEventListener('load', function () {
        // First move modal to the beginning of the file
        var modal = document.getElementById('mbbxProductModal');
        document.body.prepend(modal);

        // Get modal action buttons
        var openBtn  = document.getElementById('mbbxProductBtn');
        var closeBtn = document.getElementById('closembbxProduct');

        // Add events to toggle modal
        document.querySelector('body').addEventListener('click', function(e) {
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