<?php 

class InsertVenta{
	protected $venta = '';
	protected $asignaciones = '';
	protected $comprobantes = '';
	protected $email_cliente = '';
	protected $productos = '';
	protected $notas = '';
	protected $query = array();
	protected $queryInsertVenta = 0;
	protected $nombre_venta = '';
	protected $id_comercial = 0;
	protected $asignaciones_precio = array();
	protected $test = 0;
	protected $data_email = array();
	protected $reserva = 0;

	function __construct($venta, $asignaciones,$comprobantes,$productos,$notas,$test=0) {

		$this->test = $test;
		$this->venta = $venta;
		$this->nombre_venta = $venta['info']['token_venta'];
		$this->id_comercial = $venta['info']['id_comercial'];
		$this->asignaciones = $asignaciones;
		$this->comprobantes = $comprobantes;
		$this->productos = $productos;
		$this->notas = $notas;
		
		if (!empty($this->productos)) {
			foreach($this->productos as $tipo => $producto ){
				foreach( $producto as $id => $data ){
					
					$data_proveedor = $data['proveedor'];
					$data_producto = $data['producto'];
					if( isset($data['mensajeria']) ){}
				}
			}
		}
	}

	public function getEmailCliente() {
		return $this->email_cliente;
	}

	function insert(){
		$res = $this->insertAllData();
		if( !$this->test && !$res ) return false;
		$this->insertVenta();
		if( $this->test || $this->query['venta'] ){
			$this->insertAsignaciones();
			$this->insertComprobantes();
			$this->insertDatosProductos();
			$this->insertNota();
			return 1;
		}
	}
	/*t(ventas_notas)
	 * campos: token, texto
	 *
	 * */
	function insertNota(){
		$token = $this->nombre_venta;
		if ($this->notas != '' ){
		       	foreach( $this->notas as $nota ){
			$sql1 = "select token,texto from ".BDV.".ventas_notas where token = '$token' and texto = '$nota'";
			$res1 = getResult(DB2,$sql1);
			if( $res1->rows == 0 ){
				$sql = "insert into ".BDV.".ventas_notas (token,texto) values ('$token','$nota') ";
				if( $this->test ) echo"TEST : $sql<br>"; else $res = getResult(DB2,$sql);
			}
			$this->query['notas'][] = ( $res->query ) ? 1 : 0;
			}
		}
	}
	/* t(venta_datos_cliente)
	 * campos: token, id_producto, nombre, direccion, dni, telefono, telefono_contacto, email, facturacion, es_cliente
	 *
	 * */
	function insertDataCliente( $info, $id_producto, $facturacion ){

		$token = $this->nombre_venta;
		$nombre = mysql_escape_string(ltrim($info['nombre']));
		$direccion = mysql_escape_string($info['dir']);
		$dir_google = mysql_escape_string($info['dir_google']);
		
		$dni = mysql_escape_string($info['dni']);
		$dni = str_replace(array(' ', '_', '-', '.', ':'), '', trim($dni));

		$telefono = mysql_escape_string($info['telefono']);
		$telefono_contacto = mysql_escape_string($info['contacto']);
		$email = mysql_escape_string($info['email']);

		if ($facturacion == 1) {
			$this->email_cliente = $email;
		}	

		$sap = ( isset($info['cardcode']) ) ? mysql_escape_string(rtrim($info['cardcode'])) : '';
		$sap = str_replace(' ', '', trim($sap));
		
		$nota = ( isset($info['nota']) ) ? mysql_escape_string($info['nota']) : '';

		$sql = "insert into ".BDV.".ventas_datos_cliente (token,id_producto,nombre,direccion,dni,telefono,telefono_contacto,email,facturacion,SAP,nota,dir_google) values ".
			"('$token','$id_producto','$nombre','$direccion','$dni','$telefono','$telefono_contacto','$email','$facturacion','$sap','$nota','$dir_google')";
		
		if ($sap == 'GENIUS') {
			syslog(LOG_INFO, __FILE__ . ':'.$sql);
		}
		
		if( $this->test ) echo"TEST: $sql<br>"; 
		else $res = getResult(DB2,$sql);

		$this->query['cliente'] = ( $res->query ) ? 1 : 0;
		
	}
	/*	t(ventas_datos_proveedor)
	 *	campos: token, id_producto,id_proveedor,precio_compra,fecha_pago,fecha_limite,tipo_pago
	 *
	 * */
	function insertDataProveedor( $info, $id_producto ){
		$token = $this->nombre_venta;
		$producto = $this->venta['info']['producto_actual'];
		$id_proveedor = $info['sel_proveedor'];
		$fecha_pago = ( isset($info['fecha_pago_proveedor']) ) ? $info['fecha_pago_proveedor'] : '0000-00-00' ;
		$fecha_limite = $info['fecha_limite_proveedor'];
		$precio_compra = $info['precio_compra_proveedor'];

		if( $producto == 'devolucion' && $precio_compra > 0 ){
			$precio_compra *= -1;
		}
		$tipo_pago = $info['tipo_pago'];
		$nota = $info['nota'];
		$sql = "insert into ".BDV.".ventas_datos_proveedor (token,id_producto,id_proveedor,precio_compra,fecha_pago,".
			"fecha_limite,tipo_pago,nota) ".
			"values ('$token','$id_producto','$id_proveedor','$precio_compra','$fecha_pago','$fecha_limite','$tipo_pago','$nota')";
		if( $this->test ){
			debug($sql, 'TEST'); 
		}
		else $res = getResult(DB2,$sql);
		
		syslog(LOG_INFO,"gest_ventas_insertProveedor $sql");
		
		$this->query['proveedor'] = ( $res->query ) ? 1 : 0;
	}
	/*
	 * t(ventas_datos_producto)
	 * campos: 	token, id_producto, id_tipo, fecha_inicial, fecha_final, servicios, 
	 * 		mensajeria, seguro_cancelacion,cantidad_dias,localizador,
	 * */
	function insertDataProducto( $info, $id_producto){
		
		$token = $this->nombre_venta;
		$producto = $this->venta['info']['producto_actual'];
		
		$id_tipo = ( isset($info['id_tipo']) ) ? $info['id_tipo'] : 0;
		$fecha_venta = ( isset( $info['fecha_venta'] ) ) ? $info['fecha_venta'] : '0000-00-00';
		$fecha_inicial = ( isset( $info['fecha_inicial'] ) ) ? $info['fecha_inicial'] : '0000-00-00';
		$fecha_final = ( isset( $info['fecha_final'] ) ) ? $info['fecha_final'] : '0000-00-00';
		$reserva = ( isset( $info['reserva'] ) ) ? $info['reserva'] : 0;
		if( $reserva == 1 ){
			$this->reserva = 1;
		}
		$servicios = ( isset( $info['otros_servicios'] ) ) ? $info['otros_servicios'] : 0;
		$mensajeria = ( isset( $info['mensajeria'] ) ) ? $info['mensajeria'] : 0;
		$seguro_cancelacion = ( isset( $info['seguro_cancelacion'] ) ) ? $info['seguro_cancelacion'] : 'N';
		$cantidad_dias = ( isset( $info['cantidad_dias'] ) ) ? $info['cantidad_dias'] : 0;
		$cantidad_elem = ( isset( $info['cantidad_elem'] ) ) ? $info['cantidad_elem'] : 0;
		$localizador = ( isset( $info['localizador'] ) ) ? trim($info['localizador']) : '';
		$cat = ( isset( $info['cats'] ) ) ? $info['cats'] : 0;
		$cia = ( isset( $info['cias'] ) ) ? $info['cias'] : 0;
		$pasajeros = ( isset( $info['pasajeros'] ) ) ? $info['pasajeros'] : 0;
		$destino = ( isset( $info['destino'] ) ) ? $info['destino'] : '';
		$regreso = ( isset( $info['regreso'] ) ) ? $info['regreso'] : '';

		$cantidad = ( !empty($this->asignaciones_precio) ) ? $this->asignaciones_precio[$id_producto] : 0;
		if( $producto == 'devolucion' && $cantidad > 0 ){
			$cantidad *= -1;
		}
		$precio_venta_cliente = ( isset( $info['precio_venta_cliente'] ) ) ? $info['precio_venta_cliente'] : 0;
		if( $producto == 'devolucion' && $precio_venta_cliente  > 0 ){
			$precio_venta_cliente  *= -1;
		}

		$sql = "insert into ".BDV.".ventas_datos_producto ".
			"(token,".
			"id_producto,".
			"id_tipo,".
			"reserva,".
			"fecha_venta,".
			"fecha_inicial,".
			"fecha_final,".
			"cantidad,".
			"precio_venta_cliente,".
			"servicios,".
			"seguro_cancelacion,".
			"cantidad_dias,".
			"cantidad_elem,".
			"localizador,".
			"cia_aerea,".
			"cat_coche,".
			"pasajeros,".
			"destino,".
			"regreso) ".
			"values (".
			"'".$token."',".
			"'".$id_producto."',".
			"'".$id_tipo."',".
			"'".$this->reserva."',".
			"'".$fecha_venta."',".
			"'".$fecha_inicial."',".
			"'".$fecha_final."',".
			"'".$cantidad."',".
			"'".$precio_venta_cliente."',".
			"'".$servicios."',".
			"'".$seguro_cancelacion."',".
			"'".$cantidad_dias."',".
			"'".$cantidad_elem."',".
			"'".$localizador."',".
			"'".$cia."',".
			"'".$cat."',".
			"'".$pasajeros."',".
			"'".d8($destino)."',".
			"'".d8($regreso)."')";

		if( $this->test ) echo"TEST SQL PRODUCTO --> <b>$sql</b><br>"; else $res = getResult(DB2,$sql);
	
		syslog(LOG_INFO,"gest_ventas_insertProducto $sql");

		$this->query['producto'] = ( $res->query ) ? 1 : 0;
	}
	/* T(ventas_)
	 *
	 * */
	function insertDatosProductos(){
		$productos = $this->productos;
		foreach($productos as $tipo => $producto ){
			foreach( $producto as $id => $data ){
				if( $data['facturacion'] != $data['cliente'] )
					$this->insertDataCliente( $data['cliente'], $id, CLIENTE_NO_FACTURA_VENTA );
				$this->insertDataCliente( $data['facturacion'], $id, CLIENTE_FACTURA_VENTA );
				
				$data_proveedor = $data['proveedor'];
				$data_producto = $data['producto'];
				$this->insertDataProveedor( $data_proveedor, $id);
				$this->insertDataProducto( $data_producto, $id);
				if( isset($data['mensajeria']) ){
					$data_proveedor['sel_proveedor'] = $data_producto['datos_mensajeria']['mensajeria'];
					$data_proveedor['precio_compra_proveedor'] = $data['mensajeria']['cantidad'];
					$id_sub = $data['mensajeria']['nombre'];
					$this->insertDataCliente( $data['facturacion'], $id_sub, CLIENTE_FACTURA_VENTA);
					$this->insertDataProveedor( $data_proveedor, $id_sub);
					$data_producto['id_tipo'] = $data_producto['datos_mensajeria']['id_producto_m'];
					$data_producto['otros_servicios'] = $data_producto['datos_mensajeria']['id_servicio_m'];
					$this->insertDataProducto( $data_producto, $id_sub);
				}	
			}
		}
	}
	/* T(ventas_ingresos) son los Comprobantes
	 * * Campos: token, tipo, importe,restante,divisa,num_pedido,texto
	 * */
	function insertComprobantes() {

		$comprobantes = $this->comprobantes;
		syslog(LOG_INFO, __FILE__ .':asignaciones:[comprobantes]'.serialize($comprobantes));

		$token = $this->nombre_venta;
		$values = '';
		$producto = $this->venta['info']['producto_actual'];
		foreach( $comprobantes as $datos ){
			$num_pedido = $datos['num_pedido'];
			$importe = str_replace(',','.',$datos['importe']);
			if( $producto == 'devolucion' && $importe > 0 ){
				$importe *= -1;
			}
			$restante = $datos['restante'];
			$divisa = $datos['divisa'];
			$texto_divisa = $datos['moneda'];
			$texto = $datos['comprobante'];
			$tipo_ingreso = $datos['nombre'];
			$token_pago = $datos ['token'];
			$comercio = (isset($datos['comercio']) ? $datos['comercio'] : '');

			$values .= "('$token','$num_pedido','$tipo_ingreso','$importe','$restante','$divisa','$texto_divisa','$texto','".date('Y-m-d H:i:s')."', '".$token_pago."', '".$comercio."'),";
		}

		if( $values != '' ){
			$values = str_replace('),)',')',$values.")");
			$sql ="insert ".BDV.".ventas_ingresos ".
				"(token,num_pedido,tipo_ingreso,importe,restante,divisa,texto_divisa,texto,fecha,token_ingreso,comercio) ".
				"values ".$values;
			syslog(LOG_INFO,"gest_ventas_insertIngreso $sql");
			if( $this->test ) echo "TEST : $sql<br>"; else $res = getResult(DB2,$sql);
			$this->query['comprobantes'] = ( $res->query ) ? 1 : 0;
		}
	}

