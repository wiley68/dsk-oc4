<?php

namespace Opencart\Catalog\Controller\Extension\MtDskapiCredit\Payment;

/**
 * Class Dskapi
 *
 * Payment method controller for DSKAPI credit - displays payment option and handles order confirmation
 *
 * @package Opencart\Catalog\Controller\Extension\MtDskapiCredit\Payment
 */
class Dskapi extends \Opencart\System\Engine\Controller
{
    /**
     * DSKAPI live URL for API requests
     *
     * @var string
     */
    private string $dskapiLiveUrl = '';

    /**
     * Module code identifier
     *
     * @var string
     */
    private $module = 'module_mt_dskapi_credit';

    /**
     * Constructor - initializes DSKAPI defaults
     *
     * @param \Opencart\System\Engine\Registry $registry
     */
    public function __construct(\Opencart\System\Engine\Registry $registry)
    {
        parent::__construct($registry);

        $this->loadDskapiDefaults();
    }

    /**
     * Displays the payment method option in checkout
     *
     * Loads CSS/JS, prepares data for interest schemes popup, and validates payment eligibility
     *
     * @return string Rendered payment method view
     */
    public function index(): string
    {
        $this->load->language('extension/mt_dskapi_credit/payment/dskapi');

        // Note: CSS and JavaScript are loaded inline in the template
        // because OpenCart 4.x loads payment methods via AJAX and external files don't execute

        $data['language'] = $this->config->get('config_language');

        // Load module version from install.json
        $installInfo = json_decode(
            file_get_contents(DIR_EXTENSION . 'mt_dskapi_credit/install.json'),
            true
        );
        $dskapi_version = $installInfo['version'] ?? '';

        // Get module configuration and API settings
        $dskapi_cid = $this->config->get($this->module . '_cid');
        $dskapi_status = $this->config->get($this->module . '_status');
        $paramsdskapi = $this->makeApiRequest('/function/getminmax.php?cid=' . $dskapi_cid);
        if (!$paramsdskapi || !is_array($paramsdskapi) || empty($paramsdskapi)) {
            return '';
        }

        // Get order information
        $this->load->model('checkout/order');
        $order_id = $this->session->data['order_id'];
        $order_info = $this->model_checkout_order->getOrder($order_id);
        $dskapi_price = (float) $order_info['total'];

        // Set minimum and maximum order amounts
        $data['dskapi_minstojnost'] = (float) $paramsdskapi['dsk_minstojnost'];
        $data['dskapi_maxstojnost'] = (float) $paramsdskapi['dsk_maxstojnost'];
        $data['dskapi_min_000'] = (float) $paramsdskapi['dsk_min_000'];
        $data['dskapi_status_cp'] = (int) $paramsdskapi['dsk_status'];
        $data['dskapi_status'] = $dskapi_status;
        $data['dskapi_price'] = number_format($dskapi_price, 2, ".", "");

        // Adjust minimum amount if percentage is 0 and default installments <= 6
        $dskapi_purcent = (float) $paramsdskapi['dsk_purcent'];
        $dskapi_vnoski_default = (int) $paramsdskapi['dsk_vnoski_default'];
        if (($dskapi_purcent == 0) && ($dskapi_vnoski_default <= 6)) {
            $data['dskapi_minstojnost'] = (float) $data['dskapi_min_000'];
        }

        // Check if payment is valid (status enabled, API status active, price within range)
        $data['dskapi_is_valid'] = (
            $dskapi_status &&
            $data['dskapi_status_cp'] != 0 &&
            $dskapi_price >= $data['dskapi_minstojnost'] &&
            $dskapi_price <= $data['dskapi_maxstojnost']
        );

        $data['DSKAPI_LIVEURL'] = $this->dskapiLiveUrl ?: (string) $this->config->get('dskapi_liveurl');
        $data['dskapi_cid'] = $dskapi_cid;
        // dskapi_price is already formatted on line 58, do not overwrite it

        // Prepare data for popup (same as in cart page)
        $dskapiLiveUrl = $this->dskapiLiveUrl ?: (string) $this->config->get('dskapi_liveurl');
        $dskapi_currency_code = $this->session->data['currency'] ?? $this->config->get('config_currency');
        $dskapi_product_id = $this->resolveCartProductId();

        // Determine currency sign
        $dskapi_sign = 'лв.';
        $paramsdskapieur = $this->makeApiRequest('/function/geteur.php?cid=' . urlencode($dskapi_cid));
        if ($paramsdskapieur !== null) {
            $dskapi_eur = (int) $paramsdskapieur['dsk_eur'];
            // Set currency sign based on EUR setting
            // 0 = no conversion, 1 = BGN, 2 = EUR
            switch ($dskapi_eur) {
                case 0:
                    // No conversion needed
                    break;
                case 1:
                    // BGN currency
                    $dskapi_sign = 'лв.';
                    break;
                case 2:
                    // EUR currency
                    $dskapi_sign = 'евро';
                    break;
            }
        }
        $data['dskapi_sign'] = $dskapi_sign;
        $data['dskapi_eur'] = $paramsdskapieur !== null ? (int) $paramsdskapieur['dsk_eur'] : 0;
        $data['dskapi_currency_code'] = $dskapi_currency_code;
        $data['dskapi_product_id'] = $dskapi_product_id;

        // Load data for popup
        $paramsdskapi = $this->makeApiRequest('/function/getproduct.php?cid=' . urlencode($dskapi_cid) . '&price=' . urlencode($dskapi_price) . '&product_id=' . urlencode($dskapi_product_id));

        if ($paramsdskapi) {
            // Extract installment and payment data from API response
            $data['dskapi_vnoski'] = (int) ($paramsdskapi['dsk_vnoski_default'] ?? 0);
            $data['dskapi_vnoska'] = number_format((float) ($paramsdskapi['dsk_vnoska'] ?? 0), 2, ".", "");
            $data['dskapi_vnoski_visible'] = (int) ($paramsdskapi['dsk_vnoski_visible'] ?? 0);
            // Calculate total payment amount (monthly payment * number of installments)
            $data['obshtozaplashtane'] = number_format((float) ($paramsdskapi['dsk_vnoska'] ?? 0) * (float) ($paramsdskapi['dsk_vnoski_default'] ?? 0), 2, ".", "");
            $data['dskapi_gpr'] = number_format((float) ($paramsdskapi['dsk_gpr'] ?? 0), 2, ".", "");

            // Set CSS classes and image paths based on device type (mobile or desktop)
            if ($this->isMobileDevice()) {
                // Mobile-specific CSS classes and images
                $data['dskapi_PopUp_Detailed_v1'] = "dskapim_PopUp_Detailed_v1";
                $data['dskapi_Mask'] = "dskapim_Mask";
                $data['dskapi_picture'] = $dskapiLiveUrl . '/calculators/assets/img/dskm' . ($paramsdskapi['dsk_reklama'] ?? '1') . '.png';
                $data['dskapi_product_name'] = "dskapim_product_name";
                $data['dskapi_body_panel_txt3'] = "dskapim_body_panel_txt3";
                $data['dskapi_body_panel_txt4'] = "dskapim_body_panel_txt4";
                $data['dskapi_body_panel_txt3_left'] = "dskapim_body_panel_txt3_left";
                $data['dskapi_body_panel_txt3_right'] = "dskapim_body_panel_txt3_right";
                $data['dskapi_sumi_panel'] = "dskapim_sumi_panel";
                $data['dskapi_kredit_panel'] = "dskapim_kredit_panel";
                $data['dskapi_body_panel_footer'] = "dskapim_body_panel_footer";
                $data['dskapi_body_panel_left'] = "dskapim_body_panel_left";
            } else {
                // Desktop-specific CSS classes and images
                $data['dskapi_PopUp_Detailed_v1'] = "dskapi_PopUp_Detailed_v1";
                $data['dskapi_Mask'] = "dskapi_Mask";
                $data['dskapi_picture'] = $dskapiLiveUrl . '/calculators/assets/img/dsk' . ($paramsdskapi['dsk_reklama'] ?? '1') . '.png';
                $data['dskapi_product_name'] = "dskapi_product_name";
                $data['dskapi_body_panel_txt3'] = "dskapi_body_panel_txt3";
                $data['dskapi_body_panel_txt4'] = "dskapi_body_panel_txt4";
                $data['dskapi_body_panel_txt3_left'] = "dskapi_body_panel_txt3_left";
                $data['dskapi_body_panel_txt3_right'] = "dskapi_body_panel_txt3_right";
                $data['dskapi_sumi_panel'] = "dskapi_sumi_panel";
                $data['dskapi_kredit_panel'] = "dskapi_kredit_panel";
                $data['dskapi_body_panel_footer'] = "dskapi_body_panel_footer";
                $data['dskapi_body_panel_left'] = "dskapi_body_panel_left";
            }
        } else {
            // Fallback values if API request fails
            $data['dskapi_vnoski'] = 0;
            $data['dskapi_vnoska'] = '0.00';
            $data['dskapi_vnoski_visible'] = 0;
            $data['obshtozaplashtane'] = '0.00';
            $data['dskapi_gpr'] = '0.00';
            $data['dskapi_PopUp_Detailed_v1'] = "dskapi_PopUp_Detailed_v1";
            $data['dskapi_Mask'] = "dskapi_Mask";
            $data['dskapi_picture'] = $dskapiLiveUrl . '/calculators/assets/img/dsk1.png';
            $data['dskapi_product_name'] = "dskapi_product_name";
            $data['dskapi_body_panel_txt3'] = "dskapi_body_panel_txt3";
            $data['dskapi_body_panel_txt4'] = "dskapi_body_panel_txt4";
            $data['dskapi_body_panel_txt3_left'] = "dskapi_body_panel_txt3_left";
            $data['dskapi_body_panel_txt3_right'] = "dskapi_body_panel_txt3_right";
            $data['dskapi_sumi_panel'] = "dskapi_sumi_panel";
            $data['dskapi_kredit_panel'] = "dskapi_kredit_panel";
            $data['dskapi_body_panel_footer'] = "dskapi_body_panel_footer";
            $data['dskapi_body_panel_left'] = "dskapi_body_panel_left";
        }

        // Set gap setting for popup positioning
        $data['dskapi_gap_popup'] = $this->config->get($this->module . '_gap_popup') ?? 0;

        $data['DSKAPI_VERSION'] = $dskapi_version;

        // Generate absolute path for font files (for inline CSS)
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $data['dskapi_fonts_path'] = $protocol . $host . '/extension/mt_dskapi_credit/catalog/view/stylesheet/';

        return $this->load->view('extension/mt_dskapi_credit/payment/dskapi', $data);
    }

