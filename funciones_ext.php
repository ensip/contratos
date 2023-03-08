<?php

if (!defined('SERVER_MAIL')) DEFINE('SERVER_MAIL','phpmailer'); //mail/phpmailer
set_include_path('/var/www/html/factura/');
include_once("phpmailer/class.phpmailer.php");
include_once("inc/clases/Class.DBConnect.php");
include_once("inc/clases/Class.Utilities.php");

function checkUserLocked( $id ){
    	$con = new DBConnect(); 
	$con->setDB( DB2 );

	$sql = "select login from PrepagosJyc.admin where login = 1 and id  = $id";
	$con->makeQuery( $sql );
	syslog(LOG_INFO," LOGIN_VERIFICAR : $sql res:".$con->rows);
	if( isset($_SESSION['new_verificar_user']) && $con->rows == 0 ){
		unset($_SESSION['new_verificar_user']);
		echo '<script language="JavaScript">location="http://192.168.0.16/contratos/verificar/index.php"</script>'; // redirecciona
	}
}
function getSqlOCRD($select,$where)
{
    $sql = "SELECT $select FROM RDR1 as t1 INNER Join ORDR as t2 ".
    "ON cast(t1.DocEntry as varchar(50))= cast(t2.DocEntry as varchar(50)) ".
    "WHERE $where AND cast(t1.U_SEI_Telf as varchar(50)) NOT LIKE '' AND t2.DocStatus='O' ".
    "ORDER BY t1.VisOrder ASC";

    return $sql;      
}
/*
    Devuelve los pins que tenga un cardcode
*/
function getPin( $cardcode, $impago = '' ){
    $con = new DBConnect(); 
    $con->setDB( DB );

    $select = "cast(t1.U_SEI_Telf as varchar(50)) as pin";
    $where = "cast(t2.CardCode as varchar(50))='$cardcode'";
	
    $sql2 = getSqlOCRD($select,$where, $impago);

    $con->makeQuery( $sql2 );
    $num_pins = $con->getNumRows();

    $pins = array();

    if ( $num_pins >= 1 )
    {
        $row2 = $con->fetchObject();

        foreach ($row2 as $key => $pi) 
        {
            if( $num_pins > 1 )
            {
                $pins[] = $pi->pin;
            }
            else
            {
                $pins[] = $pi;
            }
        }
    }

    return $pins;

}
function getIva($cardcode)
{
    $con = new DBConnect(); 
    $con->setDB( DB );
    //con iva: C011896
    $sql = "SELECT ECVatGroup, Rate FROM OCRD LEFT JOIN OVTG ON EcVatGroup=Code WHERE CardCode LIKE '$cardcode' ";
    $con->makeQuery( $sql );
    $num_pins = $con->getNumRows();

    if($num_pins >= 1)
    {
        $row = $con->fetchAssoc();

        $iva = sprintf('%s',$row['Rate']);

        if( $iva == '' )
        {
            $iva = 21;
        }
        else
        {
            if ( $iva >= 0 )
            {
                $iva = $row['Rate'];
            }
        }    
    }

    return $iva;
}
/*
    Devuelve pins activos de un array de pins dado
*/
function getActivePins($pins,$activo)
{
    $con = new DBConnect(); 
    $con->setDB( DB2 );
    $pins_Activos = null;

    $status = 'status = '.$activo;

    if( isset($pins['prepago']) && $pins['prepago'] == 1 )
    {
        $consulta = " pincode pin FROM PrepagosJyc.usuarios WHERE $status AND pincode ";
    }
    else if( isset($pins['prepago']) && $pins['prepago'] == 0 )
    {
        $consulta = " username pin FROM mya2billing.cc_card WHERE $status AND username ";
    }
    else
    {
        return 0;
    }
    
    if(!empty($pins['pins']))
    {
        foreach ($pins['pins'] as $key => $value) 
        {
            $sql = "SELECT $consulta = '$value'";
            $con->makeQuery( $sql );
            $p = 0;

            while( $row = $con->fetchObject() )
            {
                $pins_Activos['pins'][] = $row->pin;
            }
        }
    }

    return $pins_Activos;   
}
/*
  Obtengo pins en funcion del frozenfor Prepago/postpago
*/
function getPinsImpagos($cardcode)
{
    // $cardcode = 'C018560'; //TO DO: Quitar card Code
    $con = new DBConnect();
    $con->setDB( DB );
    //añadir estado usuario 11/07/18
    $sql = "SELECT T0.frozenfor as Ff FROM ocrd AS T0 WHERE (T0.CardCode = '$cardcode') ";
    $con->makeQuery( $sql );
    
    while( $row = $con->fetchObject() )
    {
        $Ff = $row->Ff;
    }
    $pins['prepago'] = 1;
    if(isset($Ff) && $Ff == 'N')
    {
        $pins['prepago'] = 0;
    }
    
    $pins['pins'] = getPin( $cardcode, 'impago' );
    $pins_ = getActivePins($pins,0);

    $res = null;
    if($pins_ != null)
    {
        $res = $pins_;
    }

    return array('res'=>$res,'prepago'=>$pins['prepago']);
}
function getEstadoPinsSap( $pin ){
	$con = new DBConnect();
	$con->setDB( DB );
	print_r($pin);
	$sql = "SELECT cast(T2.U_SEIAct as varchar(50)) as estadoSap FROM  OCRD AS T0 LEFT OUTER JOIN ".
		"ORDR AS T1 ON T0.CardCode = T1.CardCode LEFT OUTER JOIN RDR1 AS T2 ON T1.DocEntry = T2.DocEntry ".
		"WHERE (cast(T2.U_SEI_Telf as varchar(50))= '$pin')";
	$con->makeQuery( $sql );
	$estado = '';
	if( $con->rows > 0 ){
		$f = $con->fetchObject();
		$estado = $f->estadoSap;
	}
	return $estado;
}
/*
    1 - Busca los datos de un movil de la OCRD
    2 - Si tiene mas de 1 Cardcode guarda el resto en un array
    3 - Devuelve array cardcodes
*/
function getDataOCRD_($movil,$tipo)
{
    $con = new DBConnect(); 
    $con->setDB( DB );

    if($tipo == 'movil')
    {
        $where = "cast(Cellular as varchar(50)) LIKE '".$movil."' ";
    }
    
    if($tipo == 'cardcode')
    {
        $where = "CardCode LIKE '".$movil."' ";
    }
    //Cellular, CardCode, qrygroup3 as prepago,frozenfor as congelado,CardName
    $sql = "SELECT CardCode, qrygroup3 as prepago,frozenfor as congelado,CardName FROM OCRD WHERE $where";
    $con->makeQuery( $sql );
    //numero de cardcodes con el numero
    $num_rows = $con->getNumRows();
    if( $num_rows > 1 ) syslog(LOG_INFO, "[$movil] Num CardCodes: $num_rows");
    $cardcodes = array();
    $row = $con->fetchAssoc();
    /*
      Si el num de cardcodes es > 1
      buscar todos y guardar en un array para mostrar
    */

    if( $num_rows > 1 )
    {
        foreach ($row as $key => $value) 
        {
            $cardcodes[$value['CardCode']]['CardName'] = $value['CardName']; 
            $cardcodes[$value['CardCode']]['CardCode'] = $value['CardCode']; 
            $cardcodes[$value['CardCode']]['prepago'] = 0; 

            if( $value['prepago'] == 'Y' && $value['congelado'] == 'Y' )
            {
                    $cardcodes[$value['CardCode']]['prepago'] = 1; 
            }
        }

    }else if( $num_rows == 1 )
    {
            $cardcodes[$row['CardCode']]['CardCode'] = $row['CardCode']; 
            $cardcodes[$row['CardCode']]['CardName'] = $row['CardName']; 
            $cardcodes[$row['CardCode']]['prepago'] = 0; 

            if( $row['prepago'] == 'Y' && $row['congelado'] == 'Y' )
        {
                  $cardcodes[$row['CardCode']]['prepago'] = 1; 
            }
    }
    
    return $cardcodes;

}
/*
  Devuelve los datos de una oferta en concreto

*/
function getDataOferta($id_oferta){

    $con = new DBConnect();
    $con->setDB( DB2 ); 

    $sql = "SELECT precio, precio_regalo, porcentaje_regalo porc_regalo, regalo_cuc FROM PrepagosJyc.contratos_ofertas WHERE id='$id_oferta'";
    $con->makeQuery( $sql );
    $num_rows = $con->getNumRows();

    if( $num_rows >= 1)
    {
        $oferta = $con->fetchObject();
    }
    
    return $oferta;
}
function getLastRefill($id_user)
{
    $con = new DBConnect();
    $con->setDB( DB2 );
    $sql = "SELECT max( date ) fecha FROM PrepagosJyc.`refills` r, PrepagosJyc.`usuarios` u WHERE  r.`id_user` = u.`id` AND u.`id` = '$id_user' GROUP BY id_user";
     $con->makeQuery( $sql );

    $fecha = '0000-00-00';
    while($row = $con->fetchObject())
    {
        $fecha = $row->fecha;
    }
    return $fecha;
}
function getLastCall( $extension ){
	$sql = "select max( calldate) as fecha from PrepagosJyc.consumo where extension ='$extension' and duracion > 0";
	$res = getResult( DB2, $sql);
	$last_call = 'No tiene';
	if( $res->rows >0 ){
		$i = $res->fetchObject();
		$last_call = $i->fecha;
	}
	return $last_call;
}
function getComercialOwner($id_user)
{
    $con = new DBConnect();
    $con->setDB( DB2 );
    $sql = "SELECT id_comercial FROM PrepagosJyc.usuarios WHERE id=$id_user";
    $con->makeQuery( $sql );

    while($row = $con->fetchObject())
    {
        $id_comercial = $row->id_comercial;
    }    

    return $id_comercial;

}
//Data prepago
function getDataUsuario($pin)
{
    $data = array();
    $con = new DBConnect();
    $con->setDB( DB2 );

    $row_tarifa = 0;
    $tarifa = 0;
    //,'52'
    $sql = "SELECT precio_minuto, u.id, u.login, p.promo, p.code, p.tarifaID id_tarifa, pt.especial, p.IVA as iva ".
	    "FROM PrepagosJyc.usuarios u ".
	    "INNER JOIN PrepagosJyc.promotarifas pt ON tarifa=tarifaID AND cod_zona IN ('0053') ".
	    "INNER JOIN PrepagosJyc.promos p ON p.tarifaID=pt.tarifaID ".  
	    "WHERE pincode='$pin' and `status`= 1 ";
    $con->makeQuery( $sql );

    //syslog(LOG_INFO, $sql );

    $num_rows = $con->getNumRows();

    if($num_rows >= 1)
    {
        $row = $con->fetchObject();
        $data['precio_minuto'] = $row->precio_minuto;
        $data['id'] = $row->id;
	$data['last_recharge'] = getLastRefill($row->id);
	$data['last_call'] = getLastCall($row->login);
        $data['id_comercial'] = getComercialOwner($row->id);
        $data['id_tarifa'] = $row->id_tarifa;
        $data['nombre'] = $row->promo;
        $data['code_sap'] = $row->code;
	$data['iva'] = $row->iva;
	$sql_esp = "select id from PrepagosJyc.promos_especiales where id_tarifa = {$row->id_tarifa}";
	$con->makeQuery($sql_esp);
	if($con->getNumRows() > 0){
        	$data['especial'] = 1;
	}
        $tariff = getTariffSap($pin);
        $data['retariff'] = getRetarificacionSAP($tariff);
        $con_sr = getSaldoRecargaCubacelContratos(1, $data['id']);
        
        $num_rows = $con_sr->getNumRows();
        if($num_rows > 0){
            $row_sr = $con_sr->fetchObject();
            $data['saldo_recarga'] = $row_sr->saldo;
        }
    }

    return $data;
}
function getTarifasPrepago()
{
    $con = new DBConnect();
    $con->setDB( DB2 );

    $sql = "SELECT precio_minuto, p.promo, pt.especial, p.tarifaID id_tarifa  FROM PrepagosJyc.promotarifas pt ".
    "INNER JOIN PrepagosJyc.promos p ON p.tarifaID=pt.tarifaID WHERE pt.cod_zona = '0053' ";
    $con->makeQuery( $sql );  
  
    $row = $con->fetchObject(); 

    return $row;
}
/*function getTarifaPostpago($pin){
  
  $con = new DBConnect();
  $con->setDB( DB2 );

  $sql = "SELECT t.tariffgroupname FROM `cc_tariffgroup` as t, cc_card WHERE tariff=t.id AND username='$pin'";
  $con->makeQuery( $sql );  
  
  $row = $con->fetchObject(); 

  return $row;

}*/
function insertFactura($id_contrato,$res_accion){

  $text = str_replace("'", "\'", $res_accion);

  $con = new DBConnect();
  $con->setDB( DB2 );
  $sql = "UPDATE PrepagosJyc.contratos SET factura='".$text."' WHERE id='$id_contrato' LIMIT 1 ";

  $res = $con->makeQuery( $sql );

  return $res;
}
function insertSAPOCLG($n_cliente,$texto,$sap_fecha,$sap_hora,$details = ''){
    
	$sql_sap = "select MAX(ClgCode) as clgcode from OCLG";
	$res = getResult(DB,$sql_sap);
    
	if ( $res->rows > 0 ) 
	{
		$clgcode_reg = $res->fetchObject();
	
		$clgcode = $clgcode_reg->clgcode;
		$clgcode = $clgcode + 1;
	
		if ($clgcode < 100000000) 
		{
			$clgcode = 100000000;
		}
		$campo = 'Notes';
		if( $details != '' ){
			$campo = 'Details';
		}
		$sql_sap = "insert into OCLG (ClgCode, CardCode, $campo, Recontact, BeginTime, EndDate, ENDTime) ".
			"values ($clgcode,'$n_cliente','".$texto."','$sap_fecha','$sap_hora','$sap_fecha','$sap_hora')";
		$res = getResult(DB,$sql_sap);
		syslog(LOG_INFO, "insertSAPOCLG : $sql_sap");

		file_put_contents("/var/log/contratos/notas_insert/$clgcode.txt", $sql_sap . ($res ? 'inserted' : 'no-inserted'));
	}    
}
function updateSaldoUser($pin, $saldo, $prepago)
{
	$con = new DBConnect();
      	$con->setDB( DB2 );
      
	if ( $pin > 0 || $pin != '' )
	{
		if ( $prepago == 0 )
		{
			$sql="UPDATE cc_card SET credit = credit + $saldo WHERE username = $pin LIMIT 1";
		}
		else if ( $prepago == 1 )
		{
			$sql="UPDATE PrepagosJyc.`balance` SET `saldo` = saldo + $saldo WHERE `id_usuario` = $pin LIMIT 1";
		}
	}
	$res = $con->makeQuery( $sql );

	syslog(LOG_INFO,"Contratos : updateSaldoUser --> pin:$pin , prepago:$prepago , inc saldo:$saldo : $res ");
      
	return $res;
}
function GetCreditUser($pin, $prepago)
{
    $con = new DBConnect();
    $con->setDB( DB2 );
    $sql = '';
    if ( $pin > 0 && $pin != '' )
    {
        if ( $prepago == 0 )
        {
            //$pin = 75337; //pin EDU
            $sql="SELECT credit as saldo FROM cc_card WHERE username = $pin LIMIT 1";
        }
        else if ( $prepago == 1 )
        {
            //$pin =4383;//ìn DIEGO
            $sql="SELECT saldo FROM PrepagosJyc.`balance` WHERE `id_usuario` = $pin LIMIT 1";
        }
    }
    else
    {
	    return '';
    }
    //syslog(LOG_INFO,"Contratos funciones_ext GetCreditUser : $sql");
    if ( $sql != '' )
    {
        $res = $con->makeQuery( $sql );  
        $num_rows = $con->getNumRows();
        if ( $num_rows > 0 )
        {  
            $row = $con->fetchObject();
        }
    }

    syslog(LOG_INFO,"Contratos funciones_ext GetCreditUser [count:$num_rows][saldo: {$row->saldo}][pre:$prepago][pin:$pin]");

    return $row;

}
function updateStatusUser($pin, $prepago)
{
    $con = new DBConnect();
    $con->setDB( DB2 );

    if($pin > 0 || $pin != '')
    {
        if($prepago ==0)
        {
            //$pin = 75337; //pin EDU
            $sql="UPDATE cc_card SET `status`= 1 WHERE username = $pin LIMIT 1";
        }
        else if ( $prepago == 1 )
        {
            //$pin =4383;//ìn DIEGO
            $sql="UPDATE PrepagosJyc.`usuarios` SET `status`= 1 WHERE `pincode` = $pin LIMIT 1";
        }
    }
    $res = $con->makeQuery( $sql );
    
    return $res;  

}
function updateContrato($id_contrato,$tipo_contrato, $id_validador = 0)
{
    $con = new DBConnect();
    $con->setDB( DB2 );

    $check = 1;

    if ( $tipo_contrato == 'combinado' )
    {
        $check = 3;
    }

    if ( $tipo_contrato == 'sim_act' )
    {
        $check = 5;
    }

    if ( $tipo_contrato == 'fallo' )
    {
        $check = 2;
    }
    
    /*$new_verificar_user = 0;
    if( isset($_SESSION['new_verificar_user']) ) {
        $new_verificar_user = $_SESSION['new_verificar_user'];
    }*/
    
    $sql = "UPDATE PrepagosJyc.contratos SET `check`=$check, id_validador='".$id_validador."' WHERE id='$id_contrato' LIMIT 1 ";
    //$sql = "UPDATE PrepagosJyc.contratos SET `check`='".$check."' WHERE id='".$id_contrato."' LIMIT 1 ";
    $res = $con->makeQuery( $sql );

    syslog(LOG_INFO,"CONTRATOS_UPDATE_CONTRATO : update contrato --> id_contrato: $id_contrato , check:$check tipo contrato: $tipo_contrato");

    return $res;
}

