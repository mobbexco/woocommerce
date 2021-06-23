jQuery(function($){    
    $(document).ready(function(){
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

        //Only if the SHORTCODE button is avalible then add click event
        if(btnShortcode)
        {
            // When the user clicks on the button, show/open the modal
            btnShortcode.onclick  = function(e) {
                e.preventDefault();
                var select_element = document.getElementById("mobbex_methods_list");
                var pre_build_table = document.getElementById("mobbex_payment_plans_list");
                var close_button = document.getElementById("closembbxProductShortcode");
                var label_title = document.getElementById("select_title");
                modal_body.innerHTML = '';//clear the modal 
                modalShortcode.style.display = "grid";
                window.dispatchEvent(new Event('resize'));
                //add the select html element
                select_element.style.display = "";
                close_button.style.display = "";
                label_title.style.display = "";
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
                var close_button = document.getElementById("closembbxProductShortcode");
                var label_title = document.getElementById("select_title");
                close_button.style.display = "none";
                label_title.style.display = "none";
            }
        }
        // When the user clicks anywhere outside of the modal, close it
        window.onclick = function(event) {
            if (event.target == modalShortcode) {
                modalShortcode.style.display = "none";
                var close_button = document.getElementById("closembbxProductShortcode");
                var label_title = document.getElementById("select_title");
                close_button.style.display = "none";
                label_title.style.display = "none";
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
                var pre_build_table = document.getElementById("mobbex_payment_plans_list");
                var product_total = 0;
                //if it is a simple product, else it is a grouped or variable product
                if(global_data_assets.product_type == 'simple')
                {
                    product_total = parseFloat(global_data_assets.price * this.value);
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
                        product_id : global_data_assets.product_id,
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
                }
            }   
       });   
});


/**
*   Search all parts and calculate the final price
*   quantity,variation_id params are usen only for variable products
 */
function calculate_totals(quantity,variation_id)
{
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

