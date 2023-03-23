<div id="mobbex_product_data" class="panel woocommerce_options_panel hidden">
    <?php do_action('mbbx_product_options'); ?>
    <h2><?=  __('Plans Configuration', 'mobbex-for-woocommerce') ?></h2>
    <p><?=  __('Select the plans you want to appear in the checkout', 'mobbex-for-woocommerce') ?></p>
    <div class="mbbx_plans_cont">
        <div class="mbbx_plan_list">
            <p><?= __('Common plans', 'mobbex-for-woocommerce') ?></p>
            <?php foreach ($commonFields as $field) : ?>
                <input type="hidden" name=" <?= $field['id'] ?>" value="no">
                <?php woocommerce_wp_checkbox($field); ?>
            <?php endforeach; ?>
        </div>
        <div class="mbbx_plan_list">
            <p><?= __('Plans with advanced rules', 'mobbex-for-woocommerce') ?></p>
            <?php foreach ($advancedFields as $source_ref => $fields) : ?>
                <div class='mbbx_plan_source'>
                    <img src='https://res.mobbex.com/images/sources/<?= $source_ref ?>.png'>
                    <p><?= $sourceNames[$source_ref] ?></p>
                </div>
                <?php foreach ($fields as $field) {
                    woocommerce_wp_checkbox($field);
                } ?>
            <?php endforeach; ?>
        </div>
    </div>
    <hr>
    <h2><?= __('Multisite', 'mobbex-for-woocommerce') ?></h2>
    <div>
        <?php
        woocommerce_wp_checkbox([
            'id'          => 'mbbx_enable_multisite',
            'value'       => $enable_ms ? 'yes' : false,
            'label'       => __('Enable Multisite', 'mobbex-for-woocommerce'),
            'description' => __('Enable it to allow payment for this product to be received by another merchant.', 'mobbex-for-woocommerce'),
        ]);

        woocommerce_wp_select([
            'id'            => 'mbbx_store',
            'value'         => $store['id'],
            'label'         => __('Store', 'mobbex-for-woocommerce'),
            'wrapper_class' => 'really-hidden',
            'options'       => array_merge(['new' => __('New Store', 'mobbex-for-woocommerce')], $store_names),
        ]);

        woocommerce_wp_text_input([
            'id'            => 'mbbx_store_name',
            'value'         => $store['name'],
            'label'         => __('New Store Name', 'mobbex-for-woocommerce'),
            'desc_tip'      => true,
            'wrapper_class' => 'really-hidden',
        ]);

        woocommerce_wp_text_input([
            'id'            => 'mbbx_api_key',
            'value'         => $store['api_key'],
            'label'         => __('API Key', 'mobbex-for-woocommerce'),
            'description'   => __('Your Mobbex API key.', 'mobbex-for-woocommerce'),
            'desc_tip'      => true,
            'wrapper_class' => 'really-hidden',
        ]);

        woocommerce_wp_text_input([
            'id'            => 'mbbx_access_token',
            'value'         => $store['access_token'],
            'label'         => __('Access Token', 'mobbex-for-woocommerce'),
            'description'   => __('Your Mobbex access token.', 'mobbex-for-woocommerce'),
            'desc_tip'      => true,
            'wrapper_class' => 'really-hidden',
        ]);
        ?>
    </div>
    <hr>
    <h2><?= __('Multivendor', 'mobbex-for-woocommerce')?></h2>
    <p><?=  __('Write the UID of the entity corresponding to the product.', 'mobbex-for-woocommerce') ?></p>
    <div>
        <?php 
            woocommerce_wp_text_input([
                'id'            => 'mbbx_entity',
                'value'         => $entity,
                'label'         => __('Entity UID', 'mobbex-for-woocommerce'),
                'description'   => __('The uid of the product entity.', 'mobbex-for-woocommerce'),
                'desc_tip'      => true,
            ]);
        ?>
    </div>
    <hr>
    <h2><?= __('Subscriptions', 'mobbex-for-woocommerce') ?></h2>
    <div>
    <?php
        woocommerce_wp_checkbox([
            'id'          => 'mbbx_sub_enable',
            'value'       => $is_subscription ? 'yes' : false,
            'label'       => __('Is a subscription:', 'mobbex-for-woocommerce'),
            'description' => __('Turns the product into a subscription.', 'mobbex-for-woocommerce'),
        ]);

        woocommerce_wp_text_input([
            'id'            => 'mbbx_sub_uid',
            'value'         => $subscription_uid,
            'label'         => __('Subscription UID:', 'mobbex-for-woocommerce'),
            'desc_tip'      => true,
            'wrapper_class' => 'really-hidden',
        ]);
        ?>
    </div>
    <?php do_action('mbbx_product_options_end') ?>
</div>