function getOferta($oferta)
{
    $con = new DBConnect();
    $con->setDB( DB2 );

    // saca la descripcion de la oferta
    $sql = "SELECT * FROM PrepagosJyc.contratos_ofertas WHERE id = $oferta";
    $con->makeQuery( $sql );
    $result = $con->fetchObject();

    return $result;
}
function getProveedorOferta($oferta) {
	return array( 
		'proveedor' => ( !empty($oferta->proveedor_recarga) ? 'Manual Cuba' : 'Proveedor por defecto' ), 
		'cantidad' => ( !empty($oferta->recarga_manual_cuba) ? ' - ' . $oferta->recarga_manual_cuba : '')
	);
}
function UpdateOfertaContrato($nombre, $id_oferta, $id)
{
    $con = new DBConnect();
    $con->setDB( DB2 );

    $sql = "UPDATE PrepagosJyc.contratos SET ofertas='$nombre', id_oferta='$id_oferta' WHERE id='$id'";
    $res = $con->makeQuery( $sql );

    return $res;
}
function getSaldoRecargaCubacelContratos($prepago, $id)
{
    $con = new DBConnect();

    //chequeo si tiene saldo_recarga AND `saldo` >= '$amount_divisa'
    if ( $prepago == 1)
    { 
        $con->setDB( DB2 );
        $sql = "SELECT `saldo` FROM PrepagosJyc.`saldo_recarga` WHERE `id_usuario` = $id ";
    }
    if ( $prepago != 1)
    { 
        $con->setDB( DB3 );
        $sql = "SELECT `saldo` FROM `saldo_recarga` WHERE `pin` = $id "; 
    }
    $res = $con->makeQuery( $sql );
    $num_rows = $con->getNumRows();

    return $con;
}
function UpdateRegalosCuc($pin,$prepago,$cant)
{
    $con = new DBConnect();

    $id = 0;

    if( $prepago == 1 )
    {
        $con->setDB( DB2 );
        $sql = "SELECT id FROM PrepagosJyc.usuarios WHERE pincode='$pin'";
        $res = $con->makeQuery( $sql );
        
        if($con->getNumRows() == 1)
        {
            $row = $con->fetchObject();
            $id = $row->id;
        }
    }
    else
    {
        $con->setDB( DB3 );
        $id = $pin;
    }
    $resultados = getSaldoRecargaCubacelContratos($prepago, $id);
    $num_rows = $resultados->getNumRows();

    //descuento saldo - amount_divisa

    if( $num_rows == 0 )
    {
        $saldo_base = 0;
        $date_time = date('Y-m-d H:i:s');
        if ( $prepago == 1 ){ $sql = "INSERT INTO PrepagosJyc.`saldo_recarga` (`id_usuario`,`saldo`,`fecha`) VALUES ('$id','$cant','$date_time')";}
        if ( $prepago != 1 ){ $sql = "INSERT INTO `saldo_recarga` (`pin`,`saldo`,`fecha`) VALUES ('$id','$cant','$date_time')"; }
    }
    if ( $num_rows > 0 ) 
    {
        $row = $resultados->fetchObject();
        $saldo_base = $row->saldo;

        if ( $prepago == 1) { $sql = "UPDATE PrepagosJyc.`saldo_recarga` SET saldo = saldo + $cant, fecha = '$date_time'  WHERE `id_usuario` = $id"; }
        if ( $prepago != 1) { $sql = "UPDATE `saldo_recarga` SET saldo = saldo + $cant, fecha = '$date_time' WHERE `pin` = $id"; }        
    }
    $res = $con->makeQuery( $sql );

    $id_prepago = '';
    if ( $prepago == 1 ){ $id_prepago = "id prepago: $id,";}
    $CONTENT_LOG = date('H:i:s')." - PIN: $pin, $id_prepago Prepago: $prepago, Cant: $cant, Saldo Recarga Base: $saldo_base\r\n$sql\r\n";
    logRegaloCuc($CONTENT_LOG);

    return $res; 

}
function setRegalosCucNew($id_oferta,$id_contrato,$importe)
{
	$data_c = getDataContrato($id_contrato);
	$data = getDataOferta($id_oferta);
    
	$regalo_cuc = $data->regalo_cuc;
	$importe_cobrado = $data->precio;
	$pin = $data_c->pin;
	$prepago = $data_c->prepago;
	
	if( $importe_cobrado == 0 ){
		$valor_cuc = $regalo_cuc;
	}else{
		$valor_cuc = ($importe * $regalo_cuc) / $importe_cobrado;
	}
	//para oferta paquetería prueba vero 13/02/19, quito pk ya no hay recargas web: 12/03/19
	if( $id_oferta == 462 ){
		//$valor_cuc =  $regalo_cuc;
	}

	//LOG
	$CONTENT_LOG = date('Y-m-d H:i:s')." - Id Contrato: $id_contrato - CONTRATOS_CUC ($id_oferta) $id_contrato - $valor_cuc = ".
		"($importe * $regalo_cuc) / $importe_cobrado";
	syslog( LOG_INFO , $CONTENT_LOG );
	logRegaloCuc($CONTENT_LOG);
	//FIN LOG
   	
       	if( $pin != 0 ){	
		
		$res_cuc = UpdateRegalosCuc( $pin , $prepago , $valor_cuc );
		return $valor_cuc;
		exit();
	}
	return 0;

}

