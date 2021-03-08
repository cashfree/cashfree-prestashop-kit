<?php

class CashfreePaymentfailModuleFrontController extends ModuleFrontController
{

    public $ssl = true;

    public $isLogged = false;

    public $display_column_left = false;

    public $display_column_right = false;

    public $service;

    protected $ajax_refresh = false;

    protected $css_files_assigned = array();

    protected $js_files_assigned = array();
    
    public function __construct()
    {
		
		
        $this->controller_type = 'modulefront';
        
        $this->module = Module::getInstanceByName(Tools::getValue('module'));
        if (! $this->module->active) {
            Tools::redirect('index');
        }
        $this->page_name = 'module-' . $this->module->name . '-' . Dispatcher::getInstance()->getController();
		
        
        parent::__construct();
    }

    public function postProcess()
    {
		
		$err_reason = $_GET['cf_errreason'] ;
		
		$this->context->smarty->assign('err_reason', $err_reason);				        
        
    }

    public function initContent()
    {
		
        parent::initContent();
        $this->setTemplate('payment-fail.tpl');
    }

}
