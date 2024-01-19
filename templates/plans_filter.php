<table class="mbbx-plans-cont">
    <tbody>
        <tr style="text-align: center;">
            <td><?= __('Common Plans', 'mobbex');   ?></td>
            <td><?= __('Advanced Plans', 'mobbex'); ?></td>
        </tr>
        <tr>
            <td class="mbbx-plans">
                <?php foreach ($commonFields as $key => $field) : ?>
                    <div class="mbbx-plan">
                        <?php if (isset($sourceNames[$sourceGroups[$field['label']][0]])) ?>
                        <div class="source-popover">
                            <h3>Medios Disponibles</h3>
                            <?php foreach ($sourceGroups[$field['label']] as $sourceRef) : ?>
                                <div class="mbbx-plan-group">
                                    <img src="https://res.mobbex.com/images/sources/original/<?= $sourceRef; ?>.png">
                                    <p><?= isset($sourceNames[$sourceRef]) ? $sourceNames[$sourceRef] : $sourceRef ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="mbbx-plan-cont">
                            <input type="hidden" name="<?= $field['id'] ?>" value="no">
                            <input type="checkbox" name="<?= $field['id'] ?>" id="<?= $field['id'] ?>" value="yes" <?= checked($field['value'], 'yes', false) ?>>
                            <label for="<?= $field['id'] ?>"><?= $field['label'] ?></label>
                        </div>
                        <p><?= $field['description'] ?></p>
                    </div>
                <?php endforeach; ?>
            </td>
            <td class="mbbx-plans">
                <?php foreach ($advancedFields as $sourceRef => $fields) : ?>
                    <div class="mbbx-plan-group">
                        <img src="https://res.mobbex.com/images/sources/<?= $sourceRef; ?>.png">
                        <p><?= $sourceNames[$sourceRef]; ?></p>
                    </div>
                    <?php foreach ($fields as $key => $field) : ?>
                        <div class="mbbx-plan-advanced">
                            <div class="mbbx-plan-cont">
                                <input type="checkbox" name="<?= $field['id'] ?>" id="<?= $field['id'] ?>" value="yes" <?= checked($field['value'], 'yes', false) ?>>
                                <label for="<?= $field['id'] ?>"><?= $field['label'] ?></label>
                            </div>
                            <p><?= $field['description'] ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </td>
        </tr>
    </tbody>
</table>

<style>
    .mbbx-plan {
        position: relative;
    }

    .mbbx-plans-cont {
        border: 1px gainsboro solid;
        width: 90%;
    }

    .mbbx-plans-cont tbody {
        vertical-align: top
    }

    .mbbx-plans-cont td {
        width: 50%;
        border: 1px gainsboro solid;
        padding: 15px;
    }

    .mbbx-plans-cont label {
        font-weight: 400 !important;
    }

    .mbbx-plan-cont {
        display: flex;
        align-items: center;
        padding: 0 10px;
    }

    .mbbx-plan-cont label {
        margin: 0;
        margin-left: .5em;
    }

    .mbbx-plan-group {
        display: flex;
        justify-content: left;
        align-items: center;
        padding: 5px;
        background-color: #eaeffb;
        margin-bottom: 15px;
    }

    .mbbx-plan-group img {
        width: 2.5em;
        margin: 0 5px;
    }

    .source-popover {
        display: none;
        position: absolute;
        top: 2em;
        left: 30px;
        min-width: 15em;
        justify-content: center;
        flex-direction: column;
        border-radius: 5px;
        background-color: #eaeffb;
        padding: 1rem;
        z-index: 100;
    }

    .source-popover h3 {
        text-align: center;
    }

    .mbbx-plan:hover .source-popover {
        display: flex;
    }

    .mbbx-plan-group p {
        margin: 0;
    }

    .mbbx-plan-advanced {
        padding-left: 20px;
    }

    .mbbx-plan-source {
        display: flex;
        align-items: center;
        background-color: #1755cd17;
    }

    .mbbx-plan-source * {
        display: inline;
    }

    .mbbx-plan-source img {
        width: 30px;
        border-radius: 100%;
    }

    .mbbx-update-plans {
        margin: 20px 0;
    }

    .mbbx-update-plans p {
        margin-top: 10px;
    }
</style>