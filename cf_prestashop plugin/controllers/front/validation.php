<?php
class CashfreeValidationModuleFrontController extends ModuleFrontController
{
	public $warning = '';
	public $message = '';
	public function initContent()
	{
		parent::initContent();
	
		$this->context->smarty->assign(array(
			'warning' => $this->warning,
			'message' => $this->message
		));

		$this->setTemplate('module:cashfree/views/templates/front/validation.tpl');
	}

	public function postProcess()
	{
		$merchant_id = Configuration::get('Cashfree_MERCHANT_ID');
		$secret_key = Configuration::get('Cashfree_MERCHANT_KEY');

		$orderId = $_POST["orderId"];
		$orderAmount = $_POST["orderAmount"];
		$referenceId = $_POST["referenceId"];
		$txStatus = $_POST["txStatus"];
		$paymentMode = $_POST["paymentMode"];
		$txMsg = $_POST["txMsg"];
		$txTime = $_POST["txTime"];
		$signature = $_POST["signature"];
		$data = $orderId.$orderAmount.$referenceId.$txStatus.$paymentMode.$txMsg.$txTime;

		$hash_hmac = hash_hmac('sha256', $data, $secret_key, true) ;
		$computedSignature = base64_encode($hash_hmac);
		if ($signature == $computedSignature) {
		   $bool = true;
		 } else {
			 $bool = false;
		}
		$cart = $this->context->cart;
		$cart_id = $cart->id;

		$amount = $cart->getOrderTotal(true,Cart::BOTH);
		$responseMsg1 = $_POST['txMsg'];

		if ($bool == true) {
			// Create an array having all required parameters for status query.
			//$requestParamList = array("MID" => $merchant_id , "ORDERID" => $_POST['ORDERID']);
			
			//$StatusCheckSum = getChecksumFromArray($requestParamList,$secret_key);
				
			//$requestParamList['CHECKSUMHASH'] = $StatusCheckSum;
		
			/*<option value="13" >Awaiting Cash On Delivery validation</option>
			<option value="1" >Awaiting check payment</option>
			<option value="6" >Canceled</option>
			<option value="5" >Delivered</option>
			<option value="18" >Failed</option>
			<option value="19" >Invalid credential</option>
			<option value="12" >On backorder (not paid)</option>
			<option value="9" >On backorder (paid)</option>
			<option value="2" >Payment accepted</option>
			<option value="8" >Payment error</option>
			<option value="15" >Payment Failed</option>
			<option value="16" >Payment Pending</option>
			//<option value="14" >Payment Received</option>
			<option value="17" >Pending</option>
			<option value="3" >Processing in progress</option>
			<option value="7" >Refunded</option>
			<option value="11" >Remote payment accepted</option>
			<option value="4" >Shipped</option>
			<option value="20" >Suspected Fraud</option>*/


			if ($txStatus == "SUCCESS") {
				//$responseParamList = callNewAPI(Configuration::get('Cashfree_STATUS_URL'), $requestParamList);
				//if($responseParamList['STATUS']=='TXN_SUCCESS' && $responseParamList['TXNAMOUNT']==$amount)
				if (true)
				{
					$status_code = "Ok";
					$message= $responseMsg1;
					$responseMsg= $responseMsg1;
					$status = Configuration::get('Cashfree_ID_ORDER_SUCCESS');
				}
				else{
					$responseMsg = "It seems some issue in server to server communication. Kindly connect with administrator.";
					$message = 'Security Error !!';
					$status = Configuration::get('Cashfree_ID_ORDER_FAILED');
					$status_code = "Failed";
				}					
			}
			 else if ($txStatus == "CANCELLED") {
				$responseMsg = "Transaction Cancelled. ";
				$message = $responseMsg1;
				$status = "6";
				$status_code = "Failed";
			} else  {
				$responseMsg = "Transaction Failed. ";
				$message = $responseMsg1;
				$status = Configuration::get('Cashfree_ID_ORDER_FAILED');
				$status_code = "Failed";
			}			
			
		} else {
			$status_code = "Failed";
			$responseMsg = "Security Error ..!";
			$message = $responseMsg1;
			$status = Configuration::get('Cashfree_ID_ORDER_FAILED');

		}
		error_log("CASHFREE ORDER STATE ".Configuration::get('Cashfree_ID_ORDER_SUCCESS'));

		$classmethods = get_class_methods('Configuration');
		foreach ($classmethods as $method_name) {
			error_log("CLASS METHODS ".$method_name);
		}
		/*error_log("Bool ".$bool);
		error_log("TxStatus ". $txStatus);
		error_log("Status_Code". $status_code);
		error_log("responseMsg = ". $responseMsg1);
		error_log("Status ".$status);
		*/
		$customer = new Customer($cart->id_customer);

		$this->module->validateOrder((int)$cart_id,  $status, (float)$amount, "Cashfree", null, null, null, false, $customer->secure_key); //updating order status in order history of Prestashop

		if ($status == Configuration::get('Cashfree_ID_ORDER_SUCCESS')) {
		Tools::redirect('index.php?controller=order-confirmation&id_cart='.(int)$cart->id.'&id_module='.(int)$this->module->id.'&id_order='.$this->module->currentOrder.'&key='.$customer->secure_key);
		} else {
			$this->message = $message;
			$this->warning= $responseMsg;
			$this->is_guest = $customer->is_guest;

			Tools::redirect('index.php');
	}
	}
}