function logRegaloCuc($CONTENT_LOG)
{
    $Log_file   = "/var/www/html/factura/inc/logs/regalo_cuc_log.txt";
    $fp = fopen($Log_file, "a+");
    fputs($fp,$CONTENT_LOG);
    fclose($fp);
}
/*
  Compruebo si ya existe ese comprobante
*/
function checkRefill($note){

  $con = new DBConnect();
  $con->setDB( DB2 );

  $sql = "SELECT id FROM PrepagosJyc.refills WHERE `note` = '$note'";
  $res = $con->makeQuery( $sql );
  $num_rows = $con->getNumRows();

  $res = 0;
  if($num_rows >= 1){$res = 1;}

  return $res;
}
/*
  Añade el log de la recarga con campos: fecha, admin, id_user, amont, note (comprovane(tpc,cuenta), reference:manual)

  Params: admin, is_user, amount, note

*/
function setRefill($data){

  extract($data);

  $con = new DBConnect();
  $con->setDB( DB2 );

  $reference = 'manual';
  if(isset($tipo) && $tipo == 'TPV_SHA'){
    $reference = 'TPV_SHA';
  }

  $sql = "INSERT INTO PrepagosJyc.refills (`date`,`admin`,`id_user`,`amount`,`note`,`reference`)". 
  " VALUES('".date('Y-m-d H:i:s')."','$admin','$id_user','$amount','$note','$reference')";
  $res = $con->makeQuery( $sql );

  return $res;

}
/*
  Inserta log registro en mya2billing:
  params: pin, amount
*/
function setPayPost($data)
{
    extract($data);

    $did = getDataCC_CARD($pin,'id');          
    $card_id = $did->id;
    
    $con = new DBConnect();
    $con->setDB( DB2 );

    $sql = "INSERT INTO cc_logrefill (`date`,`credit`,`card_id`,`description`)".
    " VALUES('".date('Y-m-d H:i:s')."','$amount','$card_id','[BLOB-NULL]')";
    //$res = $con->makeQuery( $sql );

    $sql = "SELECT MAX(id) Mid FROM `cc_logrefill` WHERE credit='$amount' AND card_id = '$card_id'";
    $res = $con->makeQuery( $sql );
    $num_rows = $con->getNumRows();
    
    if($num_rows >= 1)
    {
        $row = $con->fetchObject();
        $id_logrefill = $row->Mid;
    }  

    $sql = "INSERT INTO cc_logpayment (`date`,`payment`,`card_id`,`id_logrefill`,`description`)".
    " VALUES ('".date('Y-m-d H:i:s')."','$amount','$card_id','$id_logrefill','[BLOB-NULL]')";
    $res = $con->makeQuery( $sql );

    return $res;

}
function getEmailComercial($id_vendedor)
{
  $con = new DBConnect();
  $con->setDB( DB2 );

  $sql = "SELECT email FROM PrepagosJyc.admin WHERE id='$id_vendedor'";
  $res = $con->makeQuery($sql);
  $email = '';
  
  if( $con->getNumRows() >= 1 )
  {
      $row = $con->fetchObject();
      $email = $row->email;
  }

  return $email;
}
function sendEmail($html_content, $subject, $email, $cc, $add_cc)
{
    $email_envio = '';

    if ( $add_cc == 'gestion' )
    {
        $emails_envio = array('auxiliar@ensip.com', 'auxiliar@jyctel.com');
    }
  
    return php_mailer($html_content,$subject,$email,$cc,$emails_envio);
}