    /**
     * Confirms the order and redirects to payment processing
     *
     * Validates order and payment method, updates order status, and redirects to dskapi_start
     *
     * @return void
     */
    public function confirm(): void
    {
        $this->load->language('extension/mt_dskapi_credit/payment/dskapi');

        $json = [];

        // Validate order exists
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

        // Validate payment method is DSKAPI
        if (!isset($this->session->data['payment_method']) || $this->session->data['payment_method']['code'] != 'dskapi.dskapi') {
            $json['error'] = $this->language->get('error_payment_method');
        }

        if (!$json) {
            // Update order status
            $this->load->model('checkout/order');

            $this->model_checkout_order->addHistory($this->session->data['order_id'], $this->config->get('payment_dskapi_order_status_id'));

            // Redirect to payment processing page
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

    /**
     * Loads DSKAPI default configuration from system config file
     *
     * Uses static flag to ensure configuration is loaded only once per request
     *
     * @return void
     */
    private function loadDskapiDefaults(): void
    {
        static $loaded = false;

        // Return early if already loaded
        if ($loaded) {
            $this->dskapiLiveUrl = (string) $this->config->get('dskapi_liveurl');

            return;
        }

        $defaults = [];

        // Load configuration from system config file
        $configPath = dirname(__DIR__, 3) . '/system/config.php';

        if (is_file($configPath)) {
            $fileConfig = include $configPath;
            if (is_array($fileConfig)) {
                $defaults = array_merge($defaults, $fileConfig);
            }
        }

        // Set configuration values
        foreach ($defaults as $key => $value) {
            $this->config->set($key, $value);
        }

        // Store DSKAPI live URL
        $this->dskapiLiveUrl = (string) $this->config->get('dskapi_liveurl');

        $loaded = true;
    }

    /**
     * Makes an API request and returns the decoded JSON response
     *
     * @param string $endpoint API endpoint (without base URL)
     * @param int $timeout Timeout in seconds
     * @param array $data Optional POST data to send with request
     * @return array|null Decoded JSON response or null on error
     */
    private function makeApiRequest(string $endpoint, int $timeout = 5, array $data = []): ?array
    {
        $dskapiLiveUrl = $this->dskapiLiveUrl ?: (string) $this->config->get('dskapi_liveurl');

        // Initialize cURL request
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 2);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_URL, $dskapiLiveUrl . $endpoint);

        // Add POST data if provided
        if (!empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'cache-control: no-cache'
            ]);
        }

        // Execute request and get response
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        // Validate response
        if ($response === false || $httpCode !== 200 || !empty($curlError)) {
            return null;
        }

        // Decode JSON response
        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    /**
     * Определя product_id на количката. Ако има само един продукт, връща неговото ID, иначе 0.
     *
     * @return int
     */
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

    /**
     * Detects whether the current visitor uses a mobile device
     *
     * @return bool True if mobile device detected, false otherwise
     */
    private function isMobileDevice(): bool
    {
        // Get user agent string
        $useragent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if (empty($useragent)) {
            return false;
        }

        // Pattern to match common mobile device user agents
        $mobilePattern = '/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i';

        // Check if user agent matches mobile patterns
        return (bool) preg_match($mobilePattern, $useragent)
            || (bool) preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i', substr($useragent, 0, 4));
    }
}
