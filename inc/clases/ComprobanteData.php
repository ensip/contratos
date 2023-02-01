<?php 
//include_once("/var/www/html/factura/contratos/ventas/funciones_ventas.php");
/*
 * Obtiene datos comprobante, si es TK decodifica y obtiene datos, si es pegado saca datos del texto.
 **
 * */

class ComprobanteData{
	
	protected $comprobante = '';
	protected $ingreso = '';
	protected $tk = array();
	protected $datos_comprobante = array();
	protected $no_comprobar_num_pedido = false;
	public $warning = '';

	function __construct($info)
	{
		$this->comprobante = (isset($info['texto'])) ? $info['texto'] : '';
		$this->ingreso = (isset($info['ingreso'])) ? $info['ingreso'] : '';
		$this->datos_comprobante['divisa'] = (isset($info['divisa'])) ? $info['divisa'] : '';
	}
	
	public function getDataLacubana($token) 
	{
		$datos = array();

		$con = getConnLi('getDataCbn');
		//$sql = "select pagado,cantidad,nombre_divisa,id_transaccion,notas,fecha_pago from cbn.payment_tokens where token = '".$token."' and id_usuario = '".$usuario."' and test = 0";
		$sql = "select pagado,cantidad,nombre_divisa,id_transaccion,notas,fecha_pago from cbn.payment_tokens where token = '".$token."' and test = 0";
		//syslog(LOG_INFO, __FILE__ . ' _ ' - __method__ . ':'.$sql);
		$res = $con->query($sql);

		if ($res->num_rows > 0) {
			while ($row = $res->fetch_object()) {
				$datos = $row;
			}
		}
		return $datos;
	}

	public function getDataCR($cr, $version) 
	{
		$resultado = '';
		$this->datos_comprobante['nombre'] = 'TPV_CR';
		$this->datos_comprobante['num_pedido'] = $cr;

		$sql = "select * from notificaciones_paytpv where rOrder = '$cr' and Response = 'OK'";
		
		if ($version == 'cr') {
			$conn = getConnLi(); //mira en bbdd cubarentcars
		} else {
			$conn = getConnLi('getDataCrNew'); //mira en bbdd cubarentcars nueva
		}

		if ($res = $conn->query($sql)) {

			if ($res->num_rows > 0) {
				while($reg = $res->fetch_object()) {//debug($reg);

					$divisa = ($reg->Currency == 'EUR') ? 1 : 2;
					if ($version == 'cr') {
						$importe = preg_replace("/\./" , "," , ($reg->AmountEur + 0));			

						if ($reg->Currency == 'USD') {
							$importe = $reg->Amount / 100;
						}
					} else {
						$importe = preg_replace("/\./" , "," , (($reg->Amount/100) + 0));			
					}

					$this->datos_comprobante['importe'] = $importe;
					$this->datos_comprobante['operacion'] = $reg->Response .":". $reg->ErrorDescription;
					$this->datos_comprobante['concepto'] = $reg->Concept;
					$this->datos_comprobante['fecha'] = formatFechaPaytpvPays($reg->BankDateTime);

					
					$resultado = ($reg->Response == 'OK') ? "Pago realizado con éxito Tipo Operación: Autorización " : "Pago fallido Tipo Operación: Denegada ";
					$resultado .= "Importe: " . $importe ."&nbsp;". $reg->Currency . " ";
					$resultado .= "Número pedido: $cr ";
					$resultado .= "Operación Autorizada con Código: ".$reg->id_transaccion." ";
					$resultado .= "Código de Error: {$reg->ErrorID} ";
					$resultado .= "ID pagos referencia: ".$reg->TokenUser;

					if ($divisa  != $this->datos_comprobante['divisa']) {
						return array(0 => 'error' , 'str' => 'Error divisa');
					}else{
						$this->datos_comprobante['moneda'] = $reg->Currency;
					}
				}
			} else{
				return array(0 => 'error' , 'str' => 'No hay comprobante asociado a este token ' . $cr);
			}
		}
		return d8($resultado);
	}

