<?php

function DDBillCCPay( $data ) {

	$priKey = openssl_get_privatekey($data["private_key"]);
	$pubKey = openssl_get_publickey($data["public_key"]);

	$input_charset = "UTF-8";		
	$interface_version = "V3.0";
	$sign_type = $data["sign_type"];
	$service_type = "credit_pay";

	$merchant_code = $data["merchant_code"];
	$notify_url = $data["notify_url"];
	$currency = $data["currency"];
	$card_type = $data["card_type"];
	$order_amount = $data["order_amount"];
	$order_no = $data["order_no"];
	$order_time = $data["order_time"];
	$product_code = $data["product_code"];
	$product_desc = $data["product_desc"];
	$product_name = $data["product_name"];
	$product_num = $data["product_num"];
	$return_url = $data["return_url"];
	$show_url = $data["show_url"];

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
	$client_ip = $data["client_ip"];

	if( $product_name != "" ){
		$product_name = mb_convert_encoding($product_name, "UTF-8", "UTF-8");
	}
	if( $product_desc != "" ){
		$product_desc = mb_convert_encoding($product_desc, "UTF-8", "UTF-8");
	}
	if( $extend_param != "" ){
		$extend_param = mb_convert_encoding($extend_param, "UTF-8", "UTF-8");
	}
	if( $extra_return_param != "" ){
		$extra_return_param = mb_convert_encoding($extra_return_param, "UTF-8", "UTF-8");
	}
	if( $product_code != "" ){
		$product_code = mb_convert_encoding($product_code, "UTF-8", "UTF-8");
	}
	if( $notify_url != "" ){
		$notify_url = mb_convert_encoding($notify_url, "UTF-8", "UTF-8");
	}
	if( $return_url != "" ){
		$return_url = mb_convert_encoding($return_url, "UTF-8", "UTF-8");
	}
	if( $show_url != "" ){
		$show_url = mb_convert_encoding($show_url, "UTF-8", "UTF-8");
	}

	$signSrc = "";
	if( $card_type != "" ){
		$signSrc = $signSrc."card_type=".$card_type."&";
	}
	if( $client_ip != "" ){
		$signSrc = $signSrc."client_ip=".$client_ip."&";
	}
	if( $currency != "" ){
		$signSrc = $signSrc."currency=".$currency."&";
	}
	if( $extend_param != "" ){
		$signSrc = $signSrc."extend_param=".$extend_param."&";
	}
	if( $extra_return_param != "" ){
		$signSrc = $signSrc."extra_return_param=".$extra_return_param."&";
	}
	if( $input_charset != "" ){
		$signSrc = $signSrc."input_charset=".$input_charset."&";
	}
	if( $interface_version != "" ){
		$signSrc = $signSrc."interface_version=".$interface_version."&";
	}
	if( $merchant_code != "" ) {
		$signSrc = $signSrc."merchant_code=".$merchant_code."&";
	}
	if( $notify_url != "" ){
		$signSrc = $signSrc."notify_url=".$notify_url."&";
	}
	if( $order_amount != "" ) {
		$signSrc = $signSrc."order_amount=".$order_amount."&";
	}
	if( $order_no != "" ){
		$signSrc = $signSrc."order_no=".$order_no."&";
	}
	if( $order_time != "" ) {
		$signSrc = $signSrc."order_time=".$order_time."&";
	}
	if( $product_code != "" ){
		$signSrc = $signSrc."product_code=".$product_code."&";
	}
	if( $product_desc != "" ){
		$signSrc = $signSrc."product_desc=".$product_desc."&";
	}
	if( $product_name != "" ){
		$signSrc = $signSrc."product_name=".$product_name."&";
	}
	if( $product_num != "" ){
		$signSrc = $signSrc."product_num=".$product_num."&";
	}
	if( $return_url != "" ){
		$signSrc = $signSrc."return_url=".$return_url."&";
	}
	if( $service_type != "" ){
		$signSrc = $signSrc."service_type=".$service_type;
	}
	if( $show_url != "" ){
		$signSrc = $signSrc."&show_url=".$show_url;
	}
	
	openssl_sign($signSrc, $sign_info, $priKey, OPENSSL_ALGO_MD5);
	$sign = base64_encode($sign_info);
?>
<form name="dinpayForm" id="dinpayForm" method="post" action="https://ipay.dinpay.com/gateway?input_charset=UTF-8">
        <input type="hidden" name="sign" value="<?php echo $sign; ?>" />
        <input type="hidden" name="card_type" value="<?php echo $card_type; ?>" />
        <input type="hidden" name="merchant_code" value="<?php echo $merchant_code; ?>" />
        <input type="hidden" name="currency" value="<?php echo $currency; ?>" />
        <input type="hidden" name="order_no" value="<?php echo $order_no; ?>" />
        <input type="hidden" name="order_amount" value="<?php echo $order_amount; ?>" />
        <input type="hidden" name="service_type" value="<?php echo $service_type; ?>" />
        <input type="hidden" name="input_charset" value="<?php echo $input_charset; ?>" />
        <input type="hidden" name="notify_url" value="<?php echo $notify_url; ?>" />
        <input type="hidden" name="interface_version" value="<?php echo $interface_version; ?>" />
        <input type="hidden" name="sign_type" value="<?php echo $sign_type; ?>" />
        <input type="hidden" name="order_time" value="<?php echo $order_time; ?>" />
        <input type="hidden" name="product_name" value="<?php echo $product_name; ?>" />
        <input type="hidden" name="client_ip" value="<?php echo $client_ip; ?>" />
        <input type="hidden" name="extend_param" value="<?php echo $extend_param; ?>" />
        <input type="hidden" name="extra_return_param" value="<?php echo $extra_return_param; ?>" />
        <input type="hidden" name="product_code" value="<?php echo $product_code; ?>" />
        <input type="hidden" name="product_desc" value="<?php echo $product_desc; ?>" />
        <input type="hidden" name="product_num" value="<?php echo $product_num; ?>" />
        <input type="hidden" name="return_url" value="<?php echo $return_url; ?>" />
        <input type="hidden" name="show_url" value="<?php echo $show_url; ?>" />
		<input type="submit" name="Submit" value="<?php echo $data['order_button_text']; ?>" />
</form>
<?php 
}

function DDBillCCPayNotify( $data ) {

	$priKey = openssl_get_privatekey($data["private_key"]);
	$pubKey = openssl_get_publickey($data["public_key"]);

	$merchant_code = $data["merchant_code"];
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
	if( $bank_seq_no != "" ){
		$signStr = $signStr."bank_seq_no=".$bank_seq_no."&";
	}
	if( $extra_return_param != "" ) {
	    $signStr = $signStr."extra_return_param=".$extra_return_param."&";
	}
	$signStr = $signStr."interface_version=V3.0&";
	$signStr = $signStr."merchant_code=".$merchant_code."&";
	if( $notify_id != "" ){
	    $signStr = $signStr."notify_id=".$notify_id."&notify_type=".$notify_type."&";
	}
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

function DDBillCCCustomerIDCheck( $data ) {

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
	$information = isset($res->response->information) ? (string)$res->response->information : __('Error on Customer Identity checking', 'woocommerce-ddbillcc');

	if( $status == 0 || $status == 2 )
		return true;
	else
		return $information;
}