function sendEmailContrato($res_accion, $html_sms, $email, $add_cc, $cardname){

	$html_content = '';
	$res = 0;

	if( $email != '' ) {

		$html_content .= $res_accion;
		$html_content .= "<tr><td colspan='2'>-------------------------------------</td></tr>";
		$html_content .= $html_sms;
		$html_content .= "</table>";
		
		syslog(LOG_INFO, __file__ . ":Contratos funciones sendEmailContrato $email");
		
		$res = sendEmail($html_content,'Contrato nuevo ('.$cardname.') - Jyctel', $email, 'contratos@jyctel.com',$add_cc);
	}
	
	return $res;
}

function sendSmsContrato( $id_contrato, $regalo_cuc, $tipo_contrato, $saldo, $datos_extra ){
    
	$c = getDataContrato($id_contrato);  
        
	$moneda = '';
	if( $c->divisa == 1 ){ $moneda = 'EUR'; }
	if( $c->divisa == 2 ){ $moneda = 'USD'; }
    
	//syslog( LOG_INFO, __FILE__ . "getPrefix f_ext {$c->Cellular} $tipo_contrato " );  
    
	$message = '';
	$add_text = ( $regalo_cuc > 0 ) ?  "y $regalo_cuc CUC de regalo" : '';
    
	switch ($tipo_contrato) {
	case 'cubanacard':
		$message = $c->CardName ." su saldo se ha incrementado en $saldo $moneda $add_text. Gracias por su compra. Le recomendamos bodega con entrega en Cuba: http://bit.ly/2pfGl60";
		break;
	case 'combinado':
		$message = $c->CardName ." su saldo se ha incrementado en $saldo $moneda $add_text. Gracias por su compra. Le recomendamos bodega con entrega en Cuba: http://bit.ly/2pfGl60";
		break;
	case 'impagos':
		$message = $c->CardName.", hemos recibido su pago. Su servicio JYCTEL ha sido reactivado. RECOMENDAMOS para RENTA de COCHES en Cuba: http://bit.ly/2NFWoDo";
		break;
	case 'impagos_sin_activacion':
		$message = $c->CardName .", hemos recibido su pago. Su servicio JYCTEL ha sido reactivado. RECOMENDAMOS para RENTA de COCHES en Cuba: http://bit.ly/2NFWoDo";
		break;  
	case 'SIM':
		$message = "Su SIM Cubacel ha sido tramitada con ID ".$datos_extra['reference_operator'].", retirar en ".$datos_extra['oficina']." el titular de la CI ".$datos_extra['id_card_sim']." Gracias por su compra. JYCTEL";
		break;      
	}
	
	$result = 0;

	if( $message != '' ){	
		$pre = getPrefix( $c->Cellular );
		$prefix = ( $pre != '-1' && $pre == '6' ) ? '34' : '';
		$response = Utilities::countryFromNumber( $prefix . $c->Cellular, new DBConnect() );
		$proveedor = Utilities::check_proveedor( $response->id_prov, new DBConnect() );
	
		$data = array( 
			'proveedor' => $proveedor,    
			'sender_info' => 'Jyctel',
			'send_number' => $prefix . $c->Cellular,
			'message' => $message, 
			'messageid' => substr(str_shuffle(str_repeat('0123456789',3)),0,9),
			'tipo_contrato' => $tipo_contrato . ' : ' . $id_contrato
		);
	
		$result = Utilities::send_sms( $data, new DBConnect() );
	
		InsertLogSMSNotification( $data , (array)$result );
	}      
	return $result;
}
/*
 * $data : proveedor, sender_info, send_number, message, messageid
 * $res :  resultID,msgID,resultMess (callback)
*/
function InsertLogSMSNotification( $data, $res ){

  extract($data);
  extract($res);
  $con = new DBConnect();
  $con->setDB( DB3 );  
  $sql = sprintf( "INSERT INTO FACTONLINE.sms_cdr_notifications ".
	  "(`sender_info`,`fecha`,`send_number`,`messageid`,`proveedor`,`resultID`,`msgID`,`resultMess`,`message`)".
	  " VALUE ('%s','%s','%s','%s','%s','%s','%s','%s','%s')", 
	  $data[ 'sender_info' ],
	  date('Y-m-d H:i:s'),
	  $data[ 'send_number' ],
	  $data[ 'messageid' ],
	  $data[ 'proveedor' ],
	  $res[ 'resultID' ],
	  $res[ 'msgID' ],
	  $res[ 'resultMess' ],
	 ( $data[ 'message' ] != '' ) ? $data[ 'message' ] : $data[ 'tipo_contrato' ]
  );
  $res = $con->makeQuery($sql);        

  syslog(LOG_INFO, __FILE__ . "Contratos sms_cdr_notification $sql");
}
function getNotIncludesContratos(){
	//usuario vero
	return $excludes = "AND Cardcode not in ('C020034', 'C020060') and Cellular != '663601039'";
}

function getNumContratos($tipo_c,$date,$check){
    
	$con = new DBConnect();
	$con->setDB( DB2 );
    
	$and_not_test = '';
	if(!strpos($_SERVER[REQUEST_URI], 'contratos_')) {	//21 Diego, 57 Edu
		$and_not_test = 'AND c.cod_vendedor not in (21';
		if( strpos( $tipo_c, 'ivr_permitido') === false ){
			$and_not_test .= ',57';
		}    
		$and_not_test .= ')';
	}
	$no_include = getNotIncludesContratos();
    
	$sql = "SELECT c.id FROM PrepagosJyc.contratos c INNER JOIN PrepagosJyc.contratos_ofertas co ON c.id_oferta = co.id ".
		"WHERE ".$tipo_c." AND `check`= $check AND fecha >= '".$date->format('Y-m-d H:i:s')."' $no_include $and_not_test ORDER BY fecha";
	$res = $con->makeQuery($sql);        
     
    
	$num_rows = $con->getNumRows();
    
	return $num_rows;
}

function generateNumPedido(){

  $con = new DBConnect();
  $con->setDB(DB2);
  $new_n_pedido = 0;
  for ($i=0; $i < 10; $i++){ 
  
    $digits = 5;
    $n_pedido =  rand(pow(10, $digits-1), pow(10, $digits)-1);

    $sql = "SELECT num_pedido FROM PrepagosJyc.contratos WHERE num_pedido = '$n_pedido'";
    $res = $con->makeQuery($sql);        

    $es_n_pedido = $con->getNumRows();

    if($es_n_pedido == 0){

      $new_n_pedido = $n_pedido;
      break;
    }

  }
  return $new_n_pedido;
}
function checkIfExistsNumPedido( $num_pedido, $and = '' )
{
	$sql = "SELECT num_pedido FROM PrepagosJyc.contratos WHERE fecha >= '" . date('2020-01-01') . "' and num_pedido = '" . $num_pedido . "' " . $and;
	$res = getResult(DB2,$sql);

	if( $res->rows > 0 ){
		return true;
	}else{
		$sql = "select id from Ventas.ventas_ingresos where num_pedido = '".$num_pedido."' and token in (select token from Ventas.ventas_datos_producto where estado = 1)";
		$res = getResult(DB2,$sql);
		if( $res->rows > 0 ) return true;
	}	
	syslog(LOG_INFO, 'error : ' . __FILE__ . ' : ' . __method__ . ':not-found: '.$num_pedido);

	return false;
}

