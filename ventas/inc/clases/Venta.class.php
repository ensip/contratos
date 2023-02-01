<?php

include('InterfaceVenta.class.php');

class Venta{
	protected $actualizar_precios = array();
	protected $asignaciones = array('asignaciones' => array(),'comprobantes' =>array(),'productos' => array(),'errores' => array() );
	protected $asignaciones_creadas = null;
	protected $comprobante = array();
	protected $errores_asignacion = '';
	protected $fecha_final_incorrecta = 0;
	protected $hay_productos_set = 0;
	protected $html_asignaciones_creadas = null;
	protected $id_comercial = 0;
	protected $info_venta = array();
	protected $nota = array();
	protected $ok_to_add = NO_ADD_FORM; 
	protected $post_data = array();
	protected $precios_guardados = array('totales'=>array('compra'=>0,'venta'=>0,'margen'=>0));
	protected $producto = null;
	protected $products = array();
	protected $producto_fijado = '';
	protected $res_crearVenta = null;
	protected $resumen_productos = array();
	protected $ver_input_nota = 0;
	protected $ver_form_comprobante = 0;
	protected $ver_form_asignacion = 0;
	protected $warnings = array();

	function __construct( $id_comercial, $post ){

		$this->id_comercial = $id_comercial;
		/* guardor datos del post */
		$this->post_data = $post;
	}
	/*
	 * si los datos son completos y correctos se devuelven para añadirlos
	 * 
	 * */
	function addDatosNuevos( $post_data ){
		$producto_actual = $this->info_venta['producto_actual'];
		if( isset( $post_data[$producto_actual]) ){
			$post_actual = $post_data[ $producto_actual ][ 'datos' ];
			$id_actual = $post_data[ $producto_actual ][ 'id' ];
			$datos_actual = ( isset( $post_actual ) ) ? $post_actual : null;
		}
		//$datos['venta'] = $this->info_venta['token_venta'];	
		
		$datos[ $producto_actual ][ $id_actual ] = $datos_actual;
		if( isset($datos_actual['producto']['localizador']) && $datos_actual['producto']['localizador'] != '' )
		{
			$this->interface->addLocalizador( $datos_actual['producto']['localizador'] );
		}
		$this->interface->setBorrarValores( 1 );
		$this->producto->addProductos( $datos );
		$this->products = $datos;
	}	
	
	function addInfoVenta ($producto_actual) {

		$this->info_venta['producto_actual'] = $producto_actual;
		$this->info_venta['fecha_venta'] = date('Y-m-d');	
		$this->info_venta['id_comercial'] = $this->id_comercial;
		
		if ( isset( $this->post_data['venta']['nombre'] ) )
		{
			$this->info_venta['token_venta'] = $this->post_data['venta']['nombre'];
		}
		else
		{
			$new_token = 'V-'.time();
		        $sql = "select token from ".BDV.".ventas where token = '$new_token' ";	
			$res = getResult( DB2, $sql );
			if( $res->rows != 0 ){
				sleep(1);
				$new_token = 'V-'.time();
			}
			$this->info_venta['token_venta'] = $new_token;
		}
	}

	function addNota( $nota ){
		$this->nota = $nota;
	}

	/*obtengo el precio de compra del producto añadido que usario en getPreciosVenta*/
	function addPrecioCompra( $tipo ){
		
		$id = $this->post_data[$tipo]['id'];
		$precio_compra = $this->products[$tipo][$id]['proveedor']['precio_compra_proveedor'];	
		//$this->precios_nuevos = array('compra' => $precio_compra );
	}
	function checkAddNewProduct ($check_campos, $new_product) {
		$datos_check = array();
		$info_producto_reserva = null;
		$reserva = 0;

		if ($new_product['producto']['reserva'] == 1) {
                        $datos_check['cliente'] = $new_product['cliente'];
                        $info_producto_reserva = $new_product['producto'];
                        $reserva = 1;

		} else {
	                $datos_check = $new_product;
		}	
	
		if($check_campos == NO_COMPROBAR_CAMPOS) {
			$ok_to_add = OK_ADD_FORM;

		} else if ($check_campos) {

			$ok_to_add = OK_ADD_FORM;
			$check_campos = COMPROBAR_CAMPOS;
			$campos_vacios = $this->getCamposVacios($datos_check, $check_campos); 
			if ( !is_null($campos_vacios)) {
				$this->interface->setCamposVacios ($campos_vacios);
				$ok_to_add = NO_ADD_FORM;
			}

			if ($reserva) {
				$datos_check['producto'] = $info_producto_reserva;
                        }
                        if (!$this->checkFechas($datos_check, $reserva)) {
				$this->fecha_final_incorrecta = 1;
                                $ok_to_add = NO_ADD_FORM;
                        }
		}
		return $ok_to_add;
	}
	function getCamposVacios ($campos, $check_campos, $apartado_a_buscar = null) {
		
		$campos_vacios = null;
		$campos_no_obligatorios = DatosVenta::getCamposNoObligatorios ($apartado_a_buscar);
		foreach (DatosVenta::getApartadosVenta () as $apartado) {
			if (isset($campos [$apartado]) && ($vacios = ControlCampos::checkCamposVacios (
				$check_campos,
				$campos [$apartado],
				$campos_no_obligatorios [$apartado]
			)) != null) {

				$campos_vacios [$apartado] = $vacios;
			}
		}
		return $campos_vacios;
	}

