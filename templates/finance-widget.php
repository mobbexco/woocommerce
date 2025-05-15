<div id="mbbx-finance-widget"></div>
<script id="mbbx-finance-data">
    window.mobbexSources = <?= json_encode($data['sources']) ?>;
    window.mobbexTheme = "<?= $this->config->theme ?>";

    (function(window, $) {
        // Saves root
        window.mobbexFinanceWidgetRoot = window.mobbexFinanceWidgetRoot || null;

        function renderFinanceWidget() {
            // Gets widget div
            var container = document.getElementById('mbbx-finance-widget');
            if (container) {
                // Clears shadowRoot if exists
                if (container.shadowRoot)
                    container.attachShadow({
                        mode: 'open'
                    });

                if (!window.mobbexFinanceWidgetRoot)
                    window.mobbexFinanceWidgetRoot = kv.createRoot(container);

                window.mobbexFinanceWidgetRoot.render(
                    J.jsx(Sd, {
                        children: J.jsx(ry, {
                            sources: window.mobbexSources || [],
                            theme: window.mobbexTheme
                        })
                    })
                );
            }
        }

        // Render widget on DOM ready
        $(document).ready(renderFinanceWidget);

        // Listen for woocommerce cart updates to re-render widget
        $(document.body).on('updated_cart_totals updated_wc_div wc_fragments_refreshed', function() {
            // Destroy previous widget root if exists . Avoid shadowRoot error
            if (window.mobbexFinanceWidgetRoot) {
                window.mobbexFinanceWidgetRoot.unmount();
                window.mobbexFinanceWidgetRoot = null;
            }
            renderFinanceWidget();
        });
    })(window, jQuery);
</script>