<?php

namespace Opencart\Admin\Controller\Extension\MtDskapiCredit\Module;

/**
 * Class MtDskapiCredit
 *
 * @package Opencart\Admin\Controller\Extension\MtDskapiCredit\Module
 */
class MtDskapiCredit extends \Opencart\System\Engine\Controller
{
    private $module = 'module_mt_dskapi_credit';
    private $description = 'Банка ДСК покупки на Кредит';
    private string $path = 'extension/mt_dskapi_credit/module/mt_dskapi_credit';
    private $event_product_view = 'extension/mt_dskapi_credit/event/mt_dskapi_credit_product_view';
    private $event_product_controller = 'extension/mt_dskapi_credit/event/mt_dskapi_credit_product_controller';
    private $event_cart_controller = 'extension/mt_dskapi_credit/event/mt_dskapi_credit_cart_controller';
    private $event_cart_view = 'extension/mt_dskapi_credit/event/mt_dskapi_credit_cart_view';
    private $event_checkout = 'extension/mt_dskapi_credit/event/mt_dskapi_credit_checkout';

    public function index(): void
    {
        $this->load->language($this->path);
        $this->load->model('setting/extension');

        $this->document->setTitle(strip_tags($this->language->get('heading_title')));
        $this->description = strip_tags($this->language->get('text_description'));

        $data['breadcrumbs'] = [];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
        ];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module')
        ];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link($this->path, 'user_token=' . $this->session->data['user_token'])
        ];

        $data['save'] = $this->url->link($this->path . '|save', 'user_token=' . $this->session->data['user_token']);
        $data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module');

        $data[$this->module . '_status'] = $this->config->get($this->module . '_status');
        $data[$this->module . '_cid'] = $this->config->get($this->module . '_cid');
        $data[$this->module . '_reklama'] = $this->config->get($this->module . '_reklama');
        $data[$this->module . '_gap'] = $this->config->get($this->module . '_gap') ?: 0;
        $data[$this->module . '_gap_popup'] = $this->config->get($this->module . '_gap_popup') ?: 20;

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view($this->path, $data));
    }

    public function save(): void
    {
        $this->load->language($this->path);

        $json = [];

        if (!$this->user->hasPermission('modify', $this->path)) {
            $json['error'] = $this->language->get('error_permission');
        }

        if (isset($this->request->post[$this->module . '_cid'])) {
            $cr_cid = $this->request->post[$this->module . '_cid'];
        } else {
            $cr_cid = $this->config->get($this->module . '_cid');
        }
        if (trim($cr_cid) == '') {
            $json['error'] = $this->language->get('error_cid');
        }

        if (isset($this->request->post[$this->module . '_gap'])) {
            $cr_gap = $this->request->post[$this->module . '_gap'];
        } else {
            $cr_gap = $this->config->get($this->module . '_gap');
        }
        if (!is_numeric($cr_gap)) {
            $json['error'] = $this->language->get('error_gap');
        }

        if (isset($this->request->post[$this->module . '_gap_popup'])) {
            $cr_gap_popup = $this->request->post[$this->module . '_gap_popup'];
        } else {
            $cr_gap_popup = $this->config->get($this->module . '_gap_popup');
        }
        if (!is_numeric($cr_gap_popup)) {
            $json['error'] = $this->language->get('error_gap_popup');
        }

        if (!$json) {
            $this->init();
            $this->load->model('setting/setting');
            $this->model_setting_setting->editSetting($this->module, $this->request->post);
            $json['success'] = $this->language->get('text_success');
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    private function init(): void
    {
        $dsk_separator = (VERSION >= '4.0.2') ? '.' : '|';
        $this->load->model('setting/event');

        $this->model_setting_event->deleteEventByCode($this->module . '_before_product_controller');
        $this->model_setting_event->addEvent([
            'code' => $this->module . '_before_product_controller',
            'description' => $this->description . '- добавя код във продуктовия котролер.',
            'trigger' => 'catalog/controller/product/product/before',
            'action' => $this->event_product_controller . $dsk_separator . 'init',
            'status' => true,
            'sort_order' => 0
        ]);

        $this->model_setting_event->deleteEventByCode($this->module . '_after_product_view');
        $this->model_setting_event->addEvent([
            'code' => $this->module . '_after_product_view',
            'description' => $this->description . '- добавя код във продуктовия темплейт.',
            'trigger' => 'catalog/view/product/product/after',
            'action' => $this->event_product_view . $dsk_separator . 'init',
            'status' => true,
            'sort_order' => 0
        ]);

        $this->model_setting_event->deleteEventByCode($this->module . '_before_cart_controller');
        $this->model_setting_event->addEvent([
            'code' => $this->module . '_before_cart_controller',
            'description' => $this->description . '- добавя код във котролер количка.',
            'trigger' => 'catalog/controller/checkout/cart/before',
            'action' => $this->event_cart_controller . $dsk_separator . 'init',
            'status' => true,
            'sort_order' => 0
        ]);

        $this->model_setting_event->deleteEventByCode($this->module . '_after_cart_view');
        $this->model_setting_event->addEvent([
            'code' => $this->module . '_after_cart_view',
            'description' => $this->description . '- добавя код в темплейт количка.',
            'trigger' => 'catalog/view/checkout/cart/after',
            'action' => $this->event_cart_view . $dsk_separator . 'init',
            'status' => true,
            'sort_order' => 0
        ]);

        $this->model_setting_event->deleteEventByCode($this->module . '_before_checkout');
        $this->model_setting_event->addEvent([
            'code' => $this->module . '_before_checkout',
            'description' => $this->description . '- запазва параметър dskapi=1 в session при зареждане на checkout страницата.',
            'trigger' => 'catalog/controller/checkout/checkout/before',
            'action' => $this->event_checkout . $dsk_separator . 'init',
            'status' => true,
            'sort_order' => 0
        ]);

        $this->model_setting_event->deleteEventByCode($this->module . '_after_checkout_view');
        $this->model_setting_event->addEvent([
            'code' => $this->module . '_after_checkout_view',
            'description' => $this->description . '- добавя JavaScript код за автоматично избиране на payment метода dskapi.',
            'trigger' => 'catalog/view/checkout/checkout/after',
            'action' => $this->event_checkout . $dsk_separator . 'addScript',
            'status' => true,
            'sort_order' => 0
        ]);

        $this->load->model('user/user_group');
        $groups = $this->model_user_user_group->getUserGroups();

        foreach ($groups as $group) {
            $this->model_user_user_group->addPermission($group['user_group_id'], 'access', $this->event_product_view);
            $this->model_user_user_group->addPermission($group['user_group_id'], 'access', $this->event_product_controller);
            $this->model_user_user_group->addPermission($group['user_group_id'], 'access', $this->event_cart_controller);
            $this->model_user_user_group->addPermission($group['user_group_id'], 'access', $this->event_cart_view);
            $this->model_user_user_group->addPermission($group['user_group_id'], 'access', $this->event_checkout);
        }
    }

    public function install()
    {
        if ($this->user->hasPermission('modify', $this->path)) {
            $this->init();
        }

        $this->load->model('extension/mt_dskapi_credit/module/mt_dskapi_credit');
        $this->model_extension_mt_dskapi_credit_module_mt_dskapi_credit->install();
    }

    public function uninstall()
    {
        if ($this->user->hasPermission('modify', $this->path)) {
            $this->load->model('setting/event');
            $this->model_setting_event->deleteEventByCode($this->module . '_after_product_view');
            $this->model_setting_event->deleteEventByCode($this->module . '_before_product_controller');
            $this->model_setting_event->deleteEventByCode($this->module . '_before_cart_controller');
            $this->model_setting_event->deleteEventByCode($this->module . '_after_cart_view');
            $this->model_setting_event->deleteEventByCode($this->module . '_before_checkout');
            $this->model_setting_event->deleteEventByCode($this->module . '_after_checkout_view');
        }

        $this->load->model('extension/mt_dskapi_credit/module/mt_dskapi_credit');
        $this->model_extension_mt_dskapi_credit_module_mt_dskapi_credit->uninstall();
    }
}
