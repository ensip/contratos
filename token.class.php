<?php

class token_parent {
	public $campo_order = '';
	public $class_token = '';
	public $comprobante = '';
	protected $count_match = 0;
	protected $match = '';
	protected $num_pedido = '';
	public $tabla_banco_tokens = '';
	public $tipo_comprobante = '';
	protected $token = '';
	protected $token_original = '';
	protected $user_token = '';

	public function __construct($token, $match) {
		$this->count_match = count($match);
		$this->match = $match;
		$this->token_original = $token;
		$this->token = $token;
		$this->comprobante = $token;
		$this->crear_num_pedido();

		syslog(LOG_INFO, __FILE__.":".__class__.": tipo_comprobante: ".$this->tipo_comprobante." , COMPROBANTE: ".$token . " -> ".$this->num_pedido);
	}
	public function es_match() {
		if (!empty($this->match) && $this->count_match) {
			return true;
		}
		return false;
	}
	public function es_tpv_tk() {
		return (preg_match_all('/TK/m', $this->tipo_comprobante, $matches, PREG_SET_ORDER, 0) ? true : false);
	}
	public function get_campo_order() {
		return $this->campo_order;
	}
	public function get_count_match() {
		return $this->count_match;
	}
	public function get_match() {
		return $this->match;
	}
	public function get_num_pedido() {
		return $this->num_pedido;
	}
	public function get_tabla_banco_tokens() {
		return $this->tabla_banco_tokens;
	}
	public function get_tipo_comprobante() {
		return $this->tipo_comprobante;
	}
	public function get_token() {
		return $this->token;
	}
	public function get_token_original() {
		return $this->token_original;
	}
	public function get_user_token() {
		return $this->user_token;
	}
	protected function set_user_token($user) {
		$this->user_token = trim($user);
	}
}

class token_tki extends token_parent {

	const match_user_token = 1;
	const match_identifier_token = 2;
	const match_token = 3;

	public $campo_order = 'DS_MERCHANT_ORDER';
	public $tabla_banco_tokens = 'paytpv_tokens';
	public $tipo_comprobante = 'TK';

	public function __construct($token, $match) {
		parent::__construct($token, $match);
		$this->class_token = __class__;
	}

	protected function crear_num_pedido() {
	
		$user_token = $this->match[self::match_user_token];
		$this->set_user_token($user_token);
		$tk = $this->match[self::match_identifier_token];
		$token = $this->match[self::match_token];
		$this->token = $token;
		$this->num_pedido = sprintf("%s_%s_%s", $user_token, $tk, $token);
	}
}
class token_tk_cbn extends token_parent {

	const match_user_token = 2;
	const match_identifier_token = 3;
	const match_token = 4;

	public $campo_order = 'DS_MERCHANT_ORDER';
	public $tabla_banco_tokens = 'paytpv_tokens';
	public $tipo_comprobante = 'CBN';

	public function __construct($token, $match) {
		parent::__construct($token, $match);
		$this->class_token = __class__;
	}

	protected function crear_num_pedido() {
	
		$user_token = $this->match[self::match_user_token];
		$this->set_user_token($user_token);
		$tk = $this->match[self::match_identifier_token];
		$token = $this->match[self::match_token];
		$this->token = $token;
		$this->num_pedido = sprintf("CBN:%s_%s_%s", $user_token, $tk, $token);
	}
}
class token_tka extends token_parent {

	const match_user_token = 1;
	const match_identifier_token = 2;
	const match_token = 3;
	
	public $campo_order = 'ORDER_ID';
	public $tabla_banco_tokens = 'gp_tokens';
	public $tipo_comprobante = 'TKA';

	public function __construct($token, $match) {
		parent::__construct($token, $match);
		$this->class_token = __class__;

	}
	protected function crear_num_pedido() {
	
		$user_token = $this->match[self::match_user_token];
		$this->set_user_token($user_token);
		$tk = $this->match[self::match_identifier_token];
		$token = $this->match[self::match_token];
		$this->token = $token;

		$this->num_pedido = sprintf("%s_%s_%s", $user_token, $tk, $token);
	}
}

class token_cbn extends token_parent {

	const match_identifier_token = 1;
	const match_user_token = 2;
	const match_token = 3;
	
	public $campo_order = 'DS_MERCHANT_ORDER';
	public $tipo_comprobante = 'CBN';
	public $tabla_banco_tokens = 'paytpv_tokens';

	public function __construct($token, $match) {
		parent::__construct($token, $match);
		$this->class_token = __class__;
	}