function arreglarPedidosCuentaRepetidos($npedido){

  $con = new DBConnect();
  $con->setDB(DB2);
  
  /*Para generar un numero aleatorio
  $digits = 5;
  $n_pedido =  rand(pow(10, $digits-1), pow(10, $digits)-1);
  */

  $sql = "SELECT id,num_pedido FROM PrepagosJyc.contratos WHERE num_pedido = '$npedido' AND ingreso='cuenta'";
  $res = $con->makeQuery($sql);        

    $es_n_pedido = $con->getNumRows();

    if($es_n_pedido > 0){
      $row = $con->fetchObject();
      if($es_n_pedido == 1){
        $ids[0] = $row->id;
      }
      else{
        foreach ($row as $key => $value) {
          $ids[] = $value->id;
        }
      }
      foreach ($ids as $key => $value) {
         $sql_u = "UPDATE PrepagosJyc.contratos SET num_pedido = 0 WHERE id='$value' LIMIT 1";
         $res = $con->makeQuery($sql_u);   
      }
    }

  
  return $res;
}

/*
  Funcion para comprobar si se puede automatizar la recarga cubacel

*/
function checkAutomRecCubacel($oferta,$contrato)
{
	$time_syslog = time();
	$arr_multiplos = array(1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20);

	/* SUM CUCs  */ 
	$sum_recargas = $contrato['sum_recargas'];
	$recarga_es_multiplo = 0;
	$valor_es_multiplo = 0;
	$oferta_recarga = $oferta['recarga'];

	$tipo_contrato = '';
	if( isset( $contrato['tipo_contrato'] ) ){
		$tipo_contrato = $contrato['tipo_contrato'];

		if( $tipo_contrato == 'nauta' ){
			$oferta_recarga = $oferta['recarga_nauta'];
		}
	}
	
	syslog(LOG_INFO,"Contratos [$time_syslog][func_ext][tipo:{$tipo_contrato}][recs:".json_encode($contrato) );
	syslog(LOG_INFO,"Contratos [$time_syslog][func_ext][tipo:{$tipo_contrato}][oferta:".json_encode($oferta) );
	$valor_multiplo_importe = 0;
	$valor_es_multiplo = 0;
    
	foreach ($arr_multiplos as $key => $value) {
		$val_x_recarga = $oferta_recarga * $value;
    		//syslog(LOG_INFO,"Contratos [$time_syslog][func_ext][$tipo_contrato][rec:".$val_x_recarga." == ".$sum_recargas."]");
		if( $val_x_recarga > 0 && ( $val_x_recarga == $sum_recargas ) ) {
			$recarga_es_multiplo = 1;
			$valor_multiplo_recarga = $value;
			syslog(LOG_INFO,"Contratos [$time_syslog][func_ext][rec_multiplo:$recarga_es_multiplo] (OFERTA RECARGA * VALOR) ".$oferta_recarga*$value." == (SUM RECARGAS) $sum_recargas");
		}
		if( empty( $tipo_contrato ) ) {
			syslog(LOG_INFO, __FILE__ - ':'.__METHOD__ . 'tipo__:'.print_r($contrato,true));
			$tipo_contrato = 'cubacel';
			$dec = strlen(substr(strrchr($contrato['importe'], "."), 1));
			$redondeo = 1;
    
			if ( $dec >= 2 ) {
				$redondeo = $dec;
			}
			$contrato['importe'] = number_format($contrato['importe'], $redondeo);
		}
    
		if( $tipo_contrato != 'combinado' ) {
    
			if( round(( $oferta['importe'] * $value ),5) == round($contrato['importe'],5) ) {
				$valor_es_multiplo = 1;
				$valor_multiplo_importe = $value;
			}
		} else if( $contrato['tipo_contrato'] == 'combinado' ) {
    
			if( $sum_recargas == ($oferta['importe'] * $value)){
    
				syslog(LOG_INFO,"Contratos [$time_syslog][func_ext][IMPORTES:$sum_recargas == ($oferta[importe] * $value)]");
				$valor_es_multiplo = 1;
				$valor_multiplo_importe = $value;
			}
		}
	}
    
	syslog(LOG_INFO,"Contratos [$time_syslog][func_ext][RECARGA MULTIPLO:$recarga_es_multiplo][VALOR MULTIPLO:$valor_es_multiplo]");
    
	$es_valido = 0;
    
	if (($recarga_es_multiplo * $valor_es_multiplo) == 1) {
		if ($valor_multiplo_recarga == $valor_multiplo_importe) {
			$es_valido = 1;
		}
	}
	syslog(LOG_INFO,"Contratos [$time_syslog][func_ext][valido:$es_valido]=>VALOR MULTIPLO RECARGA:$valor_multiplo_recarga == VALOR MULTIPLO IMPORTE:$valor_multiplo_importe");
    
	return $es_valido;
}

function checkCombinadoMultiplos($importes,$contrato){

	$valor_multiplo_ll = 0;
	$llamada_multiplo = 0;
	$valor_multiplo_cu = 0;
	$cubacel_multiplo = 0;
      
	$arr_multiplos = array(1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20);
      
	foreach ($arr_multiplos as $key => $value ) {
		if( $contrato['llamadas'] * $value == $importes['llamadas']){
			
			$valor_multiplo_ll = $value;
			$llamada_multiplo = 1;
		}
	    
		if( $contrato['cubacel'] * $value == $importes['cubacel']){
			$valor_multiplo_cu = $value;
			$cubacel_multiplo = 1;
		}    
	}
	$es_multiplo = 0;
      
	if( $valor_multiplo_ll == $valor_multiplo_cu ){

		$es_multiplo = $llamada_multiplo * $cubacel_multiplo;
      	}

	return $es_multiplo;
}
function checkRecargaMultiplo($recarga,$oferta_nauta){
      
	$es_multiplo = 0;
	$arr_multiplos = array(1,2,3,4,5);
      
	foreach ($arr_multiplos as $key => $value) {
	    
		if($oferta_nauta * $value == $recarga){
		  
			$es_multiplo = 1;
		}
      	}
      
	return $es_multiplo;
}
/*
	Genero la factura del contrato, ahora mismo solo cubacel
	params: nombre_oferta,prepago,cardcode,pin,id_contrato,nota,recargas,valor_cuc,
	res_update_contrato,email_enviado,sms_enviado,html_sms
	OBSOLETA

function generarFactura($data){

  extract($data);

  $res_accion = "<table id='res_accion_contratos'>";
  $res_accion .= "<tr><td>Contrato</td><td class='right'>".$nombre_contrato."</td></tr>";
  $res_accion .= "<tr><td colspan ='2'><b>OFERTA</b></td></tr>";
  $res_accion .= "<tr><td colspan ='2'>".$nombre_oferta."</td></tr>";
  if(count($recargas)>=1){
       	  $es_preventa = ($preventa == 1)?'Preventa':'Directa';
  $res_accion .= "<tr><td>Recarga</td><td class='right'>".$es_preventa."</td></tr>";
  }
    $es_prepago = ($prepago==1?'Prepago':'Postpago');
    $es_prepago = ($prepago==3?'':$es_prepago);
  $res_accion .= "<tr><td>Tipo</td><td class='right'>".$es_prepago."</td></tr>";
  $res_accion .= "<tr><td>CardCode</td><td class='right'>".$cardcode."</td></tr>";
  $res_accion .= "<tr><td>CardName</td><td class='right'>".$cardname."</td></tr>";
    $es_pin = ($pin!=0)?"****".$pin[(strlen($pin)-3)].$pin[(strlen($pin)-2)].$pin[(strlen($pin)-1)]:'';
  $res_accion .= "<tr><td>PIN</td><td class='right'>".$es_pin."</td></tr>";
  $res_accion .= "<tr><td>Id registro</td><td class='right'>".$id_contrato."</td></tr>";
  $res_accion .= "<tr><td style='vertical-align: text-top;'>Nota:</td><td class='right' style='word-break:break-all;'>".$nota."</td></tr>"; 
  $res_accion .= "<tr><td>Recargas:</td></tr>";
    foreach ($recargas as $key => $value) {
  $res_accion .="<tr><td></td><td class='right'>".$value['num']."-".$value['monto']."".$value['moneda']."</td></tr>"; 
    }
  $res_accion .= "<tr><td>Regalo Cuc:</td><td class='right'>$valor_cuc</td></tr>";
  $res_accion .= "<tr><td colspan='2'>".($res_update_contrato==1?'<span class="updated">CONTRATO ACTUALIZADO!':'<span class="noupdated">CONTRATO NO ACTUALIZADO')."</span></td></tr>";
 // $res_accion .= $html_sms."<tr><td>Email</td><td class='right'>".($email_enviado==1?'Enviado':'No enviado')."</td></tr>";
 // $res_accion .= "<tr><td>SMS</td><td class='right'>".($sms_enviado==1?'Enviado':'No enviado')."</td></tr>";  
  $res_accion .= "</table>";

  return $res_accion;

}
 */
