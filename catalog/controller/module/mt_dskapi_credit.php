<?php

namespace Opencart\Catalog\Controller\Extension\MtDskapiCredit\Module;

/**
 * Class MtDskapiCredit
 *
 * Module controller for DSKAPI credit functionality - handles product option validation and order status updates
 *
 * @package Opencart\Catalog\Controller\Extension\MtDskapiCredit\Module
 */
class MtDskapiCredit extends \Opencart\System\Engine\Controller
{
    /**
     * DSKAPI live URL for API requests
     *
     * @var string
     */
    private string $dskapiLiveUrl = '';

    /**
     * Constructor - initializes DSKAPI defaults
     *
     * @param \Opencart\System\Engine\Registry $registry
     */
    public function __construct(\Opencart\System\Engine\Registry $registry)
    {
        parent::__construct($registry);

        $this->loadDskapiDefaults();
    }

    /**
     * Validates product options and returns JSON response with option data
     *
     * Checks if required product options are filled and calculates tax for option values
     *
     * @return void
     */
    public function dskapiCheck()
    {
        // Load language file for error messages
        $this->load->language('checkout/cart');

        $json = [];
        $this->load->model('catalog/product');
        $product_info = $this->model_catalog_product->getProduct($this->request->post['product_id']);

        if ($product_info) {
            // Get product options from POST data
            if (isset($this->request->post['option'])) {
                $option = array_filter($this->request->post['option']);
            } else {
                $option = array();
            }

            // Get all product options
            $product_options = $this->model_catalog_product->getOptions($this->request->post['product_id']);
            $optionn = 0;

            // Validate each product option
            foreach ($product_options as $product_option) {
                // Check if required option is missing
                if ($product_option['required'] && empty($option[$product_option['product_option_id']])) {
                    $json['error']['option'][$product_option['product_option_id']] = sprintf($this->language->get('error_required'), $product_option['name']);
                } else {
                    $json['success'] = 1;
                    // Calculate tax for each option value
                    foreach ($product_option['product_option_value'] as $key => $value) {
                        $product_option['product_option_value'][$key]['price'] = $this->tax->calculate($product_option['product_option_value'][$key]['price'], $product_info['tax_class_id'], $this->config->get('config_tax'));
                    }
                    // Add option result to response
                    $json['optionresult'][$optionn] = $product_option;
                    $json['optionresult'][$optionn]['product_option_id_check'] = $option[$product_option['product_option_id']];
                    $optionn++;
                }
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Updates order status in dskpayment_orders table
     *
     * API endpoint for updating order status from external DSKAPI control panel.
     * Only accepts POST requests and validates calculator_id before updating.
     *
     * @return void
     */
    public function updateOrder()
    {
        $json = array();
        $json['success'] = 'unsuccess';

        // Check if request method is POST
        if ($this->request->server['REQUEST_METHOD'] !== 'POST') {
            $json['message'] = 'Only POST method is allowed';
            $this->response->addHeader('Content-Type: application/json');
            $this->response->addHeader('Access-Control-Allow-Origin: ' . $this->dskapiLiveUrl);
            $this->response->setOutput(json_encode($json));
            return;
        }

        // Get calculator ID from configuration
        $dskapi_cid = $this->config->get('module_mt_dskapi_credit_cid');

        // Get order_id from POST data
        if (isset($this->request->post['order_id'])) {
            $dskapi_order_id = $this->request->post['order_id'];
        } else {
            $dskapi_order_id = '';
        }

        // Get status from POST data
        if (isset($this->request->post['status'])) {
            $dskapi_status = $this->request->post['status'];
        } else {
            $dskapi_status = 0;
        }

        // Get calculator_id from POST data
        if (isset($this->request->post['calculator_id'])) {
            $dskapi_calculator_id = $this->request->post['calculator_id'];
        } else {
            $dskapi_calculator_id = '';
        }

        // Validate calculator_id matches configuration
        if (($dskapi_calculator_id != '') && ($dskapi_cid == $dskapi_calculator_id)) {
            // Check if we have valid order_id and status
            if (!empty($dskapi_order_id) && isset($dskapi_status)) {
                $table_dskpayment_orders = DB_PREFIX . 'dskpayment_orders';

                // Check if record exists
                $checkQuery = $this->db->query("SELECT `order_id` FROM `$table_dskpayment_orders` WHERE `order_id` = '" . (int)$dskapi_order_id . "' LIMIT 1");

                if ($checkQuery->num_rows > 0) {
                    // If record exists, perform UPDATE
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

        // Include request data in response for debugging
        $json['dskapi_order_id'] = $dskapi_order_id;
        $json['dskapi_status'] = $dskapi_status;
        $json['dskapi_calculator_id'] = $dskapi_calculator_id;

        $this->response->addHeader('Content-Type: application/json');
        $this->response->addHeader('Access-Control-Allow-Origin: *');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Loads DSKAPI default configuration from system config file
     *
     * Uses static flag to ensure configuration is loaded only once per request
     *
     * @return void
     */
    private function loadDskapiDefaults(): void
    {
        static $loaded = false;

        // Return early if already loaded
        if ($loaded) {
            $this->dskapiLiveUrl = (string) $this->config->get('dskapi_liveurl');

            return;
        }

        $defaults = [];

        // Load configuration from system config file
        $configPath = dirname(__DIR__, 3) . '/system/config.php';

        if (is_file($configPath)) {
            $fileConfig = include $configPath;
            if (is_array($fileConfig)) {
                $defaults = array_merge($defaults, $fileConfig);
            }
        }

        // Set configuration values
        foreach ($defaults as $key => $value) {
            $this->config->set($key, $value);
        }

        // Store DSKAPI live URL
        $this->dskapiLiveUrl = (string) $this->config->get('dskapi_liveurl');

        $loaded = true;
    }
}