	function insertAsignaciones(){
		$asignaciones = $this->asignaciones;
		$token = $this->nombre_venta;
		$values = '';
		$producto = $this->venta['info']['producto_actual'];
		$asignaciones_precio = array($producto => array());
		
		syslog(LOG_INFO, __FILE__ .':asignaciones:'.serialize($asignaciones));
		
		foreach( $asignaciones as $id => $asignacion ){
			foreach( $asignacion as $datos ){
				
				$tipo = $datos['tipo'];
				$comprobante = $datos['comprobante'];

				$cantidad = str_replace(',','.',$datos['cantidad']);
				if( $producto == 'devolucion' && $cantidad > 0 ){
					$cantidad *= -1; 
				}
				
				$divisa = $datos['divisa'];
				if( isset($datos['subproducto']) ){
					$k = key($datos['subproducto']);
					$s = $datos['subproducto'][$k];
					$s_cantidad = str_replace(',','.',$s['cantidad']);
					$values .= "('$token','$tipo','$comprobante','".$s['nombre']."',".$s_cantidad.",''),";
					$cantidad = $cantidad - $s['cantidad'];
					$asignaciones_precio[$s['nombre']] += $s['cantidad'];
				}
				$producto = $datos['producto'];
				
				$asignaciones_precio[$producto] += $cantidad;
				$extra = (isset($datos['data_extra']) && $datos['data_extra']) ? 1 : 0;
				$liquidacion = ( $divisa == 1 ) ? $cantidad : 0 ;
				$values .= "('$token', '$tipo', '$comprobante', '$producto', '$cantidad', '$liquidacion', $extra),";
			}
		}

		if( $values != '' ){
			$values .= ")";
			$values = str_replace('),)',')',$values);
			
			//if( $this->test ){debug($values);}

			$sql = "insert into ".BDV.".ventas_facturas (token,tipo,comprobante,producto,cantidad,liquidacion,datos_extra) values ".$values;
		
			if( $this->test ){
				debug( $sql , 'TEST' );
			} else  {
				$res = getResult(DB2,$sql);
			}

			syslog(LOG_INFO,"gest_ventas_insertFacturas $sql");
				
			$this->query['asignaciones'] = ( $res->query ) ? 1 : 0;
			$this->asignaciones_precio = $asignaciones_precio;
		}
	}

