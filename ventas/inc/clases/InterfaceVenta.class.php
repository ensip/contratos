<?php
class Field {
	public function get($campo, $valor, $valores_campo) 
	{
		$campos_formularios = new CamposFormularios();
		$tr_campos = '';
		$nombre_campo = $valores_campo['nombre_campo'];
		$id_campo = (isset($valores_campo['id_campo']) ? $valores_campo['id_campo'] : '');
		$title = (isset($valores_campo['title'])) ? $valores_campo['title'] : '';

		if (isset($valores_campo['divisa'])) {
			$extra['divisa'] = $valores_campo['divisa'];
		}

		$span_divisa = (isset($valores_campo['divisa'])) ? '<span class="sumarize_divisa_form">' . ($valores_campo['divisa'] == 1 ? 'EUR' : 'USD') . '</span>' : '';

		if ($valores_campo['tipo'] == 'select') {

			$extra = array();	
			if ($campo == 'sel_proveedor') {
				$extra['proveedores'] = (!empty($valores_campo['list_proveedores']) ? $valores_campo['list_proveedores'] : ''); 
			}
			if (isset($valores_campo['suma_porcentaje_euro'])) {
				$extra['suma_porcentaje_euro'] = $valores_campo['suma_porcentaje_euro'];
			}
			
			$onchange = (isset($valores_campo['extras'])) ? $campos_formularios->getDataExtra($valores_campo['extras'], 'onchange') : '';
			$recalcular = (isset($valores_campo['extras'])) ? $campos_formularios->getDataExtra($valores_campo['extras'], 'recalcular') : '';

			$options = Coleccion::get($valores_campo['campos'], $extra);

			$tr_campos .= '<select name="'.$nombre_campo.'" '.$onchange.' title ="'.$title.'" '.$recalcular.' id="'.$id_campo.'" >';
			if (!empty($options)) {
				foreach ($options as $valor_op => $nombre) {
					$selected = ( $valor == $valor_op ) ? 'selected' : '';
					$tr_campos .= "<option value='$valor_op' $selected >$nombre</option>";
				}
			}
			$tr_campos .= "</select>" . $span_divisa;

		} 
		if ($valores_campo['tipo'] == 'textarea') {
			$tr_campos .= sprintf('<textarea name="%s" title="%s" %s>%s</textarea>', $nombre_campo,$title, $id_campo, $valor);
		}
		if ($valores_campo['tipo'] == 'input') {

			$autocomplete = '';
			$data_field = '';
			$readonly = '';
			$onchange = '';
			$recalcular = '';
			
			if (isset($valores_campo['extras'])) {
				$autocomplete = $campos_formularios->getDataExtra($valores_campo['extras'], 'autocomplete');
				$data_field = $campos_formularios->getDataExtra($valores_campo['extras'], 'data-field');
				$readonly = $campos_formularios->getDataExtra($valores_campo['extras'], 'readonly');
				$onchange = $campos_formularios->getDataExtra($valores_campo['extras'], 'onchange');
				$recalcular = $campos_formularios->getDataExtra($valores_campo['extras'], 'recalcular');
			}

			$tr_campos .= sprintf(
				'<input type="text" id="%s" %s name="%s" value="%s" %s %s title="%s" %s >%s',
				(!empty($id_campo) ? $id_campo : $campo), $data_field, $nombre_campo, $valor, $autocomplete, $readonly, $title, $recalcular, $span_divisa
			);
		}
		return $tr_campos;
	}
}
class InterfaceVenta{

	protected $adding = false;
	protected $borrar_valores = 0;
	protected $busqueda = null;
	protected $cabeceras = array();
	protected $campos_extra = array();
	protected $campos_formularios = array();
	protected $campos_vacios = array();
	protected $datos_campos_extra_coche = array();
	protected $datos_form = array();
	protected $divisa = 1;
	protected $editando = 0;
	protected $id_comercial = '';
	protected $localizadores = array();
	protected $producto = '';
	protected $producto_actual = '';
	protected $proveedores = array();
	protected $servicios = null;
	protected $ticket = '';
	protected $venta = '';