	public function getDataCR2V_($cr, $num_pedido) 
	{
		var_dump($_POST[$_POST['tipo_item']]['datos']['producto']['reserva']);
		$search_token_orig = token::search($num_pedido);
		//echo $token_pago_interno_orig = $search_token_orig->get_token();

		$this->datos_comprobante['nombre'] = 'TPV_CR';
		$this->datos_comprobante['num_pedido'] = $cr;
		$sql = "select * from ordenes where id = '$cr'";
		syslog(LOG_INFO, __FILE__ . ':' . __METHOD__ . ':' .$sql);
		$conn = getConnLi('getDataCrNew'); //mira en bbdd cubarentcars nueva

		if ($res = $conn->query($sql)) {

			if ($res->num_rows > 0) {
				while($reg = $res->fetch_object()) {
					if (!in_array($reg->estado, array('pagado', 'completado'))) {
						syslog(LOG_INFO, __FILE__ . ':' . __METHOD__ . ':estado no pagado ni completado: ' . $cr);
						if ($reg->estado == 'pendiente_pago') {
							$this->warning = 'pendiente-pago-comprobante';

						} else {
							return array(0 => 'error' , 'str' => 'Comprobante con estado no válido ('.$reg->estado.').');
						}
					}
					$moneda = trim($reg->divisa);
		       			$tx_authcode = '';
					$es_el_padre = 1;	

                               		if ($transacciones = unserialize($reg->transacciones_gpt)) {
						$cant_transacciones = count($transacciones);
						$campo_precio = 'total_a_pagar';
						$divisa =  1;
						if ($moneda == 'USD') {
							$divisa = 2;
							$campo_precio = 'total_a_pagar_usd';
						}
						if ($cant_transacciones == 1) {
							$importe = $reg->$campo_precio;			
						}
						foreach ($transacciones as $tx) {
							
							$token_pago_interno = '';
							$patterns = token::getPatternCr2V1();
							foreach ($patterns as $pattern) {
								if (preg_match($pattern, $tx['token'], $match)) {
									$search_token = token::search($tx['token']);
									/*echo "<!--";
									$token_pago_interno = $search_token->get_token();
									echo "-->";*/
								}
							}
							syslog(LOG_INFO, __method__ . ':'.$num_pedido. '-'.$tx['token']);	
							//if ($num_pedido == $tx['token'] || (isset($token_pago_interno) && $token_pago_interno == $token_pago_interno_orig)) 
							if ($num_pedido == $tx['token']) {
								$es_el_padre = 0;	
								$token_texto = $tx['token'];
								/*if (isset($token_pago_interno) && $token_pago_interno == $token_pago_interno_orig) {
									$token_texto = $num_pedido;
								}*/

								$this->datos_comprobante['num_pedido'] = $token_texto;
								$this->datos_comprobante['operacion'] = $tx['RESULT'] .":". $tx['MESSAGE'];
		
								$importe = $tx['AMOUNT'] / 100;                               
					       			$tx_authcode = $tx['AUTHCODE'];
							} 
						}
						if ($es_el_padre) {
							$this->datos_comprobante['num_pedido'] = $num_pedido;
							$this->datos_comprobante['operacion'] = ($reg->pagado ? '00:Approved' : '');
						}

						$this->datos_comprobante['fecha'] = $reg->fecha;
						$this->datos_comprobante['importe'] = $importe;
						$this->datos_comprobante['concepto'] = '';
	
						if ($divisa  != $this->datos_comprobante['divisa']) {
							return array(0 => 'error' , 'str' => 'Error divisa');
	
						} else {
							$this->datos_comprobante['moneda'] = $moneda;
						}
					} else {
						$divisa =  ($moneda == 'USD')  ? 2 : 1;
						$importe = $reg->cantidad_pagada;
						$this->datos_comprobante['fecha'] = $reg->fecha;
						$this->datos_comprobante['importe'] = $importe;
						$this->datos_comprobante['concepto'] = '';
						$this->datos_comprobante['num_pedido'] = $num_pedido;
						$this->datos_comprobante['operacion'] = ($reg->pagado ? '00:Approved' : '');

					}
					$resultado = "Pago realizado con éxito Tipo Operación: Autorización ";
					$resultado .= "Importe: " . $importe ."&nbsp;". $moneda . " ";
					$resultado .= "Número pedido:  " . ($es_el_padre ? $num_pedido : $token_texto) . " ";
					$resultado .= "Operación Autorizada con Código: ".$tx_authcode." ";
				}
			} else{
				return array(0 => 'error' , 'str' => 'Comprobante incorrecto en este comercio: Cubarentcars.');
			}
		}
		return d8($resultado);
	}

