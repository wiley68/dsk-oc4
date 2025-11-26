<?php

namespace Opencart\Catalog\Controller\Extension\MtDskapiCredit\Module;

/**
 * Class MtDskapiCredit
 *
 * @package Opencart\Catalog\Controller\Extension\MtDskapiCredit\Module
 */
class MtDskapiCredit extends \Opencart\System\Engine\Controller
{
    private string $module = 'module_mt_dskapi_credit';
    private string $dskapiLiveUrl = '';

    public function __construct(\Opencart\System\Engine\Registry $registry)
    {
        parent::__construct($registry);

        $this->loadDskapiDefaults();
    }
    public function index()
    {
        $this->load->language('extension/mt_dskapi_credit/module/mt_dskapi_credit');
        $installInfo = json_decode(
            file_get_contents(DIR_EXTENSION . 'mt_dskapi_credit/install.json'),
            true
        );
        $dskapi_version = $installInfo['version'] ?? '';
        $dskapi_cid = $this->config->get($this->module . '_cid');
        $order_id = isset($this->request->get['order_id']) ? (string) $this->request->get['order_id'] : 0;
    }

    /**
     * Извършва API заявка и връща декодирания JSON отговор
     *
     * @param string $endpoint API endpoint (без базовия URL)
     * @param int $timeout Timeout в секунди
     * @return array|null Декодираният JSON отговор или null при грешка
     */
    private function makeApiRequest(string $endpoint, int $timeout = 5): ?array
    {
        $dskapiLiveUrl = $this->dskapiLiveUrl ?: (string) $this->config->get('dskapi_liveurl');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 2);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_URL, $dskapiLiveUrl . $endpoint);

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
