<div id="mbbx-plans-configurator" style="display:flex"></div>
<script id="mbbx-finance-data">
    window.mobbexCommonPlans       = <?= $common_plans ?>;
    window.mobbexSelectedPlans     = <?= $selected_plans ?>;
    window.mobbexAdvancedPlans     = <?= $advanced_plans ?>;
    window.mobbexFeaturedPlans     = <?= $featured_plans ?>;
    window.mobbexManual            = Boolean(<?=$manual == "yes" ?>);
    window.mobbexShowFeaturedPlans = Boolean(<?=$show_plans == "yes" ?>);
    window.mobbexSources           = <?= json_encode($filtered_plans) ?>;
</script>