function generarFacturaGenerica($data){

	$tipo_contrato = $data['tipo_oferta'];
	$id_contrato = $data['id_contrato'];
	$contrato = getDataContrato($id_contrato);
	$cardcode = $data['cardcode'];
	$es_prepago = (isset($data['es_prepago'])) ? $data['es_prepago'] : '';
	$es_pin = (isset($data['es_pin'])) ? $data['es_pin'] : '';

	$valor_llamadas = (isset($data['valor_llamadas'])) ? $data['valor_llamadas'] : 0;
	$valor_cubacel = (isset($data['valor_cubacel'])) ? $data['valor_cubacel'] : 0;

	$recargas = $data['recargas'];
	$inc_amount = $data['inc_amount'];
	$update_saldo = (isset($data['update_saldo'])) ? $data['update_saldo']:0;
	$importe_tpv = $contrato->importe_tpv;
	$recargas = $data['recargas'];
	$id_vendedor = $contrato->cod_vendedor;
	$email_vendedor = getEmailComercial($contrato->cod_vendedor);

	$res_accion = "<table id='res_accion_contratos'>";
	$res_accion .= "<tr><td>Contrato</td><td class='right'>$tipo_contrato</td></tr>";
	$res_accion .= "<tr><td colspan ='2'><b>OFERTA</b></td></tr>";
	$res_accion .= "<tr><td colspan ='2'>$contrato->ofertas</td></tr>";
	$res_accion .= "<tr><td>Tipo</td><td class='right'>".$es_prepago."</td></tr>";
	$res_accion .= "<tr><td>CardCode</td><td class='right'>$cardcode</td></tr>";
	$res_accion .= "<tr><td>PIN</td><td class='right'>".$es_pin."</td></tr>";
	$res_accion .= "<tr><td>Id registro</td><td class='right'>$id_contrato</td></tr>";
	$res_accion .= "<tr><td>Vendedor</td><td class='right'>$id_vendedor - $email_vendedor</td></tr>";

	//si es pago con tpv tiene num pedido
	if($importe_tpv != 0){
		$res_accion .= "<tr><td>Num. Pedido</td><td class='right'>$contrato->num_pedido</td></tr>";
	}

	//si tiene valor en llamadas
	if( $valor_llamadas > 0 ){
		$res_accion .= "<tr><td>Llamadas</td><td class='right'>$valor_llamadas</td></tr>";
	}

	//si es combinado y tiene cubacel
	//$tipo_contrato=='combinado' && 
	if( $valor_cubacel > 0 ){
		$res_accion .= "<tr><td>Cubacel</td><td class='right'>$valor_cubacel</td></tr>";
	}

	//si tiene recargas
	if( isset($recargas) ){
		$res_accion .= "<tr><td>Recargas:</td></tr>";

		foreach ($recargas as $key => $value) {
			$res_accion .="<tr><td></td><td class='right'>".$value['num']."-".$value['monto']."".$value['moneda']."</td></tr>"; 
		}
	}

	//si tiene recarga de saldo
	if( $inc_amount > 0 ){
		$res_accion .= "<tr><td>SALDO ".($update_saldo==1?'':'<span class="noupdated">(FALLO)')."</span></td><td class='right'>$inc_amount</td></tr>";
	}
	return $res_accion;
}

function addRefills($prepago,$dr){
      
	if($prepago==1)
	{
		$resPP = setRefill($dr);
	}
	else
	{
		$resPP = setPayPost($dr);
	}
      
	return $resPP;  
}

function csvRead($d){

  $row = 1;
  $fichero = $d['fichero'];
  if (($handle = fopen($d['ruta'].$d['fichero'].".".$d['tipo'], "r")) !== FALSE) {
    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
      $num = count($data);
      $row++;
      foreach ($data as $d){
        
        $d1 = explode(';', $d);
        $datos[$row]['celular'] = $d1[0];
        $datos[$row]['codigo'] = $d1[1];
        $datos[$row]['archivo'] = $fichero;
          
      }
    }
    fclose($handle);
    
    
  }
  return $datos;
}
/*
alternativa si falla: test/upload_codigos.php
*/
function uploadFile($files, $id_oferta){

  $con = new DBConnect();
  $con->setDB( DB2 );
  $error_file = $files['n_archivo']['error'];
  $archivo = $files["n_archivo"]['name'];

  if (file_exists('codigos/'.$archivo)) 
  {
	  $res = "El archivo ya existe.";
  }
  else
  {
	  if (copy($files['n_archivo']['tmp_name'],'codigos/'.$archivo)) 
	  {
		  if($error_file == 0)
		  {
			  $ara = explode('.',$archivo);
			  $fichero = $ara[0];
			  $tipo = $ara[1];
			  $datos = array('fichero'=>$fichero, 'tipo'=>$tipo,'ruta'=>'codigos/');
			  $listado = csvRead($datos);     
			  $i = 1;
			  foreach ($listado as $key => $reg) 
			  {
				  if ( $reg['celular'] != '' || $reg['codigo'] != '' )
				  {
					  $sql = "INSERT INTO PrepagosJyc.contratos_list_cod_prs ".
					  "(`celular`,`codigo`,`id_oferta`,`archivo`,`estado`) ".
					  " VALUES ('".$reg['celular']."','".$reg['codigo']."','".$id_oferta."','".$reg['archivo']."','0')";
					  $res = $con->makeQuery( $sql );
					  // syslog( LOG_INFO, " insert_codigos : $sql");
				  }
				  $i = $i * $res;
			  }
		  }
      	  }
  }
  return $i;
}
function checkRechargeIfStillPending($id_contrato, $numero){

  $con = new DBConnect();
  $con->setDB( DB3 );

  $sql = "SELECT id FROM  `recargas_pendientes_no_preventas` WHERE  `check` IN (3,2) AND id_contrato='$id_contrato' AND numero='$numero'";
  $res = $con->makeQuery( $sql );


  return $con->getNumRows();
}

function getRecargaGuardadaPendiente( $id_contrato, $numero, $id_recarga = null ){
	$con = new DBConnect();
      	$con->setDB( DB3 );
      
	$sql = "SELECT id, `check`, dt_recarga, id_recarga FROM  `recargas_pendientes_no_preventas` WHERE  `check` IN (3,2) AND id_contrato='$id_contrato' AND numero='$numero' ";
	
	if( $id_recarga != null ){ 
		$sql .= "and id_recarga = $id_recarga";
	}

      	$res = $con->makeQuery( $sql );
	$row = null;	
	if( $con->getNumRows() >= 1 ){
      		$row = $con->fetchObject();
	}
	return $row;
}

function validateEmail($email){
  
  $emailErr = 0; 
  if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $emailErr = 1; 
  }


  return $emailErr;

}

function getResult($baseD,$sql)
{
  $db = new DBConnect();
  $db->setDB( $baseD );
  $db->makeQuery( $sql ); 

  return $db;
}

/*
 * se le pasa el nombre de la funcion si existe en factura/inc/dbmySQL.inc.php
 */
