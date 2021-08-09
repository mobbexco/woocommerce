<div id="mbbxProductModal"> 
    <div id="mbbxProductModalContent">
        <div id="mbbxProductModalHeader">
            <label id="mobbex_select_title" for="mbbx-method-select">Seleccione un método de pago</label>
            <span id="closembbxProduct">&times;</span>
            <select name="mbbx-method-select" id="mbbx-method-select">
                <option id="0" value="0">Todos</option>
                <?php foreach($data['sources'] as $source) : ?>
                    <?php if (!empty($source['source']['name'])) : ?>
                        <option id="<?= $source['source']['reference'] ?>" value="<?= $source['source']['reference'] ?>"><?= $source['source']['name'] ?></option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
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
                                    <td><?= $installment['name'] ?></td>
                                    <td style="text-align: center; "><?= isset($installment['totals']['total']) ? '$ ' . $installment['totals']['total'] : '' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
        </div>
    </div>
</div>
<button id="mbbxProductBtn" class="button alt">Ver Financiación</button>