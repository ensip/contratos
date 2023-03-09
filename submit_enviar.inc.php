<script src="//ajax.googleapis.com/ajax/libs/jquery/1.9.0/jquery.min.js"></script>
<?
  //if(!isset($_SESSION)){session_start();}
  
function getBlacklistByDst($num,$tipo=null)
{
	$con_li = getConnLi('getDataPre');
	$cid = 0;
	$dst = "0053$num";
	if( $tipo == 'movil' ){ $dst = "$num"; }
      
	$sql = "SELECT id FROM PrepagosJyc.`blacklist` WHERE dst = '$dst'";
	$res = $con_li->query($sql); 
	
	syslog( LOG_INFO , __FILE__ . ": [tipo:$tipo][dst:$dst] getBlacklistByDst- cant:" . $res->num_rows );
      
	return $res->num_rows;
}
function divisaRecarga()
{
	return 'cup';
}
function getTokenRecarga()
{
	return str_replace(' ', '-', microtime());
}
/*
 *      $array_recargas[] = array('num'=>$num[$key], 'monto'=>$value[$key],'moneda'=>'cuc','operatorCode'=>'CU');
 * */
function saveRecarga($data)
{
	$con_li = getConnLi('getDataPre');
	$sql = "insert into PrepagosJyc.contratos_recargas (".
		"id_contrato,".
		"preventa,".
		"recarga,".
		"amount,".
		"divisa,".
		"token,".
		"num_pedido,".
		"estado".
	") ".
	"values (".
		"'".$data['id_contrato'] ."',".
		"'".$data['preventa'] ."',".
		"'".$data['recarga'] ."',".
		"'".$data['amount'] ."',".
		"'".$data['divisa'] ."',".
		"'".$data['token'] . "',".
		"'".$data['num_pedido'] ."',".
		"'".$data['estado'] ."'".
		")";
	$res = $con_li->query($sql); 
	//syslog(LOG_INFO, __FILE__ . " : ". __method__ . ":" . $sql);
}

function saveRecargas($data, $id_contrato, $num_pedido, $estado, $preventa)
{
	$pn = new PreciosNuevos();
	
	foreach ($data as $rec) {
		if (strpos($rec['num'], 'nauta') === false) {
			$recarga['recarga'] = "53".$rec['num'];
			$recarga['amount'] = $pn->convertCucToCup($rec['monto']);
			$recarga['token'] = $rec['token'];
			$recarga['num_pedido'] = $num_pedido;
			$recarga['id_contrato'] = $id_contrato;
			$recarga['estado'] = $estado;
			$recarga['preventa'] = $preventa;
			$recarga['divisa'] = divisaRecarga();
			saveRecarga($recarga);
		}
	}
}

$res_auto_fin = $res_auto_contrato;
extract($_POST);
//si cambian de oferta el res_auto no se actualiza, asi compruebo que sea el mismo, POST vs rellenar

if( $_POST['res_auto_contrato'] != $res_auto_fin ){
	$res_auto_contrato = $res_auto_fin;
}

syslog(LOG_INFO, __FILE__ . ": pin:$pin [tipo:$tipo] - res_auto_fin:$res_auto_fin vs post-rest_auto_contrato:".$_POST['res_auto_contrato']." : res_auto_contrato:$res_auto_contrato");

if( $_SESSION['cod_vendedor'] == '21' || $cod_vendedor  == 21 ){
	echo "Debugg:<pre><p>";
	echo "tipo_cambio";print_r($row_tipo_cambio);
        echo "<br/>data_ed";print_r($data_ed);
	echo "Vs"; print_r($vs);
	echo"</p>";
        if($_POST['ENVIAR'] == 'ENVIAR CONTRATO'){
            print_r($_POST['auto_c0n']);
            print_r($_POST['num_frec_c0n']);
            /*print_r($oferta);
            echo "<p>ENVIO DINERO: $envio_dinero</p>";
            echo "<p>ENVIO DINERO FINAL: $envio_dinero</p>";
            echo "</pre>";
	    echo "<p>".$_POST['res_auto_contrato']."-vs-$res_auto_fin : $res_auto_contrato</p>";*/
	}
}
//busco nombre oferta