function getConnLi( $tipo = '' )
{
	if ($tipo == '') { 
		$dc = getDataCR();

	} else {
		$dc = $tipo();
	}

	$conn = new mysqli($dc['host'], $dc['user'], $dc['pwd'], $dc['bd']);

	/*ini_set('log_errors',1);       
	mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
	syslog(LOG_INFO, "CR : conn" . mysqli_error($conn));
	 */
	return $conn;
}

function getOfertasById($id)
{
    $ofertas = array();

    $sql = "SELECT id, nombre, visibilidad FROM PrepagosJyc.contratos_ofertas WHERE id = $id AND `status`= 1 and solo_ivr=0 ORDER BY orden ASC";
    $res = getResult(DB2,$sql);
    
    if ( $res->getNumRows() > 0 ) 
    {
        if ( $res->getNumRows() == 1 )
        {
            $o = $res->fetchObject();
            $ofertas[0] = $o;
        }
        else
        {
            $ofertas = $res->fetchObject();
        }
    }

    return $ofertas;
}
function getOfertasByTipo($id)
{
    $ofertas = array();

    $sql = "SELECT id, nombre, visibilidad FROM PrepagosJyc.contratos_ofertas WHERE tipo = $id AND `status`= 1 and solo_ivr=0 ORDER BY orden ASC";
    $res = getResult(DB2,$sql);
    
    if ( $res->getNumRows() > 0 ) 
    {
        if ( $res->getNumRows() == 1 )
        {
            $o = $res->fetchObject();
            $ofertas[0] = $o;
        }
        else
        {
            $ofertas = $res->fetchObject();
        }
    }

    return $ofertas;
}
function getNumberOfOfertas($id)
{
    $sql = "SELECT id FROM PrepagosJyc.contratos_ofertas WHERE tipo = $id AND `status`= 1 ORDER BY orden ASC";
    $res = getResult(DB2,$sql);

    return $res->getNumRows();
}
function getHtmlTiposOfertas($tipo,$descripcion) {
    
	$html = '';
	if ( isset($descripcion) && $descripcion == 'visibles' && count($tipo) >= 1) {
		foreach ($tipo as $key => $producto) {
	    		$html .= "<li class='li_primary noc0n' id='$producto->id'>";
			$html .= "<a class='a_primary'>";
                	$html .= $producto->tipo;
			$html .= "</a>";
			$html .= "</li>";
		}
    	} else if (isset($descripcion) && $descripcion == 'combinado') {
		if (isset( $tipo ) && is_array( $tipo )) {
			foreach ($tipo as $key => $producto) {  
				$html .= "<li class='li_primary noc0n' id='$producto->id'>"; 
				$html .= "<a class='a_primary'>";
				$html .= $producto->tipo;
				$html .= "</a>";
				$html .= "</li>";
			}
		}
	} else if ( count($tipo) >= 1) {
		$html .= "<li class='li_primary'>";
	
		$nombre = 'Combinado';
		if ($descripcion == 'resto') {
			$nombre = 'Resto';
		}
		$html .= "<a class='a_primary' >$nombre</a>";
		$html .= "<ul class='ul_secondary'>"; 
	
		foreach ($tipo as $key => $producto) { 
			$class_c0n = "class='noc0n'";
			$res_c0N = stripos($producto->tipo,'C0/Nuevos');
			
			if ($res_c0N !== false) {
				$class_c0n = "class='c0n'";
			}
	
			$html .= "<li id='$producto->id' $class_c0n >";
			$html .= "<a >";
			$html .= $producto->tipo;
			$html .= "</a>";
			$html .= "</li>";
		}
		$html .= "</ul>";  
		$html .= "</li>";    
	}
	return $html;
}

