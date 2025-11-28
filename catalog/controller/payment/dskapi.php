<?php

namespace Opencart\Catalog\Controller\Extension\MtDskapiCredit\Payment;


/**
 * Class Dskapi
 *
 * @package Opencart\Catalog\Controller\Extension\MtDskapiCredit\Payment
 */
class Dskapi extends \Opencart\System\Engine\Controller
{
    private string $dskapiLiveUrl = '';
    private $module = 'module_mt_dskapi_credit';

    public function __construct(\Opencart\System\Engine\Registry $registry)
    {
        parent::__construct($registry);

        $this->loadDskapiDefaults();
    }

    public function index(): string
    {
        $this->load->language('extension/mt_dskapi_credit/payment/dskapi');
        $data['language'] = $this->config->get('config_language');

        $installInfo = json_decode(
            file_get_contents(DIR_EXTENSION . 'mt_dskapi_credit/install.json'),
            true
        );
        $dskapi_version = $installInfo['version'] ?? '';

        $dskapi_cid = $this->config->get($this->module . '_cid');
        $dskapi_status = $this->config->get($this->module . '_status');
        $paramsdskapi = $this->makeApiRequest('/function/getminmax.php?cid=' . $dskapi_cid);

        $this->load->model('checkout/order');
        $order_id = $this->session->data['order_id'];
        $order_info = $this->model_checkout_order->getOrder($order_id);
        $dskapi_price = (float) $order_info['total'];

        $data['dskapi_minstojnost'] = (float) $paramsdskapi['dsk_minstojnost'];
        $data['dskapi_maxstojnost'] = (float) $paramsdskapi['dsk_maxstojnost'];
        $data['dskapi_min_000'] = (float) $paramsdskapi['dsk_min_000'];
        $data['dskapi_status_cp'] = (int) $paramsdskapi['dsk_status'];
        $data['dskapi_status'] = $dskapi_status;
        $data['dskapi_price'] = $dskapi_price;

        $dskapi_purcent = (float) $paramsdskapi['dsk_purcent'];
        $dskapi_vnoski_default = (int) $paramsdskapi['dsk_vnoski_default'];
        if (($dskapi_purcent == 0) && ($dskapi_vnoski_default <= 6)) {
            $data['dskapi_minstojnost'] = (float) $data['dskapi_min_000'];
        }

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

    /**
     * Извършва API заявка и връща декодирания JSON отговор
     *
     * @param string $endpoint API endpoint (без базовия URL)
     * @param int $timeout Timeout в секунди
     * @return array|null Декодираният JSON отговор или null при грешка
     */
    private function makeApiRequest(string $endpoint, int $timeout = 5, array $data = []): ?array
    {
        $dskapiLiveUrl = $this->dskapiLiveUrl ?: (string) $this->config->get('dskapi_liveurl');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 2);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_URL, $dskapiLiveUrl . $endpoint);
        if (!empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'cache-control: no-cache'
            ]);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpCode !== 200 || !empty($curlError)) {
            return null;
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return null;
        }

        return $decoded;
    }
}