$sql = "SELECT nombre,tipo,preventa,precio,precio_regalo,recarga_manual_cuba,proveedor_recarga FROM PrepagosJyc.contratos_ofertas WHERE id='$radio_ofertas'";
$res = $con_li->query($sql); 
$row = $res->fetch_assoc();

$id_radio_oferta = $radio_ofertas;
$radio_ofertas = $row['nombre'];
$tipo = $row['tipo'];
$precio = $row['precio'];
$precio_regalo = $row['precio_regalo'];
$preventa = ( isset($hide_preventa) && $hide_preventa == 1 )? $hide_preventa : $row['preventa'];
$recarga_manual_cuba = $row['recarga_manual_cuba'];
$proveedor_recarga_cubacel = $row['proveedor_recarga'];

  //Si es del tipo activacion SIM genero un array con los datos que serializo para guardar en bbdd

$datos_otros = '';
if( $tipo_oferta == 'ACT SIM' || $tipo_oferta == 'act sim' )
{
	$datos_otros = serialize(array('dest_msisdn'=>"SIM".$id_card_sim."_".$nombre_sim."_".$apellido_1_sim."_".$apellido_2_sim."_".$oficina_sim."",
		'id_card_sim'=>$id_card_sim,'nombre_sim'=>$nombre_sim,'apellido_1_sim'=>$apellido_1_sim,'apellido_2_sim'=>$apellido_2_sim,
		'oficina_sim'=>$oficina_sim,'proveedor'=>$nameProvider));
}
//ENTRADAS
$datos_entrada = '';
$post_entradas = null;
if( $id_radio_oferta == 540 ){
	if( isset( $_POST['entradas'] ) ){
		$post_entradas = $_POST['entradas'];
		$arr_entradas = null;
		foreach( $post_entradas as $entrada => $datos ){
			foreach( $datos as $key => $dato ){
				if( $dato != '' ){
					$arr_entradas[$entrada][ $key ] = $dato;
				}
			}
		}
		$datos_entrada = serialize( $arr_entradas );
		syslog(LOG_INFO, __FILE__ . " CONTRATOS_ENTRADAS : $datos_entrada");
	}
}

if( isset($radio_promo) ) 
{
	$sql = "UPDATE PrepagosJyc.contratos_ofertas SET status = 1 WHERE id = 32 LIMIT 1";
	$res = $con_li->query($sql); 

        $sql = "SELECT * FROM PrepagosJyc.promo_codes WHERE codigo='E14-$hide_cod_promo'";
	$res = $con_li->query($sql); 
        $num_pcs = $res->num_rows;

        $row = $res->fetch_assoc();

        switch ($radio_promo) {
            case 'llamada':
                $arr_pc['llamada'] = 1;
                $arr_pc['recarga'] = ($num_pcs>0) ?  $row['recarga'] : 0;
                break;
            case 'recarga':
             $arr_pc['llamada'] = ($num_pcs>0) ?  $row['llamada'] : 0;
                $arr_pc['recarga'] = 1;
                break;
            case 'recarga_llamada':
                $arr_pc['llamada'] = 1;
                $arr_pc['recarga'] = 1;
                 break;
        }
        //actualizo registro
	if($num_pcs > 0) {
		$sql = "UPDATE PrepagosJyc.promo_codes ".
			"SET fecha='".date('Y-m-d H:i:s')."', llamada='".$arr_pc['llamada']."', recarga='".$arr_pc['recarga']."' ".
			"WHERE id='".$row['id']."'";
		$res = $con_li->query($sql); 
        } else {
            //inserta registro
		$sql = "INSERT INTO PrepagosJyc.promo_codes(codigo, fecha, llamada, recarga) ".
			"VALUES('E14-$hide_cod_promo','".date('Y-m-d H:i:s')."', ".$arr_pc['llamada'].", ".$arr_pc['recarga'].")";
		$res = $con_li->query($sql); 
        }

        $nota .= '-- Promocion Oferta: '.$radio_promo;
}
    //busco nombre si esta vacío, pk tenia varios cardcodes
