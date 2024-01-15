<?php
$data = new \Mobbex\WP\Checkout\Model\LogTable($_POST);
?>

<div>
    <h2><?php echo __('Introduzca filtros para una busqueda específica: ', 'mobbex-for-woocommerce'); ?></h2>
</div>
<!-- From -->
<form id="filter-form" method="POST" action="">

    <!-- Filters -->
    <p>
        <label for="filter-type"><?php echo __('Tipo: ', 'mobbex-for-woocommerce'); ?></label>
        <select id="filter-type" name="filter_type">
            <option value="all"><?php echo __('Todos', 'mobbex-for-woocommerce'); ?></option>
            <option value="debug" <?php echo isset($_POST['filter_type']) &&  $_POST['filter_type'] == "debug" ? 'selected' : '' ?>>Debug</option>
            <option value="error" <?php echo isset($_POST['filter_type']) &&  $_POST['filter_type'] == "error" ? 'selected' : '' ?>>Error</option>
            <option value="fatal" <?php echo isset($_POST['filter_type']) &&  $_POST['filter_type'] == "fatal" ? 'selected' : '' ?>>Fatal</option>
            <option value="critical" <?php echo isset($_POST['filter_type']) &&  $_POST['filter_type'] == "critical" ? 'selected' : '' ?>>Critical</option>
        </select>

        <label for="filter-date"><?php echo __('Fecha: ', 'mobbex-for-woocommerce'); ?></label>
        <input type="date" id="filter-date" name="filter_date" value="<?php echo isset($_POST['filter_date']) ? $_POST['filter_date'] : ''; ?>">

        <label for="filter-text"><?php echo __('Buscar palabra: ', 'mobbex-for-woocommerce'); ?></label>
        <input type="text" id="filter-text" name="filter_text" value="<?php echo isset($_POST['filter_text']) ? $_POST['filter_text'] : ''; ?>">

        <label for="filter-limit"><?php echo __('Logs por página: ', 'mobbex-for-woocommerce'); ?></label>
        <select id="filter-limit" name="filter_limit">
            <option value="" selected disabled><?php echo __('Seleccione', 'mobbex-for-woocommerce'); ?></option>
            <option value="5" <?php echo isset($_POST['filter_limit']) &&  $_POST['filter_limit'] == "5" ? 'selected' : '' ?>>5</option>
            <option value="10" <?php echo isset($_POST['filter_limit']) &&  $_POST['filter_limit'] == "10" ? 'selected' : '' ?>>10</option>
            <option value="25" <?php echo isset($_POST['filter_limit']) &&  $_POST['filter_limit'] == "25" ? 'selected' : '' ?>>25</option>
        </select>

        <input class="button" type="submit" name="filter-submit" value="Filtrar" onclick="setFormAction('#')">
    </p>

    <!-- Table -->
    <table class="wp-list-table widefat fixed striped table-view-list posts" id="mbbxTable" <?php echo 'mobbex_slug'; ?> cellspacing="0">
        <tbody class="<?php echo 'mobbex_slug'; ?>">
            <tr>
                <th id="mbbxTh"><strong><?php echo __("Log id", 'mobbex-for-woocommerce'); ?></strong></th>
                <th id="mbbxTh"><strong><?php echo __("Type", 'mobbex-for-woocommerce'); ?></strong></th>
                <th id="mbbxTh"><strong><?php echo __("Message", 'mobbex-for-woocommerce'); ?></strong></th>
                <th id="mbbxTh"><strong><?php echo __("Date", 'mobbex-for-woocommerce'); ?></strong></th>
                <th id="mbbxTh"><strong><?php echo __("Data", 'mobbex-for-woocommerce'); ?></strong></th>
            </tr>
            <?php
            foreach ($data->logs as $log => $value) : ?>
                <tr>
                    <td id="mbbxTd"><?php echo $value['log_id']; ?></td>
                    <td id="mbbxTd"><?php echo $value['type']; ?></td>
                    <td id="mbbxTd"><?php echo $value['message']; ?></td>
                    <td id="mbbxTd"><?php echo $value['creation_date']; ?></td>
                    <td id="mbbxTd">
                        <a class="mbbxDisplayDataLink mbbxAnchor" href="#" data-modal-target="#mbbxModal-<?php echo $value['log_id']; ?>">
                            <?php echo __("Ver", 'mobbex-for-woocommerce'); ?>
                        </a>
                    </td>
                    <div id="mbbxModal-<?php echo $value['log_id']; ?>" class="modal">
                        <div class="modal-content">
                            <span class="close">&times;</span>
                            <div>
                                <h4><?php echo __("Log {$value['log_id']} type {$value['type']}:", 'mobbex-for-woocommerce'); ?></h4>
                            </div>
                            <div id="mbbxDisplayData">
                                <?php
                                echo !empty($value['data']) ? $value['data'] : 'Vacío :/';
                                ?>
                            </div>
                        </div>
                    </div>
                </tr>
            <?php
            endforeach;
            ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <div style="display: flex; justify-content: space-between;">
        <p style="align-self: flex-start;">
            <button type='submit' onclick="#" class="button" name="log-page" value='<?php echo 0; ?>'> << </button>
            <button type='submit' onclick="#" class="button" name="log-page" value='<?php echo $data->page_data['actualPage'] - 1  <  0 ? $data->page_data['actualPage'] : $data->page_data['actualPage'] - 1; ?>'> < </button>
            <span class='button'><?php echo "Mostrando " . ($data->page_data['actualPage'] + 1) . " de {$data->page_data['total_pages']}"; ?></span>
            <button type='submit' onclick="#" class="button" name="log-page" value='<?php echo $data->page_data['actualPage'] >= $data->page_data['total_pages'] ? $data->page_data['actualPage'] : $data->page_data['actualPage'] + 1; ?>' > > </button>
            <button type='submit' onclick="#" class="button" name="log-page" value='<?php echo $data->page_data['total_pages'] -1 ; ?>'> >> </button>
        </p>

    <!-- Logs export -->
        <p style="align-self: flex-end;">
            <label for="radio-1"><?php echo __('Página actual', 'mobbex-for-woocommerce'); ?></label>
            <input type="radio" id="radio-1" name="download" value="page" />
            <label for="radio-2"><?php echo __('Busqueda actual', 'mobbex-for-woocommerce'); ?></label>
            <input type="radio" id="radio-2" name="download" value="query" />
            <label for="filter-extension"><?php echo __('Descargar en formato: ', 'mobbex-for-woocommerce'); ?></label>
            <select id="filter-extension" name="filter_extension">
                <option value="txt" selected>txt</option>
                <option value="csv">csv</option>
            </select>
            <input type="submit" target="_blank" onclick="setFormAction('<?= get_rest_url(null, 'mobbex/v1/download_logs');?>')" class="button" name="download-button" value="<?php echo __("Descargar", 'mobbex-for-woocommerce'); ?> "/>
        </p>
    </div>
</form><!-- end form -->

<style>
    #mbbxTable {
        width: -webkit-fill-available;
        word-wrap: break-word;
        margin: auto;
    }

    #mbbxTh{
        text-align: center;
    }
    #mbbxTd {
        text-align: center;
        max-width: 80%;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .mbbxAnchor {
        cursor: pointer;
    }

    #mbbxDisplayData {
        text-align: inherit;
        display: contents;
        max-width: 80%;
        word-wrap: break-word;
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

    /* Download endpoint */

    function setFormAction(action) {
      document.getElementById('filter-form').action = action;
    }
</script>