	function __construct( $id_comercial = '' )
	{
		$this->id_comercial = $id_comercial;
		$this->campos_formularios = new CamposFormularios();
		$this->setDivisa();
	}
	//////////// GETTERS ////////////////////

	private function get_campos_extra() 
	{
		return $this->campos_formularios->camposExtra($this->producto);
	}
	
	function getCamposVacios() 
	{
		return $this->campos_vacios;
	}

	//////////// FIN GETTERS ////////////////
	
	function addLocalizador( $localizador )
	{	
		if(!in_array( $localizador, $this->localizadores ) ){
			array_push( $this->localizadores, $localizador );
		}
	}
	
	function addLocalizadores( $localizadores )
	{
		if( !empty($localizadores) ){
			foreach( $localizadores as $localizador ){
				$this->addLocalizador( $localizador );
			}
		}
	}

	function checkData( $data, $arr_vals )
	{
		$ok_extract = 1;
		foreach( $arr_vals as $key ){
			if( !isset( $data[$key] ) ){ $ok_extract = 0; }
		}
		return $ok_extract;
	}
	
	private function claseCampoObligatorio($key_campos_vacios, $campo)
	{
		$clase_obligatorio = '';
		if( isset($this->campos_vacios[$key_campos_vacios]) && empty($valor_input) ) {
			$clase_obligatorio = ( in_array($campo, $this->campos_vacios[$key_campos_vacios])) ? 'field_required' : '';
		}
		return $clase_obligatorio;
	}

	function setBorrarValores( $borrar_valores )
	{
		$this->borrar_valores = $borrar_valores;
	}


	function setCamposVacios( $campos_vacios )
	{
		$this->campos_vacios = $campos_vacios;
	}

	public function setAdding($adding) 
	{
		$this->adding = $adding;
	}	

	public function setEditando($editando) 
	{
		$this->editando = $editando;
	}	

	//public function setProducto( $producto, $id_producto, $token_producto, $proveedores, $campos_extra )
	public function setProducto( $datos_producto, $token_producto, $servicios)
	{
		$this->producto = $datos_producto['nombre'];
		$this->id_producto = $datos_producto['id'];
		$this->token_producto = $token_producto;
		$this->servicios = $servicios;
	}

	function setProductoActual($producto_actual) 
	{
		$this->producto_actual = $producto_actual;
	}

	function setTicket($ticket) 
	{
		$this->ticket = $ticket;
	}

	/*
	 *  Asigna la clase al campo vacio.
	 *  Si hay campos vacios asigno texto c_vacio a los que esten, para mostrarlo en la interface.
	 *  @params array $campos contiene los campos que estan vacios		
	 *  @return array $c contiene el campo con el texto para la clase asignado
	 *  
	 ** */
	private function classCampoVacio( $campo, $tipo ) 
	{
		$clase = '';
		if( isset($this->campos_vacios[$tipo]) && !is_null( $this->campos_vacios[$tipo] ) ){
			$clase = ( in_array($campo, $this->campos_vacios[$tipo] ) ) ? 'c_vacio' : '';
		}	
		return $clase;
	}

