<?php

class InterfaceVenta{

	protected $venta = '';
	protected $borrar_valores = 0;
	protected $cabeceras = array();
	protected $localizadores = array();
	protected $producto = '';
	protected $producto_actual = '';
	protected $proveedores = array();
	protected $ticket = '';
	protected $campos_extra = array();
	protected $campos_vacios = array();
	protected $datos_form = array();
	protected $busqueda = null;
	protected $id_comercial = '';

	function __construct( $id_comercial = '' )
	{
		$this->id_comercial = $id_comercial;
	}

	function addLocalizador( $localizador )
	{	
		if(!in_array( $localizador, $this->localizadores ) ){
			array_push( $this->localizadores, $localizador );
		}
	}
	
	function addLocalizadores( $localizadores ){

		if( !empty($localizadores) ){
			foreach( $localizadores as $localizador ){
				$this->addLocalizador( $localizador );
			}
		}
	}
	function checkData( $data, $arr_vals ){

		$ok_extract = 1;
		foreach( $arr_vals as $key ){
			if( !isset( $data[$key] ) ){ $ok_extract = 0; }
		}
		return $ok_extract;
	}
	function getCamposVacios () {
		return $this->campos_vacios;
	}
	function getInputProductoActual() {

	       return ($this->producto_actual != '' ) ? '<input type="hidden" name="tipo_item" value="'.$this->producto_actual.'" >' : '';
	}
	function getNavProductos () {
		$producto_actual = $this->producto_actual;
		$li_products = '';
		foreach (getProducts () as $producto) {
			$active = ( isset( $producto_actual ) && $producto_actual == $producto->nombre ) ? "active" : '';
	                $li_products .= "<li class='$active'>".
		                        "<input type='submit' name='crear' value='{$producto->nombre}' >".
		                        "<input type='hidden' name='id_tipo[{$producto->nombre}]' value='{$producto->id}' >".
		                        "</li>";
		}
	        $list_products = sprintf("<ul>%s</ul>", $li_products);

	        return $list_products;
	}	
	
	function setBorrarValores( $borrar_valores ){
		$this->borrar_valores = $borrar_valores;
	}
	function setBusqueda( $busqueda ){
		if( is_string( $busqueda ) ){
			$busqueda = '<div class="bloque_datos"><div class="bloque_dato div_bcliente">'.$busqueda.'</div></div>';
		}
		$this->busqueda = $busqueda;
	}
	function setCamposVacios( $campos_vacios ){
		$this->campos_vacios = $campos_vacios;
	}
	
	function setProducto( $producto, $id_producto, $token_producto, $proveedores, $campos_extra )
	{
		$this->producto = $producto;
		$this->id_producto = $id_producto;
		$this->token_producto = $token_producto;
		$this->proveedores = $proveedores;
		$this->campos_extra = $campos_extra;
	}
	function setProductoActual ($producto_actual) {
		$this->producto_actual = $producto_actual;
	}

	function setTicket ($ticket) {
		$this->ticket = $ticket;
	}

	/*
	 *  Asigna la clase al campo vacio.
	 *  Si hay campos vacios asigno texto c_vacio a los que esten, para mostrarlo en la interface.
	 *  @params array $campos contiene los campos que estan vacios		
	 *  @return array $c contiene el campo con el texto para la clase asignado
	 *  
	 ** */
	function getClassCampoVacio( $campo, $tipo ) {

		$clase = '';
		if( isset($this->campos_vacios[$tipo]) && !is_null( $this->campos_vacios[$tipo] ) ){
			$clase = ( in_array($campo, $this->campos_vacios[$tipo] ) ) ? 'c_vacio' : '';
		}	
		return $clase;
	}
	function divCamposVacios(){

		$html = '';
		if ( isset( $this->campos_vacios ) && !empty($this->campos_vacios) ){
		        $html  = sprintf( "<div id='check_campos' class='bloque_datos'><h2>Revisa los campos vacios</H2></div>");
		}
		return $html;
	}	
	
