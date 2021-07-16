jQuery(function($){    
    $(document).ready(function(){
        // Get the modal
        var modal = document.getElementById("mbbxProductModal");
        // Get the button that opens the modal
        var btn = document.getElementById("mbbxProductBtn");
        // Get the <span> element that closes the modal
        var span = document.getElementById("closembbxProduct");
        // Get the <select> element with all the payment method avaliable
        var select_element = document.getElementById("mobbex_methods_list");
        // Get the <div> element modal header
        var modal_header = document.getElementById("mbbxProductModalHeader");
        // Get the <div> element modal body
        var modal_body = document.getElementById("mbbxProductModalBody");

        //Only if the SHORTCODE button is avalible then add click event
        if(btn)
        {
            // When the user clicks on the button, show/open the modal
            btn.onclick  = function(e) {
                e.preventDefault();
                var select_element = document.getElementById("mobbex_methods_list");
                var pre_build_table = document.getElementById("mobbex_payment_plans_list");
                var close_button = document.getElementById("closembbxProduct");
                var label_title = document.getElementById("select_title");
                modal_body.innerHTML = '';//clear the modal 
                modal.style.display = "grid";
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
        if(span){
            // When the user clicks on <span> (x), close the modal
            span.onclick = function() {
                modal.style.display = "none";
                var close_button = document.getElementById("closembbxProduct");
                var label_title = document.getElementById("select_title");
                close_button.style.display = "none";
                label_title.style.display = "none";
            }
        }
        // When the user clicks anywhere outside of the modal, close it
        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = "none";
                var close_button = document.getElementById("closembbxProduct");
                var label_title = document.getElementById("select_title");
                close_button.style.display = "none";
                label_title.style.display = "none";
            }
        } 

        
    })
})