	/*
	 *	buscar en ensip/cbn: payment_tokens where metodo = network_iframe
	 * */
	public function getDataNetwork($num_pedido, $token) {
		
		$con = getConnLi('getDataEnsip');
		
		$sql = sprintf("select pt.*, nt.authorizationCode from payment_tokens pt inner join network_tokens nt on pt.token = nt.token and pt.token  LIKE '%s' and pagado = 1", $token);
		$res = $con->query($sql);

		$datos = array();
		if ($res->num_rows > 0) {	
			while ($row = $res->fetch_object()) {
				$datos = $row;
			}
		}
		$resultado = '';
		if (empty($datos)) {
			$con = getConnLi('getDataCbn');
			$sql = sprintf("select pt.*, nt.* from payment_tokens pt inner join network_tokens nt on pt.token = nt.token and pt.token  LIKE '%s' and pagado = 1", $token);
			$res = $con->query($sql);

			$datos = array();
			if ($res->num_rows > 0) {	
				while ($row = $res->fetch_object()) {
					$datos = $row;
				}
			}
		} 
		if (!empty($datos)) {
			
			$importe = $datos->cantidad;
			$moneda = $datos->nombre_divisa;
			
			$this->tk['divisa'] = $moneda;
			$this->tk['num_pedido'] = $num_pedido;
		
			$this->datos_comprobante['importe'] = $importe;
			$this->datos_comprobante['moneda'] = $moneda;
			$this->datos_comprobante['num_pedido'] = $num_pedido;
			$this->datos_comprobante['operacion'] = ($datos->pagado ? 'OK':'KO');
			$this->datos_comprobante['concepto'] = $datos->notas;
			$this->datos_comprobante['fecha'] = $datos->fecha_pago;

			$resultado = "Pago realizado con éxito Tipo Operación: Autorización ";
			$resultado .= "Importe: " . $importe ."&nbsp;". $moneda . " ";
			$resultado .= "Número pedido:  " . $num_pedido . " ";
			$resultado .= "Operación Autorizada con Código: ".$datos->authorizationCode." ";
		}

		return $resultado;
	}

	private function getDataPrepagos($token, $usuario) {
		$datos = array();

		$sql = "select pagado,cantidad,nombre_divisa,id_transaccion,notas,fecha_pago from PrepagosJyc.payment_tokens where token = '".$token."' and id_usuario = '".$usuario."'";
		$res = getResult(DB2 , $sql);
		
		if ($res->rows > 0) {	
			while ($row = $res->fetchObject ()) {
				$datos = $row;
			}
		}
		return $datos;
	}

