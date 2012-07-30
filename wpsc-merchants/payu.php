<?php
#--------------------------------------
# Payment module 
# For plugin : E-commerce
# Payment system : PayU
# Cards : Visa, Mastercard, Webmoney etc.
# 
# Ver 1.0
#--------------------------------------

$nzshpcrt_gateways[$num]['name'] = 'PayU';
$nzshpcrt_gateways[$num]['internalname'] = 'payu';
$nzshpcrt_gateways[$num]['function'] = 'gateway_payu';
$nzshpcrt_gateways[$num]['form'] = "form_payu";
$nzshpcrt_gateways[$num]['submit_function'] = "submit_payu";
$nzshpcrt_gateways[$num]['payment_type'] = "credit_card";
$nzshpcrt_gateways[$num]['display_name'] = 'Credit Card';
#$nzshpcrt_gateways[$num]['image'] = WPSC_URL . '/images/cc.gif';

function gateway_payu($separator, $sessionid)
{
	global $wpdb;

	$purchase_log_sql = "SELECT * FROM `".WPSC_TABLE_PURCHASE_LOGS."` WHERE `sessionid`= ".$sessionid." LIMIT 1";
	$purchase_log = $wpdb->get_results($purchase_log_sql,ARRAY_A) ;

	$cart_sql = "SELECT * FROM `".WPSC_TABLE_CART_CONTENTS."` WHERE `purchaseid`='".$purchase_log[0]['id']."'";
	$cart = $wpdb->get_results($cart_sql,ARRAY_A) ;

	// Recive payu settings
	$payu_url = get_option('payu_lu_url');

	$data['merchant'] = get_option('payu_merchant');
	$data['secret_key'] = get_option('payu_secret_key');
	$data['price_currency'] = get_option('payu_price_currency');
	$data['language'] =  get_option('payu_language');
	$data['VAT'] =  get_option('payu_VAT');
	$data['debug'] =  get_option('payu_debug_mode');
	$data['backref'] = ( get_option('payu_back_ref') != "" ) ? get_option('payu_back_ref') :  false;

	// User details
    $cData = $_POST[ 'collected_data' ];
    $keys = "'" . implode( "', '",array_keys( $cData ) ) . "'"; 

    $que = "SELECT `id`,`type`, `unique_name` FROM `".WPSC_TABLE_CHECKOUT_FORMS."` WHERE `id` IN ( ". $keys ." ) AND `active` = '1'";
    $dat_name = $wpdb->get_results( $que, ARRAY_A );

    foreach ( $dat_name  as $v )
    {
    	$billData[ $v['unique_name'] ] = $v['id'];
    }

    $array = array( 
					"BILL_FNAME" => 'billingfirstname', 
					"BILL_LNAME" => 'billinglastname', 
					"BILL_ADDRESS" => 'billingaddress', 
					"BILL_CITY" => 'billingcity',
					"BILL_PHONE" => 'billingphone',
					"BILL_EMAIL" => 'billingemail',
					"BILL_COUNTRYCODE" => 'billingcountry',
					"BILL_ZIPCODE" => 'billingpostcode',
					#-----Shiping-----
					"DELIVERY_FNAME" => 'shippingfirstname', 
					"DELIVERY_LNAME" => 'shippinglastname', 
					"DELIVERY_ADDRESS" => 'shippingaddress', 
					"DELIVERY_CITY" => 'shippingcity',
					"DELIVERY_COUNTRYCODE" => 'shippingcountry',
					"DELIVERY_ZIPCODE" => 'shippingpostcode'
				  ); 


	foreach ( $array as $k => $val )
	{	
		$val = $billData[ $val ];
		if ( $_POST[ 'collected_data' ][ $val ] )
		{ 
			$val = $_POST[ 'collected_data' ][ $val ];

			$val = trim( $val );
			$Billings[ $k ] = str_replace( "\n", ', ', $val );
		}
	}

  	if( ( $_POST['collected_data'][get_option('email_form_field')] != null) && ($Billings['BILL_EMAIL'] == null) )
    {
    	$Billings['BILL_EMAIL'] = $_POST['collected_data'][get_option('email_form_field')];
    }

	foreach ( $cart as $item )
	{
		$d['ORDER_PNAME'][] = $item['name']; # Array with data of goods
		$d['ORDER_QTY'][] = $item['quantity']; # Array with data of counts of each goods 
		$d['ORDER_PRICE'][] = $item['price']; # Array with prices of goods
		$d['ORDER_VAT'][] = $data['VAT'];# Array with VAT of each goods  => from settings
		$d['ORDER_SHIPPING'][] = 0;# Shipping cost
		$d['ORDER_PCODE'][] = "testgoods_".$item['id']; # Array with codes of goods
		$d['ORDER_PINFO'][] = ""; # Array with additional data of goods
	}


	$PayU = new PayU( $data['merchant'], $data['secret_key'] );
	$orderID = $_SERVER["HTTP_HOST"].'_' . $sessionid . '_' . md5( "payu_".time() );

	$forSend = array (
					'ORDER_REF' => $orderID, # Uniqe order 
					'ORDER_DATE' => date("Y-m-d H:i:s"), # Date of paying ( Y-m-d H:i:s ) 
					'ORDER_PNAME' => $d['ORDER_PNAME'], #array( "Test_goods" ), # Array with data of goods
					'ORDER_PCODE' => $d['ORDER_PCODE'], #array( "testgoods1" ), # Array with codes of goods
					'ORDER_PINFO' => $d['ORDER_PINFO'], #array( "" ), # Array with additional data of goods
					'ORDER_PRICE' => $d['ORDER_PRICE'], #array( $data['product_price'] ), # Array with prices of goods
					'ORDER_QTY' => $d['ORDER_QTY'], #array( 1 ), # Array with data of counts of each goods 
					'ORDER_VAT' => $d['ORDER_VAT'], #array( 0 ), # Array with VAT of each goods
					'ORDER_SHIPPING' => $d['ORDER_SHIPPING'], #array( 0.1 ), # Shipping cost
					'PRICES_CURRENCY' => $data['price_currency']  #"UAH"  # Currency
				  );

	$PayU->update( $forSend )->debug( $data['debug'] );
	
	if ( $payu_url != "" ) $PayU->url = $payu_url;

	$PayU->data['LANGUAGE']  = $data['language'];
	$PayU->data = array_merge( $PayU->data, $Billings );


	if ( $data['backref'] != false ) $PayU->data['BACK_REF'] = $data['backref'];
	
	$form = $PayU->getForm( false );

	echo $form;
	$img = WPSC_URL . '/images/payuloader.gif';

	?>
	<img style="position:absolute; top:50%; left:47%; margin-top:-125px; margin-left:-60px;" src="<?= $img ?>" >
	<script>
		function submitPayUForm()
		{
			document.getElementById('payuForm').submit();
		}
		setTimeout( submitPayUForm, 2000 );
	</script>

<? 
#----------------------------------------------------------------

	$data = array(
				'processed'  => 2,
				'transactid' => $orderID,
				'date'       => time()
				);

	$where = array( 'sessionid' => $sessionid );
	$format = array( '%d', '%s', '%s' );
	$wpdb->update( WPSC_TABLE_PURCHASE_LOGS, $data, $where, $format );
	transaction_results($sessionid, false, $orderID);


#------------------------------------------------------------------------------------------------------------
  	exit();
}

