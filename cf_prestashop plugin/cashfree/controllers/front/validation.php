<?php
class CashfreeValidationModuleFrontController extends ModuleFrontController
{

    public $ssl = true;

    public $isLogged = false;
    
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

    /**
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function postProcess()
    {		
		$Cashfree = new Cashfree();
		$url = $Cashfree->returnSuccess($_REQUEST, true);
		if (isset($url)) Tools::redirect($url);
		exit;		                                
    }
    

}
