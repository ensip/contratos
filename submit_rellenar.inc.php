<?php
$con_li = getConnLi ('getDataPre');
function checkIfMustNauta($hay_nautas,$hay_va_nauta,$oferta_nauta,$obligado_nauta)
{
    $checknauta = 0;
    $err = null;
    
    if ($oferta_nauta == 0 && ( $hay_nautas > 0 || $hay_va_nauta > 0 )) {   
        $err = Array('err'=>'Este contrato no acepta Recargas Nauta');
    }

    if ($oferta_nauta == 1) {
        if ($hay_nautas > 0 && $hay_va_nauta > 0) {
            $checknauta = 1;

	} else {
            if ( $hay_nautas > 0 || $hay_va_nauta > 0 ) {
                $err = Array('err'=>'Hay valores en nauta, revisalos');
            }
	}

        if ( $hay_nautas == 0 ) {
            if ($obligado_nauta == 1) {
                $err = Array('err'=>'Faltan valores Nauta');
            }
        }
    }

    return array('checknauta'=>$checknauta,'err'=>$err);
}

    /* vim: set ts=4 sw=4 sts=0 et smarttab ai : */
    /* La línea anterior pone settings de tabuladores a 4 espacios ara vim. Por favor, no tocar. */

//if(!isset($_SESSION)){session_start();}

    $extract_post = $_POST;
    extract($extract_post);

    $err = array();

    if( !isset($radio_ofertas) || $radio_ofertas == 0 )  {

        $err = Array('radio_ofertas'=>'No has seleccionado ninguna Oferta');
    
    } else {
    
	    $sql = "SELECT * FROM PrepagosJyc.contratos_ofertas WHERE id='$radio_ofertas' LIMIT 1";
            $res = $con_li->query ($sql);
    
	    if( $res->num_rows  > 0 ) {
		    $oferta = $res->fetch_assoc();

		    $id_o = $oferta['id'];
		    $nombre_oferta = $oferta['nombre'];
		    $es_prepago = $oferta['es_prepago'];
		    $divisa_tabla = $oferta['divisa'];
		    $tipo = $oferta['tipo'];
		    $oferta_cubacel = $oferta['cubacel'];
		    $oferta_nauta = $oferta['nauta'];  
		    $obligado_nauta = $oferta['obligado_nauta'];
		    $saldo_minimo = $oferta['saldo_minimo'];
		    $valor_fijo_nauta = $oferta['valor_nauta'];
		    $punto = $oferta['punto'];
		    $regalo_cuc = $oferta['regalo_cuc'];
		    $tarifas = unserialize($oferta['tarifas']);
		    $forzar_manual = $oferta['forzar_manual'];
		    $oferta['importe'] = $oferta['precio']; 
		    $oferta['valor_fijo_cubacel'] = $oferta['valor_cubacel'];
		    $oferta['valor_fijo_llamadas'] = $oferta['valor_llamadas'];
		    $oferta['valor_fijo_envio'] = $oferta['valor_envio'];
    
	    }
    }
    //echo "<pre>";print_r($oferta);echo "</pre>";
    /*CONTROL NAUTA*/
    $empty_nauta = array_filter($nauta_m);
    $empty_val_nauta = array_filter($value_n_);
    
    if( isset( $oferta_nauta ) ){
        
        $data_control_nauta = checkIfMustNauta(count($empty_nauta),count($empty_val_nauta),$oferta_nauta,$obligado_nauta);

        $checknauta = $data_control_nauta['checknauta'];
        $err_nauta = $data_control_nauta['err'];
        if ( $err_nauta != '' )
        {
            array_push($err, $err_nauta);
        }
        //CONTROL NAUTA 
	if( $checknauta == 1 && empty($err) ) {
		$hay_nombres_nauta = 0;
		$fallo_formato_email = 0;
		$fallo_cantidad_recarga_nauta = 0;
		$fallo_multiplo = 0;
		$cant_nauta = 0;
		$sum_recargas_nauta;
    
		foreach ($nauta_m as $key => $nombre_email_nauta) 
		{
			if($nombre_email_nauta != '')
			{
				$hay_nombres_nauta ++;
				$email_ok = validateEmail( $nombre_email_nauta.$dominio_nauta[$key] );
	    
				if($email_ok == 0)
				{
					$fallo_formato_email ++;
				}
				if( $value_n_[$key] == '' || $value_n_[$key] < '10' )
				{
					$fallo_cantidad_recarga_nauta ++;
				}
			}
			if( $nombre_email_nauta != '' && $fallo_formato_email == 0 &&  $fallo_cantidad_recarga_nauta == 0 )
			{
				$es_multiplo = checkRecargaMultiplo( $value_n_[$key] , $oferta['recarga_nauta'] );
				if( $es_multiplo == 1 ){ 
					$cant_nauta ++; 
					$recargas[] = $value_n_[$key];
					$sum_recargas_nauta += $value_n_[$key];            
				}                }
		}
		/* Control errores. */
		if( $fallo_formato_email == 1 ){ $err['error_format_nauta'] = Array('err'=>'Email Nauta mal formateado'); }        
		if( $fallo_cantidad_recarga_nauta == 1 ){ $err['error_cant_nauta'] = Array('err'=>'Falla cantidad recarga Nauta'); }
		if( $es_multiplo == 0 ){ $err['error_multiplo_cant_nauta'] = Array('err'=>'Falla cantidad recarga Nauta'); }
	}
    }
    /*FIN CONTROL NAUTA*/
    
    /*Tipo 14-> para activacion SIMS*/
    
    if( isset($tipo_oferta) && ($tipo_oferta == 'act sim' || $tipo_oferta == 'ACT SIM'))
    {
        if( isset($oficina_sim) && $oficina_sim == '0')
        {
            $err['act_sim_1'] = Array('err'=>'No has seleccionado ninguna oficina');
        }
        if( isset($id_card_sim) && $id_card_sim == '')
        {
            $err['act_sim_2'] = Array('err'=>'Carné de identidad obligatorio'); 
        }
        else
        {
            $id_card_sim = trim($id_card_sim);
        }
        if(!is_numeric($id_card_sim) )
        {
            $err['act_sim_3'] = Array('err'=>'Carné de identidad debe ser un número'); 
        }
        if(strlen(trim($id_card_sim)) != 11)
        {
            $err['act_sim_4'] = Array('err'=>'Longitud Cedula indentidad errónea (11)'); 
        }
        if($nombre_sim == '' || $apellido_1_sim == '' || $apellido_2_sim == '')
        {
            $err['act_sim_5'] = Array('err'=>'Nombre y apelllidos (los 2) para la activación obligatorios'); 
        }
    }
   
    //CHECK C0/Nuevos
    $array_c0n = checkIfCON($_POST);
    if ( count($array_c0n) > 0 )
    {
        $err =  CheckCamposC0n($array_c0n);
    }
    
    //CHECK CAMPOS DIR/PARA/TEL
    if ( isset($oferta['datos']) && $oferta['datos'] == 0 ) 
    {
        $datos[] = $oferta['id'];
        //------------------------->>>>>>>>> REVISAR
        if( $oferta['regalo_cuc'] > 0 ) {

            $ids_regalo_cucs[] = $oferta['id'];
        }
    }

    if( isset($radio_ofertas) && isset($oferta['datos']) && $oferta['datos'] > 0 ) 
    {
        if( $para == '' || $dir_para == '' || $telf_para == ''  )
        {
            $err['radio_ofertas'] = Array('err'=>'Todos los campos (Para, Direccion, Telf) tienen que estar rellenados');
        }
    }
    //------------------------->>>>>>>>> REVISAR
    if( $llamadas == '' && isset($radio_ofertas) && in_array($radio_ofertas, $ids_regalo_cucs) )
    {
        $err['llamadas_Regalos_cucs'] = Array('err'=>'No hay valor para Cubanacard');
    }

    if ( isset( $oferta['ofertas_promos'] ) && $oferta['ofertas_promos'] > 0 )
    {
        if(!isset($radio_promo))
        {
            $err['radio_promos'] = Array('err'=>'No hay una promocion asociada a la oferta.');
        }
    }

    /* SI ES UN CONTRATO TIENE EL CAMPO ENVIO DINERO A 1 comprueba que haya marcado la opción */
    if( isset( $oferta['envio_dinero'] ) ) $co_envio_dinero = $oferta['envio_dinero'];
    if( isset( $oferta['cubacel'] ) ) $co_cubacel = $oferta['cubacel'];
    if( isset( $oferta['cubanacard'] ) ) $co_cubanacard = $oferta['cubanacard'];
    if( isset( $oferta['nauta'] ) )$co_nauta = $oferta['nauta'];
    /*
        Codifico según tabla cada valor corresponde a una disposicion de los 3 elementos
        cub|cn|ed
        000-1 - 001-2 - 010-3 - 011-4 - 100-5 - 101-6 - 110-7 - 111-8
    */
    $opciones_pack = array('valor' => 1,'desc' => 'ninguno');

    /* SI EL CAMPO ENVIO DINERO DE LA OFERTA ESTA A 1, OBLIGA A RELLENAR TODOS ESTOS CAMPOS */
    if( isset($co_envio_dinero) ){
        if( $co_envio_dinero == 1)
        {
            if( $para == '' || $dir_para == '' || $telf_para == '' || $comercial_para == 'nan' )
            {
                $err['radio_ofertas'] = Array('err'=>'Todos los campos (Para, Direccion, Telf, Comercial) tienen que estar rellenados');
            }
            if( $comercial_para == "3" && $ciudad_para == '' ){
                $err['radio_ofertas_2'] = Array('err'=>'Para resto de Cuba obligado poner ciudad');
            }
   
            if( $co_cubacel == 0 && $co_cubanacard == 0 ){ $opciones_pack['valor'] = 2;$opciones_pack['desc'] = 'Envio dinero';}
            if( $co_cubacel == 0 && $co_cubanacard == 1 ){ $opciones_pack['valor'] = 4;$opciones_pack['desc'] = 'Cubanacard y Envio Dinero';}
            if( $co_cubacel == 1 && $co_cubanacard == 0 ){ $opciones_pack['valor'] = 6;$opciones_pack['desc'] = 'Cubacel y Envio Dinero';}
            if( $co_cubacel == 1 && $co_cubanacard == 1 ){ $opciones_pack['valor'] = 8;$opciones_pack['desc'] = 'Cubanacard, Cubacel y Envio Dinero';}

            //$exentos = array(275,276,278,279); && !in_array($id_o, $exentos)
            if (!array_key_exists("moneda_envio_dinero",$_POST) )
            {
                $err['moneda_envio_dinero'] = Array('err'=>'Falta la moneda del Envio de Dinero');
            }
        }
        if( $co_envio_dinero == 0)
        {
            if( $co_cubacel == 0 && $co_cubanacard == 1 ){ $opciones_pack['valor'] = 3;$opciones_pack['desc'] = 'Cubanacard';}
            if( $co_cubacel == 1 && $co_cubanacard == 0 ){ $opciones_pack['valor'] = 5;$opciones_pack['desc'] = 'Cubacel';}
            if( $co_cubacel == 1 && $co_cubanacard == 1 ){ $opciones_pack['valor'] = 7;$opciones_pack['desc'] = 'Cubanacard y Cubacel';}
        }
    
        if(( $co_envio_dinero == 1 && $envio_dinero == '' ) || 
          ( $co_cubacel == 1 && ( $cubacel == '' || $cubacel == 0 ) ) || 
            ( $co_cubanacard == 1 && isset($llamadas) && ($llamadas == ''||$llamadas == 0)) )
        {
            if( $co_cubacel == 1 && ($cubacel == ''|| $cubacel == 0) )
            {
                $err['opciones_pack'] = Array('err'=>'No hay valor para Cubacel');
            }
            if( $co_cubanacard == 1 && ($llamadas == '' || $llamadas == 0) )
            {
                $err['opciones_pack'] = Array('err'=>'No hay valor para Llamadas');
            }
        }
    }
      
    /* PARA QUE NO MIRE SI TIENE QUE SELECCIONAR INGRESO */
    if( !isset($ingreso) && isset($radio_ofertas) && $oferta['no_ingreso'] == 0 )
    {
       $err['radio_ingreso'] = Array('err'=>'No has seleccionado ningun ingreso');
    }
    else if( isset($ingreso) && $ingreso == 'cuenta' )
    {
        if( $importe_cuenta == '')
        {
            $err['importe_cuenta'] = Array('err'=>'No hay importe del ingreso cuenta');
        }
        else if( !is_numeric($importe_cuenta))
        {
            $err['importe_cuenta'] = Array('err'=>'El importe en cuenta no es numerico');
        }
        else
        {
            $contrato['importe'] = $importe_cuenta;
        }
        if( $comprobante == '')$err['radio_ingreso'] = Array('err'=>'Tienes que poner el comprobante del ingreso en cuenta');
    }
    else if( isset($radio_ofertas) && $oferta['no_ingreso'] == 0 ) {

        if($comprobante == '')
        {
            $err['comprobante'] = Array('err'=>'No hay un comprobante TPV');
        }
    }
    if($movil == '')
    {
        $err['movil_cliente'] = Array('err'=>'EL MOVIL ES OBLIGATORIO');
    }
    if( !is_numeric(trim($movil)) )
    {
        $err['movil_cliente'] = Array('err'=>'El movil del cliente tiene que ser un numero');

    } else if(strlen( trim($movil) ) < 9) {
       $err['movil_cliente'] = Array('err'=>'El movil es incorrecto');
    } else {
	$movil = cleanMovilEsp($movil);
    }  
    
	if ( isset( $n_cardcodes ) && isset( $n_cliente ) && ( $n_carcdodes == 0 || $n_cliente == 'no_cc' ) )
	{
		$n_cliente = '';
	}

	$prepago = 3;
	$pin = '';
    	$n_cliente = '';
	$check_numeros = false;
	$text_prepago = '';    
	$nombre = (isset($_POST['nombre']) && !empty($_POST['nombre']) ? $_POST['nombre'] : '');
	
	if ( $movil != '' ) {
	
		$recruit = new RecruitNewTelfs( $movil, $_SESSION['cod_vendedor'] );
		$recruit->recruit();
	
		$datos_usuario_contratos = new DatosUsuarioContratos($movil);
		$cardcodes = $datos_usuario_contratos->getCardCodes();
		$nombre_sap = $datos_usuario_contratos->getNombre();
		if ( (empty($nombre) || (!empty($nombre_sap) && !empty($nombre) && $nombre_sap !== $nombre) ) && !empty($nombre_sap)) {
			syslog (LOG_INFO, __FILE__ . ":empty nombre, setting nombre_sap:[$movil] - $nombre_sap");
			$nombre = $nombre_sap;
		}
		$n_cliente = $datos_usuario_contratos->getN_cliente();
		$prepago = $datos_usuario_contratos->getPrepago();
		$pin = $datos_usuario_contratos->getPin();
		$check_numeros = $datos_usuario_contratos->getCheckNumeros();
        	$text_impagado = $datos_usuario_contratos->textoImpagado();//ok
		$text_prepago = $datos_usuario_contratos->textoPrepago();
        	$saldo = $datos_usuario_contratos->creditUser();
	        $datos_tarifa = $datos_usuario_contratos->getDatosTarifa();
	        $dataPost = $datos_usuario_contratos->getDatosPostPago(); 

		syslog(LOG_INFO, __FILE__ . ": [$movil] ".
			"Cant Cardcodes:".count($cardcodes) . 
			' - prepago: ' . $prepago.
			" - pin : $pin ".
			//"- check_numeros : " . $check_numeros .
			"- text_prepago: ".$text_prepago
		);
    }

    syslog(LOG_INFO, __FILE__ . ": [$movil][cardcode:$n_cliente][oferta_id:". (isset($radio_ofertas) ? $radio_ofertas : 'no-radio') ."][prepago:$prepago][oferta:".(isset($tipo) ? $tipo : 'no-oferta')."]");

    $automatizarlo = 1;
    if ( isset($radio_ofertas) && $prepago == 0 && $es_prepago == 1 )
    {
        $err['no_name'] = Array('err'=>"Usuario Postpago no puede tener un contrato Prepago");
        $automatizarlo = 0;
    }
    if ( isset($radio_ofertas) && $prepago == 1 && $es_prepago == 0 )
    {
        $err['no_name'] = Array('err'=>"Usuario Prepago no puede tener un contrato Postpago");
        $automatizarlo = 0;
    }
        
    /* si tiene mas de 1 cardcode y no ha seleccionado el cc del select */
    if( isset($cardcodes) && $n_cliente == "no_cc" ) { 
	    $cant_cardcodes = count($cardcodes);
	    if ($cant_cardcodes > 1) {
		    syslog (LOG_INFO, __FILE__ . ":muchos cardcodes, unset nombre:[$movil]");
		    unset($nombre); 
	    }
    }

    /* si no hay nombre de cliente y no ha encontrado registros asociados al num movil */
    if( !isset($cardcodes) && ( count($cardcodes) == 0 || count($cardcodes) > 1 ) && $nombre == '')
    {
        $err['no_name'] = Array('err'=>"Debe rellenar el nombre/razon social.");
    }
    $id_comercial = '';
    $especial = 0;

    if ( $pin != '' ) {

        $id_tarifa = 0;

        if( !empty( $datos_tarifa ) ){         
            $dt = $datos_tarifa;
            $especial = ( isset( $dt['especial'] ) ) ? $dt['especial'] : 0;
            $id_tarifa = ( isset( $dt['id_tarifa'] ) ) ? $dt['id_tarifa'] : $id_tarifa;
            $nombre_Tarifa = ( isset( $dt['nombre'] ) ) ? $dt['nombre'] : '';
            $last_recharge = $dt['last_recharge'];
            $last_call = $dt['last_call'];
            $id_comercial = $dt['id_comercial'];
            $id_usuario_prepago = $dt['id'];
            $saldo_recarga = ( isset( $dt['saldo_recarga'] ) ) ? $dt['saldo_recarga'] : 0 ;
            
            unset($dt);
            
            //quito nombres de tarifa por orden de vero 30/06/17
            // $array_change_049 = array(31,32,33,34);if ( in_array($id_tarifa, $array_change_049) ) { $nombre_Tarifa = 'Cuba 0,49';}
            //$array_change_068 = array(26,27,28,35);if ( in_array($id_tarifa, $array_change_068) ) { $nombre_Tarifa = 'CUBA 0,68'; }
            //$array_change_otras = array(18,21);if ( in_array($id_tarifa, $array_change_otras) ) { $nombre_Tarifa = ''; }
        }

        if( isset( $tarifas ) ){ 
           if( count($tarifas) > 0 && is_array($tarifas)){
               syslog(LOG_INFO, __FILE__ .": [$movil] [$radio_ofertas][id_tarifa: ".$datos_tarifa['id_tarifa']."][tarifas: ".serialize($tarifas)."]");
               syslog(LOG_INFO, __FILE__ . ": [$movil] [datos_tarifa:".json_encode($datos_tarifa)."][pin:$pin]");
           }
           if( is_array($datos_tarifa) && is_array($tarifas) ){
               if( !in_array($datos_tarifa['id_tarifa'], $tarifas) && !empty($tarifas) ){
                  $warning[] = array('warn'=>'La tarifa del usuario no coincide con la tarifa para la oferta.');
               }
           }
           if (is_array($tarifas) && $pin == ''){
               $warning[] = array('warn'=>'Oferta no aplicable al tipo de tarifa del cliente.');  
           }
        }
        
        /* control saldo minimo, igual que en funciones_auto_contrato.php */
        if ( isset( $saldo_minimo ) ){

            if( $saldo_minimo > 0 && $pin > 0 && $pin != '' && $pin != null )
            {
                if ( $prepago == 0 ){ $id_pin = $pin; }
                else{ $id_pin = $id_usuario_prepago; }

                $saldo_user = $saldo->saldo;
                syslog(LOG_INFO, __FILE__ . ": [$movil] saldo_user($saldo_user) > saldo_minimo:($saldo_minimo) [pin:$pin]");
                if ( $saldo_user > $saldo_minimo )
                {
                    $err['no_name'] = Array('err'=>"El cliente supera el saldo máximo para esta oferta");
                }
            }   
            else if ( $saldo_minimo > 0 && ($pin == '' || $pin == null || $pin == 0) )
            {
                $err['no_name'] = Array('err'=>"Oferta no válida para el tipo de cliente");        
            }
        }
    }
    $array_control_cubacel = array(138,144,179,181,182,136,183,184,149,186,190);  
    if ( isset($id_o) &&  in_array($id_o, $array_control_cubacel) )
    {
        for( $i = 0 ; $i <= $cant_nums ; $i++ )
        {
            if($value[$i] != $oferta['recarga'] && $value[$i] > 0 )
            {
                $err['no_int'][] = Array('err'=>"Valor recarga incorrecto");
            }
        }
    }
    //compruebo que hayan numeros a recargar
    $vacios = 0;
    $cant_vacios = 0;
    $num_recargas = 0;
    $sum_recargas = 0;
    $nums_a_recargar = null;
    for( $i = 0 ; $i <= $cant_nums ; $i++ ) {
	    if (isset($num[$i]) && $num[$i] != '') {
		    if(!is_numeric( trim($num[$i]) ) || strlen(trim($num[$i])) < 8) {
			    $err['no_int'][] = Array('err'=>"El numero $num[$i] no es correcto.");
		    }
		    if($value[$i] == '' || $value[$i] == 0) {
			    $err['no_int'][] = Array('err'=>"El monto $value[$i] del num a recargar $num[$i] esta vacio.");
			    $vacios = 1;
		    }
		    if (is_numeric(trim($num[$i]))) {
			    $nums_a_recargar[] = '53'.trim($num[$i]);
		    }
		    $vacios = 0;
	    }
    	    //compruebo el monto del movil a recargar

	    if( isset($value[$i]) && $value[$i] != '') {
		    if(!is_numeric( $value[$i] )) {
			    $err['no_int'][] = Array('err'=>"El monto $value[$i] no es numerico.");
		    } else {
			    $recargas[] = $value[$i];
			    $num_recargas++;
			    $sum_recargas += $value[$i];
		    }
	    }
	    if (isset($num[$i]) && $num[$i] == '' && $value[$i] == '') { 
		    $cant_vacios ++;
	    }
    }

    syslog(LOG_INFO, __FILE__ . ": [$movil] recargas: cant_vacios=$cant_vacios , cant_nums:$cant_nums");

    if ($vacios == 0) {
        $contrato['recargas'] = $recargas;
        
	if ($sum_recargas > 0 ) { 
		$contrato['sum_recargas'] = $sum_recargas; 
	}

        if (isset($id_o) && $id_o == 190 && $num_recargas > 1) {
            $err['cubacel'] = Array('err'=>'Solo 1 recarga para este contrato');
        }        
    }

    //cambio 29/19 para que si es cubacel y no tiene recargas salte    
    if( $oferta_cubacel == 1 && ($vacios == 1 || $cant_vacios > $cant_nums) ) {
        $err['cubacel'] = Array('err'=> utf8_decode('No hay ningún número a recargar, esta oferta incluye Cubacel'));
    }
    //NUMEROS REFERIDOS
    if( isset($radio_ofertas) && $radio_ofertas == 58)
    {
        $cant_refer = count($ref);

        for ($i=0; $i < $cant_refer ; $i++) 
        { 
            if($ref[$i] != '') $cant_n_refer ++;
        }
        
        if( $num_referido == '' )$err['referidos'] = Array('err'=>'El numero a recargar está vacío.');
        if( $cant_n_refer ==  0 )$err['referidos'] = Array('err'=>'No has puesto ningun referido.');
        if( $val_referido == '' )$err['referidos'] = Array('err'=>'Falta la cantidad a recargar.');
    }

    // Gestión comprobante:
    // - Pega datos comprobante de BBDD. Campos:
    //   
    //   Pago realizado con éxito
    //   Tipo Operación: Autorización
    //   Importe: 10 EUR
    //   Número pedido: 91184_TK_86101149d301ba737a547615c05e1a0f
    //   Operación Autorizada con Código: 378505/050830083427927458102878291704
    //   Código de Error: 0
    //   ID pagos referencia: 19
       
    if( isset($oferta) && $cod_vendedor_sesion == '21'){ /*echo "<pre>PARA DEBUG"; print_r($comprobante); echo "</pre>";*/  }

    $divisa = (isset ($oferta ['divisa'])) ? $oferta['divisa'] : 0;

    $count_match = 0;
    $num_pedido_tk = '';
    $num_pedido_cbn = '';
    $num_pedido_network = '';
    $match_tk = false;
    $match = array();

    if (!isset($tipo_comprobante)) {
	    $tipo_comprobante = '';
    }

    $check_de_nuevo = 1;
    if (!empty($_POST['num_pedido']) && preg_match('/'.trim($num_pedido).'/', $comprobante)) {
	    $check_de_nuevo = 0;
    }
 
    $log_num_pedido = "[num_pedido:$num_pedido] vs [comprobante:$comprobante] [check_de_nuevo:$check_de_nuevo] ingreso: $ingreso"; 
	
    syslog(LOG_INFO, __FILE__.": [$movil] log_num_pedido: ".$log_num_pedido);

	if ((!empty($comprobante)) && isset($ingreso) && $ingreso == 'tpv' ) {
		
		$es_match = 0;
    		$num_pedido_comprobante = '';
	    	$user_token = '';
		$tipo_comprobante_token = '';
		$tabla_banco_tokens = '';
		$es_network = false;

		$search_token = token::search($comprobante);
    
		if (!is_null($search_token)) {
    
			$es_match = $search_token->es_match();
			if ($es_match) {
				$campo_order_token = $search_token->get_campo_order();
				$tipo_comprobante_token = $search_token->get_tipo_comprobante();
				$tabla_banco_tokens = $search_token->get_tabla_banco_tokens();
				$token = $search_token->get_token();
				$token_original = $search_token->get_token_original();
				$user_token = $search_token->get_user_token();
				$num_pedido_comprobante = $search_token->get_num_pedido(); 					    

				$log_search = sprintf("token_original:%s, token: %s, user_token: %s, num_pedido:%s, tipo_comprobante:%s, tabla:%s",
					$token_original, $token, $user_token, $num_pedido_comprobante, $tipo_comprobante_token, $tabla_banco_tokens); 
				syslog(LOG_INFO, __FILE__.": [$movil] search_token: " . $log_search);
				if ($tipo_comprobante_token == 'NETWORK') {
					$es_network = true;
				}
			}
		}
		if (!empty($user_token) || $es_network) {
			
			$es_jyctel = 0;
			
			$and_id_usuario = '';
			if (!$es_network) {
				$and_id_usuario = " and id_usuario = '$user_token'";
			}	

		    	// Nuevo sistema comprobante (cardcode|ref_previa|id_usr_ensip)_TK_(token)
			$sql_refp = "select * from PrepagosJyc.payment_tokens where token like '$token' " . $and_id_usuario;
			$res = $con_li->query($sql_refp);
	   
			$hay_comprobante = 0;
			$bd_payment_tokens = '';

			if ($res && $res->num_rows > 0) {
				$es_jyctel = 1;
				$bd_payment_tokens = 'PrepagosJyc.';
				$hay_comprobante = 1;
				$db_consulta = DB2;
				$con_li_pt = $con_li;
	    
			} else {
	
				$con_ensip = getConnLi('getDataEnsip');
				$con_li_pt = $con_ensip;
				$sql_refp = "select * from payment_tokens where token like '$token' " . $and_id_usuario; 
				$res = $con_ensip->query($sql_refp);
	
				if ($res->num_rows > 0) {
					$hay_comprobante = 1;
				}
			}
	    
			if (!$hay_comprobante) {
				$con_cbn = getConnLi('getDataCbn');
				$sql_refp = "select * from payment_tokens where token like '" . $token . "' and ( id_usuario like '" . $user_token . "' or id_usuario = 0)";
				$res = $con_cbn->query ($sql_refp);
	
				if ($res->num_rows > 0) {
					$hay_comprobante = 1;
				}
			}
			//echo "<br> HAY COMPROBANTE: $sql_refp res: $hay_comprobante $num_pedido<br>";
			syslog(LOG_INFO, __FILE__.": [$movil] token:$token , num_pedido:$num_pedido , hay_comprobante $hay_comprobante");
	    
			if ($hay_comprobante) {
		
				$comprobante = "";
		
				$reg = $res->fetch_object();
				
				$texto_pago_comprobante = "Pago fallido \n\nTipo Operación: Denegada \n\n";	
				if ($reg->pagado) {
					$texto_pago_comprobante = utf8_decode("Pago realizado con éxito \n\nTipo Operación: Autorización \n\n");
				} 
		
				$comprobante .= $texto_pago_comprobante;
				$comprobante .= "Importe: ".preg_replace("/\./", ",", ($reg->cantidad+0))." ".$reg->nombre_divisa." \n\n";
				
				if ($reg->nombre_divisa == "EUR") {
					$divisa_tabla = 1;
		
				} else if ($reg->nombre_divisa == "USD") {
					$divisa_tabla = 2;
				}
				
				//changed for search_token new object actions
				if (isset($num_pedido_comprobante) && !empty($num_pedido_comprobante)) {
					$comprobante .= $num_pedido_comprobante;
				}
				$reg_pt = null;	
				if (!empty($num_pedido_comprobante) && !$es_network) { 

					$sql_pt = sprintf("select * from %s%s where %s = '%s'",
						$bd_payment_tokens,
						$tabla_banco_tokens,
						$campo_order_token,
						$num_pedido_comprobante
					);
					echo "<!--";echo $sql_pt; echo"-->";

					$res_pt = $con_li_pt->query($sql_pt);
					$cant_rows_pt = $res_pt->num_rows;
					$reg_pt = $res_pt->fetch_object();
				}
				
				if ($es_jyctel) {
		    
					$cod_error = "";
					$id_pagos_referencia = "";
		
					if (!is_null($reg_pt)) {
			
						$id_pagos_referencia = $reg_pt->id_pagos_referencia;
						$cod_error = $reg_pt->DS_ERROR_ID;
			
						if ($reg->pagado) {
							$comprobante .= " Operación Autorizada con Código: ".$reg->id_transaccion." \n\n";
						}
			
						$comprobante .= "Código de Error: $cod_error \n\n";
						$comprobante .= "ID pagos referencia: $id_pagos_referencia \n\n";
					}
				}
			}
		}
	
		$digitos = '';
		$es_genius = false;
		
		syslog(LOG_INFO, __FILE__ . ": [$movil] [Divisa:$divisa_tabla] [Comprobante: $comprobante]");
		
		if (isset($num_pedido_comprobante) && !empty($num_pedido_comprobante)) {

			syslog(LOG_INFO, __FILE__ . ": [$movil] comprobante num_pedido: ". $tipo_comprobante_token);

			$num_pedido = $num_pedido_comprobante;
		}	

		//SI LA DIVISA ES DOLARES DE LA OFERTA
		if (isset($divisa_tabla) && $divisa_tabla == 2) {
	    
			syslog(LOG_INFO, __FILE__ . ": [$movil] comprobante divisa-usd");
			$data = explode('Fecha:', $comprobante);
		
			 if ((!isset($num_pedido_comprobante) || empty($num_pedido_comprobante)) && isset($data[0])) {
				syslog(LOG_INFO, __FILE__ . ": [$movil] comprobante otro_num_pedido-usd");
				$dataa = explode('pedido:', $data[0]);
				if(isset($dataa[1])) {
					$num_pedido = trim($dataa[1]);
				}
			}

			//si existe el numero_pedido_tk pero no tiene el formato con _TK_ doy error
			if ($num_pedido_comprobante && !$es_match) {
				$err['n_pedido'] = Array('err'=>'Comprobante incorrecto (USD)');
			}
				    
			$moneda = 'Dólares';
			if(isset($data[1])) {
				$dataaa = explode('Url Comercio', $data[1]);
				$dataaaa = explode('************', $dataaa[0]);
				$digitos = trim($dataaaa[1]);
			}
	    		//para sacar importe
	    
			if (strpos($comprobante,'$') !== false || strpos($comprobante,'USD') !== false) {
	    
				$moneda = '$';
				if (strpos($comprobante,'USD') !== false) {
					$moneda = 'USD';
				}
	    
				$parte_1 = explode($moneda,$comprobante);
				$importe = explode('Importe:',$parte_1[0]);
				$importe = trim($importe[1]);
				$contrato['importe'] = str_replace(',', '.', $importe);
	    
			} else {
				
				syslog(LOG_INFO, __FILE__ . ": [$movil] comprobante sin divisa usd, comprobante $comprobante");
				
				$err['n_pedido'] = Array('err'=>'Comprobante incorrecto (no contiene divisa)');
			}
	    
			syslog(LOG_INFO, __FILE__ . ": [$movil] [contratos_usd:$importe]"); 
	
		} else { //SI ES divisa EURO
			
			syslog(LOG_INFO, __FILE__ . ": [$movil] comprobante divisa-eur");
	    
			if (!isset($num_pedido_comprobante) || empty($num_pedido_comprobante)) {
	
				syslog(LOG_INFO, __FILE__ . ": [$movil] comprobante otro_num_pedido-eur");
				$np = explode('mero pedido:', str_replace(array('&nbsp;', "\n", "    "), '', htmlentities($comprobante)));
	
				if(isset($np[1])) {
	
					$np2 = explode('mero Tarjeta:',$np[1]);
					
					if (isset($np2[0]) && preg_match('/N&uacute;/m', htmlentities($np2[0]),$matches)) {
						syslog(LOG_INFO, __FILE__ . ": [$movil] np20:". htmlentities($np2[0]));
						$np3 = explode('N', $np2[0]);

						$np2[0] = $np3[0];
					}
					syslog(LOG_INFO, __FILE__ . ": [$movil]divisa:$divisa_tabla , np2:". $np2[0]);
					if($divisa_tabla == 1) {
	
						$num_pedido = trim($np2[0]);

						if(!is_numeric($num_pedido)) {
							$np3 = explode(' ', $num_pedido);

							$num_pedido = trim(str_replace('N&uacute;', '', $np3[0]));
						}
	
					} else { 
						$np3 = trim($np2[0]); 
						$np4 = explode(' ', $np3);
	
						$num_pedido = trim(str_replace('N&uacute;', '', $np4[0]));
						//$num_pedido = trim($np4[0]);
					}
				}
	
				if(!is_numeric(trim($num_pedido))) {
					syslog(LOG_INFO, __FILE__ . ": [$movil] [NUM_PEDIDO:$num_pedido], comprobante $comprobante, np:".serialize($np));
					$np5 = explode('Fecha', $num_pedido);
					$num_pedido = trim($np5[0]);
	
					if(!is_numeric($num_pedido) && isset($ingreso) && $ingreso == 'tpv') {
						//$err['num_pedido'] = Array('err'=>'Numero pedido incorrecto, no numerico');
					}
				}
			}
	
			$comprobante = eregi_replace("[\n|\r|\n\r]",'',$comprobante); //viene de POST
			$moneda = 'EUR';
	
			if (strpos($comprobante,'$') !== false or strpos($comprobante, 'USD') !== false) {
				$moneda = '$';
			}
	
			if( substr_count ($comprobante,$moneda) == 0 ) {
				$importe = 0;
				$err['comprobante'] = Array('err'=>'Comprobante Erroneo FALTA el importe '.$moneda);
			}
			//para sacar los digitos y el importe del pago en euros
			$comprobante_f = explode(':',$comprobante);

			foreach ($comprobante_f as $campos) {

				$comprobante_fi[] = trim($campos);
				if (strpos($campos,$moneda) !== false) {

					$importe = explode($moneda,$campos);
					$importe = trim($importe[0]);
					$importe_1 = str_replace(',', '|', $importe);
					$importe_2 = str_replace('.', '', $importe_1);
					$importe = str_replace('|', '.', $importe_2);
					$contrato['importe'] = str_replace(',', '.', $importe);
				}

				if ( $moneda == 'EUR' && strpos(trim($campos),'************') !== false) {

					$digito_ssss = trim($campos);
					$digito_sss = substr($digito_ssss,12);
					$digito_ss = explode(' ',$digito_sss);
					$d_f = explode('Fecha',$digito_ss[0]);
					$digitos = $d_f[0];
				} else if (strpos($campos,'Fecha') !== false) {
					$digitos_ = explode(' ',$campos);
					$digitos = trim($digitos_[0]);
				}

				if (strpos($campos, 'GENIUS') !== false) {
					$es_genius = true;
					if (preg_match('/^014443238-/m', $comprobante, $matches) == false) {
						$quitar_comercio = true;
						$num_pedido = '014443238-' . $num_pedido;
					}
      					//php_mailer($comprobante . " : num_pedido" . $num_pedido, 'comprobante genius', 'diego@jyctel.com', 'contratos@jyctel.com');
				}
				if (strpos($campos, '348929233') !== false) {
					if (preg_match('/^348929233-/m', $comprobante, $matches) == false) {
						$quitar_comercio = true;
						$num_pedido = '348929233-' . $num_pedido;
					}
				}
			}

			// si moneda comprobante es USD y la divisa de la oferta es 1 EUR 
		      //  FUERZO TEMPORALMENTE A QUE NO COMPRUEBE LA OFERTA 355 , LA DIVISA
			
			if ( isset($id_o) && $id_o != 355 ){
				if( $moneda == '$' && $divisa_tabla == 1) {
					$err['comprobante'] = Array('err'=>'Comprobante divisa incorrecta con la oferta');
				}        
			}
		}
		$comprobante_check_comercio = str_replace("\t", '', $comprobante);	
		//control comercio
        	if (preg_match('/digo comercio:/', $comprobante_check_comercio)) {
	
			$nc = explode('comercio:', $comprobante_check_comercio);
	
			if (isset($nc[1])) {
				$nc2 = explode('Terminal', $nc[1]);
				if (isset($nc2[0])) {
					$comercio = trim($nc2[0]);    
				}
			}
		}

		if (isset($quitar_comercio)) {
			$comercio = '';
		}
		if (isset($comercio)) {
			syslog(LOG_INFO, __FILE__ .":[$movil] comercio:".$comercio);
		}
	
		$and_genius = '';
		if ($es_genius) {
			$and_genius = "and comprobante_tpv like '%GENIUS%'";
		}
		
		syslog (LOG_INFO, __FILE__ . ": [$movil] num_pedido: ".$num_pedido . ':');  

		//Compruebo si el numero de pedido ya existía
		//quito divisa del control de numero de pedido repetido: incidencia 2/8/17:Diego
		if (!empty($num_pedido)) { 
			//$sql = "SELECT * FROM PrepagosJyc.`contratos` WHERE (num_pedido LIKE '$num_pedido' || comprobante_tpv like '%$num_pedido%') AND ingreso IN ('tpv','cuenta') " . $and_genius;
			$sql = "SELECT * FROM PrepagosJyc.`contratos` WHERE num_pedido LIKE '$num_pedido' AND ingreso IN ('tpv','cuenta') " . $and_genius;
			$res = $con_li->query($sql);

			if ($res->num_rows > 0) {
				syslog (LOG_INFO, __FILE__ . ": [$movil] pedido repetido: ".$num_pedido . ': ['.$_POST['num_pedido'].'] '. $sql);  
				$err['pedido'] = Array('err'=>'Numero pedido <b>'.$num_pedido.'</b> repetido');
			}
		}
	
		if (strpos($comprobante,'denegada') !== false || strpos($comprobante,'Denegada') !== false
			|| strpos($comprobante,'DENEGADA') !== false
			|| strpos($comprobante,'No se puede realizar la operación') != false ) {
				$importe = 0;
				$err['comprobante'] = Array('err'=>'Comprobante Erroneo.');
			}
	}
	//FIN COMPROBANTE TPV
    
    //SI ES ENVIO DE DINERO COMPRUEBO PARA SACAR EL VALOR EXACTO
    if( isset($oferta['valor_fijo_envio']) && $oferta['valor_fijo_envio'] != 0 ) {
	    $envio_dinero = $oferta['valor_fijo_envio'];

    } else {
    
        //control oferta entradas 11/3/2019
        $dato_entrada_ok = null;

	if( isset($id_o) && $id_o == 540 ){
		if ( isset( $_POST['entradas'] ) ){
			$entradas_post = $_POST[ 'entradas' ];
			foreach( $entradas_post as $i_entrada => $datos_entrada ){
				foreach( $datos_entrada as $dato_entrada ){
					if( $dato_entrada != '' ){
						$dato_entrada_ok[$i_entrada] ++;
					}
				}
			}
		}
		if( $dato_entrada_ok != null ){
			foreach( $dato_entrada_ok as $entrada => $cant ){
				if( $cant != 4 ){
					$err['entradas'] = Array('err'=>'Campos entradas incompletos.');
					break;
				}
			}
		}
		if( $id_o == 540 && $dato_entrada_ok == null ){
			$err['entradas'] = Array('err'=>'Campos entradas incompletos.');
		}
	}

	$envio_dinero = 0;
	if( isset($co_envio_dinero) && $co_envio_dinero == 1 ) {
            //check comercio comisionado
		if ($comercial_para != 'nan') {   
			$importe_ed = $importe;
			if ( $importe_cuenta > 0 ) {
				$importe_ed = $importe_cuenta;
			}
			if ($importe_ed > 0){
				
				$data_ed['importe'] = $importe_ed;
				$data_ed['comercial_envio_dinero'] = $comercial_para;
				$data_ed['tipo_conversion'] = 'a_eur';
				$data_ed['tipo_cambio'] = 'eur_cuc';

				if ( $oferta['divisa'] == 2 ){
					$data_ed['tipo_conversion'] = 'a_usd';
					$data_ed['tipo_cambio'] = 'usd_cuc';
				}
				$row_tipo_cambio = getTipoCambio("WHERE tipo_cambio = '{$data_ed['tipo_cambio']}'");
				$valor_cambio = $row_tipo_cambio->valor;
				$vs = calculadoraImporteEnvioDinero($data_ed,$valor_cambio);
				if( $cod_vendedor_sesion  == 21 ) {
					print_r($vs);
				}
	     
				if( $_POST['envio_dinero'] != 0 && $vs[0] != $_POST['envio_dinero']){
		 
					$warning[] = array('warn'=>'Revisa el valor de envio de dinero.');
					unset($_POST['ENVIAR']);                   
				}
				$envio_dinero = $vs[0];
				if ( $envio_dinero == 0 ){
					$err['opciones_pack'] = Array('err'=>'Error con el calculo de envio de dinero.');
				}

			} else {
				$err['opciones_pack'] = Array('err'=>'No hay valor para el dinero a enviar');
			}
		}
	}
    }

    $tipo_validacion = '';
    /* BUSCOO EL TIPO DE CONTRATO */ 
    if (isset( $tipo ) && $tipo > 0) {
    
	    $sql = "SELECT tipo,auto, tipo_validacion FROM PrepagosJyc.contratos_tipos_ofertas WHERE id='$tipo' ";
	    $res = $con_li->query($sql);
	
	    if( $res->num_rows > 0 ) {
	
		    $row = $res->fetch_object();
	
		    $tipo_oferta = strtolower($row->tipo);
		    $tipo_validacion = $row->tipo_validacion;
		    $auto_tipo_oferta = $row->auto;
	    }

    } else {
	    $tipo = 0;
    }

    if( $tipo_validacion == 'C0/NUEVOS' ) {
	    if( isset($_POST['cia_llamaban_c0n']) && $_POST['cia_llamaban_c0n'] == '' ) {
		    $err['cia_llamaban_c0n'] = Array('err' => 'Obligatorío poner compañia con la que llamaban');
	    }
    }

    syslog(LOG_INFO, __FILE__ . ": [$movil] oferta-tipo:$tipo : validacion : $tipo_validacion ".json_encode($err));

    //LOG
    $log_ingreso = ( isset($ingreso) ) ? "ingreso:$ingreso" : ''; 
    $log_radio_ofertas = ( isset($radio_ofertas) ) ? "of:$radio_ofertas" : ''; 
    syslog(LOG_INFO, __FILE__ . ": [$movil] [ingreso:$log_ingreso] -> $log_radio_ofertas");
    
    /* COMPRUEBO SI SE PUEDE AUTOMATIZAR EL CONTRATO */

    $funcion_check_auto = '';
    $res_auto_contrato = 0;
    if( isset($forzar_manual) && $forzar_manual == 0 ){

        /* Busco si el cliente esta como especial la tarifa para no automatizar por fallo de calculo 23/12/16 Diego */
        /* Quito el control de especial : 05 / 05 / 2017_ Diego */

        $es_nauta_realmente = 0;
	if( empty($err) && ((isset($tipo) && $tipo != '') && ( isset($ingreso) && $ingreso == 'tpv' )) ) {
		if ( $auto_tipo_oferta == 1 && $automatizarlo == 1) {
			if( $tipo_oferta == 'nauta' ){
	    
				$es_nauta_realmente = 1;
				$tipo_oferta = 'Cubacel';
				$contrato['tipo_contrato'] = 'nauta';
				$contrato['sum_recargas'] = $sum_recargas_nauta;
			}
	
			$funcion_check_auto = "checkAutomRec".ucfirst($tipo_oferta);
	
			if( $row->tipo == 'Combinado' || $row->tipo == 'Cubanacard' ) {
				$funcion_check_auto = "checkAutomRecCubanacard";
	    
				$contrato['pin'] = $pin;
				$contrato['prepago'] = $prepago;
				$contrato['cardcode'] = $n_cliente;
				$contrato['saldo_minimo'] = $saldo_minimo;
				$contrato['tipo_oferta'] = $radio_ofertas;
				$contrato['id_oferta'] = $tipo;
				$contrato['importe_llamadas'] = $llamadas;
				$contrato['importe_cubacel'] = $cubacel;
				$contrato['tipo_contrato'] = $tipo_oferta;
			}
		} 
    		// si el contrato es combinado o cubanacard mira si tiene pin para automatizarlo o no
            	
		$con_pin_pasa = 1;
		if ( $tipo_oferta == 'Combinado' || $tipo_oferta == 'Cubanacard' || $tipo_oferta == 'nauta' ) {
			$con_pin_pasa = 0;
			if ($funcion_check_auto != '') {
				$con_pin_pasa = 1;
			}
		}
		if ( $prepago == 0 && $es_prepago == 1 ) {
			$con_pin_pasa = 0;
    		}
		if( $funcion_check_auto != '' && $con_pin_pasa == 1 ) {
			$res_auto_contrato = $funcion_check_auto( $oferta , $contrato );         
		}
		if ( $res_auto_contrato == 1 && $tipo_oferta == 'cubacel' ){
			$cubacel = $importe;
		}
		if ( $res_auto_contrato == 1 && $es_nauta_realmente ){
			$nauta = $importe;
		}
		syslog(LOG_INFO, __FILE__ . ": [$movil] [pin:$pin][TipOferta:$tipo_oferta][tipo:$tipo][check_auto:$funcion_check_auto][resauto:$res_auto_contrato ][id_oferta: $log_radio_ofertas][cubacel:$cubacel][nauta:$es_nauta_realmente]");
	}

    }
    syslog(LOG_INFO, __FILE__ . ": [$movil] [ingreso:$tipo_oferta][pin:$pin][$log_ingreso][auto:$res_auto_contrato][idO:$log_radio_ofertas]");

    //24/04/2020: comprobar si los numeros a recargas en un cardcode vacio estaban recargados antes: cant min numeros en parrilla = 2
    if ($res_auto_contrato) {
            if ($check_numeros && count($nums_a_recargar) >= 2) {
                //echo "check num ".$num[$i];
                foreach ($nums_a_recargar as $num_cel) {
                    if (checkCelularRecargado ($num_cel)) {
                        syslog(LOG_INFO, __FILE__ . ": [$movil] check num cel ".$num_cel." sin cardcode ".$movil);
                        $res_auto_contrato = 0;
                        break;
                    }
                }
            }
    }
    /******************CONTROL PROMOCIONES*********************/
    include('inc_control_codigos_promocionales.php');
    /****************FIN CONTROL PROMOCIONES*******************/

    $submit_rellenar = 1;
    $enviar_submit = 0;
    if( empty($err) )
    {
        $enviar_submit = 1;
    }


?>
