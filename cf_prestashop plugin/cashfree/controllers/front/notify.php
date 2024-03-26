<?php
class CashfreeNotifyModuleFrontController extends ModuleFrontController
{

  public $ssl = true;

    public $isLogged = false;

    /**
     * @throws PrestaShopException
     */
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
        sleep(30);
        $cashfree = new Cashfree();
        $cashfree->returnSuccess($_POST, "Notify Url");
		echo "OK";				
		exit;		                                
    }
    

}
