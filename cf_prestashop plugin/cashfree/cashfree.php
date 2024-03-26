<?php
/**
 * CASHFREE
 */
use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_'))
    exit;


class Cashfree extends PaymentModule
{
    const API_VERSION_20230801 = '2023-08-01';
    const CASHFREE_V3_JS_URL = "https://sdk.cashfree.com/js/v3/cashfree.js";
    public function __construct()
    {
        $this->name = 'cashfree';
        $this->tab = 'payments_gateways';
        $this->version = '2.2.0';
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

    /**
     * @throws PrestaShopException
     * @throws PrestaShopDatabaseException
     * @throws Exception
     */
    public function returnSuccess($params, $response_url){
		$orderId = $params['order_id'];
		list($oid) = explode('_', $orderId);
		try 
		{
			$apiEndpoint = (Configuration::get('CASHFREE_MODE') == 'N') ? 'https://api.cashfree.com' : 'https://sandbox.cashfree.com';
		
			$enqUrl = $apiEndpoint.'/pg/orders/'.$orderId.'/payments';
            $timeout = 30;
            $header = array(
                'Content-Type: application/json',
                'x-client-id:'.Configuration::get('CASHFREE_APP_ID'),
                'x-client-secret:'.Configuration::get('CASHFREE_SKEY'),
                'x-api-version:'.$this::API_VERSION_20230801
            );

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL,$enqUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            $curl_result=curl_exec ($ch);
            curl_close ($ch);

            $cfOrder = json_decode($curl_result);
		}
		catch(Exception $e)
        {
            $error = $e->getMessage();
            Logger::addLog("Payment Failed for cart# ".$oid." Error: ". $error, 4);

            echo 'Error! Please contact the seller directly for assistance.</br>';
            echo 'Cart ID: '.$oid.'</br>';
            echo 'Error: '.$error.'</br>';

            exit;
        }
        $errorMessageList['CANCELLED'] = 'Payment has been cancelled';
        $errorMessageList['PENDING'] = 'Payment is under review.';
        $errorMessageList['FLAGGED'] = 'Payment is under flagged';
        $errorMessageList['FAILED'] = 'Payment failed';
        $cart = $this->context->cart;
        $customer = new Customer($cart->id_customer);

        if (is_array($cfOrder) && isset($cfOrder[0]) && is_object($cfOrder[0]) && isset($cfOrder[0]->payment_status) && $cfOrder[0]->payment_status === 'SUCCESS') {
            $payments = $cfOrder[0];
            if ($cart->OrderExists()) {
                $order = new Order((int)Order::getOrderByCartId($oid));
                $query = http_build_query([
                    'controller'    => 'order-confirmation',
                    'id_cart'       => (int)$cart->id,
                    'id_module'     => (int)$this->id,
                    'id_order'      => (int)$this->currentOrder,
                    'key'           => $this->context->customer->secure_key,
                ], '', '&');

                Logger::addLog("Already Order Exist. Payment Successful for cart#".$oid.". Cashfree reference id: ".$payments->cf_payment_id. " Success Url: ".$response_url, 1);
                return 'index.php?' . $query;;
            }
            $extra_vars['transaction_id'] = $payments->cf_payment_id;
            $orderValidate = $this->validateOrder(
                $cart->id,
                (int)Configuration::get('CASHFREE_OSID'),
                $cart->getOrderTotal(true, Cart::BOTH),
                $this->displayName,
                null,
                $extra_vars,
                null,
                false,
                $customer->secure_key);
            Logger::addLog("Payment Successful for cart#".$oid.". Cashfree reference id: ".$payments->cf_payment_id . " Ret=" . (int)$orderValidate." Success Url: ".$response_url, 1);

            $query = http_build_query([
                'controller'    => 'order-confirmation',
                'id_cart'       => (int)$cart->id,
                'id_module'     => (int)$this->id,
                'id_order'      => (int)$this->currentOrder,
                'key'           => $this->context->customer->secure_key,
            ], '', '&');

            return 'index.php?' . $query;
        } else {
            $referenceId = "";
            $payment_failed_stid = 6;
            $errorMessage = $errorMessageList['PENDING'];
            if(is_array($cfOrder) && isset($cfOrder[0]) && is_object($cfOrder[0])) {
                $payments = $cfOrder[0];
                $referenceId = $payments->cf_payment_id;
                $errorMessage = $errorMessageList[$payments->payment_status];

                // Handle other cases or errors
                if($payments->payment_status === 'FAILED') {
                    $states = OrderState::getOrderStates((int)$this->context->language->id);
                    // check if order state exist
                    foreach ($states as $state) {
                        if (in_array('Payment Failed', $state)) {
                            $payment_failed_stid = $state['id_order_state'];
                        }
                    }
                }
            }

            $extra_vars['transaction_id'] = $referenceId;
            $orderValidate = $this->validateOrder(
                $cart->id ,
                $payment_failed_stid,
                $cart->getOrderTotal(true, Cart::BOTH),
                $this->displayName,
                NULL,
                $extra_vars,
                NULL,
                false,
                $cart->secure_key, NULL);
            $duplicated_cart = $this->context->cart->duplicate();
            $this->context->cart = $duplicated_cart['cart'];
            $this->context->cookie->id_cart = (int)$this->context->cart->id;

            Logger::addLog("Payment failed for cart#".$oid.". Cashfree reference id: ".$referenceId . " Ret=" . (int)$orderValidate." Success Url: ".$response_url, 1);

            Tools::redirectLink($this->context->link->getPageLink('order',null, null, array('error_msg' => $errorMessage)));
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
				$html = '<div class="alert alert-danger">'.$this->l($err_msg).'</div>';	
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
		
		$order_id = $cart->id;
				
		$customerAddress = new Address($cart->id_address_invoice);
		$total = ($cart->getOrderTotal());
		$currency = $this->context->currency;
		
		$returnURL = $this->context->link->getModuleLink('cashfree', 'validation');
        $returnURL = $returnURL.'?order_id={order_id}';
		$notifyURL  = $this->context->link->getModuleLink('cashfree', 'notify');

        $apiEndpoint = "https://sandbox.cashfree.com";
        $environment = "sandbox";
        if(Configuration::get('CASHFREE_MODE') == 'N') {
            $apiEndpoint = "https://api.cashfree.com";
            $environment = "production";
        }

		$opUrl = $apiEndpoint."/pg/orders";

        $cf_request_array = array(
            "order_id" => $order_id.'_'.time(),
            "order_amount" => round($total, 2),
            "order_note" => "Order No: ".$order_id,
            "order_currency" => $currency->iso_code,
            "customer_details" => array(
                "customer_id" => $cart->id_customer,
                "customer_email" => $customer->email,
                "customer_phone" => $customerAddress->phone
            ),
            "order_meta" => array(
                "return_url" => $returnURL,
                "notify_url" => $notifyURL
            )

        );

		$timeout = 30;
        $header = array(
            'Content-Type: application/json',
            'x-client-id:'.Configuration::get('CASHFREE_APP_ID'),
            'x-client-secret:'.Configuration::get('CASHFREE_SKEY'),
            'x-api-version:'.$this::API_VERSION_20230801
        );

        $cf_request = json_encode($cf_request_array);


        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,$opUrl);
        curl_setopt($ch,CURLOPT_POSTFIELDS, $cf_request);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        $curl_result=curl_exec ($ch);
        curl_close ($ch);

        $cfOrder = json_decode($curl_result);

        if (null !== $cfOrder && !empty($cfOrder->{"payment_session_id"}))
        {
            $paymentSessionId = $cfOrder->{"payment_session_id"};
            $cashfreeV3JsUrl = $this::CASHFREE_V3_JS_URL;
            $html_output = <<<EOT
                    <!DOCTYPE html>
                    <html lang="en">
                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <title>Cashfree Checkout Integration</title>
                       <script src="{$cashfreeV3JsUrl}"></script>
                    </head>
                    <body>
                    </body>
                    <script>
                        const cashfree = Cashfree({
                            mode: "$environment"
                        });
                         window.addEventListener("DOMContentLoaded", function () {
                            cashfree.checkout({
                                paymentSessionId: "$paymentSessionId",
                                redirectTarget: "_self",
                                platformName: "ps"
                            });
                        });
                    </script>
                    </html>
                    EOT;
            echo $html_output;
            exit;
        } else {
            $error = $cfOrder->{"message"};
            Logger::addLog("Order creation failed. Please contact the seller directly for assistance. Error: ". $error, 4);
            $checkout_type = Configuration::get('PS_ORDER_PROCESS_TYPE') ? 'order-opc' : 'order';
            $url = (_PS_VERSION_ >= '1.5' ? 'index.php?controller='.$checkout_type.'&' : $checkout_type.'.php?').'step=3&cgv=1&cashfreeerror='.$error.'#cashfree-anchor';
            Tools::redirect($url);

            exit;
        }

    }
}
