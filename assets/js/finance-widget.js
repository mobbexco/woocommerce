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
        // Get modal action buttons
        var modal    = document.getElementById('mbbxProductModal');
        var openBtn  = document.getElementById('mbbxProductBtn');
        var closeBtn = document.getElementById('closembbxProduct');

        // Toggle modal
        document.querySelector('body').addEventListener('click', function(e) {
            if (e.target == openBtn || e.target == closeBtn || e.target == modal && !e.target.closest('#mbbxProductModalContent'))
                toggleElement(modal);
        });

        // Get sources and payment method selector 
        var sources        = document.querySelectorAll('.mobbexSource');
        var methodSelector = document.getElementById('mobbex_methods_list');

        // Filter payment methods in the modal
        methodSelector.addEventListener('change', function() {
            for (source of sources)
                source.style.display = source.id != methodSelector.value && methodSelector.value != 0 ? 'none' : '';
        });
    });
}) (window);