<?php

namespace Opencart\Admin\Controller\Extension\MtDskapiCredit\Payment;

/**
 * Class Dskapi
 *
 * @package Opencart\Admin\Controller\Extension\MtDskapiCredit\Payment
 */
class Dskapi extends \Opencart\System\Engine\Controller
{

    public function index(): void
    {
        $this->load->language('extension/mt_dskapi_credit/payment/dskapi');
        $this->document->setTitle($this->language->get('heading_title'));

        $data['breadcrumbs'] = [];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
        ];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment')
        ];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/mt_dskapi_credit/payment/dskapi', 'user_token=' . $this->session->data['user_token'])
        ];

        $data['save'] = $this->url->link('extension/mt_dskapi_credit/payment/dskapi.save', 'user_token=' . $this->session->data['user_token']);
        $data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment');

        $this->load->model('localisation/order_status');
        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

        // Намиране на ID на статуса "Processing" по подразбиране
        $processing_status_id = 2; // Стандартен ID за Processing в OpenCart
        foreach ($data['order_statuses'] as $status) {
            if (strtolower($status['name']) === 'processing') {
                $processing_status_id = $status['order_status_id'];
                break;
            }
        }

        $data['payment_dskapi_order_status_id'] = $this->config->get('payment_dskapi_order_status_id') ?: $processing_status_id;

        $data['payment_dskapi_geo_zone_id'] = $this->config->get('payment_dskapi_geo_zone_id');
        $this->load->model('localisation/geo_zone');
        $data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

        $data['payment_dskapi_status'] = $this->config->get('payment_dskapi_status');

        $data['payment_dskapi_sort_order'] = $this->config->get('payment_dskapi_sort_order');

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/mt_dskapi_credit/payment/dskapi', $data));
    }

    public function save(): void
    {
        $this->load->language('extension/mt_dskapi_credit/payment/dskapi');

        $json = [];

        if (!$this->user->hasPermission('modify', 'extension/mt_dskapi_credit/payment/dskapi')) {
            $json['error'] = $this->language->get('error_permission');
        }

        if (!$json) {
            $this->load->model('setting/setting');
            $this->model_setting_setting->editSetting('payment_dskapi', $this->request->post);
            $json['success'] = $this->language->get('text_success');
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function install(): void
    {
        $this->load->model('localisation/order_status');
        $order_statuses = $this->model_localisation_order_status->getOrderStatuses();

        // Намиране на ID на статуса "Processing" по подразбиране
        $processing_status_id = 2; // Стандартен ID за Processing в OpenCart
        foreach ($order_statuses as $status) {
            if (strtolower($status['name']) === 'processing') {
                $processing_status_id = $status['order_status_id'];
                break;
            }
        }

        // Задаване на стойности по подразбиране при инсталиране
        $this->load->model('setting/setting');
        $default_settings = [
            'payment_dskapi_order_status_id' => $processing_status_id,
            'payment_dskapi_status' => 0,
            'payment_dskapi_sort_order' => 0
        ];
        $this->model_setting_setting->editSetting('payment_dskapi', $default_settings);
    }
}