	function checkFechas ($datos, $reserva) {
		
		$fechas = DatosVenta::getFechasVenta ($datos);
	        $check_fechas = 1;

		if ($reserva) {
                        if ($fechas != null) $check_fechas = 1; else $check_fechas = 0;
		}
                return ControlCampos::checkcontrolFechas($check_fechas, $fechas);
	}
	function checkProductoReservado(){
		
		$num_productos = 0;
		$son_reservas = 0;
		$resumen = $this->resumen_productos;
		if( !empty($resumen) ){
			foreach( $resumen as $tipo ){
				foreach( $tipo as $id ){
					if( $id['reserva'] )$son_reservas ++;
					$num_productos ++;
				}
			}
		}
		if( $num_productos > 0 && ($son_reservas == $num_productos) ) return true;
		else return false;
	}

	function cleanVenta () {
		unset($_POST['send']);
		unset($this->post_data['send']);
		unset($this->post_data['asignaciones_creadas']);
		unset($this->post_data['comprobantes']);
		unset($this->post_data['send']);
		unset($this->post_data['set']);
		return $this->post_data;
	}
	/*guarda array de listado de productos para asignar*/
	function createArrayAsignacionesProductos( $grupos_productos, $asignaciones ){
		$producto_actual = $this->info_venta['producto_actual'];
		if( !empty($grupos_productos) ) foreach( $grupos_productos as $tipo_producto => $producto ){
			if( $tipo_producto == $producto_actual ){
				$id_producto = key($producto);
				$producto[$id_producto]['restante'] = $producto[$id_producto]['cantidad']; 
				$excepcion_producto = 1;
				$cant_asignada = 0;
				if( isset( $asignaciones[$tipo_producto] ) ){
					$excepcion_producto = 0;
					foreach( $asignaciones[$tipo_producto] as $asignacion ) {
						$cant_asignada += $asignacion['cantidad'];
					}
					if( $producto[$id_producto]['cantidad'] >= $cant_asignada ){
						$excepcion_producto = 1;
						$producto[$id_producto]['restante'] = $producto[$id_producto]['cantidad'] - $cant_asignada;
					}
				}
				if( $excepcion_producto ){
					$this->asignaciones['productos'][$tipo_producto] = $producto;
				}
			}
		}
	}

	function createAsignacion($comprobantes, $productos ){
		return new Asignacion( $comprobantes, $productos );
	}		
	function delNota( $id_nota, $post_notas ){
		
		unset($post_notas[$id_nota]);
		return array_values($post_notas);
	}	

	/* obtengo los precios de los productos para mostrar llamada desde contrl-ventas*/
	function getHtmlResumenPrecios ($tipo_resumen) {
		$html = '';
		if ($tipo_resumen == 'producto') {
			if( isset( $this->precios_guardados['productos'] ) ){
				$tipo = $this->info_venta['producto_actual'];
				$precios = $this->precios_guardados['productos'];
				if (isset($precios[$tipo])) {
					$html = $this->interface->getHtmlResumenPrecios ($precios, $tipo);	
				}
			}

		} else if ($tipo_resumen == 'totales' ) {
			$precios = $this->precios_guardados['totales'];
			$html = $this->interface->getHtmlResumenPrecios ($precios, 'totales');	
		}

		return $html;
	}

