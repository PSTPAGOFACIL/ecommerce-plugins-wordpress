jQuery(function($){
    //maneja boton radio oculto en (pagina checkout)
    var $checkout_form = $( 'form.checkout' );
    $checkout_form.on( 'click', 'input[name="payment_method"]', function() {
        var methodId = $(this).attr("id");
        //busca input radio de la opcion y lo selecciona
        if (methodId){
            var $option = $("#" + methodId.replace("_method_","_option_"));
            if ($option.length) {
                $option.prop( "checked", true );
            }
        }
    });
    
    //logica de redireccion (pagina redirect)
    var redirect_url = $("#url_trx").val()
    if (redirect_url) {
        window.location = redirect_url;
    }

});