	/*
	 *	probado si pagado o no pagado y ok
	 *
	 * */
	function getDataCBN($match) {

		$resultado = '';

		if (is_null($match)) { 
			$id_usuario = $this->tk['ref_tk'];
			$token = $this->tk['token'];
			$tipo_tk = $this->tk['tipo_tk'];
	
		} else {	//si se llama de fuera de la clase el match se hace allí: ajax_busqueda_ventas
			$id_usuario = $match[1];
			$token = $match[3];
			$tipo_tk = $match[2];
		}		
		
		$data = $this->getDataLacubana($token);
		
		if (empty($data)) {
			return array(0 => 'error' , 'str' => 'Comprobante incorrecto');
			exit;
		} else {

			if (!$data->pagado) {
				return array(0 => 'error' , 'str' => (!empty($data->notas)) ? utf8_encode($data->notas) : 'Pago no completado');
				exit;
			}

			$merchant_order = $tipo_tk . ":" . $id_usuario . ":" . $token; 
		
			$pagado = $data->pagado;
			$cantidad = $data->cantidad;
			$divisa = $data->nombre_divisa;
			$importe = preg_replace("/\./" , "," , ($cantidad + 0));			

			//getDataDivisa : return EUR, USD
			if (getDataDivisa($divisa)  != $this->datos_comprobante['divisa']) {
				return array(0 => 'error' , 'str' => 'Error divisa');
				exit;
			}
			
			$this->tk['divisa'] = $divisa;
			$this->tk['num_pedido'] = $merchant_order;
		
			$this->datos_comprobante['importe'] = $importe;
			$this->datos_comprobante['moneda'] = $this->tk['divisa'];
			$this->datos_comprobante['num_pedido'] = $merchant_order;
			$this->datos_comprobante['operacion'] = ($data->pagado ? 'OK':'KO');
			$this->datos_comprobante['concepto'] = $data->notas;
			$this->datos_comprobante['fecha'] = $data->fecha_pago;
			
			if ($pagado) { 
				$resultado .= "Pago realizado con éxito \n\nTipo Operación: Autorización \n\n"; 
			} else { 
				$resultado .= "Pago fallido \n\nTipo Operación: Denegada \n\n"; 
			}
			$resultado .= "Número pedido: ".$merchant_order." \n\n";
			$resultado .= "Importe: " . $importe ."&nbsp;". $divisa . " \n\n";
		}
		$resultado .= "Código de Error: $cod_error \n\n";
		$resultado .= "ID pagos referencia: $id_pagos_referencia \n\n";
		
		return d8($resultado);
	}
	/*
	 *	llamada desde dentro y desde ajax_busqueda_ventas.php
	 * */
	public function getDataTK($match = null) {

		if (is_null($match)) { 
			$id_usuario = $this->tk['ref_tk'];
			$token = $this->tk['token'];
			$tipo_tk = $this->tk['tipo_tk'];
	
		} else {	//si se llama de fuera de la clase el match se hace allí: ajax_busqueda_ventas
			$id_usuario = $match[1];
			$token = $match[3];
			$tipo_tk = $match[2];
		}

		$data = $this->getDataPrepagos($token, $id_usuario);
	
		if (empty($data)) {
			return array(0 => 'error' , 'str' => 'Comprobante incorrecto');
			exit;
		} else {
		
			if (getDataDivisa($data->nombre_divisa)  != $this->datos_comprobante['divisa']) {
				return array(0 => 'error' , 'str' => 'Error divisa');
				exit;
			}
			$divisa = getDataDivisa($data->nombre_divisa);
			$this->tk['divisa'] = $divisa;
			$divisa = $data->nombre_divisa;
		
			//$merchant_order = $id_usuario."_".$this->tk['tipo_tk']."_".$token; 
			$merchant_order = '';
			if (isset($this->tk['num_pedido'])) {
				$merchant_order = $this->tk['num_pedido'];	
			}
			$pagado = $data->pagado;
			$cantidad = $data->cantidad;
			$importe = preg_replace("/\./" , "," , ($cantidad + 0));			
			
			//$id_pagos_referencia = $data->id_pagos_referencia; //TODO no esta en la tabla de cbn paytpv_tokens
			//$cod_error = $reg_pt->DS_ERROR_ID; //TODO IGUAL ARRIBA
			
			$this->datos_comprobante['importe'] = $importe;
			$this->datos_comprobante['moneda'] = $divisa;
			$this->datos_comprobante['num_pedido'] = $merchant_order;
			$this->datos_comprobante['operacion'] = ($data->pagado) ? 'OK':'KO';
			$this->datos_comprobante['concepto'] = $data->notas;
			$this->datos_comprobante['fecha'] = $data->fecha_pago;
			
			if ($pagado) { 
				$resultado .= "Pago realizado con éxito \n\nTipo Operación: Autorización \n\n"; 
			} else { 
				$resultado .= "Pago fallido \n\nTipo Operación: Denegada \n\n"; 
			}
			$resultado .= "Importe: " . $importe ."&nbsp;". $divisa . " \n\n";
			$resultado .= "Número pedido: ".$merchant_order." \n\n";
		}

		$resultado .= "Código de Error: $cod_error \n\n";
		$resultado .= "ID pagos referencia: $id_pagos_referencia \n\n";
		
		return d8($resultado);
	}	

