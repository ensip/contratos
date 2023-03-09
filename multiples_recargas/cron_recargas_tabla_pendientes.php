<?php
ob_flush();

include_once("/var/www/html/factura/contratos/multiples_recargas/recarga.api.php");
include_once("/var/www/html/factura/contratos/multiples_recargas/cntrl.recarga.php");
include_once("/var/www/html/factura/inc/clases/Class.Utilities.php");
include_once("/var/www/html/factura/contratos/funciones.php");

exec("/bin/ps aux | /bin/grep cron_recargas_tabla_pendientes | /bin/grep -v grep | grep -v sh | grep -v vi | /usr/bin/wc -l",$otro_proceso);

if ($otro_proceso[0] > 1) {
	print("Otro proceso cron_recargas_tabla_pendientes\n");
	syslog(LOG_INFO, __FILE__ . ": Otro proceso");
	exit();
}

exec("/bin/ps aux | /bin/grep set_auto_recarga | /bin/grep -v grep | grep -v sh | grep -v tee | grep -v tail | /usr/bin/wc -l",$otro_proceso);

$al = "Proceso: $otro_proceso[0] : ".date('H:i:s')." \n";

if ($otro_proceso[0] > 1) {
	//print $otro_proceso[0]."\n";
	print $al . " proceso exit\n";
	syslog(LOG_INFO, "cron_recargas_tabla_pendientes: Hay $otro_proceso[0] procesos, proceso exit");
	exit();
}

function gestion_recargas_hechas($recargas_hechas) {

	foreach ($recargas_hechas as $id_contrato => $recarga) {
		
		$recargas = getRecargasContratos($id_contrato);
		$text_contrato .= '<h2>Notificacion de recargas Pendientes</h2>';
		$text_contrato .= '{<br />&nbsp;&nbsp;&nbsp;| ID: '.$id_contrato.' <br />&nbsp;&nbsp;&nbsp;| CardCode: '.$recargas->CardCode.' <br />&nbsp;&nbsp;&nbsp;| CardName:'.$recargas->CardName.' <br />&nbsp;&nbsp;&nbsp;| Oferta:'.$recargas->ofertas.' <br />&nbsp;&nbsp;&nbsp;| Fecha: '.$recargas->fecha.' <br />&nbsp;&nbsp;&nbsp;| ';
		$text_contrato .= "$recarga}";

		$cabeceras = "Content-type: text/html\r\n";
		$cabeceras .= "From: recargas_auto@jyctel.com\r\nContent-type: text/html\r\n";
		$cabeceras .= 'Cc:administracion@jyctel.com,diego@jyctel.com' . "\r\n";
		$emails_envio = array('administracion@jyctel.com','diego@jyctel.com');

	}
	mail('auxiliar@ensip.com','Contratos recargas automaticas Pendientes',$text_contrato,$cabeceras);	
}

function getRecargasContratos($id){

	$dbClass = new DBConnect();
	$dbClass->setDB( DB2 );

	$sql = "SELECT c.id, c.Cellular, c.CardCode, c.CardName, c.fecha, c.ofertas, a.email, c.recargas FROM PrepagosJyc.contratos c, PrepagosJyc.admin a WHERE c.cod_vendedor = a.id AND c.id=".$id."";
	
	$dbClass->makeQuery( $sql );
	if ($row = $dbClass->fetchObject() ) { $recargas = $row;}

	return $recargas;
}
function insert_recargas_contratos_hechas($id_recarga_hecha, $id_contrato) {
	$dbClass = new DBConnect();
	$dbClass->setDB( DB3 );
	
	$sql = sprintf("insert into FACTONLINE.recargas_contratos_hechas (id_c,id_r) values (%s, %s)", $id_contrato, $id_recarga_hecha);
	$res = $dbClass->makeQuery($sql);
	echo "\n$sql : $res\n";
	
	return $res;

}

function updateContratoRecargasPendientes($id_contrato, $id_recarga_hecha, $estado, $confirmId) {

	$dbClass = new DBConnect();
	$dbClass->setDB( DB2 );

	$sql = "select recargas from PrepagosJyc.contratos where id = " . $id_contrato;
	$res = $dbClass->makeQuery( $sql );

	$row = $dbClass->fetchObject();
	$recargas = unserialize($row->recargas);

	$new_recargas = array();
	foreach ($recargas as $key => $values) {

		$new_recargas[$key] = $values;
		if ($key == $id_recarga_hecha) {
			echo "$key == $id_recarga_hecha\n";
			$new_recargas[$key]['ConfirmId'] = $confirmId;
			$new_recargas[$key]['status'] = ($estado == 1 ? 'Hecha' : 'Error');
		}
	}

	echo "\nID_C: $id_contrato, ID_R: $id_recarga_hecha, S: $estado, C: $confirmId \n";

	$serialized_recargas = '';
	if (!empty($new_recargas) && is_array($new_recargas)){
		$serialized_recargas = serialize($new_recargas);
		$sql = "update PrepagosJyc.contratos set recargas = '".$serialized_recargas."' where id = ".$id_contrato." limit 1";
		$res_update = $dbClass->makeQuery( $sql );
	}
	echo "\n serialized_recargas:" . $serialized_recargas . " res: " .$res_update;
}