	function processData(){
		
		$asignaciones_eliminadas = null;
		$asignaciones_para_eliminar = array();
		$check_campos = NO_COMPROBAR_CAMPOS;
		$post_data = null;
		$post_set = null;
			
		$info_producto_escogido = $this->getInfoProductoEscogido ();
		$this->addInfoVenta ($info_producto_escogido['nombre']);
		$this->interface = new InterfaceVenta ($this->id_comercial);
		$this->producto = new Producto($info_producto_escogido, $this->interface);
		$this->post_data['tipo_item'] = $info_producto_escogido['nombre'];
		$this->interface->setProductoActual($info_producto_escogido['nombre']);	
		$producto_actual = $info_producto_escogido['nombre'];
		
		if( isset( $this->post_data ) ){
			
			
			$post_data = $this->post_data;
		
			//link para ir a facturar un producto
			if( isset( $post_data['facturar'] )){
				$this->ver_form_asignacion = 1;
				$this->producto_fijado = key($post_data['facturar'][$producto_actual]);
			}
			if( isset( $post_data['set'] ) ) $post_set = $post_data['set'];
			
			/*para ver el campo nota*/
			if( isset( $post_data['add_div_nota'] )) $this->ver_input_nota = 1;	

			if( isset( $post_data['del_note'] )){
				$post_data['venta']['notas'] = $this->delNota( key($post_data['del_note']),$post_data['venta']['notas'] );
			}

			if( isset( $post_data['add_nota'] )){ $this->addNota($post_data['venta']['nota'] ); } 
			

			/* para ver los campos comprobante */
			if( isset( $post_data['add_div_comprobante'] )) $this->ver_form_comprobante = 1;
			/* si se ha dado a BUSCAR datos*/
			if( isset( $post_data['search'] )){
				$tipo_datos_busqueda = $producto_actual;
				$key_busqueda = key($post_data['search']['datos']['cliente']); 
				$datos_busqueda = $post_data[$producto_actual]['datos']['cliente'];
				if ( isset($post_data['search']['datos']['factura'] ) ){
					$this->ver_form_asignacion = 1;
					$tipo_datos_busqueda = 'factura';
					$key_busqueda = key($post_data['search']['datos']['factura']); 
					$datos_busqueda = $post_data[$tipo_datos_busqueda]['datos']['cliente'];
				}
				if ( isset($post_data['search']['busqueda']) ){
					if( isset($datos_busqueda) ){ $datos_busqueda = null;}
					$key_busqueda = 'cardcode';
					$datos_busqueda['cardcode'] = key($post_data['search']['busqueda'][$producto_actual]['cardcode']);
				}
				$busqueda = new searchCliente($key_busqueda,$datos_busqueda[$key_busqueda],$producto_actual,$tipo_datos_busqueda);
				$res_busqueda = $busqueda->getSearch();
				$this->interface->setBusqueda( $res_busqueda );
			}
			$quitar_tipo_producto = '';
			$quitar_tipo_producto = '';
			/* si se ha dado a QUITAR PRODUCTO */
			$post_quitar = null;
			if( isset( $post_data['quitar'] ) ){
				$post_quitar = $post_data['quitar'];
				$quitar_tipo_producto = key($post_quitar);
				$quitar_id_producto = key($post_quitar[$quitar_tipo_producto]);
				if( isset( $post_set[$quitar_tipo_producto][$quitar_id_producto] ) ){
					$precio_a_quitar  = $post_set[$quitar_tipo_producto][$quitar_id_producto]['proveedor'];
					//elimino producto
					unset( $post_set[$quitar_tipo_producto][$quitar_id_producto] );
					//si tipo producto vacio lo elimino del set
					if( empty($post_set[$quitar_tipo_producto]) )unset($post_set[$quitar_tipo_producto]);
					$eliminar_asignacion = 1;
					$post_data['set'] = $post_set;
					if(isset($this->asignaciones['productos'][$quitar_tipo_producto][$quitar_id_producto])){
						unset($this->asignaciones['productos'][$quitar_tipo_producto][$quitar_id_producto]);
					}
				}
				$post_set = $this->updatePostSetLocalizadores($post_set);
			}
			/* si edito un producto compruebo el precio de compra*/
			$datos_editar = null;
			if( isset( $post_data['editar'] ) ){
				$datos_editar  = $post_data['editar'];
				$quitar_tipo_producto = key($datos_editar);
				$quitar_id_producto = key($post_data['editar'][$quitar_tipo_producto]);	
				$precio_editado_old = 0;
				$precio_a_quitar = 0;
				$eliminar_asignacion = 0;
				if( isset($post_set[$quitar_tipo_producto][$quitar_id_producto]) ){
					$data_p_editado = $post_set[$quitar_tipo_producto][$quitar_id_producto];
					$precio_editado_old = $data_p_editado['proveedor']['precio_compra_proveedor_old'];
					$precio_a_quitar = $data_p_editado['proveedor']['precio_compra_proveedor'];
					//si el precio se ha editado elimino la asignacion
					if( $precio_editado_old != $precio_a_quitar && isset($post_data['asignaciones_creadas']) ){
						$eliminar_asignacion = 1;
						$data_p_editado['proveedor']['precio_compra_proveedor_old'] = $precio_a_quitar; 
						$post_set[$quitar_tipo_producto][$quitar_id_producto] = $data_p_editado;
						$post_data['set'] = $post_set;
					}
				}
			}
			if( ($post_quitar != null || $datos_editar != null ) && $eliminar_asignacion ){
				$this->updatePrecios( $quitar_tipo_producto, $precio_a_quitar, 'resta' );		
				if( isset($post_data['asignaciones_creadas']) ){
					foreach( $post_data['asignaciones_creadas'] as $key => $asig ){
						if( $asig['producto'] == $quitar_id_producto ){
							$asignaciones_para_eliminar[] = $key;
							$asignaciones_eliminadas[$key]['op'] = '+';
							$asignaciones_eliminadas[$key]['tipo'] = $asig['tipo'];
							$asignaciones_eliminadas[$key]['comprobante'] = $asig['comprobante'];
							$asignaciones_eliminadas[$key]['cantidad'] = $asig['cantidad'];
							$post_data['asignaciones_eliminadas'] = $asignaciones_eliminadas;
						}
					}
				}
			}
			//debug($_POST);
			/*compruebo si existen DATOS CREADOS */
			if( $post_set != null )
			{
				$this->interface->addLocalizadores (DatosVenta::getLocalizadores ($post_set));
				
				$this->ok_to_add = OK_ADD_FORM;
				
				$productos = DatosVenta::getProductos ($post_set);
				
				//unset( $post_set['localizadores'] );
				if( count($productos > 0 )){
					$this->hay_productos_set = 1;
					$this->producto->addProductos( $productos );
					$this->products = $productos;
				}
				$post_data['set'] = $productos;	
			}
			//si habian productos guardados añado los nuevos
			/* si se ha dado a añadir datos nuevos */
			if (isset($post_data['add_datos_cliente'])) {
	
				$check_campos = COMPROBAR_CAMPOS;
				$id_cliente = '';
				if( isset($post_data[$producto_actual]['datos']['clientes']) ){
					$id_cliente = $post_data[$producto_actual]['datos']['clientes']; 
					if( $id_cliente != '-' ){
						$post_data[$producto_actual]['datos']['cliente'] = $this->producto->getCLientesProductos( $id_cliente );
					}
				}
		
				if ($this->ok_to_add = $this->checkAddNewProduct ($check_campos, $post_data[$producto_actual]['datos'])) {
					$this->addDatosNuevos($post_data);
					$this->addPrecioCompra( $producto_actual );
				}
			}

			/*si se ELIMINA un COMPROBANTE compruebo si hay asignaciones asociadas y las elimino*/
			if( isset($post_data['eliminar']) ){
				$post_eliminar = $post_data['eliminar'];
				$id_comprobante_eliminado = (isset($post_data['asignaciones_creadas'])) ? key($post_eliminar) : '';
		
				if(isset($post_data['asignaciones_creadas'])){
					foreach($post_data['asignaciones_creadas'] as $key => $asignacion ){
						if( $id_comprobante_eliminado != '' && 
							$id_comprobante_eliminado == $asignacion['comprobante'] )
						{
							/*elimino asignacion*/
							$asignaciones_para_eliminar[] = $key;
							$asignaciones_eliminadas[$key]['tipo'] = $asignacion['tipo'];
							$asignaciones_eliminadas[$key]['comprobante'] = $asignacion['comprobante'];
							$asignaciones_eliminadas[$key]['cantidad'] = $asignacion['cantidad'];
						}
					}
				}
			}
			/* si hay ASIGNACIONES CREADAS las guardo para nuevos calculos*/
			$asignaciones = null;
			if( isset($post_data['asignaciones_creadas']) ){
				$asignaciones = $post_data['asignaciones_creadas'];
				if( isset( $post_data['del_asig'] ) ){
					$id_asig_eliminar = key($post_data['del_asig']);
					array_push( $asignaciones_para_eliminar, $id_asig_eliminar );
					$asignaciones_eliminadas[$id_asig_eliminar] = $asignaciones[$id_asig_eliminar];
					$asignaciones_eliminadas[$id_asig_eliminar]['op'] = '+';
					$post_data['asignaciones_eliminadas'] = $asignaciones_eliminadas;
				}
				//elimino asignaciones si hay por hacerlo
				if( !empty($asignaciones_para_eliminar) ){
					$asignaciones_nuevas = null;
					foreach( $asignaciones as $key => $asignacion ){
						if( !in_array( $key, $asignaciones_para_eliminar)){
							$asignaciones_nuevas[] = $asignacion;
						}
					}
					$asignaciones = null;
					if( $asignaciones_nuevas != null ){
						$asignaciones = $asignaciones_nuevas;
						$post_data['asignaciones_creadas'] = $asignaciones;
					}
				} 
			}
			/* obtengo array productos*/
			$productos = $this->producto->getDatosParaAsignar(); /*consulta*/
			/* ADD NUEVA ASIGNACION */
			if( isset($post_data['submit_asignar_new']) ){
				$comprobantes = $post_data['comprobantes'];
				$asignacion = $this->createAsignacion($comprobantes, $productos);
				/* guardo asignaciones anteriores , puede ser null */
				$asignacion->setAsignaciones( $asignaciones );
				
				$asignacion_nueva = $post_data['asignar'];
				if (ControlCampos::existenDatosFacturacion ($post_data['factura']['datos']['cliente'])) {
					$datos_facturacion['factura'] = $post_data['factura']['datos']['cliente']; 
					if (!is_null ($this->getCamposVacios ($datos_facturacion,COMPROBAR_CAMPOS,'factura'))) {
						$asignacion_nueva['facturacion'] = $datos_facturacion['factura'];
					}
				}

				$asignacion_nueva['divisa'] = $comprobantes[$asignacion_nueva['id_comprobante']]['divisa'];
				$asignacion_nueva['producto_actual'] = $producto_actual;
				$an = $asignacion_nueva;
				if( isset( $productos[$producto_actual][$an['producto']]['subproducto'] ) ){
					$asignacion_nueva['subproducto'] = $productos[$producto_actual][$an['producto']]['subproducto'];
				}
				$res_asignacion_nueva = $asignacion->updateAsignaciones( $asignacion_nueva );
				echo "<!-- AS:1"; print_r($res_asignacion_nueva);echo "-->";
				if ( !isset($res_asignacion_nueva['error']) ) {
					$post_data['asignacion_nueva'] = $asignacion_nueva;
				} else{
					$this->asignaciones['errores'] = $asignacion->checkErrores();
					$this->ver_form_asignacion = 1;
				}
				echo "<!-- AS:2"; print_r($this->asignaciones['errores']);echo "-->";
				$asignaciones = $asignacion->getAsignaciones();
			
			}/*FIN SUBMIT_ASIGNAR_NEW*/

			$new_asignaciones = null;
			if ( isset( $asignaciones ) ) {
				//elimino las asignaciones si no hay comprobantes
				if( empty( $this->asignaciones['comprobantes'] ) && !isset( $this->asignaciones['comprobantes'] ) ) {
					$asignaciones = null;
				}

				$tipos_asignacion = null;
				foreach( $asignaciones as $asignacion ){
					$new_asignaciones[$asignacion['tipo']][] = $asignacion;
				}
				
				//compruebo si hay subproductos para las asignaciones
				foreach($productos as $tipo => $data){
					if( $tipo == 'visado' ){
						$key_sub = key($data);
						$prod = $data[$key_sub];
						//miro si no existe un subproducto en productos creados miro se esta en asignaciones
						if( !isset($prod['subproducto'])){
							foreach($new_asignaciones[$tipo] as $tipo2 => $asig ){
								if( $asig['producto'] == $key_sub && isset($asig['subproducto']) ){
									unset($new_asignaciones[$tipo][$tipo2]['subproducto']);
								}
							}
						}else{
							foreach($new_asignaciones[$tipo] as $tipo2 => $asig ){
								if( $asig['producto'] == $key_sub && !isset($asig['subproducto']) ){
									$new_asignaciones[$tipo][$tipo2]['subproducto'] = $prod['subproducto'];
								}
							}
						}
					}
				}
				$this->asignaciones['asignaciones'] = $new_asignaciones;
			}
			
			$this->createArrayAsignacionesProductos( $productos, $new_asignaciones);	
			/* para ver la asignacion de comprobantes */
			if( isset( $post_data['ver_asig_comprobante'] ) || $this->errores_asignacion != '' ){
				$this->ver_form_asignacion = 1;
			}
			/**inicio gestion COMPROBANTE/*/
			$this->comprobante = new gestionComprobante( $post_data );
	               	$comprobante_eliminado = $this->comprobante->getComprobanteEliminado();
			if( isset( $post_data['comprobantes'][$comprobante_eliminado] ) ){
				unset($post_data['comprobantes'][$comprobante_eliminado]);
			}
			$this->comprobantes = $this->comprobante->getCsGuardados();
			if( !empty( $this->comprobantes ) ){
				$this->asignaciones['comprobantes'] = $this->comprobantes;
			}
		
			
			if (isset($this->post_data['send'])) {
				$this->res_crearVenta = $this->procesarVenta ();
				$this->cleanVenta (); 
			}
			
			$this->post_data = $post_data;
			$this->getPrecios( $producto_actual );
			$this->resumen_productos = $this->producto->getResumenProductos( $this->asignaciones );
		
			$this->setWarnings();
		}
	}//end processData
	function getHtmlResultVenta () {
		
		return  ($this->res_crearVenta != null) ? $this->interface->getHtmlInsert ($this->res_crearVenta) : '';
	}
	function getIdProductoEscogido ($nombre) {
		if (isset($this->post_data['crear'])) {
			$id = $this->post_data['id_tipo'][$this->post_data['crear']];
		} else {
			$searchs = array('nombre' => array ('operand' => '=', 'value' => $nombre ));
			$cols = 'id';
			$raw_data = getCollectionBusqueda ('tipos_productos',$searchs, $cols);
			if (!is_null($raw_data)) {
				$id = $raw_data->id;
			}
		}
		return $id;
	}	
	function getInfoProductoEscogido () {
		$info['nombre'] = $this->getProductoEscogido ();
		$info['id'] = $this->getIdProductoEscogido ($info['nombre']);
		
		return $info;
	}