	function InputsCamposExtra( $nombre, $campo, $valor )
	{
		$input = '';
		$nombre_campo = $this->producto."[datos][producto]";
		
		$tipo_campo = $campo['tipo'];
		if ($tipo_campo == 'input') {

			$name = ( $nombre == 'precio_compra' ) ? $nombre_campo.'[datos_mensajeria]'.'['.$nombre.']' : $nombre_campo.'['. $nombre .']';
			$readonly = ($nombre == 'total_extras' || $nombre == 'total_con_suplemento' ? 'readonly' : '');
			$input = '<input type="text" name="'.$name.'" value="'. $valor .'" '.$readonly.'>';

		} else {
			$extras = array('divisa' => $this->divisa);

			$recalcular = '';
			if ($nombre == 'cats' || $nombre == 'cantidad_dias') {
				$info_campo = $this->campos_formularios->infoCampos($nombre);
				$recalcular = $this->campos_formularios->getDataExtra($info_campo['extras'], 'recalcular');
			}

			$options = Coleccion::get($campo['campos'], $extras);
			
			$input = '<select name="'. $this->producto .'[datos][producto]['. $nombre .']" id="datos_nuevos_'.$nombre.'" '.$recalcular.'>';
			
			if (is_array($options) || is_object($options)) {
				
				if (is_object($options)){
					foreach( $options as $option){
						$input .= '<option value="'.$option->id.'" '. ( $option->id == $valor ? 'selected':'') .' >'. d8($option->nombre) .'</option>';
					}
			
				}  else {
					foreach( $options as $k => $opcion ){
						
						$value = ($k == '-') ? '' : $k;

						if (mb_detect_encoding($opcion) == 'UTF-8') {
							$value = utf8_decode($value);
							//echo "$nombre ".mb_detect_encoding($nombre) ."<br>";	
						}
						$input .= '<option value="'.$value.'" '.($value == $valor ? 'selected' : '').' >'. utf8_decode($opcion) .'</option>';
					}
				}
			}
			
			$input .= '</select>';
		}
			
		return $input;
	}
	
