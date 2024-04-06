<?php
/**
 * CASHFREE
 *
 * @author CASHFREE
 */

if (!defined('_PS_VERSION_'))
    exit;

class OrderController extends OrderControllerCore
{
    public function initContent()
    {
        parent::initContent();
        // if a get variable 'error_msg' was defined, display error message
        if (Tools::getValue('error_msg'))
            $this->errors[] = Tools::displayError(Tools::getValue('error_msg'));
    }
}