	function getPedidoEuros($comprobante) 
	{
		$np = explode('pedido:',$comprobante);
		
		if (isset($np[1])) {

			$np1 = explode('Tarjeta', $np[1]);
			
			if (strpos($np1[0] , 'N') !== false) {
				$np2 = explode('N', $np1[0]);
				
				if (isset($np2[0])) {
					$num_pedido = trim($np2[0]);
				}
				if (!ctype_digit($num_pedido)) {
					return array(0 => 'error' , 'str' => 'Num pedido incorrecto');
				} 
				else return $num_pedido;
				
			}
		}
		return array(0 => 'error' , 'str' => 'Num pedido incorrecto');
	}

	function getPedidoUsd($comprobante) 
	{
		$np = explode('pedido:', $comprobante);
		
		if (isset($np[1])) {
			$np1 = explode('Fecha', $np[1]);
			if (isset($np1[1])) {
				$num_pedido = trim($np1[0]);
				if (!ctype_digit($num_pedido)) {
					return array(0 => 'error' , 'str' => 'Num pedido incorrecto');
				}
				else return $num_pedido;
			}
		}
		return array(0 => 'error' , 'str' => 'Num pedido incorrecto');
	}

	function extractData($comprobante)
	{
		$datos_comprobante = $this->datos_comprobante;
		$datos_comprobante['nombre'] = 'TPV';
		$data = explode('Fecha:', $comprobante);
		$num_pedido = '';
	
		if (count($data) == 1) {
			return array(0 => 'error' , 'str' => 'Comprobante no válido');
		}
	
		if ($datos_comprobante['divisa'] == 1) {
			$num_pedido = $this->getPedidoEuros($comprobante);
		}
	
		if ($datos_comprobante['divisa'] == 2) {
			$num_pedido = $this->getPedidoUsd($comprobante);

			if (!is_string($num_pedido)) {
				 $num_pedido = $this->getPedidoEuros($comprobante);
			}
		}

		if (is_array($num_pedido)) return $num_pedido; //retorno error
		
		$comprobar_pedido = $num_pedido;
		//COMERCIO BBVA
		if (strpos($comprobante, '348929233') !== false) {
			$num_pedido = 'BVA-' . $num_pedido;
			$comprobar_pedido = '348929233-'.$num_pedido;
		}

		$and_genius = '';
		//COMERCIO BBVA GENIUS
		if (strpos($comprobante, '353262801') !== false) {
			
			$num_pedido = 'BVA-' . $num_pedido;

			$and_genius = " and comprobante_tpv like '%GENIUS%'";
			$comprobar_pedido = 'GENIUS-' . $num_pedido;
		}
		$and_genius = '';
		//COMERCIO BANKINTER GENIUS
		syslog(LOG_INFO, __FILE__ . ": comprobante:".$comprobante);

		if (strpos($comprobante, '014443238') !== false) {
			
			$num_pedido = 'BNKR-' . $num_pedido;

			$and_genius = " and comprobante_tpv like '%GENIUS%'";
			//$comprobar_pedido = 'GENIUS-' . $num_pedido; //quitado pk se han repetido ventas
			$comprobar_pedido = $num_pedido;
		}
		if (!$this->no_comprobar_num_pedido && checkIfExistsNumPedido($comprobar_pedido, $and_genius)) {
			syslog(LOG_INFO, __FILE__ . ": checkIfExistsNumPedido:".$comprobar_pedido);
			return array(0 => 'error' , 'str' => 'Pedido existente : ' . $num_pedido);
		}	

		$datos_comprobante['num_pedido'] = $num_pedido;
		$data_pedido = explode('pedido:', $data[0]);

		if (strpos($data_pedido[1] , ' ') !== false) { 
			$data_pedido = explode(' ' ,$data_pedido[1]);
		}
		
		if ($datos_comprobante['divisa'] == 2) {

			if (strpos($comprobante , '$') === false && strpos($comprobante , 'USD') === false) {
				return array(0 => 'error' , 'str' => 'Divisa incorrecta');
			}

			$datos_comprobante['moneda'] = 'USD';
			if (isset($data[1])) {

				$data_1 = explode('Url Comercio' , $data[1]);
				$data_digitos = explode('************' , $data_1[0]);
				$datos_comprobante['digitos_tarjeta'] = trim($data_digitos[1]);
			}
		}

		if ($datos_comprobante['divisa'] == 1) {

			if (strpos($comprobante , '€') === false && strpos($comprobante , 'EUR') === false) {
				return array(0 => 'error' , 'str' => 'Divisa incorrecta');
			}

			$datos_comprobante['moneda'] = 'EUR';
			if (strpos($comprobante , 'Tarjeta:') !== false) {
				$data = explode('Tarjeta:', $comprobante);
				if (strpos($data[1] , '************') !== false) {
					$data_1 = explode('************' , $data[1]);
					$data_2 = explode(' ' , $data_1[1]) ;
					if (!ctype_digit($data_2[0])) {
						if (strpos($data_2[0] , 'Fecha') !== false) {
							$data_f = explode('Fecha' , $data_2[0]);
							if (ctype_digit($data_f[0])) {
								$data_2[0] = $data_f[0];
							}
						}
					}
					$datos_comprobante['digitos_tarjeta'] = $data_2[0];
				}
			}
		}

		$this->comprobante = $comprobante;
		$valor_importe = $this->getValorImporte($comprobante);
		
		if (isset($valor_importe[0]) && $valor_importe[0] == 'error') {
			return $valor_importe;
		}

		$datos_comprobante['importe'] =  $valor_importe / (float)100;	
		$datos_comprobante['comprobante'] = $comprobante;
		
		syslog(LOG_INFO, __FILE__ . ": checkIfExistsNumPedido: 2");

		return $datos_comprobante;
	}

