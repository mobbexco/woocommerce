<div id="mbbx-finance-widget"></div>
<script id="mbbx-finance-data">
    window.mobbexTheme              = "<?= $data['theme'] ?>";
    window.mobbexSourcesUrl         = "<?= $data['sources_url'] ?>";
    window.showFeaturedInstallments = <?= var_export($data['show_featured_installments'], true) ?>;
</script>