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

        // Order
        if (isset($this->session->data['order_id'])) {
            $this->load->model('checkout/order');

            $order_id = $this->session->data['order_id'];
            $order_info = $this->model_checkout_order->getOrder($order_id);

            if (!$order_info) {
                $json['redirect'] = $this->url->link('checkout/failure', 'language=' . $this->config->get('config_language'), true);

                unset($this->session->data['order_id']);
            }
        } else {
            $json['error'] = $this->language->get('error_order');
        }

        if (!isset($this->session->data['payment_method']) || $this->session->data['payment_method']['code'] != 'dskapi.dskapi') {
            $json['error'] = $this->language->get('error_payment_method');
        }

        if (!$json) {
            // Order
            $this->load->model('checkout/order');

            $this->model_checkout_order->addHistory($this->session->data['order_id'], $this->config->get('payment_dskapi_order_status_id'));

            $json['redirect'] = $this->url->link(
                'extension/mt_dskapi_credit/payment/dskapi_start',
                [
                    'language' => $this->config->get('config_language'),
                    'order_id' => $order_id
                ],
                true
            );
        }

        $this->response->setOutput(json_encode($json));
    }
}
