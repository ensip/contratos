<?php
/* Envio email info Venta*/

class EmailVenta{
	protected $id_comercial;
	protected $data;
	protected $info = '';
	protected $hay_efectivo = '';
	protected $token = '';

	function __construct( $id_comercial )
	{
		$this->id_comercial = $id_comercial;
	}

	function getEmails($tipo_email='') 
	{
		$email = '';
		if (isDevel()) {
			$email = 'diego@jyctel.com';
		} else {
			if ($tipo_email == 'coche') {
				$email = 'admon@jyctel.com';
			} else {
				$email = ( isset($_SESSION['email_ventas']) ? $_SESSION['email_ventas'] : 'admon@jyctel.com' );
				
				if ($this->hay_efectivo != '') 
					$email .= ",administracion@jyctel.com,contabilidad@jyctel.com, auxiliar@jyctel.com,recargas13@jyctel.com,practicas@jyctel.com";
			}
		}
		return $email;
	}

	function getTable()
	{
		return $this->info;
	}

	public function send()
	{
		$headers = "MIME-Version: 1.0" . "\r\n";
		$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
		$headers .= 'From: <ventas@jyctel.com>' . "\r\n";
		$headers .= 'Cc: diego@jyctel.com' . "\r\n";
		
		mail( $this->getEmails(), 'Resumen Venta - '.$this->token . $this->hay_efectivo, $this->info,$headers);
	}

