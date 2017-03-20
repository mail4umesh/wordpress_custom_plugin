<?php

function DDBillWQCPay( $data ) {
	
	$priKey = openssl_get_privatekey($data["private_key"]);
	$pubKey = openssl_get_publickey($data["public_key"]);

	$merchant_name = $data["merchant_name"];
	$merchant_code = $data["merchant_code"];
	$service_type = "wxpay";	
	$interface_version = "V3.0";
	$sign_type = $data["sign_type"];
	$notify_url = $data["notify_url"];		
	$order_no = $data["order_no"];	
	$order_time = $data["order_time"];	
	$order_amount = $data["order_amount"];	
	$product_name = $data["product_name"];	
	
	$product_code = $data["product_code"];	
	$product_desc = $data["product_desc"];	
	$product_num = $data["product_num"];
	$qrcode_path = $data["qrcode_path"];
	
	$extend_param = "";
	if( $data["customer_first_name"] != "" )
		$extend_param .= "customer_first_name^".$data["customer_first_name"]."|";
	if( $data["customer_last_name"] != "" )
		$extend_param .= "customer_last_name^".$data["customer_last_name"]."|";
	$extend_param .= "customer_email^".$data["customer_email"]
		."|customer_phone^".$data["customer_phone"]
		."|customer_country^".$data["customer_country"];
	if( $data["customer_state"] != "" )
		$extend_param .= "|customer_state^".$data["customer_state"];
	$extend_param .= "|customer_city^".$data["customer_city"]
		."|customer_street^".$data["customer_street"]
		."|customer_zip^".$data["customer_zip"]."|";
	if( $data["ship_to_firstname"] != "" )
		$extend_param .= "ship_to_firstname^".$data["ship_to_firstname"]."|";
	if( $data["ship_to_lastname"] != "" )
		$extend_param .= "ship_to_lastname^".$data["ship_to_lastname"]."|";
	$extend_param .= "ship_to_email^".$data["ship_to_email"]
		."|ship_to_phone^".$data["ship_to_phone"]
		."|ship_to_country^".$data["ship_to_country"];
	if( $data["ship_to_state"] != "" )
		$extend_param .= "|ship_to_state^".$data["ship_to_state"];
	$extend_param .= "|ship_to_city^".$data["ship_to_city"]
		."|ship_to_street^".$data["ship_to_street"]
		."|ship_to_zip^".$data["ship_to_zip"];
	if( $data["customer_idNumber"] != "" )
		$extend_param .= "|customer_idNumber^".$data["customer_idNumber"];
	if( $data["customer_name"] != "" )
		$extend_param .= "|customer_name^".$data["customer_name"];
		
	$extra_return_param = $data["extra_return_param"];	
	
	$signStr = "";
	
	if( $extend_param != "" ){
		$signStr = $signStr."extend_param=".$extend_param."&";
	}
	if( $extra_return_param != "" ){
		$signStr = $signStr."extra_return_param=".$extra_return_param."&";
	}
	
	$signStr = $signStr."interface_version=".$interface_version."&";	
	$signStr = $signStr."merchant_code=".$merchant_code."&";	
	$signStr = $signStr."notify_url=".$notify_url."&";		
	$signStr = $signStr."order_amount=".$order_amount."&";		
	$signStr = $signStr."order_no=".$order_no."&";		
	$signStr = $signStr."order_time=".$order_time."&";	

	if( $product_code != "" ){
		$signStr = $signStr."product_code=".$product_code."&";
	}	
	if( $product_desc != "" ){
		$signStr = $signStr."product_desc=".$product_desc."&";
	}
	$signStr = $signStr."product_name=".$product_name."&";
	if( $product_num != "" ){
		$signStr = $signStr."product_num=".$product_num."&";
	}	
	$signStr = $signStr."service_type=".$service_type;
	
	openssl_sign($signStr, $sign_info, $priKey, OPENSSL_ALGO_MD5);
	$sign = base64_encode($sign_info);

	$postdata = array(
		'extend_param' => $extend_param,
		'extra_return_param' => $extra_return_param,
		'product_code' => $product_code,
		'product_desc' => $product_desc,
		'product_num' => $product_num,
		'merchant_code' => $merchant_code,
		'service_type' => $service_type,
		'notify_url' => $notify_url,
		'interface_version' => $interface_version,
		'sign_type' => $sign_type,
		'order_no' => $order_no,
		'sign' => $sign,
		'order_time' => $order_time,
		'order_amount' => $order_amount,
		'product_name' => $product_name,
	);
		
	$ch = curl_init();	
	curl_setopt($ch, CURLOPT_URL, "https://api.dinpay.com/gateway/api/weixin");
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
	curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
	curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postdata));  
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$response = curl_exec($ch);
	$res = simplexml_load_string($response);
	curl_close($ch);
	
	$path = dirname(__FILE__).'/qrcodes/';
	if( !file_exists($path) ){
		@mkdir($path, 0755, true);
	}

	//remove all 3 days old QR Code Images...
	if( $handle = opendir($path) )
	{
		while( false !== ($file = readdir($handle)) )
		{
			if( (time()-filectime($path.$file)) >= 259200 )
			{  
			   if( preg_match('/\.png$/i', $file) )
			   {
				  unlink($path.$file);
			   }
			}
		}
	}

	$return = array();
	$return['status'] = false;
	$return['html'] = '';
	
	$resp_code = $res->response->resp_code;
	if( $resp_code == "SUCCESS" )
	{
		$qrcode = $res->response->trade->qrcode;
		$pic = $qrcode_path.'/qrcode-'.$order_no.'.png';
		$spic = $path.'qrcode-'.$order_no.'.png';
		if( file_exists($spic) )
			@unlink($spic);

		$errorCorrectionLevel = 'L';
		$matrixPointSize = 10;
		QRcode::png($qrcode, $spic, $errorCorrectionLevel, $matrixPointSize, 2);
		$return['html'] = '<center><div class="merchant_name">'.__('Payee').': '.$merchant_name.'</div><div class="order_no">'.__('Order ID').': '.$order_no.'</div><div class="order_amount">'.__('Amount').': ¥'.$order_amount.'</div><br />'.__('Scan QR Code to Pay').'<br /><img src="'.$pic.'?v='.time().'"></center>';
		$return['status'] = true;
	}
	else
	{ 
		$return['html'] = __('Code').': '.$res->response->resp_code.'<br />'.__('Description').': '.$res->response->resp_desc;
		$return['status'] = false;
	}
	
	return $return;
}