	/* Devuelve info de la venta guardada en Producto*/
	function getInfoVenta(){
		return $this->info_venta;
	}
	function procesarVenta(){
		$res_insert = null;
		
		$venta['info'] = $this->info_venta;
		$datos_venta = $this->post_data['venta'];
		$venta['precios'] = $datos_venta['precios'];
		
		$productos = DatosVenta::getProductos ($this->post_data['set']);
		foreach($productos as $tipo => $p ){
			foreach($p as $id => $info )
				$productos[$tipo][$id]['facturacion'] = $productos[$tipo][$id]['cliente'];
		}

		$asignaciones = $this->asignaciones['asignaciones'];
		foreach($asignaciones as $tipo_producto => $asignacion ){
			foreach( $asignacion as $id => $info ){
				if(isset($info['facturacion'])){
					$cli_fact_producto = $info['producto'];
					$cli_datos_extra = $info['facturacion'];
					if( isset($productos[$tipo_producto][$cli_fact_producto]) ){
						$productos[$tipo_producto][$cli_fact_producto]['facturacion'] = $cli_datos_extra;
					}
				}
				if( isset($info['subproducto'])){
					$id_sub = key($info['subproducto']);
					$productos[$tipo][$info['producto']]['mensajeria'] = $info['subproducto'][$id_sub];
				}
			}
		}
		$notas = ( isset($datos_venta['notas']) ) ? $datos_venta['notas'] : '' ;
		$comprobantes = $this->asignaciones['comprobantes'];
		$test = 0;
		
		$insert = new InsertVenta($venta, $asignaciones, $comprobantes, $productos, $notas, $test);
		
		if( !$test )$res_ins = $insert->insert();	
		else $res_ins = 1;
		
		if( !$res_ins ){
			$res_insert['text'] = 'Venta existente';
			$res_insert['venta'] = 0;

		} else{

			if( !$test )$res_insert = $insert->getResult();
			syslog( LOG_INFO, "ventas_insert : " . serialize($res_insert) );
			$res_insert['venta'] = 1;
			
			$email['token_venta'] = $this->post_data['venta']['nombre'];
			$email['resumen'] = $this->producto->getResumenProductos ($this->asignaciones);
			$email['productos'] = $this->post_data['set'];
			
			//$email['asignaciones_creadas'] = $this->post_data['asignaciones_creadas'];
			$cmail = new EmailVenta( $this->info_venta['id_comercial'] );
			$cmail->setData($email);
			$text_venta = $cmail->getTable();
			
			if (!$test) $cmail->send();
			
			//$this->interface->setTicket ($text_venta);
			
			if (!$test) insertActividadSAP( $email );

			$this->procesarPostVenta($productos, $venta, $cmail);		
		}
		return $res_insert;
	}
	
