<div id="mbbx-finance-widget"></div>
<script id="mbbx-finance-data">
    window.mobbexTheme          = "<?= $data['theme'] ?>";
    window.mobbexSourcesUrl     = "<?= $data['sources_url'] ?>";
    window.featuredInstallments = <?= $data['featured_installments'] ?: "null" ?>;
</script>