	public function ListadoNotas($notas)
	{
		$html = "<div class='div_notas bloque_dato bloque_datos_creados'>";
		$html .= "<h2 class='h2_resumen_venta'>Notas</h2>";
		$html .= "<hr>";
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
	function htmlCliente( $datos_form, $tipo_datos ) 
	{
		$this->datos_form = $datos_form;
		return $this->htmlFormCliente( $tipo_datos );

	}
	/* genero el formulario para el producto : Venta:getFormProduct*/
	function AddProductForm( $datos, $clientes )
	{
		$this->datos_form = $datos;
		$html  = $this->AddButton();
		$html .= $this->divCamposVacios();
		$html .= ( isset($this->busqueda) && is_string( $this->busqueda ) ? $this->busqueda : '');
		$html .= $this->htmlFormProducto();
		$html .= $this->htmlFormProveedor();
		$html .= $this->htmlFormCliente( $this->producto, $clientes );
		$html .= $this->AddButton();

		return $html;
	}
	
	public function AddButton()
	{
		return '<div class="div_input"><input type="submit" name="add_datos_cliente" value="'.d8('Añadir').'"></div>';
	}

	
	private function getIdProveedorSuplementosObligatorios() 
	{
		$id = 0;
		if (!is_null($this->proveedores)) {
			foreach( $this->proveedores as $proveedor ){
				if ($proveedor->campos_extra_coche_obligatorios) {
					$id = $proveedor->id;
				}
			}
		}
		return $id;
	}

	private function getValue($tipo, $campo) 
	{
		if (isset($this->datos_form[$tipo])) {
			$data = $this->datos_form[$tipo];
			return (!$this->borrar_valores ? ( isset($data[$campo]) ? $data[$campo] : '') : '' );
		}
		return '';
	}
	
	private function calcularValorCampoExtraCoche($valor_input, $nombre_campo_extra) 
	{
		if ($nombre_campo_extra == 'chofer') {
			$cantidad_dias = $this->getValue('producto', 'cantidad_dias');
			$cantidad_chofer = $this->getValue('proveedor', 'cantidad_chofer');
			$valor_input = $this->campos_extra_coches->calculoValorChofer($cantidad_dias, $cantidad_chofer);
		}
		if ($nombre_campo_extra == 'combustible') {
			$cats = $this->getValue('producto', 'cats');
			$valor_input = $this->campos_extra_coches->getCombustible($cats);
		}
		if ($nombre_campo_extra == 'dropoff') {
			//$valor_input = (real)str_replace(',','.',$this->getValue('proveedor', $nombre_campo_extra));
			$valor_input = $this->campos_extra_coches->getDropoff($this->getValue('proveedor', $nombre_campo_extra));
		}
		if ($nombre_campo_extra == 'total_extras') {
			$valor_input = $this->campos_extra_coches->getTotalExtras($valor_input, $this->divisa);
		}

		return $valor_input;
	}

	public function loadValoresCamposExtra() 
	{
		if ($this->producto_actual == 'coche') {
			$this->datos_campos_extra_coche = CamposExtraCoches::getValores();
		}
	}

	public function setDivisa($divisa = 0) 
	{
		if ($divisa > 0) {
			$this->divisa = $divisa;
		} else{
			$this->divisa = (isset($_POST['divisa_venta'])) ? $_POST['divisa_venta'] : 1;
		}
	}
	////////////////////// HTML ////////////////////////////////////////

	public function AsignacionesErrores($errores_asignaciones) 
	{
		$html = '<div id="check_campos" class="">'.
			   '<p>'.d8($errores_asignaciones).'</p>'.
			'</div>';
		return $html;
	}

	public function Busqueda( $busqueda )
	{
		if( is_string( $busqueda ) ){
			$busqueda = '<div class="bloque_datos"><div class="bloque_dato div_bcliente">'.$busqueda.'</div></div>';
		}
		$this->busqueda = $busqueda;
	}

	
	public function ButtonAddNota() 
	{
		return sprintf('<input type="submit" value="%s" name="add_div_nota">', d8('Crear nota'));
	}
	
	/* si hay comprobantes genera el boton para hacer asignaciones
	 * call : Venta.class
	 * */
	public function ButtonAsignacion() 
	{
		return '<input type="submit" value="'.d8('Facturar Comprobantes').'" name="ver_asig_comprobante">';
	}
	
	public function ButtonComprobante() 
	{
		return '<input type="submit" value="'.d8('Crear Comprobante').'" name="add_div_comprobante">';
	}
	function divCamposVacios()
	{
		$html = '';
		if ( isset( $this->campos_vacios ) && !empty($this->campos_vacios) ){
		        $html  = sprintf( "<div id='check_campos' class='bloque_datos'><h2>Revisa los campos vacios</H2></div>");
		}
		return $html;
	}	
	/* 
	 * OBSOLETE: NO SE USA
	 * guardo precios de los productos precios:array
	 * */
	function getFieldsPrecios( $precios )
	{
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
	
	private function HiddenLocalizadores()
	{
		$data = '';
		if( !empty($this->localizadores) ){
			foreach( $this->localizadores as $k => $localizador ){
				$data .= "<input type='hidden' name='set[localizadores][$k]' value='$localizador'>";
			}
		}
		return $data;
	}	

	function htmlFormCliente( $tipo_datos, $clientes = null )
	{
		$datos_form = $this->datos_form['cliente'];	
		$listado_clientes = $this->ListClients($clientes);
		
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

		foreach( $campos  as $campo_nombre ) {
			
			$clase_obligatorio = $this->claseCampoObligatorio('cliente', $campo_nombre);
			$clase = $this->ClassCampoVacio( $campo_nombre, 'cliente' );	
			$valor = $this->getValue('cliente', $campo_nombre);
			
			$nombre = $tipo_datos."[datos][cliente][$campo_nombre]";
			$nombre_busqueda = 'search[datos]['.$nombre_tipo_busqueda.']['.$campo_nombre.']';

			$search = '';$class_input = '';
			$data_search = array('nombre','dni','telefono','email');
			if( in_array( $campo_nombre , $data_search ) ){
				$class_input = 'input_search';
				$search = sprintf('<input type="image" title="Busqueda usuario" class="search_icon"'.
					'src="../../images/search.png"  name="%s"  >', $nombre_busqueda);
			}
			$text_areas = array('nota', 'dir_google', 'dir');
			if (in_array($campo_nombre, $text_areas)) {
				$input = '<textarea name="'.$nombre.'" class="'.$class_input.'" >'.$valor.'</textarea>';
			} else {
				//echo "<!--$v , $valor <br>-->";
				$valor_input = cleanValor($valor, $campo_nombre);
				$input = '<input type="text" name="'.$nombre.'" value="'.$valor_input.'" class="'.$class_input.'">';
			}
			$tr_campo_valor = $this->campos_formularios->cabeceras($campo_nombre);	

			$html .= sprintf('<tr class="%s %s"><th class="color_bg_th" >%s</th>'.
				'<td>%s'.
				'%s</td></tr>', $clase_obligatorio, $clase, $tr_campo_valor, $input,$search);
		}	
		$html .= '</table>';	
		$html .= '</div>';

		return $html;
	}
	function htmlFormProducto() 
	{
		$campos_trs = '';
		$campos_tr_extra = '';
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
			$campos_tr_extra .= sprintf('<tr class="%s"><th class="color_bg_th">%s</th><td>%s</td></tr>',
				$clase, 'Localizadores existentes', $sel_loc );
		}
		if ($this->adding) {
			//debug($this->datos_form['producto']);
		}
		
		/* si hay campos extras genero los campos*/
		$campos_extra = $this->get_campos_extra();
		if (!empty($campos_extra)) {	
			foreach ( $campos_extra as $nombre_campo_extra => $campo_extra ){
				$valor_input = '';

				$clase = $this->classCampoVacio( $nombre_campo_extra, 'producto' );
				if( !$this->borrar_valores ){	
					if(in_array($nombre_campo_extra, array('mensajeria', 'precio_compra'))) { 
						$valor_input = ( isset($this->datos_form['producto']['datos_mensajeria'][$nombre_campo_extra]) ) ? $this->datos_form['producto']['datos_mensajeria'][$nombre_campo_extra] : '' ;
					} else{
						$valor_input = $this->getValue('producto', $nombre_campo_extra);

					}
				}
				
				$clase_obligatorio = $this->claseCampoObligatorio('producto', $nombre_campo_extra);

				$campo_obligatorio = '';
				if (empty($campo_obligatorio) && $valor_input != '') {
					$clase = '';
				}

				$input = $this->InputsCamposExtra( $nombre_campo_extra, $campo_extra, $valor_input );
				$nombre = $campo_extra['nombre'];
				
				$campos_tr_extra .= sprintf(
					'<tr class="%s %s"><th class="color_bg_th">%s</th><td>%s</td></tr>',
					$clase_obligatorio, $clase, $nombre, $input 
				);
			}
		}
		
		$campos = $this->campos_formularios->campos('producto');
		
		foreach( $campos as $campo => $valores_campo ){	
			
			$clase_obligatorio = $this->claseCampoObligatorio('producto', $campo);
			$clase = $this->classCampoVacio( $campo, 'producto' );	
			$valor = $this->getValue('producto', $campo);

			$tr_campo = $this->campos_formularios->cabeceras($campo);	
			if (isset($valores_campo[$this->producto]['nombre'])) {
				$tr_campo = $valores_campo[$this->producto]['nombre'];
			}

			$autocomplete = ( strpos( $campo, 'fecha') !== false ) ? "autocomplete='off'" : "";
			if ($valor == '' && $campo == 'fecha_venta') { 
				$valor = date('Y-m-d');
			}
			
			$disabled = ($this->producto == 'devolucion' && $campo == 'fecha_venta') ? 'readonly' : '';

			$autocomplete = '';
			$data_field = '';
			if (isset($valores_campo['extras'])) {
				$autocomplete = $this->campos_formularios->getDataExtra($valores_campo['extras'], 'autocomplete');
				$data_field = $this->campos_formularios->getDataExtra($valores_campo['extras'], 'data-field');
			}

			$campos_trs .= sprintf(
				'<tr class="%s %s">'.
				  '<th class="color_bg_th" >%s</th>'.
				  '<td><input type="text" id="%s" %s name="%s[datos][producto][%s]" value="%s" %s %s></td>'.
				'</tr>',
				$clase_obligatorio,$clase, $tr_campo,$campo,$data_field,$this->producto,$campo,$valor,$autocomplete, $disabled);
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
			$campos_trs,
			$campos_tr_extra,
			$this->producto,
			$this->id_producto);

		return $html;

	}

	/* HTML campos proveedor*/
	function htmlFormProveedor()
	{
		$p_ops = '';
		$html_proveedores = '';
		
		$campos = $this->campos_formularios->camposProveedor();
		$tr_campos = '';
		foreach( $campos as $campo => $valores_campo ){	
			if( ($campo == 'fecha_pago_proveedor' && $this->id_comercial != 24) || !isset($valores_campo['nombre']) ){
				continue;
			}
				
			$tr_campo_nombre = $valores_campo['nombre'];	
			if (isset($valores_campo[$this->producto]['nombre'])) {
				$tr_campo_nombre = $valores_campo[$this->producto]['nombre'];
			}

			$clase_obligatorio = $this->claseCampoObligatorio('proveedor', $campo);
			$clase = $this->ClassCampoVacio( $campo, 'proveedor' );	
			$valor = $this->getValue('proveedor', $campo);
			
			$valores_campo['nombre_campo'] = $this->producto.'[datos][proveedor]['.$campo.']';
			$valores_campo['id_campo'] = 'datos_nuevos_'.$campo;
			$valores_campo['list_proveedores'] = (isset($valores_campo['proveedores']) ? $this->servicios->getProveedoresByProducto($this->producto) : array()); 
			if (!empty($valores_campo['list_proveedores'])) {
				$this->proveedores = $valores_campo['list_proveedores'];
			}
			if (isset($valores_campo['suma_porcentaje_euro'])) {
				$valores_campo['suma_porcentaje_euro'] = $this->datos_campos_extra_coche['suma_porcentaje_euro'];
			}
			if (isset($valores_campo['divisa'])) {
				$valores_campo['divisa'] = $this->divisa;
			}

			$tr_campos .= '<tr class="'.$clase_obligatorio.' '.$clase.'">';
			$tr_campos .= sprintf('<th class="color_bg_th">%s</th>', $tr_campo_nombre);
			$tr_campos .= sprintf('<td>%s</td>', Field::get($campo, $valor, $valores_campo));
			//$tr_campos .= sprintf('<td>%s</td>', $this->getField($campo, $valor, $valores_campo));
			$tr_campos .= '<tr>';
		}
		
		$tr_campos .= $this->htmlFormProveedorSuplementosCoche();

		$html = sprintf('<div class="bloque_dato"><h4 class="th_datos">Proveedor</h4>'.
			'<table>%s</table></div>', $tr_campos );

		return $html;
	}

	function htmlFormProveedorSuplementosCoche()
	{
		$html = '';

		if ($this->producto_actual == 'coche') {

			$totales_extras = array();
			$this->campos_extra_coches = new CamposExtraCoches($this->divisa);
			$html .= '<tr>';
			$html .= '<th colspan="2"><hr></th>';
			$html .= '</tr>';
			$html .= '<tr>';
			$html .= '<td colspan="2"><h4 class="th_datos">Suplementos coche</h4></td>';
			
			$campos = $this->campos_formularios->camposSuplementosCoche();
			foreach ($campos as $campo => $valores_campo) {
				if ($campo == 'total_extras') {
					continue ;
				}

				$valores_campo['nombre_campo'] = $this->producto.'[datos][proveedor]['.$campo.']';
				$valores_campo['id_campo'] = 'datos_nuevos_'.$campo;

				$clase_obligatorio = $this->claseCampoObligatorio('proveedor', $campo);
				$clase = $this->ClassCampoVacio( $campo, 'proveedor' );	

				$valor = '';
				if (isset($_POST)) { //SI NO HAY POST NO CARGO VALORES
					$valor = $this->calcularValorCampoExtraCoche($this->getValue('proveedor', $campo), $campo);
				}
				if (isset($valores_campo['suma_total'])) {
					$totales_extras[$campo] = $valor;
					if ($campo == 'combustible') {
						$totales_extras[$campo] = $this->getValue('producto', 'cats');
					} 
				}

				$html .= '<tr class="'.$clase_obligatorio.' '.$clase.'">';
				$html .= sprintf('<th class="color_bg_th" >%s</th>', $valores_campo['nombre']);
				$html .= sprintf('<td>%s</td>', Field::get($campo, $valor, $valores_campo));
			}
			$html .= '</tr>';
			
			if (!empty($totales_extras)) {
				$campo = 'total_extras';
				$totales_extras['combustible'] = $this->campos_extra_coches->getCombustible($this->getValue('producto', 'cats'));
				$totales_extras['seguro'] = $this->campos_extra_coches->calculoValorSeguro($totales_extras['seguro'], $this->getValue('producto', 'cantidad_dias'));
				$totales_extras['dropoff'] = $this->campos_extra_coches->calculoValorConIncremento($totales_extras['dropoff']);
				$valor_input = $this->calcularValorCampoExtraCoche($totales_extras, $campo);

				$valores_campo = $this->campos_formularios->infoCampos($campo);
				$valores_campo['nombre_campo'] = $this->producto.'[datos][proveedor]['.$campo.']';
				$valores_campo['id_campo'] = 'datos_nuevos_'.$campo;
				$td = sprintf('<td>%s</td>', Field::get($campo, $valor_input, $valores_campo));
			
				$html .= sprintf('<tr class="%s %s"><th class="color_bg_th">%s</th>%s</tr>',
					$clase_obligatorio, $clase, 'Total Extras', $td );
			}
				
			$html .= '<input type="hidden" name="campos_extra_coche_obligatorios" value="'.$this->getIdProveedorSuplementosObligatorios().'">';
		}
		return $html;
	}
	
	public function InputAddNota()
	{
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
	
	function InfoVenta( $data )
	{
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
	function InputProductoActual() 
	{
	       return ($this->producto_actual != '' ) ? '<input type="hidden" name="tipo_item" value="'.$this->producto_actual.'" >' : '';
	}

	function NavProductos() 
	{
		$producto_actual = $this->producto_actual;
		$li_products = '';
		foreach (getProducts() as $producto) {
			$active = ( isset( $producto_actual ) && $producto_actual == $producto->nombre ) ? "active" : '';
	                $li_products .= "<li class='$active'>".
		                        "<input type='submit' name='crear' value='{$producto->nombre}' >".
		                        "<input type='hidden' name='id_tipo[{$producto->nombre}]' value='{$producto->id}' >".
		                        "</li>";
		}
	        $list_products = sprintf("<ul>%s</ul>", $li_products);

	        return $list_products;
	}	

	function ResumenPrecios( $precios, $tipo )
	{
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
			    '<tr ><th colspan="6" class="th_datos" >Precios '.ucfirst($tipo).' <span class="sumarize_divisa"></span></th>'. 
			         '<th class="color_bg_th width_precios_venta" >Compra</th>'.
			         '<td><input size="1"  type="text" name="'.$nombre_campo.'[compra]" value="%s" readonly ></td>'.
			         '<th class="color_bg_th width_precios_venta">Venta</th>'.
			         '<td><input size="1" type="text" name="'.$nombre_campo.'[venta]" value="%s" readonly ></td>'.
			         '<th class="color_bg_th width_precios_venta">Margen</th>'.
			         '<td><input type="text" size="1" name="'.$nombre_campo.'[margen]" value="%s" readonly ></td>'.
			    '</tr>'.
			  '</table>'.
			'</div>',$compra, $venta, $margen);
	}
	/* devuelve titulo venta: deberia estar en interface.*/
	function TituloVenta($token)
       	{
		return " <h2 class='h2_resumen_venta'>RESUMEN VENTA ".$token."</h2><hr>";
	}


	private function ListClients( $clients )
	{
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

	/*
	 *	llamado desde crearVentas.controller
	 * */
	public function FormDatosCreados($datos)
	{
		if ($datos == '') return '';

		$array = null;
		$html = '';
		$html_prods = null;
		$html_prods_actual = null;
		$datosCreados = new DatosCreados($datos, $this->divisa);
		$datosCreados->setEditando($this->editando);

		foreach( $datos as $tipo_producto => $info ){
			$visible = 0;
			$order = 1;	
			$datosCreados->setProducto($tipo_producto);
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
		$html .= $this->HiddenLocalizadores();

		return $html;
	}

	public function InputSend()
	{
		return '<div class="div_input"><input type="submit" name="send" value="Enviar"></div>';
	}

	/* viene de Producto*/
	public function InputsAsignaciones( $asignaciones)
	{
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
	
	public function ResumenProductos( $datos_productos, $hay_comprobantes )
	{
		$html = '';
		if( !empty($datos_productos) ){
			$html .= '<div class="datos_factura" style="">';
			$html .= '<div class="bloque_dato bloque_datos_creados" >';
			$html .= '<h2 class="h2_resumen_venta">Resumen productos</h2>';
			$html .= "<hr>";
			$html .= "<div class='div_table table_resumen_productos'>";
			foreach( $datos_productos as $tipo_producto => $productos ){
				foreach( $productos as $key2 => $producto ){
					$html .= "<div class='div_table_tr'>";
					$class = ( $tipo_producto != 'devolucion' && $producto['compra'] > $producto['venta'] ) ? 'warning' : 'done' ; 
					$html .= "<span class='div_table_th'>Producto <b>[".ucfirst($tipo_producto)."]</b>->";
					$html .= "&nbsp;".ucfirst($key2)."</span>";
					$html .= "<span class='div_table_td $class'>&nbsp;Compra:</span>";
					$html .= "<span class='div_table_th $class'>&nbsp;".$producto['compra']."&nbsp;|</span>";
					$html .= "<span class='div_table_td $class'>&nbsp;Venta:</span>";
					$html .= "<span class='div_table_th $class'>&nbsp;".$producto['venta']."&nbsp;|</span>";
					$html .= "<span class='div_table_td $class'>&nbsp;Margen:</span>";
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

	function Warnings($wrs)
	{
		$html = '';
		$leyendas = array(
			'campos-vacios' => 'Hay campos vacios obligatorios.',
			'cantidad-comprobante-por-asignar' => 'Cantidad comprobante no asignada.',
			'comprobante-sin-asignar' => 'Comprobantes sin asignar.',
			'fecha-final-incorrecta' => 'Fecha final menor que la fecha inicial.',
			'marcado-reserva' => 'Hay un producto reservado.',
			'margen-negativo'=>'Hay margen negativo.', 
			'producto-incompleto' => 'Producto no facturado completamente.',
			'productos-sin-asignar' => 'Hay algún producto sin facturar.',
			'pendiente-pago-comprobante' => 'Comprobante <b>PENDIENTE DE PAGO</b>, solo podrá ser utilizado con una reserva.',
		);
		if( !empty( $wrs) ){
			$wrs = array_unique($wrs);
			$html .= "<h2 class='h2_resumen_venta'>Alertas</h2>";
			$html .= "<hr>";
			foreach( $wrs as $warn_text ) $html .= "<p>".d8($leyendas[$warn_text])."</p>";
		}
		return $html;
	}
	
	public function Insert( $res_insert )
	{
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
