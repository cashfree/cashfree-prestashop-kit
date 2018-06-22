<?php

class CashfreePaymentModuleFrontController extends ModuleFrontController {
	public $ssl = true;
	
	public function init() {
		parent::init();
	}
	
	public function initContent() {
		parent::initContent();
		
		global $smarty, $cart;

		$bill_address = new Address(intval($cart->id_address_invoice));
		$customer = new Customer(intval($cart->id_customer));

		if (!Validate::isLoadedObject($bill_address) OR ! Validate::isLoadedObject($customer))
			return $this->l("Cashfree error: (invalid address or customer)");
		$secretKey = "secret_key";
		$time = strtotime("now");
		
		$order_id = intval($cart->id);
		$customerName = strval($customer->firstname.' '.$customer->lastname);
		$customerPhone = "0000000000"; //not sending data. 
		$customerEmail = strval($customer->email);
		//$returnUrl = redirect('index.php?controller=order-confirmation&id_cart='.(int)$cart->id.'&id_module='.(int)$this->module->id.'&id_order='.$this->module->currentOrder.'&key='.$customer->secure_key);		
		//$notifyUrl = redirect('index.php?controller=order-confirmation&id_cart='.(int)$cart->id.'&id_module='.(int)$this->module->id.'&id_order='.$this->module->currentOrder.'&key='.$customer->secure_key);		
		
		$returnUrl = "http://localhost:8888/en/module/cashfree/validation"; //allows redirection to the Cashfree page
		$notifyUrl = "http://localhost:8888/en/module/cashfree/validation"; //allows redirection to the Cashfree page 

		
		// $order_id = "RHL_" . strtotime("now") . "__" . $order_id; // just for testing

		$amount = $cart->getOrderTotal(true, Cart::BOTH);
		$orderCurrency = "INR";
		$orderNote = "PrestaShop order";

		$appId = Configuration::get("Cashfree_MERCHANT_ID");
		
		$secretKey = Configuration::get("Cashfree_MERCHANT_KEY");
		
		$secretKey = "2279c0ffb9550ad0f9e0652741c8d06a49409517";
		
		$postData = array( 
		"appId" => $appId, 
		"orderId" => $order_id, 
		"orderAmount" => $amount,
		"orderCurrency" => $orderCurrency, 
		"customerName" => $customerName, 
		"customerPhone" => $customerPhone, 
		"customerEmail" => $customerEmail,
		"returnUrl" => $returnUrl, 
		);	

		ksort($postData);
		$signatureData = "";
		foreach ($postData as $key => $value){
			 $signatureData .= $key.$value;
		}
		$signature = hash_hmac('sha256', $signatureData, $secretKey,true);
		$signature = base64_encode($signature);
	   
		$postData["signature"] = $signature;
		
		$post_variables = $postData;
		


		// array(
		// 	"appID" => Configuration::get("Paytm_MERCHANT_ID"),
		// 	"orderID" => $order_id,
		// 	#"CUST_ID" => intval($cart->id_name),
		// 	"orderAmount" => $amount,
		// 	"customerName" => $customerName,
		// 	"customerPhone" => $customerPhone,
		// 	"customerEmail" => $customerEmail,
		// 	#"_ID" => Configuration::get("Paytm_MERCHANT_CHANNEL_ID"),
		// 	#"INDUSTRY_TYPE_ID" => Configuration::get("Paytm_MERCHANT_INDUSTRY_TYPE"),
		// 	#"WEBSITE" => Configuration::get("Paytm_MERCHANT_WEBSITE"),
		// 	"returnUrl" => $returnUrl,
		// 	"signature" => $signature,

		// );



		// if(isset($bill_address->phone_mobile) && trim($bill_address->phone_mobile) != "")
		// 	$post_variables["MOBILE_NO"] = preg_replace("#[^0-9]{0,13}#is", "", $bill_address->phone_mobile);

		// if(isset($customer->email) && trim($customer->email) != "")
		// 	$post_variables["EMAIL"] = $customer->email;

		// if (Configuration::get("Cashfree_CALLBACK_URL_STATUS") == "0")
		// 	$post_variables["CALLBACK_URL"] = $this->module->getDefaultCallbackUrl();
		// else
		// 	$post_variables["CALLBACK_URL"] = Configuration::get("Cashfree_CALLBACK_URL");


		//$post_variables["CHECKSUMHASH"] = getChecksumFromArray($post_variables, Configuration::get("Cashfree_MERCHANT_KEY"));

		$smarty->assign(
						array(
							"cashfree_post" => $post_variables,
							"action" => Configuration::get("Cashfree_GATEWAY_URL")
							)
					);
		
		// return $this->display(__FILE__, 'cashfree.tpl');
		$this->setTemplate('module:cashfree/views/templates/front/payment_form.tpl');
	}
}
