<div id="mbbxProductModal"> 
    <div id="mbbxProductModalContent">
        <div id="mbbxProductModalHeader">
            <label id="select_title" for="methods" style="display:none;">Seleccione un método de pago:</label>
            <span id="closembbxProduct" style="display:none;">&times;</span>
            <select name="methods" id="mobbex_methods_list">
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
                    <?php if ($source['installments']['enabled']) : ?>
                        <table>
                            <?php foreach($source['installments']['list'] as $installment) : ?>
                                <tr>
                                    <td><?= $installment['name'] ?> </td>
                                    <td style="text-align: center; ">$ <?= $installment['totals']['total'] ?></td>
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
<button id="mbbxProductBtn" class="button alt" style="<?= $data['button_styles'] ?>">Ver Financiación</button>