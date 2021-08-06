<?php if (current_action() == 'product_cat_add_form_fields') : ?>
    <table>
        <tbody class="mbbx_multisite_table">
<?php endif; ?>
            <!-- Plans filter -->
            <tr class="form-field">
                <th scope="row" valign="top" colspan="2"><h2><?=  __('Common plans', 'mobbex-for-woocommerce') ?></h2></th>
            </tr>
            <?php foreach ($common_fields as $field) : ?>
                <tr class="form-field mbbx-plan">
                    <th scope="row" valign="top"><label for="<?= $field['id'] ?>"><?= $field['label'] ?></label></th>
                    <td>
                        <input type="hidden" name="<?= $field['id'] ?>" value="no">
                        <input type="checkbox" name="<?= $field['id'] ?>" id="<?= $field['id'] ?>" value="<?= $field['value'] ?>" <?= checked($field['value'], 'yes', false) ?>>
                    </td>
                </tr>
            <?php endforeach; ?>
            <tr class="form-field">
                <th scope="row" valign="top" colspan="2"><h2><?=  __('Plans with advanced rules', 'mobbex-for-woocommerce') ?></h2></th>
            </tr>
            <?php foreach ($advanced_fields as $source_ref => $fields) : ?>
                <tr class="form-field">
                    <th scope="row" valign="top" colspan="2" class="mbbx_plan_source">
                        <img src='https://res.mobbex.com/images/sources/<?= $source_ref ?>.png'><p><?= $source_names[$source_ref] ?></p>
                    </th>
                </tr>
                <?php foreach ($fields as $field) : ?>
                    <tr class="form-field mbbx-plan mbbx-plan-advanced">
                        <th scope="row" valign="top"><label for="<?= $field['id'] ?>"><?= $field['label'] ?></label></th>
                        <td>
                            <input type="hidden" name="<?= $field['id'] ?>" value="no">
                            <input type="checkbox" name="<?= $field['id'] ?>" id="<?= $field['id'] ?>" value="<?= $field['value'] ?>" <?= checked($field['value'], 'yes', false) ?>>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endforeach; ?>

            <!-- Multisite fields -->
            <tr class="form-field">
                <th scope="row" valign="top"><h2><?=  __('Multisite', 'mobbex-for-woocommerce') ?></h2></th>
            </tr>
            <tr class="form-field">
                <th scope="row" valign="top"><label for="mbbx_enable_multisite"><?= __('Enable Multisite', 'mobbex-for-woocommerce') ?></label></th>
                <td>
                    <input type="checkbox" name="mbbx_enable_multisite" id="mbbx_enable_multisite" value="yes" <?= checked($enable_ms, true, false) ?>>
                    <p class="description"><?= __('Enable it to allow payment for this product to be received by another merchant.', 'mobbex-for-woocommerce') ?></p>
                </td>
            </tr>
            <tr class="form-field mbbx_store_field">
                <th scope="row" valign="top"><label for="mbbx_store"><?= __('Store', 'mobbex-for-woocommerce') ?></label></th>
                <td>
                    <select name="mbbx_store" id="mbbx_store">
                        <option value="new"><?= __('New Store', 'mobbex-for-woocommerce') ?></option>
                        <?php foreach ($store_names as $id => $name) : ?>
                            <option value="<?= $id ?>" <?= selected($id, $store['id'], false) ?>><?= $name ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr class="form-field mbbx_store_name_field">
                <th scope="row" valign="top"><label for="mbbx_store_name"><?= __('New Store Name', 'mobbex-for-woocommerce') ?></label></th>
                <td>
                    <input type="text" name="mbbx_store_name" id="mbbx_store_name" value="<?= $store['name'] ?>">
                </td>
            </tr>
            <tr class="form-field mbbx_api_key_field">
                <th scope="row" valign="top"><label for="mbbx_api_key"><?= __('API Key', 'mobbex-for-woocommerce') ?></label></th>
                <td>
                    <input type="text" name="mbbx_api_key" id="mbbx_api_key" value="<?= $store['api_key'] ?>">
                    <p class="description"><?= __('Your Mobbex API key.', 'mobbex-for-woocommerce') ?></p>
                </td>
            </tr>
            <tr class="form-field mbbx_access_token_field">
                <th scope="row" valign="top"><label for="mbbx_access_token"><?= __('Access Token', 'mobbex-for-woocommerce') ?></label></th>
                <td>
                    <input type="text" name="mbbx_access_token" id="mbbx_access_token" value="<?= $store['access_token'] ?>">
                    <p class="description"><?= __('Your Mobbex access token.', 'mobbex-for-woocommerce') ?></p>
                </td>
            </tr>
            <tr class="form-field">
                <th scope="row" valign="top" colspan="2"><hr></th>
            </tr>
<?php if (current_action() == 'product_cat_add_form_fields') : ?>
        </table>
    </tbody>
<?php endif; ?>