	protected function crear_num_pedido() {
	
		$user_token = $this->match[self::match_user_token];
		$this->set_user_token($user_token);
		$tk = $this->match[self::match_identifier_token];
		$token = $this->match[self::match_token];
		$this->token = $token;
		
		$this->num_pedido = sprintf("%s:%s:%s", $tk, $user_token, $token);
	}
}

class token_cbn_middle extends token_parent {

	const match_identifier_token = 2;
	const match_user_token = 1;
	const match_token = 3;
	
	public $campo_order = 'DS_MERCHANT_ORDER';
	public $tipo_comprobante = 'CBN';
	public $tabla_banco_tokens = 'paytpv_tokens';

	public function __construct($token, $match) {
		parent::__construct($token, $match);
		$this->class_token = __class__;
	}

	protected function crear_num_pedido() {
	
		$user_token = $this->match[self::match_user_token];
		$this->set_user_token($user_token);
		$tk = $this->match[self::match_identifier_token];
		$token = $this->match[self::match_token];
		$this->token = $token;
		
		$this->num_pedido = sprintf("%s:%s:%s", $user_token, $tk, $token);
	}
}
class token_cr extends token_parent {

	const match_identifier_token = 'CR';
	const match_user_token = 1;
	const match_token = 2;
	
	const match_user_token_cr2 = 1;
	const match_token_cr2 = 2;
	const match_random_cr2 = 3;
	
	public $tipo_comprobante = 'CR';
	public $version_comprobante = 'CR';

	public function __construct($token, $match) {
		parent::__construct($token, $match);
		$this->class_token = __class__;
		$this->set_version_cr($token);
		$this->crear_num_pedido();
		
	}
	private function set_version_cr($token) {
		syslog(LOG_INFO, __FILE__.':>>'.$token);
		$pattern = token::getPatternCr('CR2');
		if (preg_match($pattern, $token, $matches)) {
			$this->version_comprobante = 'CR2';
		}
	}

	protected function crear_num_pedido() {
		syslog(LOG_INFO, __FILE__.':>>'.$this->version_comprobante);
		if ($this->version_comprobante == 'CR') {
			$identifier = $this->version_comprobante;
			$user_token = $this->match[self::match_user_token];
			$tk = (isset($this->match[self::match_identifier_token])) ? $this->match[self::match_identifier_token] : '';
			$token = $this->match[self::match_token];
			$this->token = $token;

			$this->num_pedido = sprintf("%s:%s:%s:%s", $identifier,$user_token, $tk, $token);
		}
		if ($this->version_comprobante == 'CR2') {
			$identifier = $this->version_comprobante;
			$user_token = $this->match[self::match_user_token_cr2];
			$token = $this->match[self::match_token_cr2];
			$this->token = $token;
			$random = $this->match[self::match_random_cr2];
			
			$this->num_pedido = sprintf("%s:%s:%s:%s", $identifier, $user_token, $token, $random);

		}
		$this->set_user_token($user_token);
	}
}
class token_cr2_v1 extends token_cr {

	const match_identifier_token = 'CR2';
	const match_user_token_cr2 = 1;
	const match_token_cr2 = 2;
	const match_random_cr2 = 3;
	
	public $tipo_comprobante = 'CR';
	public $version_comprobante = 'CR2';

	public function __construct($token, $match) {
		parent::__construct($token, $match);
	}
	private function set_version_cr($token) {
		return false;
	}

	protected function crear_num_pedido() {
		$identifier = $this->version_comprobante;
		$user_token = $this->match[self::match_user_token_cr2];
		$token = $this->match[self::match_token_cr2];
		$this->token = $token;
		$random = $this->match[self::match_random_cr2];
			
		$this->num_pedido = sprintf("%s_%s_%s_%s", $identifier, $user_token, $token, $random);
		$this->set_user_token($user_token);
	}
}
class token_network extends token_parent {

	const match_identifier_token = 1;
	const match_token = 2;
	
	public $tipo_comprobante = 'NETWORK';

	public function __construct($token, $match) {
		parent::__construct($token, $match);
		$this->class_token = __class__;
	}
	protected function crear_num_pedido() {
	
		$identifier = $this->match[self::match_identifier_token];
		$token = $this->match[self::match_token];
		$this->token = $token;
		
		$this->num_pedido = $identifier . '-' . $token;
		$this->num_pedido = sprintf("%s-%s", $identifier, $token);
	}
}

class token {

