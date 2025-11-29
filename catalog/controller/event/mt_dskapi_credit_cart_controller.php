<?php

namespace Opencart\Catalog\Controller\Extension\MtDskapiCredit\Event;

/**
 * Class MtDskapiCreditCartController
 *
 * @package Opencart\Catalog\Controller\Extension\MtDskapiCredit\Event
 */
class MtDskapiCreditCartController extends \Opencart\System\Engine\Controller
{
    private $module = 'module_mt_dskapi_credit';

    /**
     * Initializes the cart controller event - loads CSS and JavaScript files for the cart page
     *
     * @param string &$route
     * @param array &$data
     * @return void
     */
    public function init(&$route, &$data): void
    {
        if ($route == 'checkout/cart') {
            if ($this->config->get($this->module . '_status')) {
                $this->document->addStyle('extension/mt_dskapi_credit/catalog/view/stylesheet/dskapi_cart.css?ver=' . filemtime(DIR_EXTENSION . 'mt_dskapi_credit/catalog/view/stylesheet/dskapi_cart.css'));
                $this->document->addScript('extension/mt_dskapi_credit/catalog/view/javascript/dskapi_cart.js?ver=' . filemtime(DIR_EXTENSION . 'mt_dskapi_credit/catalog/view/javascript/dskapi_cart.js'));
            }
        }
    }
}
