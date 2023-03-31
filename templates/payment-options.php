<?php if ($gateway->config->unified_mode != 'yes' && !empty($gateway->methods)) : ?>
    <?php foreach ($gateway->methods as $method) : ?>
        <li class="wc_payment_method payment_method_mobbex_method_<?= "$method[subgroup]" ?>">
            <input id="payment_method_mobbex_method_<?= "$method[subgroup]" ?>" type="radio" class="input-radio" name="payment_method" value="<?= $gateway->id ?>" <?php checked($gateway->chosen, true); ?> method-type="method" group="<?= "$method[group]:$method[subgroup]" ?>" data-order_button_text="<?= $gateway->order_button_text ?>" />
            <label for="payment_method_mobbex_method_<?= "$method[subgroup]" ?>">
                <?= (count($gateway->methods) == 1 || $method['subgroup'] == 'card_input') && $gateway->get_title() ? $gateway->get_title() : $method['subgroup_title'] ?> <img src="<?= $method['subgroup_logo'] ?>">
            </label>
            <?php if ($gateway->has_fields() || $gateway->get_description()) : ?>
                <div class="payment_box payment_method_<?= $gateway->id."_method_$method[subgroup]" ?>" <?php if (!$gateway->chosen) : ?>style="display:none;" <?php endif; ?>>
                    <?php $gateway->payment_fields(); ?>
                </div>
            <?php endif; ?>
        </li>
    <?php endforeach; ?>
<?php else : ?>
    <li class="wc_payment_method payment_method_<?= $gateway->id ?>">
        <input id="payment_method_<?= $gateway->id ?>" type="radio" class="input-radio" name="payment_method" value="<?= $gateway->id ?>" <?php checked($gateway->chosen, true); ?> data-order_button_text="<?= $gateway->order_button_text ?>" />
        <label for="payment_method_<?= $gateway->id ?>">
            <?= $gateway->get_title(); ?> <?= $gateway->get_icon(); ?>
        </label>
        <?php if ($gateway->has_fields() || $gateway->get_description()) : ?>
            <div class="payment_box payment_method_<?= $gateway->id ?>" <?php if (!$gateway->chosen) : ?>style="display:none;" <?php endif; ?>>
                <?php $gateway->payment_fields(); ?>
            </div>
        <?php endif; ?>
    </li>
<?php endif; ?>

<?php if (!empty($gateway->cards)) : ?>
    <?php foreach ($gateway->cards as $key => $card) : ?>
        <?php if (!empty($card['installments'])) : ?>
            <li class="wc_payment_method payment_method_mobbex_card_<?= $key ?>">
                <input id="payment_method_mobbex_card_<?= $key ?>" type="radio" class="input-radio" name="payment_method" value="<?= $gateway->id ?>" <?php checked($gateway->chosen, true); ?> method-type="card" key="<?= $key ?>" data-order_button_text="<?= $gateway->order_button_text ?>" />
                <label for="payment_method_mobbex_card_<?= $key ?>">
                    <?= $card['name'] ?> <img src="<?= $card['source']['card']['product']['logo'] ?>">
                </label>
                <div class="payment_box payment_method_mobbex_card_<?= $key ?>" <?php if (!$gateway->chosen) : ?>style="display:none;" <?php endif; ?>>
                    <div id="wallet-<?= $key ?>">
                        <p class="form-row mbbx-card-form-row">
                            <label for="wallet-<?= $key ?>-installments">Cuotas</label>
                            <select required id="wallet-<?= $key ?>-installments">
                                <?php foreach ($card['installments'] as $installment) : ?>
                                    <option value="<?= $installment['reference'] ?>"><?= $installment['name'] ?> (<?= $installment['totals']['installment']['count'] ?> cuota/s de $<?= $installment['totals']['installment']['amount'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </p>
                        <p class="form-row mbbx-card-form-row">
                            <label for="wallet-<?= $key ?>-code">Código de seguridad</label>
                            <input type="password" name="securityCode" maxlength="<?= $card['source']['card']['product']['code']['length'] ?>" placeholder="<?= $card['source']['card']['product']['code']['name'] ?>" id="wallet-<?= $key ?>-code" required>
                        </p>
                        <input type="hidden" name="cardNumber" value="<?= $card['card']['card_number'] ?>" id="wallet-<?= $key ?>-number">
                    </div>
                </div>
            </li>
        <?php endif; ?>
    <?php endforeach; ?>
<?php endif; ?>