	/* parsea los datos y devuelve el texto del comprobante
	 * */
	function getDataIngreso($d) 
	{
		$importe = $d['importe'];
		if (!is_numeric($importe) || $importe == 0) {
			return array(0 => 'error' , 'str' => 'Importe incorrecto') ;
		}
		$div = getDataDivisa($d['divisa']);
		$nombre = ucfirst($d['ingreso']);
		$prenombre = '';
		if ($d['ingreso'] != 'tpv') {
			$prenombre = $nombre[0];
		}
		$nombre_comprobante = $prenombre.date('YmdHis');
		if ($d['ingreso'] == 'pago_link') {
			$nombre_comprobante = $d['input_comprobante'];
		}
		syslog(LOG_INFO, __FILE__ . ':' . __METHOD__ . ': ingreso:' . $d['ingreso'] . ',comprobante:' . $d['input_comprobante']);
		$datos_comprobante = array(
			'importe' => $importe,
			'divisa' => $d['divisa'],
			'nombre' => $nombre,
			'moneda' => $div,
			'num_pedido' => $nombre_comprobante,
			'comprobante' => $d['input_comprobante']
		);
		$this->comprobante = $d['input_comprobante'];

		return $datos_comprobante;
	}

	/*
	 * 	inc/clases/GestionComprobante
	 *	checkIfExistsNumPedido : contratos/funciones_ext.php
	 * */
	function getComprobante($data = null) {
		
		if (empty($data['input_comprobante'])){
			return array(0 => 'error' , 'str' => 'Falta el comprobante');
		}
		if ($this->ingreso != 'tpv') { 
		
			//devuelve array_comprobante y setea comprobante al texto del mismo
			if ($data['ingreso'] == 'TPV_CR') {

				if (checkIfExistsNumPedido($data['input_comprobante'])) {
					syslog(LOG_INFO, __FILE__ . "ajax_gestion: TPV_CR");
					return array(0 => 'error' , 'str' => 'Pedido existente');
				}

				$patterns = token::getPatternCr();
				foreach ($patterns as $pattern) {
					if (preg_match($pattern, $data['input_comprobante'], $match)) {
						$res_comprobante = $this->getDataCR($data['input_comprobante']); 
	
						if (isset($res_comprobante[0]) && $res_comprobante[0] == 'error') {	
							return $res_comprobante;
						} 
						$this->datos_comprobante['comprobante'] = $res_comprobante;
					}
				}
			
			} else { 
				$this->datos_comprobante = $this->getDataIngreso($data);
			}

		} else {
			if ($this->comprobante != '') {
				
				$datos_comprobante = $this->searchDatos();	
				if (!empty($datos_comprobante)) {
					if (isset($datos_comprobante[0]) && $datos_comprobante[0] == 'error') {
						return $datos_comprobante;
					}

					$this->datos_comprobante['comprobante'] = $datos_comprobante;
				}
			}

			if (empty($this->tk)) {
				$this->datos_comprobante = $this->extractData($this->comprobante);
			}
		}
		return $this->datos_comprobante;
	}

