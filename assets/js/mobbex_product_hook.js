jQuery(function($){    
    $(document).ready(function(){
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
                // Get the content
                modal.style.display = "block";
                window.dispatchEvent(new Event('resize'));
                document.getElementById('iframe').style.width = "100%"; 
                document.getElementById('iframe').style.height = "100%"; 
                return true;    
            }
        }

        // When the user clicks anywhere outside of the modal, close it
        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = "none";
            }
        } 
    })
})

//acumulate poduct price based in the quantity only if the button is active
jQuery(function($){    

        //event for all elements with quantity as part of its name.
        $('[name*=quantity]').change(function(){
            var variation_id = $("input[name=variation_id]").val();
            if (!(this.value < 1)) {
                var product_total = 0;
                //if it is a simple product, else it is a grouped or variable product
                if(global_data_assets.product_type == 'simple')
                {
                    product_total = parseFloat(global_data_assets.price * this.value);
                }else
                {
                    product_total = calculate_totals(this.value,variation_id);
                }
                //change the value send to the service
                document.getElementById("iframe").src = "https://mobbex.com/p/sources/widget/arg/"+ global_data_assets.tax_id +'?total='+product_total;
            }   
       });   
});

/**
*   Search all parts and calculate the final price
*   quantity,variation_id params are usen only for variable products
 */
function calculate_totals(quantity,variation_id){
    total_amount = 0 ;//total price
    if(global_data_assets.product_type == "grouped"){
        jQuery("input[name*='quantity']").each(function() {
            var index_id_begin = this.name.indexOf('[')+1;
            var index_id_end = this.name.indexOf(']'); 
            var id = this.name.substring(index_id_begin,index_id_end);
            total_amount = total_amount + (this.value * global_data_assets[id]);
        });
    }else if(global_data_assets.product_type == "variable"){
        //in case quantity is not set, the default value is 1
        if(!quantity){
            quantity =  1;
        }
        total_amount = total_amount + (quantity * global_data_assets[variation_id]);
    }
    
    return total_amount;
}