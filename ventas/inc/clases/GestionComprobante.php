<?php

class gestionComprobante
{
	protected $cpost = array();
	protected $vpost = array();
	protected $addpost = '';	
	protected $cs_guardados = array();
	protected $list_comprobantes = '';
	protected $comprobante = null;
	protected $sum_importe = 0;
	protected $log = '';
	protected $view_log = 1;
	protected $eliminar_comprobante = '';
	protected $no_comprobar_num_pedido = false;
	public $warning = '';
	
	function __construct($post, $log = 0)
	{
		/* datos del formulario de venta */
		if( isset( $post['set'] ) )$this->setpost = $post['set'];

		/* datos formulario */
		if( isset( $post['comprobante'] ) ){
			/* guardo datos comprobante */
			$this->vpost = $post['comprobante'];
			
			/* boton añadir comprobante */
			if( isset( $post['add_comprobante'] ) ){
				$this->addpost = $post['add_comprobante'];
			
				/* extraigo datos del comprobante si hay texto */
				if ( $this->vpost['input_comprobante'] != '' ){
					$this->comprobante = $this->extractComprobanteData();
					
					if (isset($this->comprobante['importe'])) {
						$this->comprobante['restante'] = $this->comprobante['importe'];
					}
				}
				else{
					$this->comprobante[0] = 'error';
					$this->comprobante['str'] = 'Falta el comprobante';
				}
			}
		}
		if( isset( $post['submit_asignar_new']) ){
			if( isset($post['asignacion_nueva'] ) ){
				$asignacion_nueva = $post['asignacion_nueva'];
				$comprobante = $post['comprobantes'][$asignacion_nueva['id_comprobante']];
				$comprobante['restante'] -= $asignacion_nueva['cantidad'];
				$post['comprobantes'][$asignacion_nueva['id_comprobante']] = $comprobante;
			}
		}
		
		//si edito un producto, restauro cantidad restante comprobante asociado
		//necesito: el producto editado, y las asignaciones relacionadas
		if( isset( $post['editar']) || isset($post['quitar']) || isset($post['del_asig']) ){
			if( isset($post['asignaciones_eliminadas']) ){
				foreach( $post['asignaciones_eliminadas'] as $asig ){
					$comprobante = $asig['comprobante'];
					if( isset($post['comprobantes'][$comprobante]) ){
						if( $asig['op'] == 'equal' ){
							$post['comprobantes'][$comprobante]['restante'] = $asig['cantidad'];
						}
						if( $asig['op'] == '+' ){
							$post['comprobantes'][$comprobante]['restante'] += $asig['cantidad'];
						}
					}
				}
			}
		}

		if( isset( $post['eliminar']) ){
			$this->eliminar_comprobante = key($post['eliminar']);
		}
		/* Obtener listdo comprobantes */
		if (isset($post['comprobantes'])) {

			$this->cs_guardados = $this->getComprobantesGuardados($post['comprobantes']);	
			$this->log .= 'GUARDADOS : ' . count($this->cs_guardados).'<br/>';
		}

		/* si existe boton eliminar comprobante */
			
		$this->addComprobante();
		
		$this->log .= "GUARDADOS + NEW : ".count($this->cs_guardados)."<br/>";

		$this->view_log= $log;
	}
	/*
	 * Devuelve los comprobantes guardados en el POST comprobantes : call: gestionComprobante.php
	 * cs : viene vacio: array() 
	 * retorna array
	 *
	 * */
	function getComprobantesGuardados( $comprobantes_post ){
		
		$this->cpost = $comprobantes_post;

		$del_comprobante = '';
		/* guardo comprobante para no incluir si se ha eliminado */
		if( $this->eliminar_comprobante != '' ){
			$del_comprobante = $this->eliminar_comprobante;
		}	
		$cs = array();
		if( isset( $this->cpost ) && !empty( $this->cpost ) ){
			foreach( $this->cpost as $i => $c ){
				$incluir = true;
				if( $del_comprobante != '' && $i == $del_comprobante ){
					$incluir = false;
				}
				if( $incluir ){
					$cs[$i] = $c;
				}
			}			
		}
		return $cs;
		
	}	
	/* call: this*/	
	function getPostValues(){

		$v = $this->vpost;

		$d['sel_divisa'] = array ();
		$d['nombre_divisa'] = '';
		if( isset( $v['divisa'] ) ){ 
		
			if ( $v['divisa'] == 1 ){ $d['sel_divisa'][1] = 'selected'; $d['sel_divisa'][2] = ''; }
			if ( $v['divisa'] == 2 ){ $d['sel_divisa'][2] = 'selected'; $d['sel_divisa'][1] = ''; }

			$d['nombre_divisa'] = getDataDivisa( $v['divisa'] );
		}
	
		$d['sel_ingreso'] = array ();
		if( isset( $v['ingreso'] ) ){ 
			$d['sel_ingreso'] = array();	
			if ( $v['ingreso'] == 'tpv' ){ $d['sel_ingreso']['tpv'] = 'selected'; }
			if ( $v['ingreso'] == 'cuenta' ){ $d['sel_ingreso']['cuenta'] = 'selected'; }
			if ( $v['ingreso'] == 'efectivo' ){ $d['sel_ingreso']['efectivo'] = 'selected'; }
			if ( $v['ingreso'] == 'financiacion' ){ $d['sel_ingreso']['financiacion'] = 'selected'; }
		}
		
		$d['importe'] = 0;
		if( isset( $v['importe'] ) && $v['importe'] != '' && is_numeric( $v['importe'] ) ){ 

			$d['importe'] = $v['importe']; 
		}

		if( isset( $v['input_comprobante'] ) && $v['input_comprobante'] != '' ) { 

			$d['comprobante'] = $v['input_comprobante'];
		}

		return $d;
	}
	