	/*
	 * ajax_busqueda_ventas
	 * return array: nombre, divisa, importe, digitos_tarjeta(si existen),moneda, num_pedido
	 *
	 */
	public function getDatosComprobante() {
		return $this->datos_comprobante;
	}
	/*
	 *	no se usa
	 * */
	function getImporte()
	{
		$importe = 0;
		if ($this->comprobante[0] != 'error') {
			$importe = $this->datos_comprobante['importe'];
		}
		return $importe;
	}
	/*
	 *	no se usa
	 * */
	function getDigitos()
	{
		$digitos_tarjeta  = $this->datos_comprobante['digitos_tarjeta'];
		return  ($digitos_tarjeta != '' ? $digitos_tarjeta : '');
	}

	private function getMoneda($comprobante)
	{
		$moneda = 'EUR';

		switch($comprobante) {
			case (preg_match('/USD|usd/', $comprobante, $matches) !== 0):
				break;    
			case (preg_match('/EUR|eur/', $comprobante, $matches) !== 0):
				break;
			case (preg_match('/\$/', $comprobante, $matches) !== 0):
				break;
			case (preg_match('/\€/', $comprobante, $matches) !== 0):
				break;        
			default:
				$moneda = '';
				break;
		}

		if (isset($matches[0])) {
			$moneda = $matches[0];
		}
		return $moneda;
	}

	private function getTextoImporte($comprobante, $moneda)
	{
		$data = explode($moneda , $comprobante);
		$data_importe_1 = explode('Importe:' , $data[0]);
		$importe = trim($data_importe_1[1]);
		$importe = strip_tags($importe);

		return $importe;
	}

