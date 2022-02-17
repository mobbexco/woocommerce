<?php if (current_action() == 'product_cat_add_form_fields') : ?>
    <table>
        <tbody class="mbbx_multisite_table">
        <?php endif; ?>
        <!-- Plans filter -->
        <tr class="form-field">
            <th scope="row" valign="top">
                <h2><?= __('Common plans', 'mobbex-for-woocommerce') ?></h2>
            </th>
            <td>
                <h2><?= __('Plans with advanced rules', 'mobbex-for-woocommerce') ?></h2>
            </td>
        </tr>
        <tr class="form-field mbbx-plans-cont">
            <td>
                <?php foreach ($common_fields as $field) : ?>
                    <div class="mbbx-plan">
                        <input type="hidden" name="<?= $field['id'] ?>" value="no">
                        <input type="checkbox" name="<?= $field['id'] ?>" id="<?= $field['id'] ?>" value="<?= $field['value'] ?>" <?= checked($field['value'], 'yes', false) ?>>
                        <label for="<?= $field['id'] ?>"><?= $field['label'] ?></label>
                    </div>
                <?php endforeach; ?>
            </td>
        </tr>
        <tr class="form-field mbbx-plans-cont advanced">
            <td>
                <?php foreach ($advanced_fields as $source_ref => $fields) : ?>
                    <div class="mbbx-plan-source">
                        <img src='https://res.mobbex.com/images/sources/<?= $source_ref ?>.png'>
                        <p><?= $source_names[$source_ref] ?></p>
                    </div>
                    <?php foreach ($fields as $field) : ?>
                        <div class="mbbx-plan">
                            <input type="checkbox" name="<?= $field['id'] ?>" id="<?= $field['id'] ?>" value="yes" <?= checked($field['value'], 'yes', false) ?>>
                            <label for="<?= $field['id'] ?>"><?= $field['label'] ?></label>
                        </div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </td>
        </tr>

        <!-- Multisite fields -->
        <tr class="form-field">
            <th scope="row" valign="top">
                <h2><?= __('Multisite', 'mobbex-for-woocommerce') ?></h2>
            </th>
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
            <th scope="row" valign="top" colspan="2">
                <hr>
            </th>
        </tr>
        <!-- Entity -->
        <tr class="form-field">
            <th scope="row" valign="top"><label for="mbbx_entity"><?= __('Entity UID', 'mobbex-for-woocommerce') ?></label></th>
            <td>
                <input type="text" id="mbbx_entity" name="mbbx_entity" value="<?= $entity ?>">
                <p class="description"><?= __('Product entity uid.', 'mobbex-for-woocommerce') ?></p>
            </td>
        </tr>
        <?php if (current_action() == 'product_cat_add_form_fields') : ?>
        </tbody>
    </table>

<?php endif; ?>