	function getHtmlInfoVenta( $data ){
		$arr_vals = array('fecha_venta', 'id_comercial', 'token_venta' );
		$token_venta = '';
		$fecha_venta = '';
		$id_comercial = '';
		if( $this->checkData( $data, $arr_vals ) ){ 
			
			$token_venta = $data['token_venta'];
			$this->venta = $token_venta;
			$fecha_venta = $data['fecha_venta'];
			$id_comercial = $data['id_comercial'];
		}

		$inputs_venta = "<input type='hidden' name='venta[nombre]' value='$token_venta'>";
		
		$inputs_venta .=  sprintf('<p>Fecha:<b>&nbsp;%s&nbsp;</b>|&nbsp;Comercial:&nbsp;<b>%s</b>|&nbsp;Venta:&nbsp;<b>%s</b>'
			, $fecha_venta, $id_comercial, $token_venta);

		return $inputs_venta;
	}
	/* guardo precios de los productos precios:array*/
	function getFieldsPrecios( $precios ){
		$html_valores = '';
		if( $precios != null ){
		foreach ( $precios as $valor => $campos ){
			foreach( $campos as $campo => $precio ){
				$name = "venta[precios][productos][$valor][$campo]";
				$html_valores .= '<input type="text" value="'.$precio.'" name="'.$name.'">';
			}
		}
		}
		return $html_valores;
	}
	
	function getHtmlAsignacionesErrores ($errores_asignaciones) {
		$html = '<div id="check_campos" class="">'.
			   '<p>'.d8($errores_asignaciones).'</p>'.
			'</div>';
		return $html;
	}
	
	function getHtmlButtonAddNota() {
		return sprintf ('<input type="submit" value="%s" name="add_div_nota">', d8('Crear nota'));
	}

	function getHtmlInputAddNota(){
		
		$nota = sprintf(
			'<div class="div_table bloque_datos_creados bloque_dato">'.
			  '<div class="div_table_tr">'.
			    '<span class="div_table_th">'.
			      '<h2 class="th_datos">Nota</h2>'.
			    '</span>'.
			  '</div>'.
			  '<div class="div_table_tr">'.
			    '<span class="div_table_td" >'.
			      '<textarea cols="50" rows="4" name="venta[nota]"></textarea>'.
			    '</span>'.
			  '</div>'.
			  '<div class="div_table_tr">'.
			    '<span class="div_table_td">'.
			      '<input type="submit" value="%s" name="add_nota">'.
			    '</span>'.
			  '</div>'.
			'</div>', d8('Añadir nota') );

		return $nota;
	}
	
	function getHtmlResumenPrecios( $precios, $tipo ){
		
		if( $tipo == 'totales' ){
			$precios = $precios;
			$nombre_campo = "venta[precios][totales]";
		}
		else{
			$precios = $precios[$tipo];
			$nombre_campo = "venta[precios][productos][".$tipo."]";
		}
		
		$compra = ( isset($precios['compra']) ) ? $precios['compra'] : 0;
		$venta = ( isset($precios['venta']) ) ? $precios['venta'] : 0;	
		$margen = ( isset($precios['margen']) ) ? $precios['margen'] : 0;	
		
		return sprintf(	
			'<div class="bloque_dato border_blue">
			  <table class="table_sumarize">'.
			    '<tr ><th colspan="6" class="th_datos" >Precios '.ucfirst($tipo).'</th>'. 
			         '<th class="color_bg_th width_precios_venta" >Compra</th>'.
			         '<td><input size="5"  type="text" name="'.$nombre_campo.'[compra]" value="%s" readonly ></td>'.
			         '<th class="color_bg_th width_precios_venta">Venta</th>'.
			         '<td><input size="5" type="text" name="'.$nombre_campo.'[venta]" value="%s" readonly ></td>'.
			         '<th class="color_bg_th width_precios_venta">Margen</th>'.
			         '<td><input type="text" size="5" name="'.$nombre_campo.'[margen]" value="%s" readonly ></td>'.
			    '</tr>'.
			  '</table>'.
			'</div>',$compra, $venta, $margen);
	}
	/* devuelve titulo venta: deberia estar en interface.*/
	function getHtmlTituloVenta($token) {
		return " <h2 class='h2_resumen_venta'>RESUMEN VENTA ".$token."</h2>";
	}
	