function updateRecarga_pendiente($id, $check, $confirmId) {
	
	$dbClass = new DBConnect();
	$dbClass->setDB( DB3 );

	$text_Error = ($check==2) ? 'error_recarga' : '';

	echo $sql = "UPDATE FACTONLINE.recargas_pendientes_no_preventas "
	." SET  `check`=$check, `error`='$text_Error', `dt_recarga` = now(), confirmID = '$confirmId' WHERE id=$id";
	
	$res = $dbClass->makeQuery($sql);
	
	return $res;
}

/*
sleep(70);
exit;
 */

$dgPc =  getPararCron( 'parar_cron' );
$parar_cron_running = $dgPc->parar_cron;

if ($parar_cron_running) {
	syslog(LOG_INFO, __FILE__ . ":Cron parado: $parar_cron_running\n");
	exit;
}

syslog(LOG_INFO, __FILE__ . " Se ejecutara cron_recargas_tabla_pendientes");

$max_recargas = 10;
$recarga_current = 0;

$recargas_hechas = array();

$dbClass = new DBConnect();
$dbClass->setDB( DB3 );

$sql_count = "SELECT id FROM FACTONLINE.recargas_pendientes_no_preventas rp WHERE `check`= 3 ";

$wait = "";
while ( $recarga_current <= $max_recargas && $dbClass->makeQuery($sql_count) ) {
	
	$hay_pendientes = $dbClass->getNumRows();	

	syslog(LOG_INFO, __FILE__ . ": Pendientes : ".$hay_pendientes);
	syslog(LOG_INFO, __FILE__ . " : recarga_current : $recarga_current <= max_recargas : $max_recargas");

	//si no quedan recargas pendientes salgo del bucle
	
	if ($hay_pendientes == 0) {
		syslog(LOG_INFO, __FILE__ . ": No quedan recargas pendientes");
		break;
	}
	
	$sql = "SELECT rp.id, rp.numero, rp.monto, rp.divisa, rp.id_contrato, rp.operator_code, rp.id_recarga "
	."FROM FACTONLINE.recargas_pendientes_no_preventas rp WHERE `check`=3 "
	."AND rp.numero NOT IN (SELECT numero FROM FACTONLINE.recargas_pendientes_no_preventas WHERE dt_recarga > DATE_SUB(NOW(),INTERVAL 1 MINUTE)) "
	."LIMIT 1";	
	$res = mysql_query( $sql );

	while ($recarga_current <= $max_recargas && mysql_num_rows($res) > 0) {

		if(mysql_num_rows($res) == 0){ 
			break;
		}
		
		syslog(LOG_INFO, __FILE__ . ": Quedan recargas pendientes : " . mysql_num_rows($res));

		while ($recarga_current <= $max_recargas && $row = mysql_fetch_object($res)) {

			$recarga_current ++;

			$min_minutos_delay_mismo_num_cuba = 5;

			$sql_tiempo_seguridad = "SELECT created FROM mobile_logs WHERE mobNumber = '".$row->numero."' and (created >= '".(time() - 60 * $min_minutos_delay_mismo_num_cuba)."')";
				
			if ($res_t = $dbClass->makeQuery( $sql_tiempo_seguridad )) {
			
				// No recargo aún
				if ($dbClass->getNumRows() > 0) {
					continue;
				}

				echo "<p>HAGO RECARGA</p>";
				$params = array(
					'PhoneNumber'  => $row->numero,
					'monto'        => $row->monto,
					'moneda'       => $row->divisa,
					'operatorCode' => $row->operator_code,
					'token' => 'IDC_' . $row->id_contrato
				);

				if ($row->operator_code == "CU" and preg_match("/@/", $row->numero)) {
					$params['operatorCode'] = "NU";
				}
				
				syslog(LOG_INFO, __FILE__ . ": recarga pendiente : " . serialize($params));

				echo "<pre>";print_r($params);echo "</pre>";
				$res_rec = make_recharges($params);
				//$res_rec[0]='ok';

				if ($res_rec[0] == 'ok') {
					$estado = 1;
					$confirmId = $res_rec[1];
					$recargas_hechas[$row->id_contrato] .= "<p>[".$row->numero." - ".$row->monto." ".$row->divisa."] - ConfirmId: ".$confirmId."</p>";
				} else {
					$estado = 2;
					$confirmId = '';
					$recargas_hechas[$row->id_contrato] .= "<p>[ERROR: ".$row->numero." - ".$row->monto." ".$row->divisa."]</p>";
				}
				updateRecarga_pendiente($row->id, $estado, $confirmId);
				updateContratoRecargasPendientes($row->id_contrato, $row->id_recarga, $estado, $confirmId); 
				insert_recargas_contratos_hechas($row->id_recarga, $row->id_contrato);
			}	
			//break;		
		}
		break;
	}
}

if(!empty($recargas_hechas)) {
	gestion_recargas_hechas($recargas_hechas);
}

syslog(LOG_INFO, __FILE__ . ": exit");

exit;	