	private function getValorImporte($comprobante)
	{
		$importe = $this->getTextoImporte($comprobante, $this->getMoneda($comprobante));	

		$valor_importe = 0;

		if ($importe == 0 || $importe == '') {
			$valor_importe = array(0 => 'error' , 'str' => 'Comprobante incorrecto, falta el importe');
		} else {
			if (strpos($importe, '.') !== false && strpos($importe, ',') !== false) {
				$temp_importe = str_replace(array('.') , array(''), $importe);
				$temp_importe = str_replace(array(',') , array('.'), $temp_importe);
				$valor_importe = $temp_importe * 100;

			} else{
				$valor_importe = str_replace(',' , '' , $importe);
			}
			
			syslog(LOG_INFO, __FILE__ .":importe :".$valor_importe);
			if (!preg_match('/^[0-9]+(\.[0-9]{1,2})?$/', $valor_importe))
			{
				$valor_importe = array(0 => 'error' , 'str' => 'Importe incorrecto');
			}
		}
		return $valor_importe;
	}
	private function getDataToken($searchToken) {

		$match = null;
		$dataComprobante = array();
		$tipoComprobante = $searchToken->tipo_comprobante;
		$nombreComprobante = $tipoComprobante;
	
		switch($tipoComprobante) {

			case 'CBN':
				$dataComprobante = $this->getDataCBN($match); 
			break;
			case 'CR':
				$dataComprobante = $this->getDataCR($searchToken->get_num_pedido(), 'cr2'); 
				if (isset($dataComprobante[0]) && $dataComprobante[0] == 'error') {
					$dataComprobante = $this->getDataCR2V_($searchToken->get_token(), $searchToken->get_num_pedido()); 
				}
			break;	
			case 'NETWORK':
				$dataComprobante = $this->getDataNetwork($searchToken->get_num_pedido(), $searchToken->get_token()); 
			break;
			default:
				if ($searchToken->es_tpv_tk()) {
					$nombreComprobante = 'TK';
					$dataComprobante = $this->getDataTK($match); 

				}
			break;
		}
		if (!empty($dataComprobante)) {
			$this->setNombreComprobante($nombreComprobante);
		}
		return $dataComprobante;
	}
	private function searchDatos() {

		$res_comprobante = array();

		$search_token = token::search($this->comprobante);
		if (!is_null($search_token)) {
			if ($search_token->es_match()) {
				$match = null;

				if (!$this->no_comprobar_num_pedido && checkIfExistsNumPedido($search_token->get_num_pedido())) {
					syslog(LOG_INFO, __FILE__ . ": TK(I|A) : " . $search_token->get_num_pedido());
					return array(0 => 'error' , 'str' => 'Token <b>'.$search_token->get_num_pedido().'</b> existente');
				}
				
				$this->tk['ref_tk'] = $search_token->get_user_token();
				$this->tk['tipo_tk'] = $search_token->get_tipo_comprobante();
				$this->tk['token'] = $search_token->get_token();
				$this->tk['num_pedido'] = $search_token->get_num_pedido();

				$res_comprobante = $this->getDataToken($search_token);	
			}
		}
		return $res_comprobante;
	}
	public function set_no_comprobar_num_pedido($bool) {
		$this->no_comprobar_num_pedido = $bool;
	}
	private function setNombreComprobante($tipo_token) 
	{
		$this->datos_comprobante['nombre'] = strtoupper($this->ingreso) . '_' . $tipo_token;
	}
}
/*
echo"<pre>";
$comprobante = 'Datos de la operación	Importe:37,00 $	Comercio:JYCTEL	(ESPAÑA)	Terminal:	22443063-3	Número pedido:	1500919152
	Fecha:	24/07/2017   19:59	Descripción producto:	Pago prepago	OPERACIÓN AUTORIZADA CON CÓDIGO:  024103
	Nombre Titular: 	Comercial Jyc	Número Tarjeta: 	************4333	Url Comercio:	http://www.jyctel.com
	Descripción producto: 	Pago prepago
	Entra en iupay y descubre una nueva forma de comprar, más sencilla, rápida y segura. Información en www.iupay.es	IMPRIMIRCONTINUAR';

$comprobante = 'Resultado de la compra    Tipo Operación: Autorización Importe: 20,00  EUR Comercio: JYCTEL ESPA#A S(BARCELONA) Código comercio: 010664902 Terminal: 1 Número pedido: 144194 Número Tarjeta: ************8675 Fecha: 21 / 08 / 2017 Hora: 11 : 03 : 41 Operación Autorizada con Código:040180    DEB  ';
//$comprobante = 'C022384_TK_338b023b7e08a90062fe4009b5b635ec';
//$comprobante = 'PRE220000613_TK_3053f5ae007371a60f2181d9f959c9df';
$importe = $c->getImporte();
//echo"OBJ:";print_r($c);
echo"Comprobante:";print_r($res);
echo"<p>Importe: $importe</p>";
echo "Digitos: ".$digitos = $c->getDigitos();
 */
?>