if (empty($_POST['nombre'])) {

	$data_ocrd =  getDataOCRD($db,$movil);
        $n_cliente = $data_ocrd['CardCode'];
        $nombre = $data_ocrd['CardName'];
        $pin = $data_ocrd['pin'];
        $prepago = $data_ocrd['prepago'];
}

//nums recarga
$new_num = array_filter($num);
$new_value = array_filter($value);

$fraude = 0;
$num_fraude = '';
$array_recargas = array();

foreach ($new_num as $key => $res) {
	if( getBlacklistByDst($num[$key]) > 0) {
		$fraude = 1;
		$num_fraude = $num[$key];
	}
	$array_recargas[] = array(
	'num' => $num[$key], 
	'monto' => $value[$key],
	'moneda' => 'cuc',
	'operatorCode'=>'CU',
	'proveedor_cubacel' => $proveedor_recarga_cubacel,
	'recarga_manual_cuba' => $recarga_manual_cuba,
	'token' => getTokenRecarga()
	);
}

if( getBlacklistByDst($movil,'movil') > 0 ) { 
	$fraude = 1;
	$num_fraude = "Cellular: ".$movil;
}
  
if( $cant_nums_nauta > 0 ) {
	$new_num_nauta= array_filter($nauta_m);
	$new_value_nauta = array_filter($value_n_);
	foreach ($new_num_nauta as $key => $res) 
	{
		$array_recargas[] = array(
			'num' => $nauta_m[$key].$dominio_nauta[$key], 
			'monto' => $value_n_[$key],
			'moneda' => 'cuc',
			'operatorCode'=>'NU'
		);
	}    
    
	syslog(LOG_INFO, __FILE__ . ": contratos_nauta [$movil] - rec_nauta:".serialize($array_recargas));
}

if( isset($ingreso) && $ingreso == 'cuenta') {
	$importe = 0;
	$comprobante_cuenta = $comprobante;
	$comprobante = '';
	$npedido = 0;
} else {
	$importe_cuenta = 0;
	$comprobante_cuenta = '';
	$npedido = $num_pedido;
}
$importe_total = $importe_cuenta + $importe;
$fecha_final = $fecha." ".$hora;
$pin_f = ( $pin != '' || $pin != null) ? $pin : 0;
$cod_v = ( $cod_vendedor != '' ) ? $cod_vendedor : $_SESSION['cod_vendedor'];
$cod_v = ( $cod_v == '' ) ? 0 : $cod_v;

//compruebo que no se haya insertado ya el contrato
$contrato_repetido = 0;

$new_num_pedido = $npedido;

if (isset($comercio) && !empty($comercio) && $num_pedido != 0) {
	$new_num_pedido = $comercio . '-' . $num_pedido;
}

syslog(LOG_INFO, __FILE__ . ": Contratos -tipo Oferta : $tipo_oferta , id_oferta:$id_radio_oferta, npedido: $npedido new_num_pedido:$new_num_pedido");

