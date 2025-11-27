<?php

namespace Opencart\Catalog\Controller\Extension\MtDskapiCredit\Payment;

class DskapiStart extends \Opencart\System\Engine\Controller
{
    public function index(): void
    {
        $this->load->language('extension/mt_dskapi_credit/payment/dskapi');

        $data['breadcrumbs'] = [
            [
                'text' => $this->language->get('text_home'),
                'href' => $this->url->link('common/home')
            ],
            [
                'text' => $this->language->get('text_title'),
                'href' => $this->url->link('extension/mt_dskapi_credit/payment/dskapi_start')
            ],
        ];

        $data['header'] = $this->load->controller('common/header');
        $data['footer'] = $this->load->controller('common/footer');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');

        $this->response->setOutput($this->load->view('extension/mt_dskapi_credit/payment/dskapi_start', $data));
    }
}
