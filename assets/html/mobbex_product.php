<?php    
    $prices = array();//store children prices
    $product_type = "simple";//set product type default to simple
    $product_id = $post->ID;
    if($product->is_type('grouped')){
        $product = wc_get_product($product_id); //composite product
        $children = $product->get_children();//get all the children
        
        foreach($children as $child){
            $price = wc_get_product($child)->get_price();
            $id = wc_get_product($child)->get_id();
            $prices[$id] = $price;
        }
        $product_type = "grouped";
    }elseif($product->is_type('variable')){ 
        //echo print_r(wc_get_product($product->get_children()[1])->get_price_html());
        global $woocommerce;
        $children_id = $product->get_children();//get all the children ids
        foreach($children_id as $child_id){
            $product = new WC_Product_Variation($child_id);
            $price = $product->get_price();
            if(!$price){
                $price = 0;
            }
            $prices[$child_id] = $price;
        }
        $product_type = "variable";
    }
?>


<!-- The Modal -->
<div id="mbbxProductModal" class="modal">
    <!-- Modal content -->  
    <div id="mbbxProductModalContent" class="modal-content">
        <span id="closembbxProduct" class="close">&times;</span>
        <iframe id="iframe" src=<?php echo $url_information ?>></iframe>
    </div>
</div>

<div id="mbbxProductModalShortcode" class="modal" > 
    <div id="mbbxProductModalContentShortcode" class="modal-content">
        <div id="mbbxProductModalHeaderShortcode" class="modal-content">
            <span id="closembbxProductShortcode" class="close">&times;</span>
            <label for="methods">Seleccione un método de pago:</label>
        </div>
        <div id="mbbxProductModalBodyShortcode" class="modal-content">
        </div>
    </div>
</div>

