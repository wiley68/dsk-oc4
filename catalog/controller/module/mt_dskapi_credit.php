<?php

namespace Opencart\Catalog\Controller\Extension\MtDskapiCredit\Module;

/**
 * Class MtDskapiCredit
 *
 * @package Opencart\Catalog\Controller\Extension\MtDskapiCredit\Module
 */
class MtDskapiCredit extends \Opencart\System\Engine\Controller
{

    public function dskapiCheck()
    {
        $this->load->language('checkout/cart');

        $json = [];
        $this->load->model('catalog/product');
        $product_info = $this->model_catalog_product->getProduct($this->request->post['product_id']);
        if ($product_info) {
            if (isset($this->request->post['option'])) {
                $option = array_filter($this->request->post['option']);
            } else {
                $option = array();
            }
            $product_options = $this->model_catalog_product->getOptions($this->request->post['product_id']);
            $optionn = 0;
            foreach ($product_options as $product_option) {
                if ($product_option['required'] && empty($option[$product_option['product_option_id']])) {
                    $json['error']['option'][$product_option['product_option_id']] = sprintf($this->language->get('error_required'), $product_option['name']);
                } else {
                    $json['success'] = 1;
                    foreach ($product_option['product_option_value'] as $key => $value) {
                        $product_option['product_option_value'][$key]['price'] = $this->tax->calculate($product_option['product_option_value'][$key]['price'], $product_info['tax_class_id'], $this->config->get('config_tax'));
                    }
                    $json['optionresult'][$optionn] = $product_option;
                    $json['optionresult'][$optionn]['product_option_id_check'] = $option[$product_option['product_option_id']];
                    $optionn++;
                }
            }
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
}
