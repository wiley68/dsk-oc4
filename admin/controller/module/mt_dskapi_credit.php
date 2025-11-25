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
    private $description = 'DSK Credit покупки на Кредит';
    private string $path = 'extension/mt_dskapi_credit/module/mt_dskapi_credit';

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

    }

    public function install()
    {
        if ($this->user->hasPermission('modify', $this->path)) {
            $this->init();
        }
    }

    public function uninstall()
    {
        if ($this->user->hasPermission('modify', $this->path)) {

        }
    }
}
