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

    public function __construct(\Opencart\System\Engine\Registry $registry)
    {
        parent::__construct($registry);

        $this->loadDskapiDefaults();
    }

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
            $json['redirect'] = $this->url->link(
                'index.php?route=extension/mt_dskapi_credit/module/mt_dskapi_credit.index',
                'order_id=' . $this->session->data['order_id'],
                true
            );
            $json['success'] = 'success';
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
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

    private function resolveCartProductId(): int
    {
        $products = $this->cart->getProducts();

        if (empty($products)) {
            return 0;
        }

        $productIds = [];
        foreach ($products as $product) {
            if (!isset($product['product_id'])) {
                continue;
            }

            $productIds[(int) $product['product_id']] = true;
        }

        if (count($productIds) === 1) {
            return (int) array_key_first($productIds);
        }

        return 0;
    }

    private function isMobileDevice(): bool
    {
        $useragent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if (empty($useragent)) {
            return false;
        }

        $mobilePattern = '/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i';

        return (bool) preg_match($mobilePattern, $useragent)
            || (bool) preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i', substr($useragent, 0, 4));
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