	private function procesarPostVenta($productos, $venta, $cmail) {
		$token = $venta['info']['token_venta'];
		$cmail->setToken($token);
		foreach( $productos as $tipo => $producto ){
			if( $tipo == 'coche' ){
				$info_p = $producto[key($producto)];
				//$cmail->sendEmailCoche( $info_p);
				$cmail->sendEmailTrustPilot($venta, $producto, $this->asignaciones['asignaciones']['coche']);
			}
		}
	}

	function getInterface() {
		return $this->interface;
	}
	/* obtiene precios: llamada desde el construct*/
	function getPrecios( $producto_actual ){
	
		$precios_compra_totales = $this->producto->getPrecios();
		$this->precios_guardados['totales']['compra'] = $precios_compra_totales; 
		$precios_venta_totales = $this->comprobante->getSumImportes();
		$this->precios_guardados['totales']['venta'] = $precios_venta_totales; 
		if( ($precios_compra_totales > 0 && $precios_venta_totales > 0) || $producto_actual == 'devolucion' ){
			$margen_total = $precios_venta_totales - $precios_compra_totales;
			$this->precios_guardados['totales']['margen'] = $margen_total; 
		}
		//tengo asignaciones: asignaciones y asignaciones_creadas -> debo unificarlo
		$precio_compra_producto = 0;
		if( isset( $this->products[$producto_actual]) ){
			$productos_actuales = $this->products[$producto_actual];
			$precio_compra_mens = 0;
			foreach( $productos_actuales as $producto_actual_precio ){
				$precio_compra_prov = str_replace(',','.',$producto_actual_precio['proveedor']['precio_compra_proveedor']);
				$precio_compra_producto += $precio_compra_prov;
				if (isset($producto_actual_precio['producto']['datos_mensajeria']['precio_compra']) && $producto_actual_precio['producto']['datos_mensajeria']['precio_compra'] > 0 )
				 	$precio_compra_mens = str_replace(',','.',$producto_actual_precio['producto']['datos_mensajeria']['precio_compra']);
					$precio_compra_producto += $precio_compra_mens;
			}
		}
		if( !empty( $this->asignaciones) && isset($this->asignaciones['asignaciones'][$producto_actual]) ){
			$precios_productos_actuales = $this->asignaciones['asignaciones'][$producto_actual];
			$precio_venta_producto = 0;
			foreach( $precios_productos_actuales as $precio ){
				$precio_venta_producto += $precio['cantidad'];
			}
			$this->precios_guardados['productos'][$producto_actual]['compra'] = $precio_compra_producto;
			$this->precios_guardados['productos'][$producto_actual]['venta'] = $precio_venta_producto;
			$this->precios_guardados['productos'][$producto_actual]['margen'] = $precio_venta_producto - $precio_compra_producto;
		}
	}