function DDBillWQCPayNotify( $data ) {

	$priKey = openssl_get_privatekey($data["private_key"]);
	$pubKey = openssl_get_publickey($data["public_key"]);

	$merchant_code	= $data["merchant_code"];	
	$notify_type = $data["notify_type"];
	$notify_id = $data["notify_id"];
	$interface_version = $data["interface_version"];
	$sign_type = $data["sign_type"];
	$dinpaySign = base64_decode($data["sign"]);
	$order_no = $data["order_no"];
	$order_time = $data["order_time"];	
	$order_amount = $data["order_amount"];
	$extra_return_param = $data["extra_return_param"];
	$trade_no = $data["trade_no"];
	$trade_time = $data["trade_time"];
	$trade_status = $data["trade_status"];
	$bank_seq_no = $data["bank_seq_no"];
	
	$signStr = "";
	if($bank_seq_no != ""){
		$signStr = $signStr."bank_seq_no=".$bank_seq_no."&";
	}
	if($extra_return_param != ""){
		$signStr = $signStr."extra_return_param=".$extra_return_param."&";
	}	
	$signStr = $signStr."interface_version=".$interface_version."&";	
	$signStr = $signStr."merchant_code=".$merchant_code."&";
	$signStr = $signStr."notify_id=".$notify_id."&";
	$signStr = $signStr."notify_type=".$notify_type."&";
    $signStr = $signStr."order_amount=".$order_amount."&";	
    $signStr = $signStr."order_no=".$order_no."&";	
    $signStr = $signStr."order_time=".$order_time."&";	
    $signStr = $signStr."trade_no=".$trade_no."&";	
	$signStr = $signStr."trade_status=".$trade_status."&";
	$signStr = $signStr."trade_time=".$trade_time;
	
	$flag = openssl_verify($signStr, $dinpaySign, $pubKey, OPENSSL_ALGO_MD5);	
	if( $flag ){
		return true;
	} else {
		return false;
	}
}

function DDBillWQCCustomerIDCheck( $data ) {

	$priKey = openssl_get_privatekey($data['private_key']);
	$pubKey = openssl_get_publickey($data['public_key']);

	$merchant_code = $data['merchant_code'];
	$sign_type = $data['sign_type'];
	$service_type = "identity_check";
	$interface_version = "V3.1";
	$merchant_serial_no = time();
	$id_no = $data['customer_id'];
	$real_name = $data['customer_name'];;

	$signStr = "";
	$signStr = $signStr."id_no=".$id_no."&";	
	$signStr = $signStr."interface_version=".$interface_version."&";	
	$signStr = $signStr."merchant_code=".$merchant_code."&";	
	$signStr = $signStr."merchant_serial_no=".$merchant_serial_no."&";		
	$signStr = $signStr."real_name=".$real_name."&";		
	$signStr = $signStr."service_type=".$service_type;	

	openssl_sign($signStr, $sign_info, $priKey, OPENSSL_ALGO_MD5);
	$sign = base64_encode($sign_info);
	
	$postdata = array(
		'sign' => $sign,
		'merchant_code' => $merchant_code,
		'merchant_serial_no' => $merchant_serial_no,
		'sign_type' => $sign_type,
		'real_name' => $real_name,
		'service_type' => $service_type,
		'interface_version' => $interface_version,
		'id_no' => $id_no,
	);	

	$ch = curl_init();	
	curl_setopt($ch, CURLOPT_URL, "https://identiy.dinpay.com/IdentityCheck");
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_HEADER, false);
	curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postdata));  
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$response = curl_exec($ch);
	$res = simplexml_load_string($response);
	curl_close($ch);

	$status = isset($res->response->status) ? (int)$res->response->status : 1;
	$information = isset($res->response->information) ? (string)$res->response->information : __('Error on Customer Identity checking', 'woocommerce-ddbillwqc');

	if( $status == 0 || $status == 2 )
		return true;
	else
		return $information;
}
