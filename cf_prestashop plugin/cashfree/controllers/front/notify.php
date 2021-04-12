<?php
class CashfreeNotifyModuleFrontController extends ModuleFrontController
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
    sleep(30);
    $Cashfree = new Cashfree();
		$url = $Cashfree->returnsuccess($_POST, "Notify Url");
		echo "OK";				
		exit;		                                
    }
    

}
