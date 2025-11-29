<?php

namespace Opencart\Catalog\Controller\Extension\MtDskapiCredit\Event;

/**
 * Class MtDskapiCreditProductController
 *
 * @package Opencart\Catalog\Controller\Extension\MtDskapiCredit\Event
 */
class MtDskapiCreditProductController extends \Opencart\System\Engine\Controller
{
    private $module = 'module_mt_dskapi_credit';

    public function init(&$route, &$data): void
    {
        if ($route == 'product/product') {
            if ($this->config->get($this->module . '_status')) {
                $this->document->addStyle('extension/mt_dskapi_credit/catalog/view/stylesheet/dskapi_products.css?ver=' . filemtime(DIR_EXTENSION . 'mt_dskapi_credit/catalog/view/stylesheet/dskapi_products.css'));
                $this->document->addScript('extension/mt_dskapi_credit/catalog/view/javascript/dskapi_products.js?ver=' . filemtime(DIR_EXTENSION . 'mt_dskapi_credit/catalog/view/javascript/dskapi_products.js'));
            }
        }
    }
}
