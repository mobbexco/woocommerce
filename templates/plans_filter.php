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
                        <div class="mbbx-plan-cont">
                            <input type="hidden" name="<?= $field['id'] ?>" value="no">
                            <input type="checkbox" name="<?= $field['id'] ?>" id="<?= $field['id'] ?>" value="<?= $field['value'] ?>" <?= checked($field['value'], 'yes', false) ?>>
                            <label for="<?= $field['id'] ?>"><?= $field['label'] ?></label>
                        </div>
                        <p><?= $field['description'] ?></p>
                    </div>
                <?php endforeach; ?>
            </td>
            <td class="mbbx-plans">
                <?php foreach ($advancedFields as $sourceRef => $fields) : ?>
                    <div class="mbbx-plan-source">
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
    .mbbx-plans-cont {
        border: 1px gainsboro solid;
        width: 500px;
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
    }

    .mbbx-plan-cont label {
        margin: 0;
        margin-left: .5em;
    }

    .mbbx-plan-advanced {
        padding-left: 20px;
    }

    .mbbx-plan-source {
        display: flex;
        align-items: center;
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