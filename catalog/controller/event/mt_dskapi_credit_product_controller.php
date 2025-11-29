<?php

namespace Opencart\Catalog\Controller\Extension\MtDskapiCredit\Event;

/**
 * Class MtDskapiCreditProductController
 *
 * Event controller for product page - loads CSS and JavaScript files for DSKAPI credit module
 *
 * @package Opencart\Catalog\Controller\Extension\MtDskapiCredit\Event
 */
class MtDskapiCreditProductController extends \Opencart\System\Engine\Controller
{
    /**
     * Module code identifier
     *
     * @var string
     */
    private $module = 'module_mt_dskapi_credit';

    /**
     * Initializes the event hook - loads CSS and JavaScript files for product page
     *
     * @param string &$route The route being processed
     * @param array &$data The data array being passed to the view
     * @return void
     */
    public function init(&$route, &$data): void
    {
        if ($route == 'product/product') {
            if ($this->config->get($this->module . '_status')) {
                // Add CSS file with version cache busting
                $this->document->addStyle('extension/mt_dskapi_credit/catalog/view/stylesheet/dskapi_products.css?ver=' . filemtime(DIR_EXTENSION . 'mt_dskapi_credit/catalog/view/stylesheet/dskapi_products.css'));
                // Add JavaScript file with version cache busting
                $this->document->addScript('extension/mt_dskapi_credit/catalog/view/javascript/dskapi_products.js?ver=' . filemtime(DIR_EXTENSION . 'mt_dskapi_credit/catalog/view/javascript/dskapi_products.js'));
            }
        }
    }
}