function getTiposOfertas( $id = null )
{
	$where = (!is_null($id)) ? "where id = ".$id : '';
	$sql = "SELECT id,tipo FROM PrepagosJyc.contratos_tipos_ofertas ".$where." ORDER by id ASC";
    $res = getResult(DB2,$sql);
    $html = '';

    if ( $res->getNumRows() > 0 ) {
	    $tipo_ofertas = $res->fetchObject();
	    $externo = 0;    
  		
	    if ($res->rows == 1) {
		    $una_oferta = $tipo_ofertas;
		    unset($tipo_ofertas);
	    	    $tipo_ofertas[0] = $una_oferta;
	    }
	    //en Resto antes había Otros
       
	    if ( isset($_SESSION['tipo_comercial']) && $_SESSION['tipo_comercial'] == 'externo') {
		    $externo = 1;    
		    $tipos_visibles = array('Envio Dinero');
	    } else {
    		    $tipos_visibles = array('Cubacel','Cubanacard','Resto');
	    }
     	    // $tipos_co_nuevos = array('C0/Nuevos - Cubanacard','C0/Nuevos - Cubacel','C0/Nuevos - Combinado');
	  
	    foreach ($tipo_ofertas as $key => $producto) {

            if ( getNumberOfOfertas($producto->id) >= 1 )
            {

		if ( in_array($producto->tipo, $tipos_visibles) )
                {
                    $tipos['visibles'][] = $producto;
                }
                /*else if ( in_array($producto->tipo, $tipos_co_nuevos)) {
                    $tipos['c0_nuevos'][] = $producto;
		}*/
		else if ( !$externo && ($producto->tipo == 'Combinado Nauta Cubanacar' || $producto->tipo == 'Combinado')){
			$tipos['combinado'][] = $producto;
		}
                else if (!$externo) {
                    $tipos['resto'][] = $producto;
                }
            }
        }    
	if ($externo) { return '';exit();}          

	//$html = "<a name='listado_ofertas'></a>";   
        $html = "<ul class='lista_ofertas'>"; 
        //$tipos_ordenados = array_merge(array_flip(array('visibles','c0_nuevos','resto')),$tipos);
	if (!$externo) {
		$tipos_ordenados = array_merge(array_flip(array('visibles','combinado','resto')),$tipos);
	} else {
		$tipos_ordenados = $tipos;
	}
        foreach ($tipos_ordenados as $descripcion => $producto) 
        {
            $html .= getHtmlTiposOfertas($producto,$descripcion);
            
        }
        $html .= "</ul>";  
    }

    return $html;
}
function getRateInitialPostpago($pin)
{
    $dTa = getDataCC_CARD($pin,'tariff');              
   
    $rateinitial = 0;
    if ( is_object($dTa) )
    {
        $tariff = $dTa->tariff;
    
        $con = new DBConnect();
        $con->setDB( DB2 ) ; 
        $sql3 = "SELECT idtariffplan FROM  `cc_tariffgroup_plan` WHERE  `idtariffgroup` =$tariff";
        $con->makeQuery( $sql3 ); 
        syslog(LOG_INFO, "POSTPAGO_REG idtariffplan: $sql3 $con->rows");  
        if ( $con->rows >= 1 )
        {
            $datos3 = $con->fetchArray();
            $idtariffplan = $datos3[0];

            $sql4 = "SELECT rateinitial FROM  `cc_ratecard` WHERE  `idtariffplan` =$idtariffplan AND  `dialprefix` = '0053' ";
            $con->makeQuery( $sql4 ); 
            syslog(LOG_INFO, "POSTPAGO_REG rateinitial: $sql4 $con->rows");     
            if ( $con->rows >= 1 )
            {
                $datos4 = $con->fetchArray();
                $rateinitial = $datos4[0];
            }
        }
    }
    syslog(LOG_INFO, "POSTPAGO_REG rateinitial: $rateinitial");     
    return $rateinitial;
}
/*
  Para calculo incremento saldo Cubanacard, viene de tratar_contrato.php y funciones_auto_contrato.php
  $valor_porcentaje = ($iva/100)+1;//iva personal
  
*/
function calculoIncrementoSaldo($prepago,$pin,$contrato,$valor_porcentaje,$data_user)
{
    $regalo_oferta = array();
    $data_oferta = getDataOferta($contrato->id_oferta);
    $regalo_oferta['precio_regalo'] = $data_oferta->precio_regalo;
    $regalo_oferta['porc_regalo'] = $data_oferta->porc_regalo;
    $inc_amount = $contrato->llamadas;

    if ( $prepago == 1 )
    {
        $tarifa_asociada = $data_user['precio_minuto'];
        $valor_iva_final = $valor_porcentaje;

	if( isset($regalo_oferta['precio_regalo']) && $regalo_oferta['precio_regalo'] > 0 )
        {
		//if( $data_user['especial'] == 1 ){ $valor_porcentaje = 1; }
		$precio_regalo = $regalo_oferta['precio_regalo'];
		$valor_iva = $data_user['iva'];
		$valor_iva_final = $valor_iva;
		//si el iva es 0 multiplico por 1
		if($valor_iva == 0){
			$valor_iva_final = 1;
		}
      		$valor_a_dividir = 0;

	      	//si en sap retariff es null y es especial: PrepagosJyc.promos_especiales le quito el bono 
		//if( $data_user['retariff'] == null && $data_user['especial'] == 1 ) 
		if( $data_user['especial'] == 1 ) 
    		{
                	syslog(LOG_INFO, "CONTRATOS_INC_SALDO -> QUITO REGALO $pin -> iva user: $valor_iva");
	                $bono = 1.2556; //1.2056
			//if($valor_iva != 0){ $valor_a_dividir = 1.2056 * $valor_iva; }
			//QUITO EL BONO
                	$precio_regalo = round(($regalo_oferta['precio_regalo']  / $bono),2);
            	}
		syslog(LOG_INFO,__FILE__ ."[PIN:$pin][especial:{$data_user['especial']}][IVA:$valor_iva_final][PR:{$regalo_oferta['precio_regalo']}]");
		
		$inc_amount = round(($contrato->llamadas * $tarifa_asociada) / ( $precio_regalo * $valor_iva_final ),2);
		syslog(LOG_INFO,__FILE__ ."[PIN:$pin]  $inc_amount = round(($contrato->llamadas * $tarifa_asociada) / ( $precio_regalo * $valor_iva_final ),2);");
	}

        if ( isset($regalo_oferta['porc_regalo']) && $regalo_oferta['porc_regalo'] > 0 )
        {
            $valor_porcentaje = ($contrato->llamadas * $regalo_oferta['porc_regalo'])/100;
            $inc_amount = round($contrato->llamadas + $valor_porcentaje,2);
	}
    }
    if ( $prepago == 0 )
    {
        if( isset($regalo_oferta['precio_regalo']) && $regalo_oferta['precio_regalo'] > 0 )
        {
            $valor_sin_rateinitial = round(($contrato->llamadas / $valor_porcentaje),2);
            $rateinitial = getRateInitialPostpago($pin);
            if( $rateinitial > 0 )
            {
                syslog(LOG_INFO, "POSTPAGO_REG rateinitial > 0 : $rateinitial");     
                $valor_precio_regalo = $regalo_oferta['precio_regalo'];
                $inc_amount = round((($valor_sin_rateinitial * $rateinitial) / $valor_precio_regalo),2);
            }
        }
        else
        {
            $inc_amount = round($contrato->llamadas / $valor_porcentaje,2);
            syslog(LOG_INFO, "POSTPAGO_REG precio_regalo <= 0 : $inc_amount");     
        }
             
    }  
     if(isset($_GET['test'])){echo $datos_debug;}
    syslog(LOG_INFO, "POSTPAGO_REG inc_amount: $inc_amount"); 
    return $inc_amount;
}
function getDataCC_CARD($pin,$tipo)
{
    $sql = "SELECT $tipo FROM  `cc_card` WHERE  `username` = '$pin'";
    $res = getResult(DB2,$sql);

    $data = ( $res && $res->rows > 0 ) ? $res->fetchObject() : '';
 
    return $data;
}
function getRetarificacionSAP($tariff){

    $sql = "SELECT CAST(U_SEIRetarif AS varchar(50)) AS U_SEIRetarif FROM [@SEI_PREFTARIFINT]
     WHERE (U_SEIPrefijo LIKE '53') AND (U_SEITarif = '$tariff')";
    $res = getResult(DB,$sql);
     
    if ( $res->rows > 0 ) {
        $row1 = $res->fetchObject();
    }
    return $row1->U_SEIRetarif;
    
}
function getTariffSap($pin){
    $sql = "SELECT cast(U_SEITarIn as varchar(50)) as utesitarin FROM RDR1 WHERE cast( U_SEI_Telf as varchar(50)) LIKE '$pin'";
    $res = getResult(DB,$sql);
     
    if ( $res->rows > 0 ) {
        $row1 = $res->fetchObject();
    }
	  return $row1->utesitarin;
}
function getPretariffSap($pin){
	$sql = "SELECT cast(U_SEITarIn as varchar(50)) as utesitarin, CAST(s.U_SEIPrice AS varchar(50)) AS precio ".
		"FROM RDR1 r, [@SEI_PREFTARIFINT] s ".
		" WHERE r.U_SEITarIn = s.U_SEITarif AND U_SEIPrefijo = '53' AND cast( r.U_SEI_Telf as varchar(50)) LIKE '$pin'";
	$data_sbo = getResult( DB, $sql );
	
	if( $data_sbo->rows > 0 ) return $data_sbo->fetchObject();
}
function getDatosClientePostpago($pin)
{
    $dicc = getDataCC_CARD($pin,'initialbalance');              
    $data['initialbalance'] = $dicc->initialbalance;

    $data['tarifpromo'] = '';
    $data['utesitarin'] = getTariffSap($pin);
    return $data;
}
function getLongTinyUrl($url)
{
    $sql = "SELECT long_url FROM PrepagosJyc.tiny_url_master WHERE tiny_url = '$url'";
    $res = getResult(DB2,$sql);
    
    $long_url = ''; 
    if($res->rows > 0)
    {
        $d = $res->fetchObject();
        $long_url = $d->long_url;
    }
    else
    {
        $long_url = $url;
    }

    return $long_url;
}
function formatFechaPaytpvPays( $fecha ){
	$ffecha = substr($fecha,0,4)."-";
	$ffecha .= substr($fecha,4,2)."-";
	$ffecha .= substr($fecha,6,2)." ";
	$ffecha .= substr($fecha,8,2).":";
	$ffecha .= substr($fecha,10,2).":";
	$ffecha .= substr($fecha,12,2);

	return $ffecha;
}
function getLeyendasOfertas( $leyenda = '' ){

	$con = new DBConnect();
	$con->setDB( DB2 );	

	$sql = "select valor from PrepagosJyc.contratos_leyenda";
	$res = $con->makeQuery($sql);

	$options_leyenda = '';
	if( $con->getNumRows() > 0 ){
		while ($row = $con->fetchObject()) {
			foreach ($row as $valor) {
				$leyendas[] = $valor->valor;
			}
		}
		
		foreach ($leyendas as $l){
			$selected = ( $leyenda == $l ) ? 'selected' : '';
			$options_leyenda .= '<option value="'.$l.'" '.$selected.'>'.$l.'</option>';
			
		}
	}

	return $options_leyenda;
}
function checkCelularRecargado ($celular) {

	$con = new DBConnect();
	$con->setDB( DB3 );	

	$sql = "select id from mobile_logs where mobNumber = '53".$celular."'";
	$res = $con->makeQuery ($sql);
	if ($con->getNumRows() > 0 ) return true;
	else return false;
}

function getHistorialMovil ($movil, $no_parrafo, $id_contrato = '') {

	if (empty($movil)) return '';

	$con = new DBConnect();
	$con->setDB( DB2 );     // 104001 Prepagos/Pospagos

	$and_id = '';
	if (!empty($id_contrato)) {
		$and_id = ' and id < '.$id_contrato;
	}	

	//$and_solo_recargas = "recargas != 'a:0:{}' and";
	$sql = "select min(fecha) as primera_fecha, max(fecha) as segunda_fecha, round(sum(importe_tpv+importe_cuenta),2) as gasto ".
		"from PrepagosJyc.contratos where cellular = '".$movil."'".$and_id;
        $con->makeQuery( $sql );
        $raw = $con->fetchObject();
	$fechas = '';
	
	if ($raw) {
		$fechas = '<span class="">Primer contrato: <b>'.$raw->primera_fecha . '</b>,</span>'.
			'<span class="">Ultimo contrato: <b>'.$raw->segunda_fecha .'</b>,</span>'.
			'<span class="">Gasto total: <b>'.$raw->gasto.'</b></span>';

		if (!$no_parrafo) {
			$fechas = '<p style="margin: 8px 0px;font-size:12px;">'.$fechas.'</p>';
		} else {
			$fechas = str_replace (',', '', $fechas);
		}
        }
        return $fechas;
}

function getNewDirContratos()
{
	$req_uri = $_SERVER['REQUEST_URI'];
	$data = explode('/', $req_uri);
	if (isset($data[1]) && strpos($data[1], 'contratos') !== false) {
		return $data[1];
	} else {
		return 'contratos';
	}
}
