<?php
$data   = new \Mobbex\WP\Checkout\Model\LogTable(wp_unslash($_POST));
$params = $data->params;
?>

<div>
    <h2><?= __('Introduzca filtros para una busqueda específica: ', 'mobbex-for-woocommerce'); ?></h2>
</div>
<!-- From -->
<form id="logs-list-table-form" method="POST" action="" class="wp-list-table widefat fixed striped table-view-list log-files">
    <input type="hidden" name="_wpnonce" value="<?= esc_attr(wp_create_nonce('wp_rest')); ?>">

    <!-- Filters -->
    <p>
        <label for="filter-type"><?= __('Tipo: ', 'mobbex-for-woocommerce'); ?></label>
        <select id="filter-type" name="filter_type">
            <option value="all"><?= __('Todos', 'mobbex-for-woocommerce'); ?></option>
            <option value="debug" <?= selected($params['filter_type'], 'debug', false); ?>>Debug</option>
            <option value="error" <?= selected($params['filter_type'], 'error', false); ?>>Error</option>
            <option value="fatal" <?= selected($params['filter_type'], 'fatal', false); ?>>Fatal</option>
            <option value="critical" <?= selected($params['filter_type'], 'critical', false); ?>>Critical</option>
        </select>

        <label for="filter-date"><?= __('Fecha: ', 'mobbex-for-woocommerce'); ?></label>
        <input type="date" id="filter-date" name="filter_date" value="<?= esc_attr($params['filter_date']); ?>">

        <label for="filter-text"><?= __('Buscar palabra: ', 'mobbex-for-woocommerce'); ?></label>
        <input type="text" id="filter-text" name="filter_text" value="<?= esc_attr($params['filter_text']); ?>">

        <label for="filter-limit"><?= __('Logs por página: ', 'mobbex-for-woocommerce'); ?></label>
        <select id="filter-limit" name="filter_limit">
            <option value="" selected disabled><?= __('Seleccione', 'mobbex-for-woocommerce'); ?></option>
            <option value="5" <?= isset($_POST['filter_limit']) ? selected($params['filter_limit'], 5, false) : ''; ?>>5</option>
            <option value="10" <?= isset($_POST['filter_limit']) ? selected($params['filter_limit'], 10, false) : ''; ?>>10</option>
            <option value="25" <?= isset($_POST['filter_limit']) ? selected($params['filter_limit'], 25, false) : ''; ?>>25</option>
        </select>

        <input class="button" type="submit" name="filter-submit" value="Filtrar">
    </p>

    <!-- Table -->
    <table class="wp-list-table widefat fixed striped table-view-list posts" id="mbbxTable" <?= 'mobbex_slug'; ?> cellspacing="0">
        <tbody id="the-list" class="<?= 'mobbex_slug'; ?>">
            <tr>
                <th id="mbbxTh"><strong><?= __("Log id", 'mobbex-for-woocommerce'); ?></strong></th>
                <th id="mbbxTh"><strong><?= __("Type", 'mobbex-for-woocommerce'); ?></strong></th>
                <th id="mbbxTh"><strong><?= __("Message", 'mobbex-for-woocommerce'); ?></strong></th>
                <th id="mbbxTh"><strong><?= __("Date", 'mobbex-for-woocommerce'); ?></strong></th>
                <th id="mbbxTh"><strong><?= __("Data", 'mobbex-for-woocommerce'); ?></strong></th>
            </tr>
            <?php foreach ($data->logs as $log => $value) : ?>
                <tr>
                    <td id="mbbxTd"><?= esc_html($value['log_id']); ?></td>
                    <td id="mbbxTd"><?= esc_html($value['type']); ?></td>
                    <td id="mbbxTd">
                        <?= esc_html(mb_strimwidth($value['message'], 0, 130)); ?>
                    </td>
                    </td>
                    <td id="mbbxTd"><?= esc_html($value['creation_date']); ?></td>
                    <td id="mbbxTd">
                        <a class="mbbxDisplayDataLink mbbxAnchor" href="#" data-modal-target="#mbbxModal-<?= esc_attr($value['log_id']); ?>">
                            <?= __("Ver", 'mobbex-for-woocommerce'); ?>
                        </a>
                    </td>
                    <div id="mbbxModal-<?= esc_attr($value['log_id']); ?>" class="modal">
                        <div class="modal-content">
                            <span class="close">&times;</span>
                            <div>
                                <p><h4> Message: </h4><?= esc_html($value['message']); ?></p>
                            </div>
                            <div>
                                <p><h4> Log: </h4><?= esc_html("Log {$value['log_id']} type {$value['type']}:"); ?></p>
                            </div>
                            <div id="mbbxDisplayData">
                                <h4> Data: </h4><?= !empty($value['data']) ? esc_html($value['data']) : 'Vacío :/'; ?>
                            </div>
                        </div>
                    </div>
                </tr>
            <?php endforeach;?>
        </tbody>
    </table>

    <!-- Pagination -->
    <div style="display: flex; justify-content: space-between;">
        <p style="align-self: flex-start;">
            <button type='submit' class="button" name="log-page" value="<?= 0; ?>"> << </button>
            <button type='submit' class="button" name="log-page" value='<?= $data->page_data['actualPage'] - 1  <  0 ? $data->page_data['actualPage'] : $data->page_data['actualPage'] - 1; ?>'> < </button>
            <span class='button'><?= "Mostrando " . ($data->page_data['actualPage'] + 1) . " de {$data->page_data['total_pages']}"; ?></span>
            <button type='submit' class="button" name="log-page" value='<?= $data->page_data['actualPage'] >= $data->page_data['total_pages'] ? $data->page_data['actualPage'] : $data->page_data['actualPage'] + 1; ?>'> > </button>
            <button type='submit' class="button" name="log-page" value='<?= $data->page_data['total_pages'] - 1; ?>'> >> </button>
        </p>

        <!-- Logs export -->
        <p style="align-self: flex-end;">
            <label for="radio-1"><?= __('Página actual', 'mobbex-for-woocommerce'); ?></label>
            <input type="radio" id="radio-1" name="download" value="page" checked/>
            <label for="radio-2"><?= __('Busqueda actual', 'mobbex-for-woocommerce'); ?></label>
            <input type="radio" id="radio-2" name="download" value="query" />
            <label for="filter-extension"><?= __('Descargar en formato: ', 'mobbex-for-woocommerce'); ?></label>
            <select id="filter-extension" name="filter_extension">
                <option value="txt" <?= selected($params['filter_extension'], 'txt', false); ?>>txt</option>
                <option value="csv" <?= selected($params['filter_extension'], 'csv', false); ?>>csv</option>
            </select>
            <input
                type="submit"
                formaction="<?= esc_url(get_rest_url(null, 'mobbex/v1/download_logs')); ?>"
                class="button" name="download-button"
                value="<?= __("Descargar", 'mobbex-for-woocommerce'); ?>"
            />
        </p>
    </div>
