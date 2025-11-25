<?php

namespace Opencart\Catalog\Controller\Extension\MtDskapiCredit\Payment;

/**
 * Class Dskapi
 *
 * @package Opencart\Catalog\Controller\Extension\MtDskapiCredit\Payment
 */
class Dskapi extends \Opencart\System\Engine\Controller
{
    public function index(): string
    {
        $this->load->language('extension/mt_dskapi_credit/payment/dskapi');
        $data['language'] = $this->config->get('config_language');

        return $this->load->view('extension/mt_dskapi_credit/payment/dskapi', $data);
    }

    public function confirm(): void
    {
        $this->load->language('extension/mt_dskapi_credit/payment/dskapi');

        $json = [];

        if (!isset($this->session->data['order_id'])) {
            $json['error'] = $this->language->get('error_order');
        }

        if (!isset($this->session->data['payment_method']) || $this->session->data['payment_method']['code'] != 'dskapi.dskapi') {
            $json['error'] = $this->language->get('error_payment_method');
        }

        if ($this->session->data['payment_method']['code'] == 'dskapi.dskapi') {
            $this->load->model('checkout/order');
            $this->model_checkout_order->addHistory($this->session->data['order_id'], $this->config->get('payment_dskapi_order_status_id'));
            $json['redirect'] = $this->url->link('checkout/success', 'language=' . $this->config->get('config_language'), true);
            $json['success'] = 'success';
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
}