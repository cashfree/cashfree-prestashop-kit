<?php
/**
 * CASHFREE
 */

if (!defined('_PS_VERSION_'))
    exit;


class Cashfree extends PaymentModule
{
    public function __construct()
    {
        $this->name = 'cashfree';
        $this->tab = 'payments_gateways';
        $this->version = '1.6.3';
        $this->author = 'Cashfree';
        $this->need_instance = 1;
        $this->bootstrap = true;        		

        parent::__construct();

        $this->displayName = $this->l('Cashfree');
        $this->description = $this->l('Cashfree');	
		
		/* Backward compatibility */
		if (_PS_VERSION_ < 1.5)
			require(_PS_MODULE_DIR_.'cashfree/backward_compatibility/backward.php');
		
    }

    public function install()
    {
		$this->addOrderState('Payment Failed');
        if (!parent::install() || !$this->registerHook('payment') || !$this->registerHook('adminOrder') || !$this->registerHook('orderConfirmation')
            || !$this->createTables() 
        ) {
            return false;
        }

        return true;

    }
	
	public function addOrderState($name)
    {
        $state_exist = false;
        $states = OrderState::getOrderStates((int)$this->context->language->id);
 
        // check if order state exist
        foreach ($states as $state) {
            if (in_array($name, $state)) {
                $state_exist = true;
                break;
            }
        }
 
        // If the state does not exist, we create it.
        if (!$state_exist) {
            // create new order state
			$order_state = new OrderState();
			$order_state->id_order_state = 21;
            $order_state->color = '#d30016';
            $order_state->send_email = false;
            $order_state->module_name = 'cashfree';
            $order_state->template = '';
            $order_state->name = array();
            $languages = Language::getLanguages(false);
            foreach ($languages as $language)
                $order_state->name[ $language['id_lang'] ] = $name;
 
            // Update object
            $order_state->add();
        }
 
        return true;
    }	
	
    protected function createTables()
    {
        if (!Db::getInstance()->Execute('
		CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_. 'cashfree_order` (
			  `cashfree_order_id` INT(11) NOT NULL AUTO_INCREMENT,
			  `order_id` INT(11) NOT NULL,
			  `referenceId` VARCHAR(255),
			  `txStatus` VARCHAR(20),			  			  
			  `gatewayResponse` TEXT,			  			  			  
			  `paymentAmount` decimal(12,2) DEFAULT NULL,
			  PRIMARY KEY (`cashfree_order_id`)
			) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT COLLATE=utf8_general_ci')
        ) {
            return false;
        }

        return true;
    }	

