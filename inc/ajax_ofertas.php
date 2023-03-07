<?php
session_start();

include('../funciones_ext.php');

$id= $_POST['id'];
$radio_ofertas= $_POST['radio_ofertas'];
$tipo_comercial = $_SESSION['tipo_comercial'];
$id_vendedor = ( isset( $_POST['id_vendedor'] ) ? $_POST['id_vendedor'] : 0 );
$tipo_comercial = 'interno';
if ($_SESSION['tipo_comercial'] == 'externo') {
	$tipo_comercial = $_SESSION['tipo_comercial'];
	$id_vendedor = $_SESSION['cod_vendedor'];
	$id = 4;
	//echo "<!--id_vendedor: $id_vendedor $tipo_comercial {$_POST['id']}-->";
	$ofertas = getOfertasById(555);
	//echo "<!--";print_r($ofertas);	echo"-->";
}else {
	$ofertas = getOfertasByTipo($id);
}
if ($tipo_comercial != 'interno') {
	syslog (LOG_INFO, __FILE__ . ":$tipo_comercial");	
}

$ofertasComerciales = array('interno'=>'','admin'=>'','externo'=>'' );
if ($ofertas != null) {
	foreach($ofertas as $key=>$oferta) {

		$cod_vendedor = $_SESSION['cod_vendedor']; 
	
		$tipoComercialOferta = $oferta->visibilidad;
		foreach (explode(':', $oferta->visibilidad) as $com) {
			if ($com == $cod_vendedor) {
				$tipoComercialOferta = $com;
				break;
			}
		}
		//$tipoComercialOferta = ( $oferta->visibilidad != '' ) ? $oferta->visibilidad : '';
		//syslog(LOG_INFO, "VISI [$cod_vendedor]:tp:$tipo_comercial : ".$tipoComercialOferta);
		
		$html_ofertas = "<div class='opt'><input type='radio' id='$key' class='radio_ofertas' name ='radio_ofertas' value='$oferta->id' onclick='setValores(this)'";
		$html_ofertas .= (isset($radio_ofertas) && $radio_ofertas ==$oferta->id) ? 'checked':'';
		$html_ofertas .=" /><label for='$key' class='label nombre_$oferta->id'>$oferta->nombre</label></div>";
		if ( $tipo_comercial == 'admin' )
		{
		    $ofertasComerciales['admin'] .= $html_ofertas;	
		}
		else if ($tipo_comercial == 'externo' )
		{
			$ofertasComerciales['externo'] .= $html_ofertas;
		}
		else if ( $tipoComercialOferta != 'externo' && $tipo_comercial == 'interno' )
		{

			//syslog(LOG_INFO, "VISI NEW [$cod_vendedor]:tp:$tipo_comercial => tco: ".$tipoComercialOferta);
			if( $tipoComercialOferta == '' ){
				$ofertasComerciales[$tipo_comercial] .= $html_ofertas;	
			}
			if( $tipoComercialOferta != '' && $tipoComercialOferta == $cod_vendedor ){
				$ofertasComerciales[$tipo_comercial] .= $html_ofertas;	
			}
			if( $_SESSION['tipo_comercial'] == 'admin' && $oferta->visibilidad != '' ){
				$ofertasComerciales['interno'] .= $html_ofertas;	
			}
		}
	}
}

if ($tipo_comercial == 'externo') {
	syslog(LOG_INFO, __FILE__ . ": $tipo_comercial  : {$oferta->visibilidad} - ". print_r($ofertas,true));	
}

$id_tipo_ofertas = "<input type='hidden' name='id_tipo_ofertas' value='$id'>";
$javascript = "<script>$('input[name=radio_ofertas]').change(function()
    {
    	var id_oferta = $(this).val();
    	$('#oferta_checked').val(id_oferta);
    	$('#id_oferta_checked_asociado').val($id);

    });
	$('input[name=radio_ofertas]').each(function () 
    {
        	var id_oferta = $(this).val();
          	$.ajax({
	        url: 'ajax/varios.php',
	        data: {'check_oferta':1,'id_oferta':id_oferta},
	        type: 'POST',
	        dataType: 'json',
	        success: function(data)
	        {
	            if(data==1)
	            {
	            	$('.c0n').trigger('click');
	            }
	        }
	      });
    });

	function setValores(e){
	  var id_contrato = e.value;
      $.ajax({
        url: 'ajax/varios.php',
        data: {'valores_fijos':1,'id_contrato':id_contrato},
        type: 'POST',
        dataType: 'json',
        success: function(data)
        {
            if(data.llamadas > 0){document.getElementById('llamadas').value=data.llamadas}
            	else{document.getElementById('llamadas').value=0}
            if(data.cubacel > 0){document.getElementById('cubacel').value=data.cubacel}
            	else{document.getElementById('cubacel').value=0}	
            if(data.nauta > 0){document.getElementById('nauta').value=data.nauta}
            	else{document.getElementById('nauta').value=0}			
        }
      });
		document.getElementById('tipo_recarga').value= '';
		if(id_contrato == 50)
      	{
          $('.box3').hide();
  
          for (var i = 0; i < 19; i++) 
          {
              $( '#num_'+i ).val('') ;
              $( '#value_'+i ).val('') ;
          };
      }
     }
</script>";

print $ofertasComerciales[$tipo_comercial].$id_tipo_ofertas.$javascript;