	public function sendEmailCoche($info) 
	{
		$f_venta = $info['producto']['fecha_venta'];
		$f_entrega = $info['producto']['fecha_inicial'];
		$f_recogida = $info['producto']['fecha_final'];
		$c_dias = $info['producto']['cantidad_dias'];
		$loc = $info['producto']['localizador'];
		$telefono = $info['cliente']['telefono'];
		$contacto = $info['cliente']['contacto'];
		$nombre = $info['cliente']['nombre'];

		$token = $this->token;
		//debug($info, $token);

		$info = '<style>
			table{text-align:left;width:fit-content;border:1px solid #03111f;}
		        table tr{ border:1px solid gray;}
		        table td, table th{padding:0px 10px; border: 1px solid gray;}
			</style>';
		$info .= '<table>
			<tbody>
			<tr><td style="text-align: center;" colspan="2"><strong>Info Coche</strong></td></tr>
			<tr><td><strong>Localizador</strong></td><td>'.$loc.'</td></tr>
			<tr><td><strong>Fecha Venta</strong></td><td>'.$f_venta.'</td></tr>
			<tr><td><strong>Recoge Coche</strong></td><td>'.$f_entrega.'</td></tr>
			<tr><td><strong>Entrega coche</strong></td><td>'.$f_recogida.'</td></tr>
			<tr><td><strong>Cantidad d&iacute;as</strong></td><td>'.$c_dias.'</td></tr>
			<tr><td><strong>Telefono</strong></td><td>'.$telefono.'</td></tr>
			<tr><td><strong>Contacto</strong></td><td>'.$contacto.'</td></tr>
			<tr><td><strong>Nombre</strong></td><td>'.$nombre.'</td></tr>
			</tbody>
			</table>';
		$this->info = $info;
	
		$headers = "MIME-Version: 1.0" . "\r\n";
		$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
		$headers .= 'From: <ventas@jyctel.com>' . "\r\n";
		$headers .= 'Cc: diego@jyctel.com' . "\r\n";
		
		mail( $this->getEmails ('coche'), 'Resumen Venta Coche - '.$this->token, $this->info,$headers);
	}

	public function sendEmailTrustPilot($venta, $producto, $asignaciones_coche) 
	{
		$fecha_pago = date('Y-m-d');
		foreach ($producto as $bloque) {
			if (isset($bloque['producto'])) {
				$fecha_pago = $bloque['producto']['fecha_venta'];
			}
			if (isset($bloque['facturacion'])) {
				$cliente = $bloque['facturacion'];
			} else{
				$cliente = $bloque['cliente'];
			}
		}
		
		$cantidad = 0;	
		foreach ($asignaciones_coche as $vals) {
			$divisa = ($vals['divisa'] == 1 ? '&euro;' : '$');
			$cantidad += $vals['cantidad'];
		}	

		$iNombre = explode(' ', $cliente['nombre']);
		$nombre = ucfirst($iNombre[0]);
		$nombre_completo = ucfirst($cliente['nombre']);

		$vars_plantilla = array(
			'[nombre]'=> $nombre,
			'[nombre-completo]'=> $nombre_completo,
			'[fecha-pago]' => $fecha_pago,
			'[precio]' => number_format( $cantidad, 2, ',' , '') . ' ' . $divisa,
		);
		
		$archivo_ori = file_get_contents('/var/www/html/factura/contratos-ventas/plantilla-cubarentcars/index.html');
		$archivo = $archivo_ori;
		foreach ($vars_plantilla as $tag => $value) {

	 	       $archivo = str_replace( $tag, $value, $archivo);
		}

		$email_trustpilot = 'cubarentcars.com+399c370bbc@invite.trustpilot.com';		
	
		$headers = "MIME-Version: 1.0" . "\r\n";
		$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
		$headers .= 'From: <ventas@jyctel.com>' . "\r\n";
		$headers .= 'Bcc: '.$email_trustpilot . "\r\n";

		$subject = 'Confirmacion de pago';
		$email_cliente = $cliente['email'];	
		//$email_cliente = 'thiegui@yahoo.es';	
		
		$res = mail($email_cliente, $subject, $archivo, $headers);

		syslog (LOG_INFO, __FILE__ . ':'.__METHOD__ . ':cantidad: ' . $cantidad);
		syslog (LOG_INFO, __FILE__ . ':'.__METHOD__ . ':' . ($res ? 'sended -- '.$email_cliente:'not-sended ' . $email_cliente));
	}

	function setData( $data ) 
	{
		$hay_efectivo = '';
		$this->data = $data;
		$this->token = $this->data['token_venta'];
		$resumen = $this->data['resumen'];
		$prods = $this->data['productos'];
		$comercial = ucfirst($_SESSION['nombre_ventas']);
	
		$tit1 = 'style="border:1px solid black;"';
		$tit2 = 'style="padding:5px;border-bottom:1px solid;background-color:#d9dcdea8;"';
		$info = "<style>
			table.resumen-venta tr{ border:1px solid gray;}
			table.resumen-venta td, table th{padding:0px 10px;}
			</style>";
		$info .= '<table class="resumen-venta" style="text-align:left;width:fit-content;border:1px solid #03111f;">';
		$info .= "<caption>Resumen Venta ( $comercial )</caption>";	
	
		foreach( $resumen as $nproducto => $datos ){
			$info .= "<tr><th $tit1><br/> >> Producto: ".strtoupper($nproducto)." | {$this->token}<br/><br/></th></tr>";
			foreach( $datos as $id_producto => $producto ){
				$infop = $prods[$nproducto][$id_producto];
				//syslog(LOG_INFO," email prods: ".json_encode($infop));
				$info_prod = $infop['producto'];
				$info_prov = $infop['proveedor'];
				$info_cli = $infop['cliente'];
				$info .= "<tr>";
				$info .= sprintf("<th $tit2 >%s : %s | %s : %s | %s : %s</th>",
					'COMPRA',$producto['compra'],'VENTA',$producto['venta'],'MARGEN',$producto['margen']);
				$info .= "</tr>";
				$info .= sprintf("<tr><th>Voucher : %s</th></tr>", $info_prod['localizador']);
				$info .= sprintf("<tr><th>Reserva : %s</th></tr>", ($info_prod['reserva'] != 0 ? 'SI':'NO'));
				$info .= sprintf("<tr><th>Fecha Venta : %s</th></tr>", $info_prod['fecha_venta']);
				$datapv = getProveedor( $info_prov['sel_proveedor'] );
				$prov = (array)$datapv[0];
				$info .= "<tr><th $tit2 >Proveedor : {$prov['nombre']}</th></tr>";
				$info .= sprintf("<tr><th>Fecha Limite Pago : %s</th></tr>", $info_prov['fecha_limite_proveedor']);
				$info .= sprintf("<tr><th>Nota : %s</th></tr>", $info_prov['nota']);
				$info .= "<tr><th $tit2 >Cliente : {$info_cli['nombre']}</th></tr>";
				$info .= "<tr><th>Telefono : {$info_cli['telefono']} / {$info_cli['contacto']}</th></tr>";
				$info .= "<tr><th>Codigo SAP : {$info_cli['cardcode']}</th></tr>";
				$info .= "<tr><th>Nota : {$info_cli['nota']}</th></tr>";
				$info .= "<tr><th $tit2>Comprobantes</th></tr>";

				foreach( $producto['asignaciones'] as $asignacion ) {

					syslog(LOG_INFO," tipo_ingreso:" . serialize($asignacion));

					$c = $asignacion['comprobante'];
					if(preg_match('/^E-/', $c, $output_array)) {
						$hay_efectivo = '- PAGO CON EFECTIVO!';
					}
					if (isset($asignacion['tipo']) && strtolower($asignacion['tipo']) == 'cuenta') {
						$hay_efectivo = '- PAGO CON INGRESO EN CUENTA!';
					}
					if (preg_match('/^C[0-9]+$/', $c, $match)) {
						$hay_efectivo = '- PAGO CON INGRESO EN CUENTA!';
					}

					$info .= "<tr><th> ".$asignacion['comprobante']." | Cantidad: ".$asignacion['asignado']."</th></tr>";
					$info .= "<tr><th>Texto Comprobante</th></tr>";
					$info .= sprintf( "<tr><th>%s</th></tr>", $asignacion[ 'texto' ] );
				}

			}
		}
		$info .= "</table>";
		syslog(LOG_INFO," hay_efectivo:" . $hay_efectivo);
		$this->hay_efectivo = $hay_efectivo;
		$this->info = $info;
	}

	function setToken($token) 
	{
		$this->token = $token;
	}
}
