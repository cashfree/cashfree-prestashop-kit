<?php
/**
 * CASHFREE
 */
use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_'))
    exit;


class Cashfree extends PaymentModule
{
    public function __construct()
    {
        $this->name = 'cashfree';
        $this->tab = 'payments_gateways';
        $this->version = '1.1.0';
        $this->author = 'Cashfree';
        $this->need_instance = 1;
        $this->bootstrap = true;       
		
		
        $this->ps_versions_compliancy = array(
            'min' => '1.7',
            'max' => _PS_VERSION_
        );


        parent::__construct();
		
		$this->meta_title = $this->l('Cashfree');
		$this->displayName = $this->l('Cashfree');		        
        $this->description = $this->l('Cashfree');	

    }

    public function install()
    {
		$this->addOrderState('Payment Failed');
        if (!parent::install() || !$this->registerHook('paymentOptions') || !$this->registerHook('adminOrder') || !$this->registerHook('hookPaymentReturn') || !$this->registerHook('orderConfirmation')           
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
	
    public function hookPaymentOptions($params)
    {
        return $this->cashfreePaymentOptions($params);
    }
	
    public function hookPaymentReturn($params)
    {		
		
        $this->cashfreePaymentReturnNew($params);
        return $this->display(dirname(__FILE__), '/tpl/order-confirmation.tpl');
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
		
		
		if ($params['order']->module != $this->name)
			return false;
		
		$errmsg = '';
		if (isset($_GET['errmsg'])) $errmsg = base64_decode($_GET['errmsg']);		
		
		if ($params['order'] && Validate::isLoadedObject($params['order']) && isset($params['order']->valid))
		{
			
		$this->smarty->assign('cashfree_order', array('id' => $params['order']->id, 'valid' => $params['order']->valid, 'errmsg'=>$errmsg));

			return $this->display(__FILE__, '/tpl/order-confirmation.tpl');
		}
    }
	
	public function returnsuccess($params, $response_url){
		$orderId = $params['orderId'];
		list($oid) = explode('_', $orderId);

		try 
		{
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
			
		}
		catch(Exception $e)
        {
            $error = $e->getMessage();
            Logger::addLog("Payment Failed for cart# ".$oid.". Cashfree reference id: ".$params['referenceId']." Error: ". $error, 4);

            echo 'Error! Please contact the seller directly for assistance.</br>';
            echo 'Cart Id: '.$oid.'</br>';
            echo 'Cashfree Reference Id: '.$params['referenceId'].'</br>';
            echo 'Error: '.$error.'</br>';

            exit;
        }		
		if ($jsonResponse['status'] == "OK") {
			
			$errmsglist['CANCELLED'] = 'Payment has been cancelled';
			$errmsglist['PENDING'] = 'Payment is under review.';			
			$errmsglist['FLAGGED'] = 'Payment is under flagged';
			$errmsglist['FAILED'] = 'Payment failed';			
		
			
			$txStatus = 'CANCELLED';
			$total = $jsonResponse['orderAmount'];			

			if (array_key_exists("txStatus", $jsonResponse))
				$txStatus = $jsonResponse["txStatus"];			
			
				
			if ($txStatus == 'SUCCESS') {	
				$cart = new Cart((int)$oid);
				if ($cart->OrderExists()) {
					$order = new Order((int)Order::getOrderByCartId($oid));
					$query = http_build_query([
						'controller'    => 'order-confirmation',
						'id_cart'       => (int)$oid,
						'id_module'     => (int)$this->id,
						'id_order'      => (int)$order->id,
						'key'           => $this->context->customer->secure_key,
					], '', '&');

					$url = 'index.php?' . $query;
					Logger::addLog("Already Order Exist. Payment Successful for cart#".$oid.". Cashfree reference id: ".$jsonResponse['referenceId'] . " Success Url: ".$response_url, 1);
					return $url;
				}			
				$extra_vars['transaction_id'] = $jsonResponse['referenceId'];
				$orderValidate = $this->validateOrder((int)$oid, (int)Configuration::get('CASHFREE_OSID'), (float)($total), $this->displayName, null, $extra_vars, null, false, $cart->secure_key);

				Logger::addLog("Payment Successful for cart#".$oid.". Cashfree reference id: ".$jsonResponse['referenceId'] . " Ret=" . (int)$orderValidate." Success Url: ".$response_url, 1);

				$query = http_build_query([
					'controller'    => 'order-confirmation',
					'id_cart'       => (int)$oid,
					'id_module'     => (int)$this->id,
					'id_order'      => (int)$this->currentOrder,
					'key'           => $this->context->customer->secure_key,
				], '', '&');
	
				$url = 'index.php?' . $query;
			}
			else if($txStatus == 'FAILED') {
				$payment_failed_stid = 6;
				$states = OrderState::getOrderStates((int)$this->context->language->id);
				// check if order state exist
				foreach ($states as $state) {
					if (in_array('Payment Failed', $state)) {
						$payment_failed_stid = $state['id_order_state'];
					}
				}
				
				$orderValidate = $this->validateOrder($this->context->cart->id , $payment_failed_stid, (float)($total), $this->displayName, NULL, NULL, NULL, false, $this->context->cart->secure_key, NULL);
			
				$duplicated_cart = $this->context->cart->duplicate();
				$this->context->cart = $duplicated_cart['cart'];
				$this->context->cookie->id_cart = (int)$this->context->cart->id;
					  
				Logger::addLog("Payment failed for cart#".$oid.". Cashfree reference id: ".$jsonResponse['referenceId'] . " Ret=" . (int)$orderValidate." Success Url: ".$response_url, 1);
				
				Tools::redirectLink($this->context->link->getPageLink('order',null, null, array('error_msg' => $errmsglist[$txStatus])));
			}
			else {
				$cancel_stid = 6;
				$orderValidate = $this->validateOrder($this->context->cart->id , $cancel_stid, (float)($total), $this->displayName, NULL, NULL, NULL, false, $this->context->cart->secure_key, NULL);
			
				$duplicated_cart = $this->context->cart->duplicate();
				$this->context->cart = $duplicated_cart['cart'];
				$this->context->cookie->id_cart = (int)$this->context->cart->id;
					  
				Logger::addLog("Payment Cancelled for cart#".$oid.". Cashfree reference id: ".$jsonResponse['referenceId'] . " Ret=" . (int)$orderValidate." Success Url: ".$response_url, 1);
				
				Tools::redirectLink($this->context->link->getPageLink('order',null, null, array('error_msg' => $errmsglist[$txStatus])));

			}			
			return $url;			
			
		}
		
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
			
			$cashfree_name = Tools::getValue('cashfree_name');
			$saveOpt = false;
			$err_msg = '';
			if (empty(Tools::getValue('cashfree_app_id'))) $err_msg = 'App ID must have value';
			if (empty(Tools::getValue('cashfree_secret_key'))) $err_msg = 'Secret Key must have value';			
		
			
			if (empty($err_msg)) $saveOpt = true;
			
        	if ($saveOpt) {
				Configuration::updateValue('CASHFREE_APP_ID', pSQL(Tools::getValue('cashfree_app_id')));
				Configuration::updateValue('CASHFREE_SKEY', pSQL(Tools::getValue('cashfree_secret_key')));			
				Configuration::updateValue('CASHFREE_OSID', pSQL(Tools::getValue('cashfree_order_status')));
				Configuration::updateValue('CASHFREE_MODE', pSQL(Tools::getValue('cashfree_mode')));			
																		
				$html = '<div class="alert alert-success">'.$this->l('Configuration updated successfully').'</div>';			
			}
			else {
				$html = '<div class="alert alert-warning">'.$this->l($err_msg).'</div>';	
			}
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
			'cashfree_form' => isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '',				
			'cashfree_app_id' => Configuration::get('CASHFREE_APP_ID'),				
			'cashfree_secret_key' => Configuration::get('CASHFREE_SKEY'),		        
            'cashfree_mode' => Configuration::get('CASHFREE_MODE'),			
            'cashfree_order_status' => $orderstatusid,			
			'cashfree_confirmation' => isset($html) ? $html : '',			
            'orderstates' => $OrderStates,
        );

        $this->context->smarty->assign($data);	
        $output = $this->display(__FILE__, 'tpl/admin.tpl');

        return $output;
    }

	
	//1.7

    public function cashfreePaymentOptions($params)
    {

        if (!$this->active) {
            return;
        }
        if (!$this->checkCurrency($params['cart'])) {
            return;
        }
        $payment_options = [
            $this->cashfreeExternalPaymentOption(),
        ];
        return $payment_options;
    }

    public function checkCurrency($cart)
    {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);

        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }
        return false;
    }

    public function cashfreeExternalPaymentOption()
    {
		$lang = Tools::strtolower($this->context->language->iso_code);
		if (isset($_GET['cashfreeerror'])) $errmsg = $_GET['cashfreeerror'];
        $this->context->smarty->assign(array(
            'module_dir' => $this->_path,
            'errmsg' => $errmsg,			
        ));		
		
		$url = $this->context->link->getModuleLink('cashfree', 'payment');
		
        $newOption = new PaymentOption();
        $newOption->setCallToActionText($this->l('Pay with Cashfree'))
			->setAction($url)
            ->setAdditionalInformation($this->context->smarty->fetch('module:cashfree/tpl/payment_infos.tpl'));

        return $newOption;
    }

    public function cashfreePaymentReturnNew($params)
    {
        // Payement return for PS 1.7
        if ($this->active == false) {
            return;
        }
        $order = $params['order'];
        if ($order->getCurrentOrderState()->id != Configuration::get('PS_OS_ERROR')) {
            $this->smarty->assign('status', 'ok');
        }	
		
        $this->smarty->assign(array(
            'id_order' => $order->id,
            'reference' => $order->reference,					
            'params' => $params,
            'total_to_pay' => Tools::displayPrice($order->total_paid, null, false),
            'shop_name' => $this->context->shop->name,
        ));
        return $this->fetch('module:' . $this->name . '/tpl/order-confirmation.tpl');
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
		try 
		{
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
				$checkout_type = Configuration::get('PS_ORDER_PROCESS_TYPE') ? 'order-opc' : 'order';
						$url = (_PS_VERSION_ >= '1.5' ? 'index.php?controller='.$checkout_type.'&' : $checkout_type.'.php?').'step=3&cgv=1&cashfreeerror='.$jsonResponse->{"reason"}.'#cashfree-anchor';
						Tools::redirect($url);	
				
				exit;
				
			}
		}
		catch(Exception $e)
		{
			$error = $e->getMessage();
            Logger::addLog("Order creation failed. Please contact the seller directly for assistance. Error: ". $error, 4);
			$checkout_type = Configuration::get('PS_ORDER_PROCESS_TYPE') ? 'order-opc' : 'order';
						$url = (_PS_VERSION_ >= '1.5' ? 'index.php?controller='.$checkout_type.'&' : $checkout_type.'.php?').'step=3&cgv=1&cashfreeerror='.$jsonResponse->{"reason"}.'#cashfree-anchor';
						Tools::redirect($url);	
				
            exit;
		}  		

    }
}