	/* inserta datos generales de la venta
	 * campos: token, fecha, tipo, precio_compra, precio_venta, margen, fraccionar_pago?, reserva?
	 * */
	function insertVenta(){

		$token = $this->nombre_venta;
		$id_comercial = $this->id_comercial;
		$info = $this->venta['info'];
		$fecha = $info['fecha_venta'].' '.date('H:i:s');
		$precios = $this->venta['precios'];
		$precio_compra = str_replace(',','.',$precios['totales']['compra']);
		$precio_venta = str_replace(',','.',$precios['totales']['venta']);
		$margen = $precios['totales']['margen'];
	
		if( $info['producto_actual'] == 'devolucion' ){
			
			$margen = abs($precio_compra) - abs($precio_venta);
			
			if( $precio_compra > 0 ){ 
				$precio_compra = $precio_compra * -1;
			}
			if( $precio_venta > 0 ){ 
				$precio_venta = $precio_venta * -1;
			}
		}

		$sql = "insert into ".BDV.".ventas (id_comercial,token,fecha,precio_compra,precio_venta,margen) values ".
			"('$id_comercial','$token','$fecha',$precio_compra,$precio_venta,$margen)";

		if( $this->test ){
			//debug($sql, 'TEST');
			return true;
		} else {
			$res = getResult(DB2,$sql);
		}
		
		syslog(LOG_INFO,"gest_ventas_insert $sql");

		$this->query['venta'] = ( $res->query ) ? 1 : 0;
	}

	function insertAllData(){
		$d_venta = json_encode($this->venta);
		
		$d_asignaciones = mysql_escape_string(serialize($this->asignaciones));
		$d_productos = mysql_escape_string(serialize($this->productos));
		$d_comprobantes = json_encode($this->comprobantes);
		$sql = "select token from ".BDV.".ventas_all_data where token = '{$this->nombre_venta}'";	
		$res = getResult(DB2,$sql);
		if( $res->rows > 0 ){
			return false;
			exit;
		}

		$sql = "insert into ".BDV.".ventas_all_data (token,fecha,venta, asignaciones,comprobantes,productos) values ".
			"('{$this->nombre_venta}','".date('Y-m-d H:i:s')."','$d_venta','$d_asignaciones','$d_comprobantes','$d_productos')";
		
		syslog(LOG_INFO,"ventas_all_data $sql");

		if( $this->test ){ 
			//debug($sql, 'TEST');
			return true;
		}
		else $res = getResult(DB2,$sql);
		$this->query['all'] = ( $res->query ) ? 1 : 0;
		
		return true;
	}

	function getResult(){
		return $this->query;
	}
}

?>