	public static function getToken($token) {

		$search_token = token::search($token);
		
		$token = '';
		if (!is_null($search_token)) {
			$token = $search_token->get_token();

		}
		return $token;
	}
	/*
	 * Extrae los datos del comprobante call: this
	 * devuelve array con datos_comprobante
	 */
	function extractComprobanteData(){

		$texto_comprobante = $this->vpost['input_comprobante'];
		if( $this->vpost['ingreso'] == 'pago_link' ){
			$texto_comprobante = str_replace( array("\r\n","\n","\r"," "), '', $texto_comprobante );
			$this->vpost['input_comprobante'] = trim( $texto_comprobante );
		}
		$info_comprobante = array(
				'texto' => $this->vpost['input_comprobante'],
				'ingreso' => $this->vpost['ingreso'],
				'divisa' => $this->vpost['divisa']);	
		$_comprobanteData = new ComprobanteData( $info_comprobante );
		if ($this->no_comprobar_num_pedido) {
			$_comprobanteData->set_no_comprobar_num_pedido($this->no_comprobar_num_pedido);
		}

		$datos = $_comprobanteData->getComprobante( $this->vpost );
		if (!empty($_comprobanteData->warning)) {
			$this->warning = $_comprobanteData->warning;
		}
		return $datos;
	}
	/* call:this */
	function getTitulosDatosComprobante( $input ){

		$titulos = array( 'nombre' => 'Nombre' , 'moneda' => 'Moneda', 'num_pedido' => 'Pedido' , 'comprobante' => 'Comprobante', 'importe' => 'Importe');
		
		if( isset ( $titulos[ $input ] ) ) return $titulos[ $input ] ;
		else return null;
	}

	/*
	 * Añade un comprobante nuevo
	 * Entrada: cs:comprobantes guardados, cs_new: datos nuevo comprobante
	 * Comprueba que exste el post add_comprobante y añade los datos obtenidos de la clase getComprobante
	 * Devuelve array comprobantes guardados con el nuevo añadido o el original si no hay nada que añadir
	 * call: this
	 * */
	function addComprobante() 
	{
		$cs_guardados = $this->cs_guardados;
		$cs_new = $this->comprobante;
		//SI HAY UN COMPROBANTE NUEVO SE AÑADE A LOS RECUPERADOS

		if (!empty($cs_new) && $this->addpost != '' && isset($cs_new['num_pedido'])) {

			$this->log .= 'NUEVO COMPROBANTE: '.$cs_new['num_pedido']."<br/>";

			if (!isset($cs_new[0]) && !isset($cs_guardados[$cs_new['num_pedido']])) {

				$this->log .= "ADD:".$cs_new['num_pedido']."<br/>";	

				$cs_new['token'] = self::getToken($cs_new['num_pedido']);

				$cs_guardados[$cs_new['num_pedido']] = $cs_new;				
			}
		}
		$this->cs_guardados = $cs_guardados;
	}