$sql = "SELECT * FROM PrepagosJyc.`contratos` WHERE fecha = '".$fecha_sin_formato."' and num_pedido = '".$new_num_pedido."'";
$res = $con_li->query($sql); 

  if ($res->num_rows > 0 ) {

	  $contrato_repetido = 1;

	  if (!empty ($num_pedido)) {
?>
	  <script>
	  alert('Pedido repetido');
	  $.wait = function( callback, seconds)
	  {
		  return window.setTimeout( callback, seconds * 1);
	  }
	  $.wait( function(){location.href = location.href }, 10);
	  </script>
<?php
	  }

  } else {
	  $npedido = $new_num_pedido;
  }
  $npedido = cleanStringToInsert($npedido);
  
  syslog(LOG_INFO, __FILE__ .": $sql Num pedido-> $npedido ($num_pedido) cant: " . $res->num_rows);
  
  
  //compruebo que no se haya insertado ya el contrato
  if ( $tipo_oferta == '' )
  {
      echo "<h3>Oferta no seleccionada, dale a MODIFICAR CONTRATO antes de enviarlo</h3>";
      ?>
          <script>
          $.wait = function( callback, seconds)
          {
              return window.setTimeout( callback, seconds * 1);
          }
          $.wait( function(){location.href = location.href }, 10);
          </script>
      <?
      exit();
  }    

  $email_vendedor = ( $email_vendedor != '') ? $email_vendedor : $_SESSION['email_vendedor'];
  $whatsapp = (isset($tiene_w) && $tiene_w == 1) ? 1:0;

  if ( $fraude == 1 )
  {
      $texto_fraude = "<p>Se ha creado un contrato con un numero catalogado como FRAUDE</p>";
      $texto_fraude .="<p>Vendedor: $email_vendedor</p>";
      $texto_fraude .="<p>Num pedido: $npedido</p>";
      $texto_fraude .="<p>Num telefono: $num_fraude</p>";

      $from = 'contratos@jyctel.com';
      $emails_envio = array('auxiliar@ensip.com','diego@jyctel.com');
      php_mailer($texto_fraude,'Alarma Fraude Nums Recarga (Contrato)','admon@jyctel.com',$from,$emails_envio);
  }
  $tipo_producto = serialize($arrayName = array('cubacel' => $cubacel, 'llamadas' => $llamadas, 'envio_dinero' =>$envio_dinero, 'nauta' => $nauta ));

  //NUMEROS REFERIDOS
  $cant_refer = count($ref);
  $num_refs = '';

  for ($i=0; $i < $cant_refer ; $i++) 
  { 
      if($ref[$i] != '' )
      {
          $num_refs .="$ref[$i];";
      }
  }
  //FALLARá, FALTA moneda y operatorCode
  if($tipo_recarga == 'num_referidos')
  {
      $array_recargas[] = array('num'=>$num_referido, 'monto'=>$val_referido);
  }

  $recargas = serialize($array_recargas); //campo recargas

  $med = '';
  if(isset($moneda_envio_dinero)){$med = $moneda_envio_dinero;}

  $ing = '';
  if(isset($ingreso)){$ing = $ingreso;}

   //para envio dinero:
  if ( $ciudad_para != '' )$nota .= "Ciudad: $ciudad_para  ".$nota;

  $sql = "INSERT IGNORE INTO PrepagosJyc.contratos (".
	  "`num_pedido`,".
	  "`cod_vendedor`,".
	  "`prepago`,".
	  "`pin`,".
	  "`comercial_cartera`,".
	  "`CardCode`,".
	  "`CardName`,".
	  "`Cellular`,".
	  "`cod_promocional`,".
	  "`ofertas`,".
	  "`recargas`,".
	  "`referidos`,".
	  "`ingreso`,".
	  "`importe_tpv`,".
	  "`digitos_tpv`,".
	  "`comprobante_tpv`,".
	  "`importe_cuenta`,".
	  "`comprobante_cuenta`,".
	  "`fecha`, ".
	  "`nombre_para`,".
	  "`direccion`,".
	  "`telf_para`,".
	  "`cubacel`,".
	  "`llamadas`,".
	  "`envio_dinero`,".
	  "`tipo_producto`,".
	  "`regalo_cuc`,".
	  "`moneda_envio_dinero`, ".
	  "`nota`,".
	  "`id_oferta`,".
	  "`preventa`,".
	  "`whatsapp`,".
	  "`opciones_pack`,".
	  "`divisa`,".
	  "`fraude`,".
	  "`punto`,".
	  "`otros`,".
	  "`datos_entrada`,".
	  "`precio_regalo`,".
	  "`comercio_tpv`".	
  	  ") ".
		  "VALUES (".
		  "'$npedido',". //num_pedido
		  "'$cod_v',".	//cod_vendedor
		  "'$prepago',".	//prepago
		  "'$pin_f',".	//pin
		  "'$id_comercial',". //comercial_cartera
		  "'$n_cliente',". //CardCode
		  "'$nombre',".	//CardName
		  "'$movil',".	//Cellular
		  "'$cod_promo',". //cod_promocional
		  "'$radio_ofertas',". //ofertas
		  "'$recargas',". //recargas
		  "'$num_refs',". //referidos
		  "'$ing',". //ingreso
		  "'".str_replace(',','.',$importe)."',". //importe_tpv
		  "'$digitos',". //digitos_tpv
		  "'$comprobante',". //comprobante_tpv
		  "'$importe_cuenta',". //importe_cuenta
		  "'$comprobante_cuenta',". //comprobante_cuenta
		  "'$fecha_sin_formato',". //fecha
		  "'$para',".  //nombre_para
		  "'$dir_para',". //direccion
		  "'$telf_para',". //telf_para
		  "'".( isset($cubacel) ? $cubacel:0 )."',". //cubacel
		  "'".( isset($llamadas) ? $llamadas:0 )."',". //llamadas
		  "'".( isset($envio_dinero) ? $envio_dinero:0 )."',". //envio_dinero
		  "'$tipo_producto',". //tipo_producto
		  "'$regalo_cuc',". //regalo_cuc
		  "'$med',". //moneda_envio_dinero
		  "'$nota',". //nota
		  "'$id_radio_oferta',". //id_oferta
		  "'$preventa',". //preventa
		  "'$whatsapp',". //whatsapp
		  "'".$opciones_pack['valor']."',". //opciones_pack
		  "'$divisa',". //divisa
		  "'$fraude',". //fraude
		  "'$punto',". //punto
		  "'$datos_otros',". //otros
		  "'$datos_entrada',". //datos_entrada
		  "'$precio_regalo',".//precio_regalo
		  "'".(isset($comercio) ? $comercio : '')."'".
		  ")";     
  
  $res = 0;
  if (!$contrato_repetido) {  
	  $res = $con_li->query($sql); 

	  $id_contrato = $con_li->insert_id;

	  if (!empty($array_recargas)) {
		  $estado_recargas = 3;
		  saveRecargas($array_recargas, $id_contrato, $npedido, $estado_recargas, $preventa);
	  }
  }
  
  if ( $res != 1 || $cod_v == 0 ) {
	  if (!$contrato_repetido) {
		  $text = '<br/>VALORES SESSION : ';
		  foreach($_SESSION as $key => $val)$text .= $key."=".$val."\r\n";

		  $from = 'contratos@jyctel.com';
		  $asunto = "Fallo INSERT Contrato nuevo (".$id_contrato.")";
		  $codigohtml = $sql."$email_vendedor".$text;

		  $codigohtml .= "<p>Fallo al insertar</p>";
		  if( isset( $_SESSION['user_vendedor'] ) && $id_contrato > 0 ){
			  $sqlup = "update PrepagosJyc.contratos set cod_vendedor='".$_SESSION['user_vendedor']."' where id='".$id_contrato."' limit 1";
			  $res = $con_li->query($sqlup); 
			  syslog( LOG_INFO, __FILE__ . ": Fallo_contratos ".$sqlup);
			  $res = 1;
		  }
		  syslog(LOG_INFO, __FILE__ .": Fallo_contratos, cod_v:$cod_v , user_vendedor: " . $_SESSION['user_vendedor'] );

		  if($cod_v == 0) {			
		      	  $codigohtml .= "<p>No tiene vendedor, ponerlo antes de hacer la recarga</p>";  
		  }

		  $emails_envio = array('administracion@jyctel.com', 'auxiliar@ensip.com');
		  php_mailer($codigohtml,$asunto, 'diego@jyctel.com', $from,$emails_envio);
		    //mail('admon@jyctel.com',$asunto,$codigohtml,$cabeceras);

	  } else {
		  syslog (LOG_INFO, __FILE__ .": contrato_repetido ".$num_pedido);
		  echo "<p>Contrato repetido</p>";
	  }
  }

	if ( $res == 1 ) {

	    include("email.php");//enviar email

	    include("insertSAP.php");// Insertar contrato en SAP  Generar texto contrato para BBDD SAP
    
	    syslog(LOG_INFO, __FILE__ . ": pin_f:$pin_f - tipo_oferta:$tipo_oferta - IDC:$id_contrato, res_auto_contrato:$res_auto_contrato, fraude:".$fraude);

	    if( $res_auto_contrato == 1 && $fraude == 0){//si es recarga cubacel genero factura y actualizo contrato
		    
		    //nombre_oferta,prepago,cardcode,pin,id_contrato,nota,recargas,valor_cuc,
            $data_factura = array(
              'nombre_oferta' => $radio_ofertas,
              'id_radio_oferta' => $id_radio_oferta,
              'importe_cobrado' => $precio, //importe_cobrado
              'importe_total' => $importe_total,
              'prepago' => $prepago,
              'preventa' => $preventa,
              'cardcode' => $n_cliente,
              'pin' => $pin_f,
              'id_contrato' => $id_contrato,//$id_contrato,
              'nota' => $nota,
              'recargas' => $array_recargas,
              'valor_cuc' => 0,
              'regalo_cuc' => $regalo_cuc,
              'tipo_oferta' => $tipo_oferta //combinado, cubanacard, cubacel...
              );

            $res_u_c = gestionContrato($data_factura);
            //print_r($res_u_c);
            syslog(LOG_INFO, __FILE__ . ": INS contrato: [res:".$res_u_c['res']."][inc_amount:".$res_u_c['inc_amount']."]: IDC=$id_contrato");
            
            if( $res_u_c['res'] == 1 )
            {
                //envio email
                $add_cc = 'gestion';
                $email = getEmailComercial($cod_v);
                $enviado = sendEmailContrato($res_u_c['res_accion'], '', $email, $add_cc, $nombre);
                //envio sms
                if($tipo_oferta != 'cubacel')
                {
                    $sms_enviado = sendSmsContrato( $id_contrato, $res_u_c['valor_cuc'], $tipo_oferta, $res_u_c['inc_amount'], '');
                    syslog(LOG_INFO, __FILE__ . ": sms_enviado: ".$sms_enviado->resultID.": IDC=$id_contrato");
                }
            }                

            insertFactura($id_contrato,$res_u_c['res_accion']);

	    if( $res_u_c['res'] == 1 ) {
                $text = str_replace("'", "\'", $res_u_c['res_accion']);
		$sql = "UPDATE PrepagosJyc.contratos SET factura = '".$text."' WHERE id = ".$id_contrato."";
		$res = $con_li->query($sql); 

                syslog(LOG_INFO, __FILE__ . ": update factura IDC: $id_contrato ");
            }
        }
        
        /*$sql = "UPDATE PrepagosJyc.contratos_list_cod_prs SET estado=1, fecha_uso='".date('Y-m-d H:i:s')."' ".
        "WHERE estado=0 AND celular='$movil' AND codigo='$cod_promo'";*/
        syslog(LOG_INFO, __FILE__ . ": id_promocion: $id_promocion : IDC: $id_contrato");
        if ( $id_promocion > 0 )
        {
		$sql = "UPDATE PrepagosJyc.contratos_list_cod_prs SET estado=1, fecha_uso='".date('Y-m-d H:i:s')."' WHERE id = $id_promocion";
		$res = $con_li->query($sql); 
	}
        
        //INSERTAR DATOS C0/Nuevos
        if ( count($array_c0n) > 0 )
        {
		insertarDatosC0n($array_c0n,$id_contrato);
        }
	$values = '';
    	if( isset($_POST[ 'auto_c0n' ]) ){
		$values = getValoresNumeros($_POST['auto_c0n'],'automatizados',$id_contrato);
	}
	if( isset($_POST[ 'num_frec_c0n' ]) ){
		$values .= getValoresNumeros( $_POST['num_frec_c0n'] , 'frecuentes', $id_contrato );
	}
	if ( $values != '') {
		$values = rtrim( $values, ',' );
		$sql_ins = "INSERT INTO PrepagosJyc.contratos_numeros_relacionados (`id_contrato`,`numero`,`tipo`) VALUES $values";
		$res = $con_li->query($sql_ins); 
		syslog(LOG_INFO,"contratos_numeros_relacionados $sql_ins");
	}

	if ( $tipo_oferta == 'envio dinero' ) {

            $datos_ed = $_POST;
            $vars_envio_dinero = checkIfEnvioDinero($datos_ed);
            $vars_envio_dinero['cantidad'] = $envio_dinero;
            $vars_envio_dinero['tipo_cambio'] = $t_c_eur_cuc;
            $vars_envio_dinero['t_c_eur_usd'] = $t_c_eur_usd;
            $vars_envio_dinero['t_c_usd_cuc'] = $t_c_usd_cuc;
	    $vars_envio_dinero['divisa'] = $divisa;

            insertDatosEnvioDinero($id_contrato,$vars_envio_dinero);
        }
	
	} else {
	    if (!$contrato_repetido) {
		    foreach($_SESSION as $key => $val)$text .= $key."=".$val."\r\n";
	    
		    $from = 'contratos@jyctel.com';
		    $cabeceras = "Content-type: text/html\r\n";
		    $cabeceras = "From: $from\r\nContent-type: text/html\r\n";
		    $cabeceras = 'Cc: diego@jyctel.com' . "\r\n";
		    $asunto = "Fallo 2 INSERT Contrato nuevo";
		    $codigohtml = $sql."$email_vendedor".$text;
	    
		    $emails_envio = array('diego@jyctel.com');
		    php_mailer($codigohtml,$asunto,'admon@jyctel.com',$from,$emails_envio);
	    }
    }
    

    if (isset($texto_email) && !empty ($texto_email)) {
	    $sql = "UPDATE PrepagosJyc.contratos SET texto_email = '".str_replace("'", "\'", $texto_email)."' WHERE id = ".$id_contrato." limit 1";
	    $res = $con_li->query($sql); 
	    if (!$res) {
		    syslog (LOG_INFO, __FILE__ . " : email no insertado: ".$sql);
	    }
    }


    if($cod_promo != '') {
    	    syslog(LOG_INFO,"Contratos codigo_promocional: $cod_promo , id_oferta = $radio_ofertas, contrato: $id_contrato");          
    }

    if ($_SESSION['tipo_comercial'] == 'externo') {
    	    print "<meta http-equiv='refresh' content='5;url=contratos.php'>"; 
    }
?>

<script>
    $.wait = function( callback, seconds)
    {
        return window.setTimeout( callback, seconds * 1000 );
    }
<?php  

    syslog (LOG_INFO, __FILE__ . ":reload: np:$npedido , $cod_v, IDC:$id_contrato " . $_POST['num_pedido']); 

    if ($cod_v == '63') {
	  //unset($_POST);
    }
?>
    $.wait( function(){location.href = location.href+'?gcv=<?=$cod_v?>' }, 1);

</script>