</form><!-- end form -->

<style>

    #mbbxTable {
        width: -webkit-fill-available;
        word-wrap: break-word;
        margin: auto;
    }

    #mbbxTh {
        text-align: center;
    }

    #mbbxTd {
        text-align: -webkit-center;
        max-width: 80%;
        white-space: inherit;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    #mbbxDisplayData {
        text-align: inherit;
        display: contents;
        max-width: 80%;
        word-wrap: break-word;
    }

    .mbbxAnchor {
        cursor: pointer;
    }

    /* Style for the modal */
    .modal {
        display: none;
        position: fixed;
        z-index: 1;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.4);
    }

    .modal-content {
        background-color: #fefefe;
        margin: 15%;
        padding: 10px;
        border: 3px solid #888;
        width: 75%;
    }

    .close {
        color: #aaa;
        float: right;
        font-size: 28px;
        font-weight: bold;
    }

    .close:hover,
    .close:focus {
        color: black;
        text-decoration: none;
        cursor: pointer;
    }
</style>

<script>
    /* Modal */

    // Get all the elements with the class "mbbxDisplayDataLink"
    var modalTriggers = document.querySelectorAll(".mbbxDisplayDataLink");

    // Attach a click event listener to each trigger
    modalTriggers.forEach(function(trigger) {
        trigger.addEventListener("click", function(event) {
            event.preventDefault();
            var modalId = this.getAttribute("data-modal-target");
            var modal = document.querySelector(modalId);
            modal.style.display = "block";
        });
    });

    // Close the modal when the close button or the background is clicked
    var modals = document.querySelectorAll(".modal");
    modals.forEach(function(modal) {
        var closeButton = modal.querySelector(".close");
        modal.addEventListener("click", function(event) {
            if (event.target === modal || event.target === closeButton) {
                modal.style.display = "none";
            }
        });
    });
</script>