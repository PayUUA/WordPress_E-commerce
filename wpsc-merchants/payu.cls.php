<?php
class PayU
{
	static $key, $merchant;
	var $url = "https://secure.payu.ua/order/lu.php";


	function __construct( $merch, $key )
	{
		if ( !isset( $merch ) || !isset( $key )  ) die( "Error merchant settings" );
		self::$merchant = $merch;
		self::$key = $key;
	}

#---------------------------------------------------------------------------------------
# Standart functions
#---------------------------------------------------------------------------------------


	# Generates hmac for a request.
	function md5_hmac($key = null, $data) 
	{	
		if ( $key == null ) $key = self::$key;
  		$b = 64; 	# byte length for md5
 		if (strlen($key) > $b) $key = pack("H*",md5($key));
  
  		$key  = str_pad($key, $b, chr(0x00));
  		$ipad = str_pad('', $b, chr(0x36));
  		$opad = str_pad('', $b, chr(0x5c));
  		$k_ipad = $key ^ $ipad;
  		$k_opad = $key ^ $opad;
  		return md5($k_opad  . pack("H*", md5($k_ipad . $data)));
	}


	function Signature( $data = null ) 
	{		
		if ( $data == null ) $data = $this->data;
		$str = "";
		foreach ( $data as $v ) $str .= $this->convData( $v );
		return $this->md5_hmac( self::$key, $str );
	}

#---------------------------------------------------------------------------------------
# Create request
#---------------------------------------------------------------------------------------

	function update( $arr )
	{
		$this->data['MERCHANT'] = self::$merchant;
		$this->data = array_merge( $this->data, $arr );
		$this->data['ORDER_HASH'] = $this->Signature();
		return $this;
	}

	function debug( $deb = 0 )
	{
		$this->data['TESTORDER'] = ( $deb == 1 ) ? "TRUE" : "FALSE";
		$this->data['DEBUG'] = $deb;
		return $this;
	}


	#Outputs a string for hmac format. For a string like 'aa' it will return '2aa'.
	function convString($string) 
	{
 	 return mb_strlen($string, '8bit') . $string;
	}

	# The same as convString except that it receives
	# an array of strings and returns the string from all values within the array.
	function convArray($array) 
	{
  		$return = '';
  		foreach ($array as $v) $return .= $this->convString( $v );
  		return $return;
	}


	function convData( $val )
	{
		return ( is_array( $val ) ) ? $this->convArray( $val ) : $this->convString( $val );
	}

	# Make inputs for form
	function makeString ( $name, $val )
	{
		$str = "";
		if ( !is_array( $val ) ) return '<input type="hidden" name="'.$name.'" value="'.$val.'">'."\n";
		foreach ($val as $v) $str .= $this->makeString( $name.'[]', $v );
		return $str;
	}



	function getForm( $button = true )
	{	
		$button = ( $button == true ) ? "<input type='submit'>" : ""; 
		
		$form = '<form id="payuForm" method="post" action="'.$this->url.'">';
		foreach ( $this->data as $k => $v ) $form .= $this->makeString( $k, $v );
		return $form . $button. "</form>";
	}


#---------------------------------------------------------------------------------------
# Read answer
#---------------------------------------------------------------------------------------

	function getPostData()
	{
		$this->post = $_POST;
		$array = array( "IPN_PID", "IPN_PNAME", "IPN_DATE", "ORDERSTATUS" );
		foreach ( $array as $name ) if ( !isset( $this->post[ $name ] ) ) die( "$name | Incorrect data" );
		$this->datetime = date("YmdHis");
		return $this;
	}


	function checkHashSignature()
	{	
		$post = &$this->post;
		$hash = $post["HASH"];  
		unset( $post["HASH"] );
		$sign = $this->Signature( $post );
		return ( $hash != $sign ) ? false : true ;
		 
	}

	function createAnswer()
	{	
		$datetime = &$this->datetime;
		$post = &$this->post;
		$data = array(
				   "IPN_PID" => $post[ "IPN_PID" ], 
				   "IPN_PNAME" => $post[ "IPN_PNAME" ], 
				   "IPN_DATE" => $post[ "IPN_DATE" ], 
				   "DATE" => $datetime
					);

		$sign = $this->Signature( $data );
		return "<EPAYMENT>$datetime|$sign</EPAYMENT>";
	}
}
?>