	public function setNoComprobarNumPedido($bool) {
		$this->no_comprobar_num_pedido = $bool;
	}
	/* Div listado comprobantes call: this*/
	function setDivComprobante( $campos, $i ){

		$importe = str_replace(',','.',$campos['importe']);
		$restante = str_replace(',','.',$campos['restante']);
		$comprobante = str_replace(array("\r","\n","\r\n"),"", $campos['comprobante'] );
		$html = '<div class="div_comprobante">';
		$html .= '<table>';
		$html .=  	'<tr>';
		$html .= 	'<td class="color_bg_th th_index">'.$i.'</td>';
		$html .=	'<th class="color_bg_th th_campo">'.$campos['num_pedido'].'</th>';
		$html .= 	'<th class="color_bg_th ">'.$campos['nombre'].'</th>';
		$html .=	'<th class="color_bg_th">Importe: '.$importe.'</th>';
		$html .=	'<th class="color_bg_th">Restante: '.$restante.'</th>';
		$html .=	'<th class="color_bg_th th_divisa">'.$campos['moneda'].'</th>';
		$html .=	'<td class="center">';
		$html .=	'<input type="image" title="Quitar" size="24" src="../../images/delete_new.png" '.
			'name="eliminar['.$campos['num_pedido'].']">';
		$html .= 	'</td>';
		$html .= 	'</tr>';
		$html .= 	'<tr class="mas_datos">';
		$html .= 		'<td colspan="6" class="color_bg_th">'.$comprobante.'</td>';
		$html .= 	'</tr>';
		$html .= "</table>";
		$html .= '</div>';
		
		foreach ( $campos as $campo => $valor ){
			if( $campo == 'comprobante' && $valor == 'all' ){
				$valor = $campos['num_pedido'];
			}
			if( $campo == 'productos_asignados'){
				if( is_array($valor) ){
					foreach( $valor as $i => $asigs ){
						$html .= "<input type='hidden' value='$asigs' ".
							"name='comprobantes[{$campos['num_pedido']}][{$campo}][$i]'>";
					}
				}
			}
			else{
				if( $campo == 'importe' || $campo == 'restante' ){ $valor = str_replace(',','.',$valor);}	
				if( $campo == 'comprobante' ) $valor = str_replace(array("\r","\n","\r\n"),"", $valor );
				$html .= "<input type='hidden' value='$valor' name='comprobantes[{$campos['num_pedido']}][{$campo}]'>";
			}
		}
		
		return $html;

	}
	/* se crea al llamar para crear el listado 
	 * SI HAY GUARDADOS SE CREA EL DIV QUE LOS MUESTRA
	 * call : this
	 *
	 * @returns string
	 * */ 
	function crearListadoComprobantes()
	{
		$html_comprobante = '';	
		if( count( $this->cs_guardados ) > 0 ){
			$html_cs = '';
			$j = 1;
			
			foreach ( $this->cs_guardados as $i => $comprobante ){
				$html_comprobante .= $this->setDivComprobante( $comprobante , $j );
				$j ++;
			}
			$this->list_comprobantes = sprintf('<div class="bloque_dato bloque_datos_creados">'.
			'<h2 class="th_datos">Comprobantes</h2>%s</div>', $html_comprobante );
		}
		
		return $this->list_comprobantes;
	}

	/* 
	 * si hay comprobantes guardados los devuelve
	 * call : Venta.class
	 * */
	function getCsGuardados(){
		$cs = null;
		if ( count( $this->cs_guardados ) > 0 ) {
			$cs = $this->cs_guardados;
		}
		return $cs;
	}
	/* call : Venta.class
	 *
	 * */
	function getComprobanteEliminado(){
		return $this->eliminar_comprobante;
	}
	/* call : cntr.venta*/
	public function getListadoComprobantes(){
		$this->crearListadoComprobantes();
		return $this->list_comprobantes;
	}
	