<script>

    //Define produc types
    var grouped = "grouped";
    var variable = "variable";

    // Get the modal
    var modal = document.getElementById("mbbxProductModal");

    // Get the button that opens the modal
    var btn = document.getElementById("mbbxProductBtn");

    // Get the <span> element that closes the modal
    var span = document.getElementById("closembbxProduct");
    
    // Get the shortcode button that opens the modal
    var btnShortcode = document.getElementById("mbbxProductBtnShortcode");

    // Get the <select> element with all the payment method avaliable
    var select_element = document.getElementById("mobbex_methods_list");
    
    // Get the <span> element that closes the modal
    var spanShortcode = document.getElementById("closembbxProductShortcode");

    // Get the modal
    var modalShortcode = document.getElementById("mbbxProductModalShortcode");

    // Get the <div> element modal header
    var modal_header = document.getElementById("mbbxProductModalHeaderShortcode");

    // Get the <div> element modal body
    var modal_body = document.getElementById("mbbxProductModalBodyShortcode");

    // Get the <span> element that closes the modal
    var spanShortcode = document.getElementById("closembbxProductShortcode");

    //only if the button is avalible
    if(span){
        // When the user clicks on <span> (x), close the modal
        span.onclick = function() {
            modal.style.display = "none";
        }
    }
    //only if the button is avalible
    if(btn){
        // When the user clicks on the button, show/open the modal
        btn.onclick  = function(e) {
            var src_iframe = document.getElementById('iframe_span_src');
            e.preventDefault();
            // Get the content
            var modal_hook_content = document.getElementById("mbbxProductModalContent");
            modal.style.display = "block";
            window.dispatchEvent(new Event('resize'));
            document.getElementById('iframe').style.width = "100%"; 
            document.getElementById('iframe').style.height = "100%"; 
            return true;    
        }
    }

    //Only if the SHORTCODE button is avalible then add click event
    if(btnShortcode)
    {
        // When the user clicks on the button, show/open the modal
        btnShortcode.onclick  = function(e) {
            e.preventDefault();
            var select_element = document.getElementById("mobbex_methods_list");
            var pre_build_table = document.getElementById("mobbex_payment_plans_list");
            modal_body.innerHTML = '';//clear the modal 
            modalShortcode.style.display = "grid";
            window.dispatchEvent(new Event('resize'));
            //add the select html element
            select_element.style.display = "";
            modal_header.appendChild(select_element); 
            //add the table html element
            pre_build_table.style.display = "";
            modal_body.appendChild(pre_build_table); 

            return true;
        }
    }
    if(select_element)
    {
        // Payment Methods Filter in the modal
        select_element.onchange  = function(e) {
            var pre_build_table = document.getElementById("mobbex_payment_plans_list");
            trs = pre_build_table.getElementsByTagName("tr");
            
            for (i = 0; i < trs.length; i++) {
                if(trs[i].id != select_element.value && select_element.value != 0){
                    trs[i].style.display = "none";
                }else{
                    trs[i].style.display = "";
                }

            }
        }

    }
    //only if the button is avalible
    if(spanShortcode){
        // When the user clicks on <span> (x), close the modal
        spanShortcode.onclick = function() {
            modalShortcode.style.display = "none";
        }
    }


    // When the user clicks anywhere outside of the modal, close it
    window.onclick = function(event) {
        if (event.target == modal) {
            modal.style.display = "none";
        }
        if (event.target == modalShortcode) {
            modalShortcode.style.display = "none";
        }
    } 

    //acumulate poduct price based in the quantity only if the button is active
    jQuery(function($){    
            var prices = <?php echo json_encode($prices); ?>;
            var price = <?php echo $product->get_price(); ?>;
            var taxId = <?php echo ($mobbexGateway->tax_id > 0 ? $mobbexGateway->tax_id : 0 ); ?>;
            var currency = '<?php echo get_woocommerce_currency_symbol(); ?>';
            var product_id = <?php echo ($product_id); ?>;

            //event for all elements with quantity as part of its name.
            $('[name*=quantity]').change(function(){
                var variation_id = $("input[name=variation_id]").val();
                if (!(this.value < 1) && (taxId > 0)) {
                    var pre_build_table = document.getElementById("mobbex_payment_plans_list");
                    var product_total = 0;
                    //if prices array is empty, then it is a simple product, else it is a grouped or variable product
                    if(jQuery.isEmptyObject(prices))
                    {
                        product_total = parseFloat(price * this.value);
                    }else
                    {
                        product_total = calculate_totals(this.value,variation_id);
                    }
                    //if pre_build_table exist then the shortcode is being used else use the hook
                    if(pre_build_table){
                        //AJAX call that retrive the payment plans table in string format
                        var data = {
                            action: 'financing',
                            method_id : 0,
                            product_id : product_id,
                            total : product_total,
                        };
                        jQuery.post('/wp-admin/admin-ajax.php' , data, function(response) {
                            if(response) {
                                //If ajax response is completed, then add the list to the body
                                document.getElementById("mobbex_payment_plans_list").remove();
                                var div = document.createElement("div");
                                div.innerHTML = response.data.table;
                                document.body.appendChild(div);
                            } else {
                                console.info("ERROR "+response);
                            }
                        });
                    }else{
                        //change the value send to the service
                        document.getElementById("iframe").src = "https://mobbex.com/p/sources/widget/arg/"+ taxId +'?total='+product_total;
                    }
                    

                }   
           });   
    });

    /**
    *   Search all parts and calculate the final price
    *   quantity,variation_id params are usen only for variable products
     */
    function calculate_totals(quantity,variation_id){
        var prices = <?php echo json_encode($prices); ?>;
        var product_type = <?php echo $product_type; ?>;
        total_amount = 0 ;//total price
        if(product_type === grouped){
            jQuery("input[name*='quantity']").each(function() {
                var index_id_begin = this.name.indexOf('[')+1;
                var index_id_end = this.name.indexOf(']'); 
                var id = this.name.substring(index_id_begin,index_id_end);
                total_amount = total_amount + (this.value * prices[id]);
            });
        }else if(product_type === variable){
            //in case quantity is not set, the default value is 1
            if(!quantity){
                quantity =  1;
            }
            total_amount = total_amount + (quantity * prices[variation_id]);
        }
        
        return total_amount;
    }

</script>