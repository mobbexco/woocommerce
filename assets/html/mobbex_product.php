<?php    
    $prices = array();//store children prices
    if($product->is_type('grouped')){
        $product = wc_get_product($post->ID); //composite product
        $children = $product->get_children();//get all the children
        
        foreach($children as $child){
            $price = wc_get_product($child)->get_price();
            $id = wc_get_product($child)->get_id();
            $prices[$id] = $price;
        }
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

<script>

    // Get the modal
    var modal = document.getElementById("mbbxProductModal");

    // Get the button that opens the modal
    var btn = document.getElementById("mbbxProductBtn");

    // Get the <span> element that closes the modal
    var span = document.getElementById("closembbxProduct");

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
            document.getElementById('iframe').style.width = "100%"; 
            document.getElementById('iframe').style.height = "100%"; 
            return false;
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
            var prices = <?php echo json_encode($prices); ?>;
            var price = <?php echo $product->get_price(); ?>;
            var taxId = <?php echo ($mobbexGateway->tax_id > 0 ? $mobbexGateway->tax_id : 0 ); ?>;
            var currency = '<?php echo get_woocommerce_currency_symbol(); ?>';
            //event for all elements with quantity as part of its name.
            $('[name*=quantity]').change(function(){
                console.info(jQuery.isEmptyObject(prices));
                if (!(this.value < 1) && (taxId > 0)) {
                    var product_total = 0;
                    //if prices array is empty, then it is a simple product
                    if(jQuery.isEmptyObject(prices))
                    {
                        product_total = parseFloat(price * this.value);
                    }else
                    {
                        product_total = calculate_totals();
                    }
                    //change the value send to the service
                    document.getElementById("iframe").src = "https://mobbex.com/p/sources/widget/arg/"+ taxId +'?total='+product_total;

                }
           });   
    });

    /**
    *   Search all parts and calculate the final price
     */
    function calculate_totals(){
        var prices = <?php echo json_encode($prices); ?>;
        total_amount = 0 ;//total price
        jQuery("input[name*='quantity']").each(function() {
            var index_id_begin = this.name.indexOf('[')+1;
            var index_id_end = this.name.indexOf(']'); 
            var id = this.name.substring(index_id_begin,index_id_end);
            total_amount = total_amount + (this.value*prices[id]);
        });
        return total_amount;
    }

</script>