	/*
	 *  Devuelve error true si el comprobante tiene errores en la posicion [0]
	 *  call : this
	 *  */
	function getErrorComprobanteAdded(){

		return ( isset($this->comprobante[0]) && $this->comprobante[0] == 'error' ) ? 1 : 0;
	}
	/*
	 * call : this
	 * */
	function getDataForm()
	{
		$comprobante = '';	
		$nombre_divisa = '';
		$sel_ingreso = null;
		$importe = 0;
		$sel_divisa = null;
		$html = '';

		/* si hay errores recupero valores para rectificar formulario */
		if ($this->addpost != '' && isset($this->comprobante[0]) && $this->comprobante[0] == 'error') {
			$valores = $this->getPostValues();
			$importe = ( isset($valores['importe']) ) ? $valores['importe'] : 0;
			$comprobante = ( isset($valores['comprobante']) ) ? $valores['comprobante'] : '';
			$sel_divisa = ( isset( $valores[ 'sel_divisa'] ) ) ? $valores[ 'sel_divisa' ] : '';
			$sel_ingreso = ( isset( $valores[ 'sel_ingreso' ]) ) ? $valores[ 'sel_ingreso' ] : '';
			$nombre_divisa = ( isset( $valores[ 'nombre_divisa' ]) ) ? $valores[ 'nombre_divisa' ] : '';

			$html .= "<div id='check_campos' class=''>";
			$html .=	"<p>" . d8($this->comprobante['str']) . "</p>";
			$html .= "</div>";
		}
		$html .= '<div  class = "bloque_dato" >';
		$html .= '<table class="">';
		$html .=	'<tr><th class="th_datos" colspan="2">Comprobante</th></tr>';
		$html .=	'<tr>';
		$html .=		'<th class="color_bg_th">Divisa</th>';
		$html .= 		'<td>';
		$html .=		'<select name="comprobante[divisa]">';

		$divisas = Coleccion::get('divisas');

		foreach ($divisas as $valor => $nombre ){

			$html .=		'<option value="'.$valor.'" '.$sel_divisa[$valor].' >'.$nombre.'</option>';
		}

		$html .= 		'</select>';
		$html .=		'</td>';
		$html .=	'</tr>';
		$html .=	'<tr>';
		$html .=		'<th class="color_bg_th">Tipo Ingreso</th>';
		$html .=		'<td>';
		$html .=		'<select name="comprobante[ingreso]">';

		$ingresos = Coleccion::get('tiposIngresos');

		foreach( $ingresos as $nombre => $valor ) if( $valor != 'crear_dev' ){

			$html .= '<option value="'.$valor.'" '.(isset($sel_ingreso[$valor]) ? $sel_ingreso[$valor] : '' ).' >'.$nombre.'</option>';
		}

		$html .= 		'</select>';
		$html .=		'</td>';
		$html .=	'</tr>';
		$html .=	'<tr>';
		$html .=		'<th class="color_bg_th">Importe</th>';
		$html .=		'<td><input type="text" name="comprobante[importe]" size="5" value="'.$importe.'"><b>'.$nombre_divisa.'</b></td>';
		$html .=	'</tr>';
		$html .=	'<tr>';
		$html .=		'<th class="color_bg_th">Comprobante</th>';
		$html .=		'<td colspan="2">
						<textarea cols="30" rows="4" name="comprobante[input_comprobante]">'.
						$comprobante.
						'</textarea>
					</td>';
		$html .= 	'</tr>';
		$html .=	'<tr>';
		$html .=		'<td colspan="1"><input type="submit" name="add_comprobante" value="Agregar"></td>';
		$html .= 	'</tr>';
		$html .= '</table>';
		$html .= '</div>';
		$html .= '<input type="hidden" name="comprobante[restante]" value="0" >';

		if ( $this->view_log ) print $this->log;

		return $html;
	}

	/* genera Form para crear comprobantes 
	 * call : venta
	 * */
	public function getFormComprobante( $ver_form ) {

		$html = '';
		$error_added = $this->getErrorComprobanteAdded();
		if( $ver_form || $error_added) {	
			$html .= $this->getDataForm();
		}

		return $html;
	}
	/* sum_importe generado en setDivComprobante 
	 * call : venta.class
	 *
	 * */
	function getSumImportes(){
		$sum_importe = 0;
		foreach( $this->cs_guardados as $comprobante ){
			$importe = str_replace(',','.',$comprobante['importe']);
			$sum_importe += $importe;
		}
		$this->sum_importe = $sum_importe;

		return $this->sum_importe;
	}
	/*
	 * call : this
	 *
	 * */
	function addInputDatos( $html, $data_ins ){

		foreach( $data_ins as $nombre => $tipo ){
			$html_ins .= '<option value="'.$nombre.'">Datos '.$tipo.'&nbsp;['.$nombre.']</option>';;
		}	
		$html_add = str_replace( 'No hay datos' , $html_ins, $html );
		return $html_add;
	}
}