	public static function getPatternTki($v = '') {
		$patterns = array(
			'TKI_PRE' => '/([A-Za-z]+[0-9]+)_(TKI?)_([A-Za-z0-9]+)/',
			'TKI_USER' => '/([0-9]+)_(TKI?)_([A-Za-z0-9]+)/',
		);
		return (!empty($v) && isset($patterns[$v]) ? $patterns[$v] : $patterns);
	}
	public static function getPatternTkCbn($v = '') {
		$patterns = array(
			'CBN_TK' => '/^(CBN):([A-Za-z0-9]+)_(TK)_([A-Za-z0-9]+)$/',
			'CBN_TKI' => '/^(CBN):([A-Za-z0-9]+)_(TKI?)_([A-Za-z0-9]+)$/',
		);

		return (!empty($v) && isset($patterns[$v]) ? $patterns[$v] : $patterns);
	}
	public static function getPatternTka($v = '') {
		$patterns = array(
			'TKA_PRE' => '/([A-Za-z]+[0-9]+)_(TKA?)_([A-Za-z0-9]+)/',
			'TKA_USER' => '/([0-9]+)_(TKA?)_([A-Za-z0-9]+)/'
		);

		return (!empty($v) && isset($patterns[$v]) ? $patterns[$v] : $patterns);

	}
	public static function getPatternCbn() {
		$patterns = array('/(CBN):([A-Za-z0-9]+):([A-Za-z0-9]+)/');
		return $patterns;
	}
	public static function getPatternCbnMiddle() {
		$patterns = array('/([A-Za-z0-9]+):(CBN):([A-Za-z0-9]+)/');
		return $patterns;
	}
	public static function getPatternCr($v = '') {
		$patterns = array(
			'CR' => '/CR:([A-Za-z0-9]+):([A-Za-z0-9]+)(:[0-9]*)?$/',
			'CR2' => '/CR2:([A-Za-z0-9]+):([0-9]+):([0-9]+)$/'
		);
		return (!empty($v) && isset($patterns[$v]) ? $patterns[$v] : $patterns);
	}
	public static function getPatternCr2V1($v = '') {
		$patterns = array(
			'CR2' => '/CR2_([A-Za-z0-9]+)_([0-9]+)_([0-9]+)$/'
		);
		return (!empty($v) && isset($patterns[$v]) ? $patterns[$v] : $patterns);
	}
	public static function getPatternNetwork() {
		$patterns = array('/(E)-([A-Za-z0-9]+)/');
		return $patterns;
	}
	private static function getTpvsInfo() {
		$tpvs_info = array (
			0 => array('patterns' => 'getPatternTkCbn', 'token' => 'token_tk_cbn'),
			1 => array('patterns' => 'getPatternTki', 'token' => 'token_tki'),
			2 => array('patterns' => 'getPatternTka', 'token' => 'token_tka'),
			3 => array('patterns' => 'getPatternCbn', 'token' => 'token_cbn'),
			4 => array('patterns' => 'getPatternNetwork', 'token' => 'token_network'),
			5 => array('patterns' => 'getPatternCr', 'token' => 'token_cr'),
			6 => array('patterns' => 'getPatternCbnMiddle', 'token' => 'token_cbn_middle'),
			7 => array('patterns' => 'getPatternCr2V1', 'token' => 'token_cr2_v1'),
		);
		return $tpvs_info;
	}	
	/*
	 *	busca a que pasarela pertenece el token
	 *	
	 * */
	public static function search($token) {
		$tpvs = self::getTpvsInfo();
		foreach ($tpvs as $tpv) {
			$get_patterns = $tpv['patterns'];
			$token_fu = $tpv['token'];
			foreach (self::$get_patterns() as $pattern) {
				if (preg_match($pattern, $token, $match)) {
					syslog(LOG_INFO, __FILE__.':'.__method__.">>$pattern: ".$token);
					return new $token_fu($token, $match);
				}
			}
		}

		return null;
	}
}
/*
$tokens = array(
	//'PRE340000002_TKI_b9763f8bf414236afe269e5054cf32d1', 
	//'CR2:eoja2q:401:1116',
	//'CR:224255293:1662208660:4782',
	//'PRE340000002_TKA_b9763f8bf414236afe269e5054cf32d1', 
	//'CBN:12520:f7b08e929dc0e905e3f4d929ef9e1d5d', 
	//'PRE100002784_TK_bb7fa07eef5ec97e379d16e6a94c5dfa',
	//'F-Asasasasaa99k9k',
	//'E-Asasasasaa99k9k',
);
foreach ($tokens as $token) {
	$search_token = token::search($token);
	print_r($search_token->get_token());
}
 */
