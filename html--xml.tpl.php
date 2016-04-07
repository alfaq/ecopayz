<?php header('Content-Type: text/xml');

function create_ecopayz_type($xml_request){
	if(isset($xml_request)){
		$SVSCustomer = $xml_request->StatusReport->SVSCustomer;
		$SVSTransaction = $xml_request->StatusReport->SVSTransaction;
		$Request = $xml_request->Request;
		$node = new stdClass();
		$node->type = 'ecopayz_info';
		node_object_prepare($node);
		$node->title = $SVSCustomer->LastName.' '.$SVSCustomer->FirstName;
		$node->field_ip[LANGUAGE_NONE][0]['value'] = $SVSCustomer->IP;
		$node->field_postalcode[LANGUAGE_NONE][0]['value'] = $SVSCustomer->PostalCode;
		$node->field_country[LANGUAGE_NONE][0]['value'] = $SVSCustomer->Country;
		$node->field_svscustomeraccount[LANGUAGE_NONE][0]['value'] = $SVSTransaction->SVSCustomerAccount;
		$node->field_processingtime[LANGUAGE_NONE][0]['value'] = $SVSTransaction->ProcessingTime;
		$node->field_batchnumber[LANGUAGE_NONE][0]['value'] = $SVSTransaction->BatchNumber;
		$node->field_id[LANGUAGE_NONE][0]['value'] = $SVSTransaction->Id;
		$node->field_status[LANGUAGE_NONE][0]['value'] = $xml_request->StatusReport->Status;
		$node->field_merchantaccountnumber[LANGUAGE_NONE][0]['value'] = $Request->MerchantAccountNumber;
		$node->field_currency[LANGUAGE_NONE][0]['value'] = $Request->Currency;
		$node->field_amount[LANGUAGE_NONE][0]['value'] = $Request->Amount;
		$node->field_txid[LANGUAGE_NONE][0]['value'] = $Request->TxID;
		$node->language = LANGUAGE_NONE;
		$node->uid = 1;
		$node->status = 0;
		node_save($node);
	}
	
}

$request = '';
if(isset($_POST['XML'])){
	$allowed_tags = array('SVSPurchaseStatusNotificationRequest','StatusReport','StatusDescription','Status','SVSTransaction','SVSCustomerAccount','ProcessingTime','Result','Description');
	
	$request = $_POST['XML'];//clear must
	//$request1 = filter_xss($_POST['XML'], $allowed_tags);
	
	//$xml_name = 'xml_respi.txt';
	//$file = file_save_data($request.'<br />'.$request1,'public://' .$xml_name);

	if(!empty($request)){
		$merchant_pas = variable_get('merchantpassword', '');
		$xml_request = simplexml_load_string($request);
		$checksum = $xml_request->Authentication->Checksum;
		if($checksum){
			$status = $xml_request->StatusReport->Status;
			$xml_pass = str_replace($checksum, $merchant_pas, $request);
			
			create_ecopayz_type($xml_request);
			//$xml_name = 'xml_resp.txt';
			//$file = file_save_data($request,'public://' .$xml_name);

			$my_checksum = md5($xml_pass);
			
			if($status == 4 && $my_checksum == $checksum){
				//4 (TransactionRequires MerchantConfirmation) - Клиент подтвердил платёж на ecoPayz и транзакция была успешно инициирована на стороне ecoPayz
				$response = '<?xml version="1.0" encoding="utf-8"?><SVSPurchaseStatusNotificationResponse><TransactionResult><Description>Description1</Description><Code>123</Code></TransactionResult><Status>Confirmed</Status><Authentication><Checksum>#pass#</Checksum></Authentication></SVSPurchaseStatusNotificationResponse>';
				$xml_pass = str_replace('#pass#', $merchant_pas, $response);
				$md5 = md5($xml_pass);
				$xml_md5 = str_replace('#pass#', $md5, $response);
				print $xml_md5;
				//$xml_name = 'xml_suc1.txt';
				//$file = file_save_data($xml_md5.' '.$checksum. ' '.$my_checksum,'public://' .$xml_name);
			}elseif($status == 5 && $my_checksum == $checksum){
				//5 (TransactionCancelled) - Ранее проведённая транзакция была отменена со стороны ecoPayz
				$response = '<?xml version="1.0" encoding="utf-8"?><SVSPurchaseStatusNotificationResponse><TransactionResult><Description>Description1</Description><Code>123</Code></TransactionResult><Status>Cancelled</Status><Authentication><Checksum>#pass#</Checksum></Authentication></SVSPurchaseStatusNotificationResponse>';
				$xml_pass = str_replace('#pass#', $merchant_pas, $response);
				$md5 = md5($xml_pass);
				$xml_md5 = str_replace('#pass#', $md5, $response);
				print $xml_md5;
			}else{
				//1 (InvalidRequest) - Неверные параметры были присланы на https://secure.ecopayz.com/PrivateArea/WithdrawOnlineTransfer.aspx?
				//2 (DeclinedByCustomer) - Клиент зашёл на платёжную страницу ecoPayz для подтверждение платежа, но отменил его
				//3 (TransactionFailed) - Клиент зашёл на платёжную страницу ecoPayz и подтвердил платёж, но транзакция не прошла
				$response = '<?xml version="1.0" encoding="utf-8"?><SVSPurchaseStatusNotificationResponse><Status>OK</Status><Authentication><Checksum>#pass#</Checksum></Authentication></SVSPurchaseStatusNotificationResponse>';
				$xml_pass = str_replace('#pass#', $merchant_pas, $response);
				$md5 = md5($xml_pass);
				$xml_md5 = str_replace('#pass#', $md5, $response);
				print $xml_md5;
			}
		}
	}
}