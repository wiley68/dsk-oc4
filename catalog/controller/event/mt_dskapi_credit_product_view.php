<?php

namespace Opencart\Catalog\Controller\Extension\MtDskapiCredit\Event;

/**
 * Class MtDskapiCreditProductView
 *
 * @package Opencart\Catalog\Controller\Extension\MtDskapiCredit\Event
 */
class MtDskapiCreditProductView extends \Opencart\System\Engine\Controller
{

    private $path = 'extension/mt_dskapi_credit/module/mt_dskapi_credit';
    private $module = 'module_mt_dskapi_credit';
    private string $dskapiLiveUrl = '';

    public function __construct(\Opencart\System\Engine\Registry $registry)
    {
        parent::__construct($registry);

        $this->loadDskapiDefaults();
    }

    public function init(&$route, &$data, &$output): void
    {
        if (!$this->config->get($this->module . '_status')) {
            return;
        }

        if (isset($this->request->get['product_id'])) {
            $data['dskapi_product_id'] = (int) $this->request->get['product_id'];
        } else {
            $data['dskapi_product_id'] = 0;
        }
        $data['base_currency'] = $this->config->get('config_currency');
        $data['current_currency'] = $this->session->data['currency'];
        $currency_rates_array = [
            'BGN' => $this->currency->getValue('BGN'),
            'EUR' => $this->currency->getValue('EUR'),
        ];
        $data['currency_rates'] = json_encode($currency_rates_array);
        $config_customer_price = $this->config->get('config_customer_price');
        if (($config_customer_price && $this->customer->isLogged()) || !$config_customer_price) {
            $this->load->model('catalog/product');
            $product_info = $this->model_catalog_product->getProduct($data['dskapi_product_id']);
            $dskapi_currency_code = $this->session->data['currency'];
            $raw_price = (float) $product_info['special'] ?: (float) $product_info['price'];
            $taxed_price = $this->tax->calculate($raw_price, $product_info['tax_class_id'], $this->config->get('config_tax'));
            $dskapi_price = $this->currency->convert($taxed_price, $this->config->get('config_currency'), $dskapi_currency_code);
        } else {
            $dskapi_price = 0;
        }
        $dskapiLiveUrl = $this->dskapiLiveUrl ?: (string) $this->config->get('dskapi_liveurl');

        $data['DSKAPI_LIVEURL'] = $dskapiLiveUrl;
        $dskapi_cid = $this->config->get($this->module . '_cid');
        $data['dskapi_cid'] = $dskapi_cid;

        if ($dskapi_currency_code != 'EUR' && $dskapi_currency_code != 'BGN') {
            return;
        }

        $paramsdskapieur = $this->makeApiRequest('/function/geteur.php?cid=' . urlencode($dskapi_cid));
        if ($paramsdskapieur === null) {
            return;
        }

        $dskapi_eur = (int) $paramsdskapieur['dsk_eur'];
        $data['dskapi_eur'] = $dskapi_eur;
        $dskapi_product_id = (int) $product_info['product_id'];
        $data['dskapi_product_id'] = $dskapi_product_id;
        $data['dskapi_product_name'] = $product_info['name'];

        $dskapi_sign = 'лв.';
        switch ($dskapi_eur) {
            case 0:
                break;
            case 1:
                if ($dskapi_currency_code == "EUR") {
                    $dskapi_price = number_format($dskapi_price * 1.95583, 2, '.', '');
                }
                $dskapi_sign = 'лв.';
                break;
            case 2:
                if ($dskapi_currency_code == "BGN") {
                    $dskapi_price = number_format($dskapi_price / 1.95583, 2, '.', '');
                }
                $dskapi_sign = 'евро';
                break;
        }

        $data['dskapi_price'] = number_format($dskapi_price, 2, ".", "");
        $data['dskapi_sign'] = $dskapi_sign;
        $data['dskapi_currency_code'] = $dskapi_currency_code;

        $paramsdskapi = $this->makeApiRequest('/function/getproduct.php?cid=' . urlencode($dskapi_cid) . '&price=' . urlencode($dskapi_price) . '&product_id=' . urlencode($dskapi_product_id));
        if ($paramsdskapi === null) {
            return;
        }

        $data['dskapi_zaglavie'] = $paramsdskapi['dsk_zaglavie'] ?? '';
        $data['dskapi_custom_button_status'] = (int) ($paramsdskapi['dsk_custom_button_status'] ?? 0);
        $dskapi_options = (bool) ($paramsdskapi['dsk_options'] ?? false);
        $dskapi_is_visible = (bool) ($paramsdskapi['dsk_is_visible'] ?? false);
        $data['dskapi_button_normal'] = $dskapiLiveUrl . '/calculators/assets/img/buttons/dsk.png';
        $data['dskapi_button_normal_custom'] = $dskapiLiveUrl . '/calculators/assets/img/custom_buttons/' . $dskapi_cid . '.png';
        $data['dskapi_button_hover'] = $dskapiLiveUrl . '/calculators/assets/img/buttons/dsk-hover.png';
        $data['dskapi_button_hover_custom'] = $dskapiLiveUrl . '/calculators/assets/img/custom_buttons/' . $dskapi_cid . '_hover.png';
        $data['dskapi_isvnoska'] = (int) ($paramsdskapi['dsk_isvnoska'] ?? 0);
        $data['dskapi_vnoski'] = (int) ($paramsdskapi['dsk_vnoski_default'] ?? 0);
        $data['dskapi_vnoska'] = number_format((float) ($paramsdskapi['dsk_vnoska'] ?? 0), 2, ".", "");
        $data['dskapi_button_status'] = (int) ($paramsdskapi['dsk_button_status'] ?? 0);
        $data['dskapi_minstojnost'] = number_format((float) ($paramsdskapi['dsk_minstojnost'] ?? 0), 2, ".", "");
        $data['dskapi_maxstojnost'] = number_format((float) ($paramsdskapi['dsk_maxstojnost'] ?? 0), 2, ".", "");
        $data['dskapi_vnoski_visible'] = (int) ($paramsdskapi['dsk_vnoski_visible'] ?? 0);
        $data['obshtozaplashtane'] = number_format((float) ($paramsdskapi['dsk_vnoska'] ?? 0) * (float) ($paramsdskapi['dsk_vnoski_default'] ?? 0), 2, ".", "");
        $data['dskapi_gpr'] = number_format((float) ($paramsdskapi['dsk_gpr'] ?? 0), 2, ".", "");

        if (
            $dskapi_price == 0 ||
            !$dskapi_options ||
            !$dskapi_is_visible ||
            $paramsdskapi['dsk_status'] != 1 ||
            $data['dskapi_button_status'] == 0
        ) {
            return;
        }

        if ($this->isMobileDevice()) {
            $data['dskapi_PopUp_Detailed_v1'] = "dskapim_PopUp_Detailed_v1";
            $data['dskapi_Mask'] = "dskapim_Mask";
            $data['dskapi_picture'] = $dskapiLiveUrl . '/calculators/assets/img/dskm' . $paramsdskapi['dsk_reklama'] . '.png';
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
            $data['dskapi_PopUp_Detailed_v1'] = "dskapi_PopUp_Detailed_v1";
            $data['dskapi_Mask'] = "dskapi_Mask";
            $data['dskapi_picture'] = $dskapiLiveUrl . '/calculators/assets/img/dsk' . $paramsdskapi['dsk_reklama'] . '.png';
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
        $data['dskapi_gap'] = $this->config->get($this->module . '_gap') ?? 0;
        $data['dskapi_gap_popup'] = $this->config->get($this->module . '_gap_popup') ?? 0;
        $installInfo = json_decode(
            file_get_contents(DIR_EXTENSION . 'mt_dskapi_credit/install.json'),
            true
        );
        $data['DSKAPI_VERSION'] = $installInfo['version'] ?? '';

        $hook1 = '<button type="submit" id="button-cart"';
        $hook2 = '</div>';
        $position_hook1 = strpos($output, $hook1);
        if ($position_hook1 !== false) {
            $suboutput_after_hook1 = substr($output, $position_hook1 + strlen($hook1));
            $position_hook2_in_suboutput = strpos($suboutput_after_hook1, $hook2);
            if ($position_hook2_in_suboutput !== false) {
                $position_hook2_after = $position_hook1 + strlen($hook1) + $position_hook2_in_suboutput + strlen($hook2);
                $output = substr($output, 0, $position_hook2_after) . $this->load->view($this->path . '_product', $data) . substr($output, $position_hook2_after);
            }
        }
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

    /**
     * Проверява дали устройството е мобилно
     *
     * @return bool
     */
    /**
     * Detects whether the current visitor uses a mobile device.
     *
     * @return bool
     */
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