function nzshpcrt_payu_callback()
{
	#Callback url : http://yoursite.com/?payu_callback=true
	global $wpdb;
	if( !isset($_GET['payu_callback']) || ($_GET['payu_callback'] != 'true') ) return;

	$PayU = new PayU( get_option('payu_merchant'), get_option('payu_secret_key') );

	$check = $PayU->getPostData()->checkHashSignature();

	if ( !$check )  die( "Incorrect signature" );
	

	$answer = $PayU->createAnswer();

	$ref = $_POST['REFNOEXT'];

	$sessID = explode( "_", $ref);
	$sessionid = $sessID[1];

	switch ( $_POST['ORDERSTATUS'] ) 
	{
		case "TEST" : {  $processed = 3; break; }
		case "ORDER_AUTHORIZED" : { $processed = 4; break; }
		case "COMPLETE" : { $processed = 3; break; }
		case "REVERSED" : { $processed = 5; break; }
		case "REFUND" : { $processed = 5; break; }

	}
	
	$notes = "Ответ системы : " . $_POST['ORDERSTATUS'] . " \n\n Система оплаты : " . $_POST['PAYMETHOD'];

	$data = array(
					'processed'  => $processed,
					'transactid' => $ref,
					'date'       => time(),
					'notes'		 => $notes
				);

	
	$where = array( 'sessionid' => $sessionid );
	$format = array( '%d', '%s', '%s', '%s' );
	$wpdb->update( WPSC_TABLE_PURCHASE_LOGS, $data, $where, $format );
	transaction_results($sessionid, false, $ref);

	die( $answer );
}

function nzshpcrt_payu_results()
{
	return true;
}

function submit_payu()
{	

	$array = array(
					'payu_merchant',
					'payu_secret_key',
					'payu_lu_url',
					'payu_price_currency',
					'payu_debug_mode',
					'payu_back_ref',
					'payu_language',
					'payu_VAT'
				  );


	foreach ( $array as $val )
	{
		if( isset( $_POST[ $val ] ) ) update_option( $val, $_POST[ $val ]);
    }

	return true;
}


