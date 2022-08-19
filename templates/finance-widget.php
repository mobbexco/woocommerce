<div id="mbbxProductModal" class="<?= $data['style']['theme'] ?>"> 
    <div id="mbbxProductModalContent">
        <div id="mbbxProductModalHeader">
            <select name="mbbx-method-select" id="mbbx-method-select">
                <option id="0" value="0">Seleccione un método de pago</option>
                <?php foreach($data['sources'] as $source) : ?>
                    <?php if (!empty($source['source']['name'])) : ?>
                        <option id="<?= $source['source']['reference'] ?>" value="<?= $source['source']['reference'] ?>"><?= $source['source']['name'] ?></option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
            <span id="closembbxProduct">&times;</span>
        </div>
        <div id="mbbxProductModalBody">
        <?php foreach($data['sources'] as $source) : ?>
            <?php if (!empty($source['source']['name'])) : ?>
                <div id="<?= $source['source']['reference'] ?>" class="mobbexSource">
                    <p class="mobbexPaymentMethod">
                        <img src="https://res.mobbex.com/images/sources/<?= $source['source']['reference'] ?>.jpg"><?= $source['source']['name'] ?>
                    </p>
                    <?php if (!empty($source['installments']['list'])) : ?>
                        <table>
                            <?php foreach($source['installments']['list'] as $installment) : ?>
                                <tr>
                                    <td>
                                        <?= $installment['name'] ?>
                                        <?php if ($installment['totals']['installment']['count'] != 1) : ?>
                                            <small>
                                                <?= $installment['totals']['installment']['count'] ?> cuotas de <?= wc_price($installment['totals']['installment']['amount']) ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: right; "><?= isset($installment['totals']['total']) ? wc_price($installment['totals']['total']) : '' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    <?php else: ?>
                        <p class="mobbexSourceTotal">
                            <?= wc_price($data['price']) ?>
                        </p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
        </div>
    </div>
</div>
<?php if ($data['style']['show_button']) : ?>
    <button id="mbbxProductBtn" class="button alt" style="
    <?= 
    ($data['style']['button_color']      ? 'background-color: ' . $data['style']['button_color'] . ';' : '') .
    ($data['style']['button_font_color'] ? 'color: ' . $data['style']['button_font_color'] . ';' : '') .
    ($data['style']['button_font_size']  ? 'font-size: ' . $data['style']['button_font_size'] . ';' : '') .
    ($data['style']['button_padding']    ? 'padding: ' . $data['style']['button_padding'] . ';' : '')
    ?>
    ">
    Ver Financiación
    </button>
<?php endif; ?>
