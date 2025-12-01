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
    private $event_order = 'extension/mt_dskapi_credit/event/mt_dskapi_credit_order';
    private $event_content_top = 'extension/mt_dskapi_credit/event/mt_dskapi_credit_content_top';

    /**
     * Displays the module settings form
     *
     * @return void
     */
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

    /**
     * Saves the module settings
     *
     * @return void
     */
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

    /**
     * Initializes event hooks for the module
     *
     * @return void
     */
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

        // Event hook за запазване на параметъра преди редиректа към логин (за всички страници)
        $this->model_setting_event->deleteEventByCode($this->module . '_save_dskapi_param');
        $this->model_setting_event->addEvent([
            'code' => $this->module . '_save_dskapi_param',
            'description' => $this->description . '- запазва параметър dskapi=1 в session преди редиректа към логин.',
            'trigger' => 'catalog/controller/common/header/before',
            'action' => $this->event_checkout . $dsk_separator . 'saveParam',
            'status' => true,
            'sort_order' => 0
        ]);

        $this->model_setting_event->deleteEventByCode($this->module . '_before_checkout');
        $this->model_setting_event->addEvent([
            'code' => $this->module . '_before_checkout',
            'description' => $this->description . '- запазва параметър dskapi=1 в session при зареждане на checkout страницата и възстановява параметъра ако липсва.',
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

        // Event hooks за админ панела - добавяне на банков статус в списъка с ордери
        $this->model_setting_event->deleteEventByCode($this->module . '_admin_order_list');
        $this->model_setting_event->addEvent([
            'code' => $this->module . '_admin_order_list',
            'description' => $this->description . '- добавя банков статус в списъка с ордери в админ панела.',
            'trigger' => 'admin/controller/sale/order/before',
            'action' => $this->event_order . $dsk_separator . 'addBankStatusToList',
            'status' => true,
            'sort_order' => 0
        ]);

        $this->model_setting_event->deleteEventByCode($this->module . '_admin_order_list_view');
        $this->model_setting_event->addEvent([
            'code' => $this->module . '_admin_order_list_view',
            'description' => $this->description . '- добавя колона "ДСК Банка Статус" в таблицата с ордери.',
            'trigger' => 'admin/view/sale/order_list/after',
            'action' => $this->event_order . $dsk_separator . 'addColumnToList',
            'status' => true,
            'sort_order' => 0
        ]);

        // Event hooks за админ панела - добавяне на банков статус в детайлния преглед на ордер
        $this->model_setting_event->deleteEventByCode($this->module . '_admin_order_info');
        $this->model_setting_event->addEvent([
            'code' => $this->module . '_admin_order_info',
            'description' => $this->description . '- добавя банков статус в детайлния преглед на ордер в админ панела.',
            'trigger' => 'admin/controller/sale/order.info/before',
            'action' => $this->event_order . $dsk_separator . 'addBankStatusToInfo',
            'status' => true,
            'sort_order' => 0
        ]);

        $this->model_setting_event->deleteEventByCode($this->module . '_admin_order_info_view');
        $this->model_setting_event->addEvent([
            'code' => $this->module . '_admin_order_info_view',
            'description' => $this->description . '- добавя поле "ДСК Банка Статус" в детайлния преглед на ордер.',
            'trigger' => 'admin/view/sale/order_info/after',
            'action' => $this->event_order . $dsk_separator . 'addFieldToInfo',
            'status' => true,
            'sort_order' => 0
        ]);

        // Event hooks за content_top - добавяне на рекламна информация на началната страница
        $this->model_setting_event->deleteEventByCode($this->module . '_before_content_top');
        $this->model_setting_event->addEvent([
            'code' => $this->module . '_before_content_top',
            'description' => $this->description . '- добавя данни за рекламна информация в content_top контролера.',
            'trigger' => 'catalog/controller/common/content_top/before',
            'action' => $this->event_content_top . $dsk_separator . 'init',
            'status' => true,
            'sort_order' => 0
        ]);

        $this->model_setting_event->deleteEventByCode($this->module . '_after_content_top_view');
        $this->model_setting_event->addEvent([
            'code' => $this->module . '_after_content_top_view',
            'description' => $this->description . '- добавя HTML за рекламна информация в content_top темплейта.',
            'trigger' => 'catalog/view/common/content_top/after',
            'action' => $this->event_content_top . $dsk_separator . 'addHtml',
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
            // Permissions за новия event hook за запазване на параметъра
            $this->model_user_user_group->addPermission($group['user_group_id'], 'access', $this->event_checkout);
            // Permissions за event hooks за админ панела
            $this->model_user_user_group->addPermission($group['user_group_id'], 'access', $this->event_order);
            // Permissions за event hooks за content_top
            $this->model_user_user_group->addPermission($group['user_group_id'], 'access', $this->event_content_top);
        }
    }

    /**
     * Installs the module - registers event hooks and creates necessary database tables
     *
     * @return void
     */
    public function install()
    {
        if ($this->user->hasPermission('modify', $this->path)) {
            $this->init();
        }

        $this->load->model('extension/mt_dskapi_credit/module/mt_dskapi_credit');
        $this->model_extension_mt_dskapi_credit_module_mt_dskapi_credit->install();
    }

    /**
     * Uninstalls the module - removes event hooks and drops database tables
     *
     * @return void
     */
    public function uninstall()
    {
        if ($this->user->hasPermission('modify', $this->path)) {
            $this->load->model('setting/event');
            $this->model_setting_event->deleteEventByCode($this->module . '_after_product_view');
            $this->model_setting_event->deleteEventByCode($this->module . '_before_product_controller');
            $this->model_setting_event->deleteEventByCode($this->module . '_before_cart_controller');
            $this->model_setting_event->deleteEventByCode($this->module . '_after_cart_view');
            $this->model_setting_event->deleteEventByCode($this->module . '_save_dskapi_param');
            $this->model_setting_event->deleteEventByCode($this->module . '_save_dskapi_param');
            $this->model_setting_event->deleteEventByCode($this->module . '_before_checkout');
            $this->model_setting_event->deleteEventByCode($this->module . '_after_checkout_view');
            $this->model_setting_event->deleteEventByCode($this->module . '_admin_order_list');
            $this->model_setting_event->deleteEventByCode($this->module . '_admin_order_list_view');
            $this->model_setting_event->deleteEventByCode($this->module . '_admin_order_info');
            $this->model_setting_event->deleteEventByCode($this->module . '_admin_order_info_view');
            $this->model_setting_event->deleteEventByCode($this->module . '_before_content_top');
            $this->model_setting_event->deleteEventByCode($this->module . '_after_content_top_view');
        }

        $this->load->model('extension/mt_dskapi_credit/module/mt_dskapi_credit');
        $this->model_extension_mt_dskapi_credit_module_mt_dskapi_credit->uninstall();
    }
}