	function getInputsCamposExtra( $nombre, $campo, $valor ){
		$input = '';
		
		$nombre_campo = $this->producto."[datos][producto]";
		
		$tipo_campo = $campo ['tipo'];
		if ($tipo_campo == 'input') {

			$name = ( $nombre == 'precio_compra' ) ? $nombre_campo.'[datos_mensajeria]'.'['.$nombre.']' : $nombre_campo.'['. $nombre .']';

			$input = '<input type="text" name="'.$name.'" value="'. $valor .'">';

		} else {

			$campos = Coleccion :: get ($campo ['campos']);
			
			$input = '<select name="'. $this->producto .'[datos][producto]['. $nombre .']">';
			
			if( $nombre == 'otros_servicios' || $nombre == 'mensajeria' ){
			
				foreach( $campos as $p ){
					$nombre = d8($p->nombre);
					$input .= '<option value="'.$p->id.'" '. ( $p->id == $valor ? 'selected':'') .' >'. $nombre .'</option>';
				}
		
			}  else {
				
				foreach( $campos as $k => $opcion ){
					$value = $k;

					if ($k == '-' && $k != 0) {
						$value = '';
					}

					if (mb_detect_encoding($opcion) == 'UTF-8') {
						$value = utf8_decode($value);
						$opcion = utf8_decode ($opcion);
				       		//echo "$nombre ".mb_detect_encoding ($nombre) ."<br>";	
					}
					$input .= '<option value="'.$value.'" '.($value == $valor ? 'selected' : '').' >'.$opcion.'</option>';
				}
			}
			
			$input .= '</select>';
		}

			
		return $input;
	}
	
	function getListadoNotas($notas){
		$html = "<div class='div_notas bloque_dato bloque_datos_creados'>";
		$html .= "<h2 class='h2_resumen_venta'>Notas</h2>";
		$html .= '<div>';
		foreach( $notas as $id => $nota ){
			$html .= '<span><textarea name="venta[notas]['.$id.']" >'.$nota.'</textarea>'.
				'<input title="Quitar" size="24" src="../../images/black_delete.png" name="del_note['.$id.']" type="image">'.
				'</span>';
		}
		$html .= "</div>";
		$html .= "</div>";
		return $html;
	}

