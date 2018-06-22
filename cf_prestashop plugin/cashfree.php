<?php 	
// error_reporting(E_ALL);	
use PrestaShop\PrestaShop\Core\Payment\PaymentOption;
if (!defined('_PS_VERSION_')) {
	exit;
}
require_once(dirname(__FILE__).'/lib/encdec_cashfree.php');
class Cashfree extends PaymentModule
{
	private $_html = '';
	private $_postErrors = array();
	private $_title;
	
	function __construct()
	{		
		$this->name = 'cashfree';
		$this->tab = 'payments_gateways';
		$this->version = 3.0;
		$this->author = 'Cashfree Development Team';
				
		parent::__construct();
		$this->displayName = $this->l(' Cashfree');
		$this->description = $this->l('Module for accepting payments by Cashfree');
		
		$this->page = basename(__FILE__, '.php');
	}
	
	public function getDefaultCallbackUrl(){
		return $this->context->link->getModuleLink($this->name, 'validation');
	}
	public function install()
	{
		if(parent::install()){
			Configuration::updateValue("Cashfree_MERCHANT_ID", "");
			Configuration::updateValue("Cashfree_MERCHANT_KEY", "");
			Configuration::updateValue("Cashfree_TRANSACTION_STATUS_URL", "");
			Configuration::updateValue("Cashfree_GATEWAY_URL", "");
			#Configuration::updateValue("Paytm_MERCHANT_INDUSTRY_TYPE", "");
			#Configuration::updateValue("Paytm_MERCHANT_CHANNEL_ID", "WEB");
			#Configuration::updateValue("Paytm_MERCHANT_WEBSITE", "");
			Configuration::updateValue("Cashfree_CALLBACK_URL_STATUS", 0);
			Configuration::updateValue("Cashfree_CALLBACK_URL", $this->getDefaultCallbackUrl());
			
			$this->registerHook('paymentOptions');
			
			if(!Configuration::get('Cashfree_ORDER_STATE')){
				$this->setCashfreeOrderState('Cashfree_ID_ORDER_SUCCESS','Payment Received','#b5eaaa');
				$this->setCashfreeOrderState('Cashfree_ID_ORDER_FAILED','Payment Failed','#E77471');
				$this->setCashfreeOrderState('Cashfree_ID_ORDER_PENDING','Payment Pending','#F4E6C9');

				//Setting setPaytmOrderState -> setCashfreeOrderState; pls check for errors
				Configuration::updateValue('Cashfree_ORDER_STATE', '1');
			}		
			return true;
		}
		else {
			return false;
		}
	
	}
	public function uninstall()
	{
		if (!Configuration::deleteByName("Cashfree_MERCHANT_ID") OR 
			!Configuration::deleteByName("Cashfree_MERCHANT_KEY") OR 
			!Configuration::deleteByName("Cashfree_TRANSACTION_STATUS_URL") OR 
			!Configuration::deleteByName("Cashfree_GATEWAY_URL") OR 
			#!Configuration::deleteByName("Paytm_MERCHANT_INDUSTRY_TYPE") OR 
			#!Configuration::deleteByName("Paytm_MERCHANT_CHANNEL_ID") OR 
			#!Configuration::deleteByName("Paytm_MERCHANT_WEBSITE") OR 
			!Configuration::deleteByName("Cashfree_CALLBACK_URL_STATUS") OR 
			!Configuration::deleteByName("Cashfree_CALLBACK_URL") OR 
			!parent::uninstall()) {
			return false;
		}
		return true;
	}
	public function setCashfreeOrderState($var_name,$status,$color){
		$orderState = new OrderState();
		$orderState->name = array();
		foreach(Language::getLanguages() AS $language){
			$orderState->name[$language['id_lang']] = $status;
		}
		$orderState->send_email = false;
		$orderState->color = $color;
		$orderState->hidden = false;
		$orderState->delivery = false;
		$orderState->logable = true;
		$orderState->invoice = true;
		if ($orderState->add())
			Configuration::updateValue($var_name, (int)$orderState->id);
		return true;
	}
	public function getContent() {
		$this->_html = "<h2>" . $this->displayName . "</h2>";
		if (isset($_POST["submitCashfree"])) {
			// trim all values
			foreach($_POST as &$v){
				$v = trim($v);
			}
			if (!isset($_POST["merchant_id"]) || $_POST["merchant_id"] == ""){
				$this->_postErrors[] = $this->l("Please Enter your Merchant APP ID.");
			}
			if (!isset($_POST["merchant_key"]) || $_POST["merchant_key"] == ""){
				$this->_postErrors[] = $this->l("Please Enter your Merchant Secret Key.");
			}
			/*if (!isset($_POST["industry_type"]) || $_POST["industry_type"] == ""){
				$this->_postErrors[] = $this->l("Please Enter your Industry Type.");
			}*/
			/*if (!isset($_POST["channel_id"]) || $_POST["channel_id"] == ""){
				$this->_postErrors[] = $this->l("Please Enter your Channel ID.");
			}/*
			if (!isset($_POST["website"]) || $_POST["website"] == ""){
				$this->_postErrors[] = $this->l("Please Enter your Website.");
			}*/
			if (!isset($_POST["gateway_url"]) || $_POST["gateway_url"] == ""){
				$this->_postErrors[] = $this->l("Please Enter Gateway Url.");
			}
			if (!isset($_POST["status_url"]) || $_POST["status_url"] == ""){
				$this->_postErrors[] = $this->l("Please Enter Transaction Status URL .");
			}
			if (!isset($_POST["callback_url"]) || $_POST["callback_url"] == ""){
				$this->_postErrors[] = $this->l("Please Enter Callback URL.");
			} else {
				$url_parts = parse_url($_POST["callback_url"]);
				if(!isset($url_parts["scheme"]) || (strtolower($url_parts["scheme"]) != "http" 
					&& strtolower($url_parts["scheme"]) != "https") || !isset($url_parts["host"]) || $url_parts["host"] == ""){
					$this->_postErrors[] = $this->l('Callback URL is invalid. Please enter valid URL and it must be start with http:// or https://');
				}
			}
			if (!sizeof($this->_postErrors)) {
				Configuration::updateValue("Cashfree_MERCHANT_ID", $_POST["merchant_id"]);
				Configuration::updateValue("Cashfree_MERCHANT_KEY", $_POST["merchant_key"]);
				Configuration::updateValue("Cashfree_GATEWAY_URL", $_POST["gateway_url"]);
				#Configuration::updateValue("Paytm_MERCHANT_INDUSTRY_TYPE", $_POST["industry_type"]);
				#Configuration::updateValue("Paytm_MERCHANT_CHANNEL_ID", $_POST["channel_id"]);
				#Configuration::updateValue("Paytm_MERCHANT_WEBSITE", $_POST["website"]);
				Configuration::updateValue("Cashfree_TRANSACTION_STATUS_URL", $_POST["status_url"]);
				Configuration::updateValue("Cashfree_STATUS", $_POST["callback_url_status"]);
				Configuration::updateValue("Cashfree_CALLBACK_URL", $_POST["callback_url"]);
				$this->displayConf();
			} else {
				$this->displayErrors();
			}
		}
		$this->_showimageCashfree();
		$this->_displayFormSettings();
		return $this->_html;
    }
    public function displayConf(){
		$this->_html .= $this->displayConfirmation($this->trans('Settings updated', array(), 'Admin.Notifications.Success'));
	}
	