function form_payu()
{
	
	
	$blLang = ( get_bloginfo( 'language', "Display" ) !== "ru-RU" ) ? "en-US" : "ru-RU";
	$Cells = getCells();

	$otp = "";
	foreach ( $Cells as $key => $val )
	{
		$dat = $val[ $blLang ];
		$otp .= "<div><label>$dat[name]</label>".
				(( !$val['isInput'] ) ? $val['code'] : "<input type='text' size='40' value='".get_option( $key )."' name='$key' />").
				"<div class='subtext'>".( ( $dat['subText'] == "" ) ? "&nbsp;" : $dat['subText']  )."</div>
				</div>";
	}


	$output = "<style>
		#payuoptions label{ width:100px; font-weight:bold; display: inline-block; }
		#payuoptions .subtext{ margin-left:110px; font-size:8px; font-style:italic; }
		#payuoptions{ border:1px dotted #aeaeae; padding:5px; }
		</style>
		<div id='payuoptions'>$otp</div>";
	return $output;
}
  
function getCells()
{
	$debug[ get_option('payu_debug_mode') ] = 
	$payu_lang[ get_option('payu_language') ] = "selected='selected'";
	return array( 
				'payu_merchant' => array(  
										"en-US" => array(
													'name' => 'Merchant',
													'subText' => 'Your merchant ID at PayU'
													),
										"ru-RU" => array(
													'name' => 'Мерчант',
													'subText' => 'Ваш идентификатор мерчанта в PayU'
													),
										'isInput' => true,
										'code' => ""
										),
				'payu_secret_key' => array(  
										"en-US" => array(
													'name' => 'Secret key',
													'subText' => ''
													),
										"ru-RU" => array(
													'name' => 'Секретный ключ',
													'subText' => ''
													),
										'isInput' => true,
										'code' => ""
										),
				'payu_lu_url' => array(  
										"en-US" => array(
													'name' => 'System url',
													'subText' => 'URL for LU(Live Update) <br> Default url - https://secure.payu.ua/order/lu.php'
													),
										"ru-RU" => array(
													'name' => 'Ссылка на PayU (Live Update)',
													'subText' => 'По умолчанию - https://secure.payu.ua/order/lu.php'
													),
										'isInput' => true,
										'code' => ""
										),
				'payu_price_currency' => array(  
										"en-US" => array(
													'name' => 'Currency of payment',
													'subText' => 'Default currency - UAH'
													),
										"ru-RU" => array(
													'name' => 'Валюта платежей',
													'subText' => 'По умолчанию - UAH. <br> Валюта должна совпадать с валютой мерчанта.'
													),
										'isInput' => true,
										'code' => ""
										),
				'payu_VAT' => array(  
										"en-US" => array(
													'name' => 'VAT',
													'subText' => 'By default = 0'
													),
										"ru-RU" => array(
													'name' => 'НДС',
													'subText' => 'По умолчанию = 0'
													),
										'isInput' => true,
										'code' => ""
										),
				'payu_debug_mode' => array(  
										"en-US" => array(
													'name' => 'Debug mode',
													'subText' => 'Will use testing mode'
													),
										"ru-RU" => array(
													'name' => 'Режим отладки',
													'subText' => 'Будет использован тестовый режим'
													),
										'isInput' => false,
										'code' => "<select name='payu_debug_mode'><option value=''> -- </option><option ".$debug[1]." value='1'>On</option><option ".$debug[0]." value='0'>Off</option></select>"
										),
				'payu_back_ref' => array(  
										"en-US" => array(
													'name' => 'Back refference',
													'subText' => 'If you need return client on specific page, change this cell'
													),
										"ru-RU" => array(
													'name' => 'Ссылка возврата',
													'subText' => 'Ссылка, на которую вернется клиент после оплаты'
													),
										'isInput' => true,
										'code' => ""
										),
				'payu_language' => array(  
										"en-US" => array(
													'name' => 'Language of payment page',
													'subText' => ''
													),
										"ru-RU" => array(
													'name' => 'Язык на странице PayU',
													'subText' => ''
													),
										'isInput' => false,
										'code' => "<select name='payu_language'><option value=''> -- </option><option ".$payu_lang['RU']." value='RU'>Russian</option>".
													"<option ".$payu_lang['EN']." value='EN'>English</option><option ".$payu_lang['RO']." value='RO'>Romanian</option>".
													"<option ".$payu_lang['DE']." value='DE'>Deutch</option><option ".$payu_lang['FR']." value='FR'>French</option>".
													"<option ".$payu_lang['IT']." value='IT'>Italian</option><option ".$payu_lang['ES']." value='ES'>Espanian</option></select>"
										),								
	 			);
}
  
add_action('init', 'nzshpcrt_payu_callback');
add_action('init', 'nzshpcrt_payu_results');
	
?>