	function getProducts() {	
		return $this->products;	
	}
	function getProductByName ($producto) {	
		return $this->products ($producto);
	}

	function updateLocalizadores($post_set) {
		
		$localizadores = DatosVenta::getLocalizadores ($post_set);
		$localizadores_productos = DatosVenta::getLocalizadoresProductos ($post_set);
		
		if ($localizadores != null) {
			$new_localizadores = null;
			foreach ($localizadores as $localizador) {
				if ( $localizadores_productos != null && in_array($localizador, $localizadores_productos)) {
					$new_localizadores[] = $localizador;	
				}
			}
			if ($new_localizadores != null){
				$localizadores = $new_localizadores;
			}
		}	
		return $localizadores;
	}
	/*
	 *	actualiza los localizadores del set de datos post_set al eliminar un producto
	 * */
	function updatePostSetLocalizadores($post_set) {
		$productos = DatosVenta::getProductos ($post_set);
		
		if (!empty($productos)) {
			$post_set['localizadores'] = $this->updateLocalizadores ($post_set);	
			
		} else {
			unset($post_set['localizadores']);
		}
		
		return $post_set;
	}


	function getNotas(){
		$notas = array();
		if( isset( $this->post_data['venta']['notas']) ){
			$notas = $this->post_data['venta']['notas']; 
		}
		if( !empty( $this->nota ) ){
			array_push($notas, $this->nota);
		}
		$html = ( !empty($notas) ) ? $this->interface->getListadoNotas($notas) : '';

		return $html;
	}
	/*	
	 *	Devuelve boton add nota, y si se ha pulsado a añadir nota devuelve campo para insertar la nota
	 *
	 * */
	function getNotaVenta(){

		//$val_nota = ( isset( $this->post_data['venta']['nota'] ) ) ? $this->post_data['venta']['nota'] : '';	
		$nota = '';
		if ($this->ver_input_nota) {
			$nota = $this->interface->getHtmlInputAddNota();
		
		} else {
			$nota = $this->interface->getHtmlButtonAddNota(); 
		}
	
		return $nota ;
	}
	function getFechaFinalIncorrecta () {
		return $this->fecha_final_incorrecta;
	}