	public function displayErrors(){
		$nbErrors = sizeof($this->_postErrors);
		$this->_html .= '
		<div class="alert error">
			<h3>'.($nbErrors > 1 ? $this->l('There are') : $this->l('There is')).' '.$nbErrors.' '.($nbErrors > 1 ? $this->l('errors') : $this->l('error')).'</h3>
			<ol>';
		foreach ($this->_postErrors AS $error)
			$this->_html .= '<li>'.$error.'</li>';
		$this->_html .= '
			</ol>
		</div>';
	}
	
	public function _showimageCashfree(){
		$this->_html .= '
		<img src="../modules/cashfree/cashfree.png" style="float:left; padding: 0px; margin-right:15px;" />
		<b>'.$this->l('This module allows you to accept payments by Cashfree.').'</b><br /><br />
		'.$this->l('If the client chooses this payment mode, your Cashfree account will be automatically credited.').'<br />
		'.$this->l('Please ensure that your Cashfree account is configured before using this module.').'
		<br /><br /><br />';
	}
	public function _displayFormSettings() {
	 	$merchant_id = isset($_POST["merchant_id"])? 
							$_POST["merchant_id"] : Configuration::get("Cashfree_MERCHANT_ID");
		$merchant_key = isset($_POST["merchant_key"])? 
							$_POST["merchant_key"] : Configuration::get("Cashfree_MERCHANT_KEY");
		/*$industry_type = isset($_POST["industry_type"])? 
							$_POST["industry_type"] : Configuration::get("Paytm_MERCHANT_INDUSTRY_TYPE");*/
		/*$channel_id = isset($_POST["channel_id"])? 
							$_POST["channel_id"] : Configuration::get("Paytm_MERCHANT_CHANNEL_ID");/*
		$website = isset($_POST["website"])? 
							$_POST["website"] : Configuration::get("Paytm_MERCHANT_WEBSITE");*/
		$gateway_url = isset($_POST["gateway_url"])? 
							$_POST["gateway_url"] : Configuration::get("Cashfree_GATEWAY_URL");
		$status_url = isset($_POST["status_url"])? 
							$_POST["status_url"] : Configuration::get("Cashfree_TRANSACTION_STATUS_URL");
		$callback_url = isset($_POST["callback_url"])? 
							$_POST["callback_url"] : Configuration::get("Cashfree_CALLBACK_URL");
		$last_updated = "";
		$path = __DIR__."/cashfree_version.txt";
		if(file_exists($path)){
			$handle = fopen($path, "r");
			if($handle !== false){
				$date = fread($handle, 10); // i.e. DD-MM-YYYY or 25-04-2018
				$last_updated = '<div class="pull-left"><p>Last Updated: '. date("d F Y", strtotime($date)) .'</p></div>';
			}
		}
		$this->bootstrap = true;
		$this->_html .= '
			<form id="module_form" class="defaultForm form-horizontal" method="POST" novalidate="">
				<div class="panel">
					<div class="panel-heading">'.$this->l("Cashfree Payment Configuration Set Up").'</div>
					<div class="form-wrapper">
						<div class="form-group">
							<label class="control-label col-lg-3 required"> '.$this->l("Merchant APP ID").'</label>
							<div class="col-lg-9">
								<input type="text" name="merchant_id" value="' . $merchant_id . '"  class="" required="required"/>
							</div>
						</div>
						<div class="form-group">
							<label class="control-label col-lg-3 required"> '.$this->l("Merchant Secret Key").'</label>
							<div class="col-lg-9">
								<input type="text" name="merchant_key" value="' . $merchant_key . '"  class="" required="required"/>
							</div>
						</div>
						
						<div class="form-group">
							<label class="control-label col-lg-3 required"> '.$this->l("Transaction Url").'</label>
							<div class="col-lg-9">
								<input type="text" name="gateway_url" value="' . $gateway_url . '"  class="" required="required"/>
							</div>
						</div>
						<div class="form-group">
							<label class="control-label col-lg-3 required"> '.$this->l("Transaction Status Url").'</label>
							<div class="col-lg-9">
								<input type="text" name="status_url" value="' . $status_url . '"  class="" required="required"/>
							</div>
						</div>
						<div class="form-group">
							<label class="control-label col-sm-3 required" for="callback_url_status">
								'.$this->l("Custom Callback Url").'
							</label>
							<div class="col-sm-9">
								<select name="callback_url_status" id="callback_url_status" class="form-control">
									<option value="1" '.(Configuration::get("Cashfree_CALLBACK_URL_STATUS") == "1"? "selected" : "").'>'.$this->l('Enable').'</option>
									<option value="0" '.(Configuration::get("Cashfree_CALLBACK_URL_STATUS") == "0"? "selected" : "").'>'.$this->l('Disable').'</option>
								</select>
							</div>
						</div>
						<div class="callback_url_group form-group">
							<label class="control-label col-sm-3 required" for="callback_url">
								'.$this->l("Callback URL").'
							</label>
							<div class="col-sm-9">
								<input type="text" name="callback_url" id="callback_url" value="'. $callback_url .'" class="form-control" '.(Configuration::get("Cashfree_CALLBACK_URL_STATUS") == "0"? "readonly" : "").'/>
							</div>
						</div>
					</div>
					<div class="panel-footer">
						<div>
							<button type="submit" value="1" id="module_form_submit_btn" name="submitCashfree" class="btn btn-default pull-right">
								<i class="process-icon-save"></i> Save
							</button>
						</div>
						'.$last_updated.'
					</div>
				</div>
			</form>
			<script type="text/javascript">
			var default_callback_url = "'.$this->getDefaultCallbackUrl().'";
			function toggleCallbackUrl(){
				if($("select[name=\"callback_url_status\"]").val() == "1"){
					$(".callback_url_group").removeClass("hidden");
					$("input[name=\"callback_url\"]").prop("readonly", false);
				} else {
					$(".callback_url_group").addClass("hidden");
					$("#callback_url").val(default_callback_url);
					$("input[name=\"callback_url\"]").prop("readonly", true);
				}
			}
			$(document).on("change", "select[name=\"callback_url_status\"]", function(){
				toggleCallbackUrl();
			});
			toggleCallbackUrl();
			</script>';
	}
	public function hookPaymentOptions($params)
	{
		if (!$this->active) {
			return;
		}
	
		$newOption = new PaymentOption();
		$newOption->setCallToActionText($this->trans('Pay with', array(), 'Modules.Cashfree.Shop'))
		//->setForm($paymentForm)
		->setLogo(_MODULE_DIR_.'cashfree/views/img/cashfree.png')
		//->setAdditionalInformation('your additional Information')
		->setAction($this->context->link->getModuleLink($this->name, 'payment'));
		$newOption->setModuleName('cashfree');
		
		return [$newOption];
	}
}
?>
