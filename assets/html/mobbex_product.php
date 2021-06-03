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
    <div id="mbbxProductModalContent" class="modal-content" style="overflow: scroll;">
        <div id="mbbxProductModalHeader" class="modal-content">
            <span id="closembbxProduct" class="close">&times;</span>
            <label for="methods">Seleccione un método de pago:</label>
        </div>
        <div id="mbbxProductModalBody" class="modal-content">

        </div>
    </div>
</div>



<script>

    //Define produc types
    const grouped = "grouped";
    const variable = "variable"

    // Get the modal
    let modal = document.getElementById("mbbxProductModal");

    // Get the button that opens the modal
    let btn = document.getElementById("mbbxProductBtn");

    // Get the <span> element that closes the modal
    let span = document.getElementById("closembbxProduct");

    let modal_content = document.getElementById("mbbxProductModalContent");

    let modal_header = document.getElementById("mbbxProductModalHeader");

    let modal_body = document.getElementById("mbbxProductModalBody");

    let select_element = document.getElementById("mobbex_methods_list");
    
    let pre_build_table = document.getElementById("mobbex_payment_plans_list");


    let product_id = <?php echo ($product_id); ?>;

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
            e.preventDefault();
            modal.style.display = "block";
            window.dispatchEvent(new Event('resize'));
            select_element.style.display = "";
            modal_header.appendChild(select_element); 
            pre_build_table.style.display = "";
            modal_body.appendChild(pre_build_table); 

            return true;
        }
    }


    if(select_element)
    {
        select_element.onchange  = function(e) {
            console.info(pre_build_table);
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

    // When the user clicks anywhere outside of the modal, close it
    window.onclick = function(event) {
        if (event.target == modal) {
            modal.style.display = "none";
        }
    } 

    //acumulate poduct price based in the quantity only if the button is active
    jQuery(function($){    
            let prices = <?php echo json_encode($prices); ?>;
            let price = <?php echo $product->get_price(); ?>;
            let taxId = <?php echo ($mobbexGateway->tax_id > 0 ? $mobbexGateway->tax_id : 0 ); ?>;
            let currency = '<?php echo get_woocommerce_currency_symbol(); ?>';
            let product_id = <?php echo ($product_id); ?>;
            modal_header.appendChild(select_element); 
            modal_body.appendChild(pre_build_table); 
            let quant = document.getElementById("quantity_60b7e27482637");
            

            //console.info("NO CAMBIO LA CANTIDAD  " + $('[name*=quantity]').value);
            //event for all elements with quantity as part of its name.
            $('[name*=quantity]').change(function(){
                var variation_id = $("input[name=variation_id]").val();
                if (!(this.value < 1) && (taxId > 0)) {
                    var product_total = 0;
                    //if prices array is empty, then it is a simple product
                    if(jQuery.isEmptyObject(prices))
                    {
                        product_total = parseFloat(price * this.value);
                    }else
                    {
                        product_total = calculate_totals(this.value,variation_id);
                    }
                    console.info("CAMBIO LA CANTIDAD");
                    //change the value send to the service
                    document.getElementById("iframe").src = "https://mobbex.com/p/sources/widget/arg/"+ taxId +'?total='+product_total;
                    let data = {
                        action: 'financing',
                        method_id : 0,
                        product_id : product_id,
                        total : product_total,
                    };

                    jQuery.post('/wp-admin/admin-ajax.php' , data, function(response) {
                        if(response) {
                            console.info(response.data.table);
                            modal_body.innerHTML = '';
                            var someDiv = document.createElement("div");
                            someDiv.innerHTML = response.data.table;
                            pre_build_table.remove();
                            let pre_build_table = someDiv;
                            pre_build_table.style.display = "";
                            modal_body.append(someDiv);
                        } else {
                            console.info("ERROR "+response);
                        }
                    });

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