	/* de las asignaciones Asignacion.class.php*/
	function htmlCliente( $datos_form, $tipo_datos ){
		$this->datos_form = $datos_form;
		return $this->htmlFormCliente( $tipo_datos );

	}
	/* genero el formulario para el producto : Venta:getFormProduct*/
	function getHtmlAddProductForm( $datos, $clientes ){
		
		$this->datos_form = $datos;
		$html  = $this->getAddButton();
		$html .= $this->divCamposVacios();
		$html .= ( isset($this->busqueda) && is_string( $this->busqueda ) ? $this->busqueda : '');
		$html .= $this->getHtmlFormProducto();
		$html .= $this->htmlFormProveedor();
		$html .= $this->htmlFormCliente( $this->producto, $clientes );
		$html .= $this->getAddButton();

		return $html;
	}
	function getAddButton(){

		return '<div class="div_input"><input type="submit" name="add_datos_cliente" value="'.d8('Añadir').'"></div>';
	}
	function getHtmlFormProducto(){
		
		$tr_extra = '';
		$cbs = getCabecerasProducto();
		$data_insert = $this->datos_form['producto'];
		$sel_loc = '';
		$clase = '';
		/* si hay localizadores doy la opcion de seleccionarlos*/
		if( $this->producto == 'vuelo' && !empty($this->localizadores) ){

			$sel_loc .= "<select name='localizadores' class='select_localizadores' >";
			$sel_loc .= "<option value=''>Escoger</option>";
			foreach( $this->localizadores as $localizador )	{
				$sel_loc .= "<option value='$localizador' >$localizador</option>";
			}
			$sel_loc .= "</select>";
			$tr_extra .= sprintf('<tr class="%s"><th class="color_bg_th">%s</th><td>%s</td></tr>',
				$clase, 'Localizadores existentes', $sel_loc );
		}
		/* si hay campos extras genero los campos*/
		$clase_obligatorio = '';
		if( !is_null( $this->campos_extra ) ){
			foreach ( $this->campos_extra as $v => $ce ){

				$campo_obligatorio = '';
				if( isset( $this->campos_vacios['producto'] ) ){
					$clase_obligatorio = ( in_array( $v, $this->campos_vacios['producto'] ) ) ? 'field_required' : '';
				}

				$clase = $this->getClassCampoVacio( $v, 'producto' );
				$valor_input = '';
				if( !$this->borrar_valores ){	
					if( $v == 'mensajeria' || $v == 'precio_compra' ){
						$valor_input = ( isset($data_insert['datos_mensajeria'][$v]) ) ? $data_insert['datos_mensajeria'][$v] : '' ;
					}
					else{
						$valor_input = ( isset($data_insert[$v]) ) ? $data_insert[$v] : '' ;
					}
				}
				$input = $this->getInputsCamposExtra( $v, $ce, $valor_input );
				$nombre = $ce['nombre'];
				
				$tr_extra .= sprintf('<tr class="%s %s"><th class="color_bg_th">%s</th><td>%s</td></tr>',
					$clase_obligatorio, $clase, $nombre, $input );
			}
		}
		
		$campos = array( 'fecha_venta','localizador','fecha_inicial', 'fecha_final', 'precio_venta_cliente');
		$clase = array();
		$trs = '';
		foreach( $campos as $campo ){	
			
			$clase_obligatorio = '';
			if( isset($this->campos_vacios['producto'] ) ){	
				$clase_obligatorio = ( in_array( $campo, $this->campos_vacios['producto'] ) ) ? 'field_required' : '';
			}
			
			$clase = $this->getClassCampoVacio( $campo, 'producto' );	
			$valor = ( isset($data_insert[$campo]) && !$this->borrar_valores ) ? $data_insert[$campo] : '' ;
			$autocomplete = ( strpos( $campo, 'fecha') !== false ) ? "autocomplete='off'" : "";
		
			if ($valor == '' && $campo == 'fecha_venta') { 
				$valor = date('Y-m-d');
			}
			$tr_campo = $cbs[ $campo ];	
			$dataAttribute = getDataAttributeInput( $campo );
			
			$disabled = '';	
			
			if( $this->producto == 'devolucion' && $campo == 'precio_venta_cliente' ){
				$tr_campo = 'Precio Venta Cliente';	
			}
			if ($this->producto == 'devolucion' && $campo == 'fecha_venta') {
				$tr_campo = d8 ('Fecha devolución');
				$disabled = 'readonly';
				$dataAttribute = '';
			}
			
				
			$trs .= sprintf(
				'<tr class="%s %s">'.
				  '<th class="color_bg_th" >%s</th>'.
				  '<td><input type="text" id="%s" %s name="%s[datos][producto][%s]" value="%s" %s %s></td>'.
				'</tr>',
				$clase_obligatorio,$clase, $tr_campo,$campo,$dataAttribute,$this->producto,$campo,$valor,$autocomplete, $disabled);
		}
		$html = sprintf('<input type="hidden" name="%s[id]" value="%s">'.
			'<div class="bloque_dato">'.
				'<h2 class="th_datos">Datos %s - %s</h2>'.
				'<table>%s%s'.
				'</table>'.
				'</div>'.
			'<input type="hidden" name="%s[datos][producto][id_tipo]" value="%s">',
			$this->producto,
			$this->token_producto,
			ucfirst($this->producto),
			$this->token_producto,
			$trs,
			$tr_extra,
			$this->producto,
			$this->id_producto);

		return $html;

	}
	/* HTML campos proveedor*/
	function htmlFormProveedor(){
		
		$p_ops = '';
		$html_proveedores = '';

		$cbs = getCabecerasProducto();
		$pst_prov = $this->datos_form['proveedor'];
		
		if( !is_null( $this->proveedores ) ) {
			
			$clase_obligatorio = '';
			if( isset($this->campos_vacios[ 'proveedor' ]) ){
				$clase_obligatorio = ( in_array( 'sel_proveedor', $this->campos_vacios['proveedor'] ) ) ? 'field_required' : '';
			}
			$clase = $this->getClassCampoVacio( 'sel_proveedor', 'proveedor' );	
			$html_proveedores = sprintf('<tr class="%s %s"><th class="color_bg_th">Escoge</th>', $clase_obligatorio, $clase);
			foreach( $this->proveedores as $p ){
				
				$valor = ( isset($pst_prov['sel_proveedor']) && !$this->borrar_valores ) ? $pst_prov['sel_proveedor'] : '' ;
				$s_cancelacion = ( $p->etiqueta == 6 ) ? d8('(s.cancelación)') : ''; 
				$p_ops .= sprintf('<option value="%s" '. ( $p->id == $valor ? 'selected':'') .'  >%s %s</option>', 
					$p->id, $p->nombre_proveedor, $s_cancelacion );
			}
			$html_proveedores .= sprintf('<td>'.
				'<select name="%s[datos][proveedor][sel_proveedor]">'.
				'<option value="">Proveedor</option>'.
				'%s'.
				'</select></td></tr>', $this->producto, $p_ops);
		}

		$tipo = $this->producto;
		$campos = array( 'fecha_pago_proveedor','fecha_limite_proveedor','precio_compra_proveedor', 'tipo_pago','nota');

		$clase = array();
		$tr_campos = '';
		foreach( $campos as $campo ){	
			if( $campo == 'fecha_pago_proveedor' && $this->id_comercial != 24 ){
				continue;
			}
			$clase_obligatorio = '';
			if( isset( $this->campos_vacios['proveedor'] ) ){	
				$clase_obligatorio = ( in_array( $campo, $this->campos_vacios['proveedor'] ) ) ? 'field_required' : '';
			}
			$clase = $this->getClassCampoVacio( $campo, 'proveedor' );	
			$valor = ( isset($pst_prov[$campo]) && !$this->borrar_valores ) ? $pst_prov[$campo] : '' ;
			
			$tipo_input = 'text';
			$tr_campos .= '<tr class="'.$clase_obligatorio.' '.$clase.'">';
			$tr_campos .= '<th class="color_bg_th" >';

			$tr_campo_valor = $cbs[ $campo ];
			$tr_campos .= ( $this->producto == 'devolucion' && $campo == 'precio_compra_proveedor' ) ? 
				'Precio Coste Proveedor' : $tr_campo_valor;
			$tr_campos .= '</th>';
			$tr_campos .= '<td>';
			
			if( $campo == 'tipo_pago' ){
				$tr_campos .= '<select name="'.$tipo.'[datos][proveedor]['.$campo.']">';
				$tipos_pago = Coleccion::get('tiposPagoProveedores');

				foreach( $tipos_pago as $valor_op => $nombre ){
					$selected = ( $valor == $valor_op ) ? 'selected' : '';
					$tr_campos .= "<option value='$valor_op' $selected >$nombre</option>";
				}
				$tr_campos .= "</select>";
			}
			else if( $campo == 'nota' ){
				$tr_campos .= '<textarea name="'.$tipo.'[datos][proveedor]['.$campo.']">'.$valor.'</textarea>';
			}else{
				$autocomplete = ( strpos( $campo, 'fecha') !== false ) ? "autocomplete='off'" : "";	
				$dataAttribute = getDataAttributeInput( $campo );
				$tr_campos .= sprintf(
					'<input type="%s" id="%s" %s name="%s" value="%s" %s>',
					$tipo_input, $campo, $dataAttribute, $tipo.'[datos][proveedor]['.$campo.']', $valor, $autocomplete
				);
			}

			$tr_campos .= '</td>';
			$tr_campos .= '<tr>';
		}

		$html = sprintf('<div class="bloque_dato"><h4 class="th_datos">Proveedor</h4>'.
			'<table>%s%s</table></div>',$html_proveedores, $tr_campos );

		return $html;
	}
	function htmlFormCliente( $tipo_datos, $clientes = null ){

		$datos_form = $this->datos_form['cliente'];	
		$listado_clientes = $this->getListClients($clientes);
		
		$cbs = getCabecerasProducto();
		$campos = array('nombre','dni','telefono','contacto','email','dir','dir_google','cardcode','nota');
		$tipo_busqueda = $this->producto;	
		if( isset($this->busqueda) && is_array( $this->busqueda)  ){
			$tipo_busqueda = key($this->busqueda);	
			$search = ( $tipo_busqueda == $tipo_datos ) ? 1 : 0;
		
			if( $search && is_array($this->busqueda[$tipo_busqueda]) && isset( $this->busqueda[$tipo_busqueda] ) ){
				$datos_form[$tipo_busqueda]['cliente'] = $this->busqueda[$tipo_busqueda]['cliente'];
				$this->datos_form = $datos_form[$tipo_busqueda];
				$datos_form = $this->datos_form['cliente'];
			}	
		}
		
		$div_class = 'bloque_dato';
		$datos_cliente = 'Datos Cliente';
		$nombre_tipo_busqueda = 'cliente';
		if( $tipo_datos == 'factura' ){  	
			$datos_cliente = 'Otros Datos Facturación';	
			$div_class = '';
			$nombre_tipo_busqueda = 'factura';
		}
		$html  = sprintf( '<div class="%s"><h4 class="th_datos">%s</h4><table>',$div_class, d8($datos_cliente)); 
		if( $listado_clientes != '' ){
			$html .= "<tr><th class='color_bg_th'>Clientes</th><td>$listado_clientes</td></tr>";	
			$html .= "<tr><td></td></tr>";
		}
		foreach ( $campos as $c => $v ) {
			
			$clase_obligatorio = '';
			if( isset( $this->campos_vacios[ 'cliente' ] ) ){
				$clase_obligatorio = ( in_array( $v, $this->campos_vacios['cliente'] ) ) ? 'field_required' : '';
			}
			$clase = $this->getClassCampoVacio( $v, 'cliente' );	
			$valor = ( isset($datos_form[$v]) && !$this->borrar_valores ) ? $datos_form[$v] : '' ;
			
			$nombre = $tipo_datos."[datos][cliente][$v]";
			$nombre_busqueda = 'search[datos]['.$nombre_tipo_busqueda.']['.$v.']';

			$search = '';$class_input = '';
			$data_search = array('nombre','dni','telefono','email');
			if( in_array( $v , $data_search ) ){
				$class_input = 'input_search';
				$search = sprintf('<input type="image" title="Busqueda usuario" class="search_icon"'.
					'src="../../images/search.png"  name="%s"  >', $nombre_busqueda);
			}
			$text_areas = array('nota', 'dir_google', 'dir');
			if (in_array($v, $text_areas)) {
				$input = '<textarea name="'.$nombre.'" class="'.$class_input.'" >'.$valor.'</textarea>';
			} else {
				//echo "<!--$v , $valor <br>-->";
				$valor_input = cleanValor($valor, $v);
				$input = '<input type="text" name="'.$nombre.'" value="'.$valor_input.'" class="'.$class_input.'">';
			}
			$tr_campo_valor = $cbs[ $v ];
			$html .= sprintf('<tr class="%s %s"><th class="color_bg_th" >%s</th>'.
				'<td>%s'.
				'%s</td></tr>', $clase_obligatorio, $clase, d8( $tr_campo_valor ), $input,$search);
		}	
		$html .= '</table>';	
		$html .= '</div>';

		return $html;
	}
	function getLocalizadores(){
		$data = '';
		if( !empty($this->localizadores) ){
			foreach( $this->localizadores as $k => $localizador ){
				$data .= "<input type='hidden' name='set[localizadores][$k]' value='$localizador'>";
			}
		}
		return $data;
	}
	function getListClients( $clients ){
		$html = '';
		if( !empty( $clients ) ){
			$html .= "<select name='{$this->producto}[datos][clientes]'>";
			$html .= "<option value='-'>Selecciona Cliente</option>";
			foreach( $clients as $tipo => $producto ){
				foreach( $producto as $id => $data ){
					$valor = $data['nombre']."-".$tipo;
					$html .= "<option value='$id'>$valor</option>";
				}
			}
			$html .= "</select>";
		}
		return $html;
	}
	function getHtmlDatosCreados( $datos){

		if ($datos == '') return '';

		$array = null;
		$html = '';
		$html_prods = null;
		$html_prods_actual = null;
		foreach( $datos as $tipo_producto => $info ){
			$datosCreados = new DatosCreados( $tipo_producto, $this->campos_extra );
			$visible = 0;
			$order = 1;	
			if( $tipo_producto == $this->producto ){
				$visible = 1;
				$order = 0;	
			}
			foreach( $info as $id_producto => $dato ){

				$data_prod  = '<div class="bloque_dato separates">';
				$data_prod .=  $datosCreados->getAddedData( $id_producto, $dato);
				$data_prod .= "</div>";
				
				if( !$order ) $html_prods_actual[] =$data_prod;
				else $html_prods[] = $data_prod;
			}
		}
		if( $html_prods_actual != null && $html_prods == null ) $array = $html_prods_actual;
		if( $html_prods != null && $html_prods_actual == null ) $array = $html_prods;
		if( $html_prods_actual != null && $html_prods != null ){
			$array = array_merge( $html_prods_actual, $html_prods);
		}
		$html = '';
		if ($array != null) {
			foreach( $array as $html_prod ){
				$html .= $html_prod;
			}
		}
		$html .= $this->getLocalizadores();

		return $html;
	}
	function getInputSend(){
		return '<div class="div_input"><input type="submit" name="send" value="Enviar"></div>';
	}
	/* viene de Producto*/
	function getInputsAsignaciones( $asignaciones){
		$html  = '<div class="bloque_dato bloque_datos_creados asignaciones">';
		$html .= '<div class=" div_comprobante" >';
		$html .= '<h2 class="th_datos">FACTURACION</h2>';
		$i = 0;
		foreach( $asignaciones as $tipo => $asigs ){
			foreach( $asigs as $k => $valores ){
				$html .= '<table>';
				$html .= '<tr><th>Id</th><th>Comprobante</th><th>Tipo</th><th>Producto</th><th>Cantidad</th></tr>';
				$html .= '<tr>';
				$html .= '<td class="color_bg_th" width=1>'.($i+1).'</td>';
				$html .= '<td class="color_bg_th">'.$valores['comprobante'].'</td>';
				$html .= '<td class="color_bg_th">'.ucfirst($valores['tipo']).'</td>';
				$html .= '<td class="color_bg_th">'.$valores['producto'].'</td>';
				$html .= '<td class="color_bg_th">'.$valores['cantidad'].'</td>';
				$html .= '<td class="" width="8">'.
				'<input title="Quitar" size="24" src="../../images/delete_new.png" name="del_asig['.$i.']" type="image">'
					.'</td>';
				$html .= "<tr><td></td></tr>";
				$html .= "</tr>";
				if( !empty( $valores['facturacion'] ) ){
					$ext_f = $valores['facturacion'];
					$html .= '<tr><td colspan="4">';
					$html .= "<table style='border: 1px solid #ccc'>";
					$html .= "<tr>";
					$html .= '<td colspan="2"><h2 class="th_datos">Datos cliente</h2></td>';
					$html .= "</tr>";
					$html .= "<tr>";
					$html .= "<th>Nombre</th>";
					$html .= '<td class="color_bg_th" colspan="3">'.$ext_f['nombre'].'</td>';
					$html .= "</tr>";
					$html .= "<tr>";
					$html .= "<th>CIF/DNI/PASS</th>";
					$html .= '<td class="color_bg_th">'.$ext_f['dni'].'</td>';
					$html .= "</tr>";
					$html .= "<tr>";
					$html .= "<th>Email</th>";
					$html .= '<td class="color_bg_th">'.$ext_f['email'].'</td>';
					$html .= "</tr>";
					$html .= "<tr>";
					$html .= "<th>".d8('Teléfono')."</th>";
					$html .= '<td class="color_bg_th">'.$ext_f['telefono'].'</td>';
					$html .= "<th>".d8('Teléfono Contacto')."</th>";
					$html .= '<td class="color_bg_th">'.$ext_f['contacto'].'</td>';
					$html .= "</tr>";
					$html .= "<tr>";
					$html .= "<th>".d8('Dirección')."</th>";
					$html .= '<td class="color_bg_th" colspan="3">'.$ext_f['dir'].'</td>';
					$html .= "</tr>";
					$html .= "</table>";
					$html .= "</td></tr>";
				}
				foreach( $valores as $campo => $valor ){
					if( $campo == 'facturacion' && is_array($valor) ){
						foreach($valor as $c2 => $val_fac ){
							$nombre_ce = "asignaciones_creadas[$i][facturacion][$c2]";
							$html .= "<input type='hidden' name='$nombre_ce' value='$val_fac'>";
						}
					}
					else if($campo != 'subproducto' ){
						$html .= "<input type='hidden' name='asignaciones_creadas[$i][$campo]' value='$valor'>";
					}
				}
				if( isset( $valores['subproducto'] ) ){
					foreach( $valores['subproducto'] as $id => $valor ){
						foreach( $valor as $campo => $val )
							$html .= sprintf("<input type='hidden' name='asignaciones_creadas[%s][subproducto][%s][%s]' value='%s'>",
								$i,$id,$campo, $val);
					}
				}
					
				$html .= '</tr>';	
				$html .= '</table>';
				$i ++;
			}
		}
				
		$html .= '</div></div>'; 
                return $html;

	}
	function getDivResumenProductos( $datos_productos, $hay_comprobantes ){
		$html = '';
		if( !empty($datos_productos) ){
			$html .= '<div class="datos_factura" style="">';
			$html .= '<div class="bloque_dato bloque_datos_creados" >';
			$html .= '<h2 class="h2_resumen_venta">Resumen productos</h2>';
			$html .= "<div class='div_table'>";
			foreach( $datos_productos as $tipo_producto => $productos ){
				foreach( $productos as $key2 => $producto ){
					$html .= "<div class='div_table_tr'>";
					$class = ( $tipo_producto != 'devolucion' && $producto['compra'] > $producto['venta'] ) ? 'warning' : 'done' ; 
					$html .= "<span class='div_table_th'>Producto <b>[".ucfirst($tipo_producto)."]</b>->";
					$html .= "&nbsp;".ucfirst($key2)."</span>";
					$html .= "<span class='div_table_td $class'>&nbsp;Compra</span>";
					$html .= "<span class='div_table_th $class'>&nbsp;".$producto['compra']."</span>";
					$html .= "<span class='div_table_td $class'>&nbsp;Venta</span>";
					$html .= "<span class='div_table_th $class'>&nbsp;".$producto['venta']."</span>";
					$html .= "<span class='div_table_td $class'>&nbsp;Margen</span>";
					$html .= "<span class='div_table_th $class'>&nbsp;".$producto['margen']."</span>";
					if( $producto['reserva'] ){
						$html .= "<span class='div_table_th $class'>&nbsp;RESERVADO</span>";
					}
					
					if( $hay_comprobantes && 
						( $producto['venta'] == 0 || ($tipo_producto != 'devolucion' && $producto['venta'] < $producto['compra'])) ){
						$html .= '&nbsp;&nbsp;<span class="div_table_th">'.
						'<input type="submit" value="ASIGNAR COMPROBANTE" name="facturar['.$tipo_producto.']['.$key2.']">'.
						'</span>';
					}
					$html .= "</div>";
				}
			}
			$html .= "</div>";
			$html .= "</div>";
			$html .= "</div>";
		}
		return $html;	
	}
	function getHtmlWarnings( $wrs ){
		$html = '';
		$leyendas = array(
			'campos-vacios' => 'Hay campos vacios obligatorios.',
			'cantidad-comprobante-por-asignar' => 'Cantidad comprobante no asignada.',
			'comprobante-sin-asignar' => 'Comprobantes sin asignar.',
			'fecha-final-incorrecta' => 'Fecha final menor que la fecha inicial.',
			'marcado-reserva' => 'Hay un producto reservado.',
			'margen-negativo'=>'Hay margen negativo.', 
			'producto-incompleto' => 'Producto no facturado completamente.',
			'productos-sin-asignar' => 'Hay algún producto sin facturar.'
		);
		if( !empty( $wrs) ){
			$wrs = array_unique($wrs);
			$html .= "<h2>Alertas</h2>";
			foreach( $wrs as $warn_text ) $html .= "<p>".d8($leyendas[$warn_text])."</p>";
		}
		return $html;
	}
	function getHtmlInsert( $res_insert ){
		$res_text = '';
		$class = '';
		$ticket = '';
		if( isset($res_insert['venta']) && $res_insert['venta'] ){
			$class = 'done';
			$res_text = 'Venta insertada correctamente';
			if ($this->ticket != '') $ticket = $this->ticket;
		}else{
			$class = 'wrong';
			$res_text = $res_insert['text'];
		}

		$html  = '<h2>Resultado Venta</h2>';
		$html .= '<p class="'.$class.'">'.$res_text.'</p>';
		$html .= $ticket;

		return $html;
	}
}
