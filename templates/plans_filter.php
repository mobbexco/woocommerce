<div id="mbbx-plans-configurator" style="display:flex"></div>
<script id="mbbx-finance-data">
    window.mobbexAdvancedPlans     = <?= $advanced_plans ? json_encode($advanced_plans) : "[]" ?>;
    window.mobbexFeaturedPlans     = <?= $featured_plans ? json_encode($featured_plans) : "[]" ?>;
    window.mobbexManual            = Boolean(<?=!empty($manual) ? $manual == "yes" : 0 ?>);
    window.mobbexShowFeaturedPlans = Boolean(<?=!empty($show_plans) ? $show_plans == "yes" : 0 ?>);
    window.mobbexSources           = <?= $filtered_plans ? json_encode($filtered_plans) : "null" ?>;
</script>