<?php

namespace Opencart\Catalog\Controller\Extension\MtDskapiCredit\Module;

/**
 * Class MtDskapiCredit
 *
 * @package Opencart\Catalog\Controller\Extension\MtDskapiCredit\Module
 */
class MtDskapiCredit extends \Opencart\System\Engine\Controller
{
    private string $dskapiLiveUrl = '';

    public function __construct(\Opencart\System\Engine\Registry $registry)
    {
        parent::__construct($registry);

        $this->loadDskapiDefaults();
    }

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

    public function updateOrder()
    {
        $json = array();
        $json['success'] = 'unsuccess';

        // Проверяваме дали заявката е POST метод
        if ($this->request->server['REQUEST_METHOD'] !== 'POST') {
            $json['message'] = 'Only POST method is allowed';
            $this->response->addHeader('Content-Type: application/json');
            $this->response->addHeader('Access-Control-Allow-Origin: ' . $this->dskapiLiveUrl);
            $this->response->setOutput(json_encode($json));
            return;
        }

        $dskapi_cid = $this->config->get('module_mt_dskapi_credit_cid');

        if (isset($this->request->post['order_id'])) {
            $dskapi_order_id = $this->request->post['order_id'];
        } else {
            $dskapi_order_id = '';
        }

        if (isset($this->request->post['status'])) {
            $dskapi_status = $this->request->post['status'];
        } else {
            $dskapi_status = 0;
        }

        if (isset($this->request->post['calculator_id'])) {
            $dskapi_calculator_id = $this->request->post['calculator_id'];
        } else {
            $dskapi_calculator_id = '';
        }

        if (($dskapi_calculator_id != '') && ($dskapi_cid == $dskapi_calculator_id)) {
            // Проверяваме дали имаме валиден order_id и status
            if (!empty($dskapi_order_id) && isset($dskapi_status)) {
                $table_dskpayment_orders = DB_PREFIX . 'dskpayment_orders';

                // Проверяваме дали записът съществува
                $checkQuery = $this->db->query("SELECT `order_id` FROM `$table_dskpayment_orders` WHERE `order_id` = '" . (int)$dskapi_order_id . "' LIMIT 1");

                if ($checkQuery->num_rows > 0) {
                    // Ако записът съществува, извършваме UPDATE
                    $this->db->query("UPDATE `$table_dskpayment_orders` 
                        SET `order_status` = '" . (int)$dskapi_status . "', 
                            `updated_at` = NOW() 
                        WHERE `order_id` = '" . (int)$dskapi_order_id . "'");

                    $json['success'] = 'success';
                    $json['message'] = 'Order status updated successfully';
                } else {
                    $json['success'] = 'unsuccess';
                    $json['message'] = 'Order not found';
                }
            } else {
                $json['success'] = 'unsuccess';
                $json['message'] = 'Invalid order_id or status';
            }
        } else {
            $json['success'] = 'unsuccess';
            $json['message'] = 'Invalid calculator_id';
        }

        $json['dskapi_order_id'] = $dskapi_order_id;
        $json['dskapi_status'] = $dskapi_status;
        $json['dskapi_calculator_id'] = $dskapi_calculator_id;

        $this->response->addHeader('Content-Type: application/json');
        $this->response->addHeader('Access-Control-Allow-Origin: *');
        $this->response->setOutput(json_encode($json));
    }

    private function loadDskapiDefaults(): void
    {
        static $loaded = false;

        if ($loaded) {
            $this->dskapiLiveUrl = (string) $this->config->get('dskapi_liveurl');

            return;
        }

        $defaults = [];

        $configPath = dirname(__DIR__, 3) . '/system/config.php';

        if (is_file($configPath)) {
            $fileConfig = include $configPath;
            if (is_array($fileConfig)) {
                $defaults = array_merge($defaults, $fileConfig);
            }
        }

        foreach ($defaults as $key => $value) {
            $this->config->set($key, $value);
        }

        $this->dskapiLiveUrl = (string) $this->config->get('dskapi_liveurl');

        $loaded = true;
    }
}