	/* Devuelve el formulario par añadir comprobantes */
	function getFormComprobante(){
		$html = $this->comprobante->getFormComprobante( $this->ver_form_comprobante );

		return  $html;
	}
	
	/* genera los productos para las asignaciones y crea el formulario para hacer la asignacion*/
	function getFormAsignacion(){
		$html = '';
		$comprobantes = $this->comprobantes;
		if( $comprobantes != null ){
			$producto_actual = $this->info_venta['producto_actual'];
			$comprobantes_asignables = null; 
			foreach( $comprobantes as $id_comprobante => $comprobante )if( $comprobante['restante'] > 0 || $producto_actual == 'devolucion' ){
					$comprobantes_asignables[$id_comprobante] = $comprobante;
			}
			$productos = $this->producto->getDatosParaAsignar(); /*consulta*/
			$asignable = 1;
			$productos_asignables = null;
			if( isset($this->resumen_productos[$producto_actual]) ){
				$resumen = $this->resumen_productos[$producto_actual];
				foreach( $productos[$producto_actual] as $id_producto => $producto ){
					$restante = $resumen[$id_producto]['venta'];
					$cantidad = $resumen[$id_producto]['compra'];
					//PONGO ASIGNABLE A 1 PARA QUE SE PUEDAN ASIGNAR MAS COMPROBANTES AUNQUE EL PRECIO DE COSTE ESTE ASUMIDO
					$asignable = ( $restante < $cantidad ) ? 1 : 1;
					if( $asignable || $producto_actual == 'devolucion' )
					{
						$producto['restante'] = $cantidad - $restante;	
						$productos_asignables[$producto_actual][$id_producto] = $producto;
					}
				}
			}
			/* si no hay productos del producto actual no se muestra el boton para asignacion*/
			if( $productos_asignables != null && $comprobantes_asignables != null ){
				$html .= $this->comprobante->getBotonAsignacion();
			}
			if( !empty($this->asignaciones['errores']) ){
				$html .= $this->interface->getHtmlAsignacionesErrores ($this->asignaciones['errores']);	
			}
			if( $this->ver_form_asignacion && $productos_asignables != null ){
				if( $productos_asignables != null && $comprobantes_asignables != null ){
					$asignacion = $this->createAsignacion($comprobantes_asignables, $productos_asignables);
					$html .= $asignacion->getFormularioAsignaciones (
						$this->info_venta['producto_actual'], 
						$this->interface, 
						$this->producto_fijado, 
						null
					);
				}
			}
		}
		return $html;
	}
	/* devuelve html del listado de comprobantes */
	function getListadoComprobantes(){
		return $listado_comprobantes = $this->comprobante->getListadoComprobantes();
	}
	/*
	 * Devuelve listado asignaciones creadas, llamado desde control_crear_venta.php
	 * */
	function getListadoAsignacionesCreadas(){
		$html = '';				
		if( !empty($this->asignaciones['asignaciones']) ){
			$html = $this->interface->getInputsAsignaciones( $this->asignaciones['asignaciones'] ); 	
		}
		return $html;
	}
	