	private function getTransaction($id_cart)
	{
		return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'cashfree_order` WHERE `order_id` = '.(int)$id_cart);
	}


	
		
    public static function setOrderStatus($oid, $status)
    {
        $order_history = new OrderHistory();
        $order_history->id_order = (int)$oid;
        $order_history->changeIdOrderState((int)$status, (int)$oid, true);
        $order_history->addWithemail(true);        
    }
	

	public function hookOrderConfirmation($params)
    {
		if ($params['objOrder']->module != $this->name)
			return false;
		if ($params['objOrder'] && Validate::isLoadedObject($params['objOrder']) && isset($params['objOrder']->valid))
		{
			if (version_compare(_PS_VERSION_, '1.5', '>=') && isset($params['objOrder']->reference))
				$this->smarty->assign('cashfree_order', array('id' => $params['objOrder']->id, 'reference' => $params['objOrder']->reference, 'valid' => $params['objOrder']->valid));
			else
				$this->smarty->assign('cashfree_order', array('id' => $params['objOrder']->id, 'valid' => $params['objOrder']->valid));

			return $this->display(__FILE__, 'views/templates/front/order-confirmation.tpl');
		}
    }
	
	
	public function returnfailure(){
	if (_PS_VERSION_ < 1.5)
						$redirect = __PS_BASE_URI__.'order-confirmation.php?id_cart='.(int)$this->context->cart->id.'&id_module='.(int)$this->id.'&id_order='.(int)$this->currentOrder.'&key='.$this->context->customer->secure_key;
					else
						$redirect = __PS_BASE_URI__.'index.php?controller=order-confirmation&id_cart='.(int)$this->context->cart->id.'&id_module='.(int)$this->id.'&id_order='.(int)$this->currentOrder.'&key='.$this->context->customer->secure_key;

					header('Location: '.$redirect);
	}
	
	


    public function hookPayment($params)
    {	
		
		if (!$this->active)
			return false;
		
		$url = $this->context->link->getModuleLink('cashfree', 'payment');		
		
		$this->smarty->assign('cashfree_ps_version', _PS_VERSION_);	
		$this->smarty->assign(array(
			'this_path' => $this->_path,
			'this_path_ssl' => Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->name.'/',
			'acturl' =>$url
		));		
	
		
		$tplname  = 'views/templates/front/payment.tpl';		
		return $this->display(__FILE__, $tplname);
    }

	public function returnsuccess($params, $urlonly = false){
		
		
  		$orderId = $params['orderId'];  
		list($oid) = explode('_', $orderId);
		$cart = new Cart((int)$oid);		
		
		$res =  $this->getTransaction($oid);
		if ($res) $urlonly = true;
		
		
		        
		$apiEndpoint = (Configuration::get('CASHFREE_MODE') == 'N') ? 'https://api.cashfree.com' : 'https://test.cashfree.com'; 
		
		$enqUrl = $apiEndpoint.'/api/v1/order/info/status';
		$cf_request = array();
   		$cf_request["appId"] = Configuration::get('CASHFREE_APP_ID');
   		$cf_request["secretKey"] = Configuration::get('CASHFREE_SKEY');
   		$cf_request["orderId"] = $orderId; 		
		
		$timeout = 10;
   
   		$request_string = "";
   		foreach($cf_request as $key=>$value) {
     		$request_string .= $key.'='.rawurlencode($value).'&';
   		}
   
   		$ch = curl_init();
   		curl_setopt($ch, CURLOPT_URL,"$enqUrl?");
   		curl_setopt($ch,CURLOPT_POST, true);
   		curl_setopt($ch,CURLOPT_POSTFIELDS, $request_string);
   		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);				
   		curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);		
   		$curl_result=curl_exec ($ch);
   		curl_close ($ch);

   		$jsonResponse = json_decode($curl_result, true);
		
		
		
   		if ($jsonResponse['status'] == "OK") {
			
			$errmsglist['CANCELLED'] = 'Payment has been cancelled';
			$errmsglist['PENDING'] = 'Payment is under review.';			
			$errmsglist['FLAGGED'] = 'Payment is under flagged';
			$errmsglist['FAILED'] = 'Payment failed';			
		
			
			$txStatus = 'CANCELLED';
			$total = $jsonResponse['orderAmount'];			

			if (array_key_exists("txStatus", $jsonResponse))
     			$txStatus = $jsonResponse["txStatus"];			
			
			$param = array(
			  "order_id"=>$oid,
			  "paymentAmount"=>$total,				
  			  "referenceId"=>$jsonResponse['referenceId'],								
			  "txStatus"=>$txStatus,				
			  "gatewayResponse"=>$curl_result,				
			);
			
			$this->insert_transaction($param);
			
			$urlorderid = $this->currentOrder;			
			$customer = new Customer((int)$cart->id_customer);
			$urlsecure_key = $customer->secure_key;
			
			
			
			
			if ($urlonly) $urlorderid = Order::getOrderByCartId($oid);
				
			if ($txStatus == 'SUCCESS') {				
				$extra_vars['transaction_id'] = $jsonResponse['referenceId'];
				if (!$urlonly)
				$orderValidate = $this->validateOrder((int)$oid, (int)Configuration::get('CASHFREE_OSID'), (float)($total), $this->displayName, null, null, null, false, $cart->secure_key);
				
				if (_PS_VERSION_ < 1.5)
						$redirect = __PS_BASE_URI__.'order-confirmation.php?id_cart='.(int)$oid.'&id_module='.(int)$this->id.'&id_order='.(int)$this->currentOrder.'&key='.$this->context->customer->secure_key;
					else
						$redirect = $this->context->shop->getBaseURL().'index.php?controller=order-confirmation&id_cart='.(int)$oid.'&id_module='.(int)$this->id.'&id_order='.(int)$this->currentOrder.'&key='.$this->context->customer->secure_key;
				
				$redirect = $this->context->shop->getBaseURL().'index.php?controller=order-confirmation&id_cart='.(int)$oid.'&id_module='.(int)$this->id.'&id_order='.(int)$urlorderid.'&key='.$urlsecure_key;
				
				$response_url = $redirect ;
				
				Logger::addLog("Payment Successful for cart#".$oid.". Cashfree reference id: ".$jsonResponse['referenceId'] . " Ret=" . (int)$orderValidate." Success Url: ".$response_url, 1);
			}
			else {
				$payment_failed_stid = 6;
				if($txStatus === 'FAILED') {
					$states = OrderState::getOrderStates((int)$this->context->language->id);
					// check if order state exist
					foreach ($states as $state) {
						if (in_array('Payment Failed', $state)) {
							$payment_failed_stid = $state['id_order_state'];
						}
					}
					$errmsg = $jsonResponse['txMsg'];
				}
				else {
					$errmsg = $errmsglist[$txStatus];
				}
				
				if (!$urlonly) 
				$orderValidate = $this->validateOrder((int)$oid, (int)$payment_failed_stid, (float)($total), $this->displayName, null, array(), null, false, $cart->secure_key);
				
						
				
				$duplicated_cart = $this->context->cart->duplicate();
				$this->context->cart = $duplicated_cart['cart'];
				$this->context->cookie->id_cart = (int)$this->context->cart->id;
				$response_url = ($this->context->link->getPageLink('order',null, null, array('error_msg' => $errmsg)));
					  
				Logger::addLog("Payment failed for cart#".$oid.". Cashfree reference id: ".$jsonResponse['referenceId'] . " Ret=" . (int)$orderValidate." Success Url: ".$response_url, 1);
				
				Tools::redirectLink($response_url);
							
			}			
			return $redirect;			
			
		}
		
		
	}
	
	
	private function cashfree_getorderid($referenceNumber){
		$dbrow = Db::getInstance()->getRow('SELECT order_id FROM `'._DB_PREFIX_.'cashfree_order` WHERE `referenceNumber` = '.(int)$referenceNumber);		
		return $dbrow['order_id']; 
	}

	private function insert_transaction($payment)		
	{									
		return Db::getInstance()->insert('cashfree_order', $payment);
	}

	public function sendCURL($RequestUrl,$RequestData) {	
			$RequesQuery = '?';
			$AndStr = '';
			foreach ($RequestData as $key => $value){
				if ($key =='items') $RequesQuery .= $AndStr.$key.'='.$value;
				else $RequesQuery .= $AndStr.$key.'='.urlencode($value);			

				$AndStr = '&';
			}
			$RequestUrl .= $RequesQuery;
	
	        $curl = curl_init($RequestUrl);
    	    curl_setopt($curl,CURLOPT_SSL_VERIFYPEER, 0);
	        curl_setopt($curl,CURLOPT_RETURNTRANSFER, 1);
    	    $PaymentRes = curl_exec($curl);
	        curl_close ($curl); 
		 
			$resAr = explode('&', $PaymentRes);
			$PaymentAr = array();	
			foreach ($resAr as $i) {
				list($key, $val) = explode('=', $i);
				$PaymentAr[$key] = urldecode($val);		
			}		
			return 	$PaymentAr;
	}
    /**
     * Uninstall and clean the module settings
     *
     * @return	bool
     */
    public function uninstall()
    {
        parent::uninstall();

        Db::getInstance()->Execute('DELETE FROM `'._DB_PREFIX_.'module_country` WHERE `id_module` = '.(int)$this->id);

        return (true);
    }

    public function getContent()
    {
        if (Tools::isSubmit('submit' . $this->name)) {
			Configuration::updateValue('CASHFREE_APP_ID', pSQL(Tools::getValue('cashfree_app_id')));
			Configuration::updateValue('CASHFREE_SKEY', pSQL(Tools::getValue('cashfree_secret_key')));			
			Configuration::updateValue('CASHFREE_OSID', pSQL(Tools::getValue('cashfree_order_status')));
			Configuration::updateValue('CASHFREE_MODE', pSQL(Tools::getValue('cashfree_mode')));			
																		
			$html = '<div class="conf confirm">'.$this->l('Configuration updated successfully').'</div>';			
        }

		$states = 	OrderState::getOrderStates((int) Configuration::get('PS_LANG_DEFAULT'));
		foreach ($states as $state)		
		{
			$OrderStates[$state['id_order_state']] = $state['name'];
			}
		$orderstatusid = Configuration::get('CASHFREE_OSID');			
		if (empty($orderstatusid)) 	$orderstatusid = '2';
		
        $data    = array(
            'base_url'    => _PS_BASE_URL_ . __PS_BASE_URI__,
            'module_name' => $this->name,            
			'cashfree_app_id' => Configuration::get('CASHFREE_APP_ID'),					
			'cashfree_secret_key' => Configuration::get('CASHFREE_SKEY'),		        
            'cashfree_mode' => Configuration::get('CASHFREE_MODE'),			
            'cashfree_order_status' => $orderstatusid,			
			'cashfree_confirmation' => $html,			
            'orderstates' => $OrderStates,	
        );


        $this->context->smarty->assign($data);	
        $output = $this->display(__FILE__, 'views/templates/admin/admin.tpl');

        return $output;
    }
	
	public function getUrl()
    {        				
		
		$cart = $this->context->cart;
		
		$customer = new Customer($cart->id_customer);
		
		
		$amount = number_format($cart->getOrderTotal(true, Cart::BOTH),2);
		$order_id = $cart->id;				
				
		$iaddress = new Address($cart->id_address_invoice);				
		$icountry_code = Country::getIsoById($iaddress->id_country) ;		
		$total = ($cart->getOrderTotal());
		$currency = $this->context->currency;
		
		
		$returnURL = $this->context->link->getModuleLink('cashfree', 'validation');		
		$notifyURL  = $this->context->link->getModuleLink('cashfree', 'notify');
		
					  
		$apiEndpoint = (Configuration::get('CASHFREE_MODE') == 'N') ? 'https://api.cashfree.com' : 'https://test.cashfree.com';  				
		
		$opUrl = $apiEndpoint."/api/v1/order/create";
  
   		$cf_request = array();
   		$cf_request["appId"] = Configuration::get('CASHFREE_APP_ID');
   		$cf_request["secretKey"] = Configuration::get('CASHFREE_SKEY');
   		$cf_request["orderId"] = $order_id.'_'.time(); 
   		$cf_request["orderAmount"] = round($total, 2);
   		$cf_request["orderNote"] = "Order No: ".$order_id;
   		$cf_request["customerPhone"] = $iaddress->phone;
   		$cf_request["customerName"] = $iaddress->firstname . ' ' . $iaddress->lastname;
   		$cf_request["customerEmail"] = $customer->email;
   		$cf_request["returnUrl"] = $returnURL;
		$cf_request["notifyUrl"] = $notifyURL;
		$cf_request["orderCurrency"] = $currency->iso_code;
		//$cf_request["orderCurrency"] ='INR';
		
		
		$timeout = 10;
   
   		$request_string = "";
   		foreach($cf_request as $key=>$value) {
     		$request_string .= $key.'='.rawurlencode($value).'&';
   		}
   
   		$ch = curl_init();
   		curl_setopt($ch, CURLOPT_URL,"$opUrl?");
   		curl_setopt($ch,CURLOPT_POST, count($cf_request));
   		curl_setopt($ch,CURLOPT_POSTFIELDS, $request_string);
   		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
   		curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
   		$curl_result=curl_exec ($ch);
   		curl_close ($ch);

   		$jsonResponse = json_decode($curl_result);
		
   		if ($jsonResponse->{'status'} == "OK") {
     		$paymentLink = $jsonResponse->{"paymentLink"};
			
			return $paymentLink;
			exit;
   		} else {	
			
			Tools::redirectLink($this->context->link->getPageLink('order',null, null, array('error_msg' => $jsonResponse->{"reason"})));
			
			exit;
    		
   		}  		

    }


}
