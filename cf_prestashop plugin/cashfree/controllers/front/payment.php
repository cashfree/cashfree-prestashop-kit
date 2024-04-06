<?php
/**
 * CASHFREE
 *
 * @author CASHFREE
 */

if (!defined('_PS_VERSION_'))
    exit;

class CashfreePaymentModuleFrontController extends ModuleFrontController
{

    public $ssl = true;

    public $isLogged = false;

    public $service;

    public function __construct()
    {

        $this->controller_type = 'modulefront';

        $this->module = Module::getInstanceByName(Tools::getValue('module'));
        if (!$this->module->active) {
            Tools::redirect('index');
        }
        $this->page_name = 'module-' . $this->module->name . '-' . Dispatcher::getInstance()->getController();


        parent::__construct();
    }

    /**
     * @return void
     */
    public function postProcess()
    {

        $Cashfree = new Cashfree();
        $url = $Cashfree->getUrl();
        Tools::redirect(Tools::safeOutput($url, true));

    }


}