	/*
	 * Obtengo los datos de los formularios del producto, + proveedores, y del cliente
	 * genero el formulario con el post, el flag check_campos, la lista de proveedores y los campos extra
	 *
	 * */
	function getFormProduct(){
		$producto_actual = '';
		$bloque = '';
		if( isset( $this->info_venta['producto_actual'] ) ){
			$producto_actual = $this->info_venta['producto_actual'];
			$data_cliente = null;
			if( isset( $this->post_data[ $producto_actual ] ) ){
				$data_cliente = $this->post_data[ $producto_actual ][ 'datos' ];
			}
			$list_clientes = $this->producto->getClientesProductos();
			$bloque = $this->interface->getHtmlAddProductForm( $data_cliente, $list_clientes );	
		}
		
		return $bloque;	
	}

	
	/*
	 * obtiene los datos creados de producto->interface->getDatosCreados
	 * llamada desde contrl_crear_ventas
	 * obtiene datos creados si hay productos creados set o el producto esta ok para ser añadido (se añade nuevo)
	 * */
	function getProductosCreados(){
		
		return ($this->hay_productos_set || $this->ok_to_add === OK_ADD_FORM) ? $this->producto->getProductos() : '';
	}
	function getListadoCantidadProductos(){
		return $this->producto->getCantidadProductos();
	}
	/*devuelve div resumen productos: sumarize_venta */
	function getResumenProductos(){
		$resumen_productos_html = '';
		if( $this->resumen_productos != null ){ 
			$comprobantes = ( !empty($this->asignaciones['comprobantes']) ) ? 1 : 0;
			$resumen_productos_html = $this->interface->getDivResumenProductos( $this->resumen_productos, $comprobantes );
		}
		return $resumen_productos_html ; 
	}
	function getSendButton(){
		$send = 0;
		if( $this->checkProductoReservado() && !empty($this->asignaciones['asignaciones']) ){
			$send = 1;
		}
		if( !empty($this->asignaciones['asignaciones']) ){
			$send = 1;
			if( !empty($this->warnings) ){
				if( in_array( 'producto-incompleto',$this->warnings ) ){
					if( !in_array( 'marcado-reserva',$this->warnings ) )$send = 0;
				}
			}
		}
		if( $send ){
			return $this->interface->getInputSend();
		}
		else{
			return false;
		}
	}
	
	/*
	 *	devuelvo el nombre del producto del boton crear
	 *	nombre_producto : string|null
	 *	return vuelo:defecto | producto:checked
	 * */
	function getProductoEscogido () {
		$post_data = $this->post_data;
		$producto_escogido = 'vuelo';
		if (isset($post_data['tipo_item']) && $post_data['tipo_item'] != null) {
			$producto_escogido = $post_data['tipo_item'];
			if (isset($post_data['crear'])) {
		       		$producto_escogido = $post_data['crear'];
			}
			if (isset($post_data['facturar'])) {
				$producto_escogido = key($post_data['facturar']);
			}	
		}	
		
		return $producto_escogido;
	}

	function getWarnings () {
		return $this->warnings;
	}
	

	function setWarnings(){
		$compra = $this->precios_guardados['totales']['compra'];
		$venta = $this->precios_guardados['totales']['venta'];
		$margen = $this->precios_guardados['totales']['margen'];
		$i = 0;
		$campos_vacios = $this->interface->getCamposVacios();
		if (!empty($campos_vacios)) {
			$this->warnings[$i] = 'campos-vacios';
		}
		if( $margen < 0 && $compra > 0 ){
			$this->warnings[$i+1] = 'margen-negativo';
		}
		if ($this->getFechaFinalIncorrecta()) {
			$this->warnings[($i+1)] = 'fecha-final-incorrecta';
		}
		if( !empty($this->resumen_productos) ){
			$comprobantes = $this->asignaciones['comprobantes'];
			foreach( $comprobantes as $comprobante ){
				if( $comprobante['restante'] == $comprobante['importe'] )$this->warnings[$i] = 'comprobante-sin-asignar';
				$i++;
				if( $comprobante['restante'] != 0 ) $this->warnings[$i] = 'cantidad-comprobante-por-asignar';
			}
			foreach( $this->resumen_productos as $tipo => $producto ){
				foreach($producto as $id => $datos ){
					if( !empty($comprobantes) && $datos['venta'] == 0 )$this->warnings[$i] = 'productos-sin-asignar';
					$i ++;
					if( $datos['reserva'] == 1 ){
						$this->warnings[$i] = 'marcado-reserva';
						break;
					}
					$i ++;
					if( $tipo != 'devolucion' && $datos['venta'] < $datos['compra'] ){
						$this->warnings[$i] = 'producto-incompleto';
						break;
					}
				}
				$i ++;
			}
		}
	}
	/* si quito un producto actualizo precios : desde processData*/
	function updatePrecios( $tipo, $cantidad, $accion ){
		$this->actualizar_precios['totales']['compra'] = $cantidad;	
		$this->actualizar_precios['productos'] = $tipo;
		$this->actualizar_precios['accion'] = $accion;
	}
}
?>
