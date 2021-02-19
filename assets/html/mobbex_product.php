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
            var price = <?php echo $product->get_price(); ?>;
            var taxId = <?php echo ($mobbexGateway->tax_id > 0 ? $mobbexGateway->tax_id : 0 ); ?>;
            var currency = '<?php echo get_woocommerce_currency_symbol(); ?>';

            $('[name=quantity]').change(function(){
                if (!(this.value < 1) && (taxId > 0)) {

                    var product_total = parseFloat(price * this.value);
                    //change the value send to the service
                    document.getElementById("iframe").src = "https://mobbex.com/p/sources/widget/arg/"+ taxId +'?total='+product_total;

                }
           });   
    });

</script>