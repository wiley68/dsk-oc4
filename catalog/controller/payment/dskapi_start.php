<?php

namespace Opencart\Catalog\Controller\Extension\MtDskapiCredit\Payment;

class DskapiStart extends \Opencart\System\Engine\Controller
{
    private string $dskapiLiveUrl = '';
    private string $dskapiMail = '';
    private $module = 'module_mt_dskapi_credit';

    public function __construct(\Opencart\System\Engine\Registry $registry)
    {
        parent::__construct($registry);

        $this->loadDskapiDefaults();
    }
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

        $dskapi_cid = $this->config->get($this->module . '_cid');

        // Прочитане на order_id от GET параметър или от сесията и веднага изтриване от сесията
        if (isset($this->request->get['order_id'])) {
            $order_id = (string)$this->request->get['order_id'];
        } elseif (isset($this->session->data['order_id'])) {
            $order_id = (string)$this->session->data['order_id'];
        } else {
            $order_id = 0;
        }

        // Веднага изтриване на order_id от сесията, за да предотвратим използването на стари данни
        unset($this->session->data['order_id']);

        if ($order_id != 0) {
            // Proceed to bnpl Process    
            $this->load->model('checkout/order');
            $this->load->model('catalog/product');
            $this->load->model('tool/image');
            $order = $this->model_checkout_order->getOrder($order_id);

            if (!$order) {
                $data['redirect_url'] = $this->url->link('checkout/failure', 'language=' . $this->config->get('config_language'), true);
                $data['success'] = false;
            } else {
                // Продължаваме само ако ордерът съществува
                $products = $this->cart->getProducts();

                $dskapi_fname = isset($order['firstname']) ? trim($order['firstname'], " ") : '';
                $dskapi_lastname = isset($order['lastname']) ? trim($order['lastname'], " ") : '';
                $dskapi_phone = isset($order['telephone']) ? $order['telephone'] : '';
                $dskapi_email = isset($order['email']) ? $order['email'] : '';
                $dskapi_billing_city = isset($order['payment_city']) ? $order['payment_city'] : '';
                $dskapi_billing_address_1 = isset($order['payment_address_1']) ? $order['payment_address_1'] : '';
                $dskapi_billing_postcode = isset($order['payment_postcode']) ? $order['payment_postcode'] : '';
                $dskapi_shipping_city = isset($order['shipping_city']) ? $order['shipping_city'] : '';
                $dskapi_shipping_address_1 = isset($order['shipping_address_1']) ? $order['shipping_address_1'] : '';

                $dskapi_total = isset($order['total']) ? $order['total'] : '';

                $dskapi_eur = 0;
                $paramsdskapieur = $this->makeApiRequest('/function/geteur.php?cid=' . $dskapi_cid);

                $dskapi_currency_code = isset($order['currency_code']) ? $order['currency_code'] : 'BGN';
                $dskapi_currency_code_send = 0;
                if ($paramsdskapieur != null) {
                    $dskapi_eur = (int)$paramsdskapieur['dsk_eur'];
                    switch ($dskapi_eur) {
                        case 0:
                            break;
                        case 1:
                            $dskapi_currency_code_send = 0;
                            if ($dskapi_currency_code == "EUR") {
                                $dskapi_total = number_format($dskapi_total * 1.95583, 2, ".", "");
                            }
                            break;
                        case 2:
                            $dskapi_currency_code_send = 1;
                            if ($dskapi_currency_code == "BGN") {
                                $dskapi_total = number_format($dskapi_total / 1.95583, 2, ".", "");
                            }
                            break;
                    }
                }

                $ident = 0;
                $products_id = '';
                $products_name = '';
                $products_q = '';
                $products_p = '';
                $products_c = '';
                $products_m = '';
                $products_i = '';
                foreach ($products as $product) {
                    $products_id .= $product['product_id'];
                    $products_id .= '_';
                    $products_q .= $product['quantity'];
                    $products_q .= '_';

                    $products_p_temp = $this->tax->calculate($product['price'], $product['tax_class_id'], $this->config->get('config_tax'));
                    switch ($dskapi_eur) {
                        case 0:
                            break;
                        case 1:
                            if ($dskapi_currency_code == "EUR") {
                                $products_p_temp = number_format($products_p_temp * 1.95583, 2, ".", "");
                            }
                            break;
                        case 2:
                            if ($dskapi_currency_code == "BGN") {
                                $products_p_temp = number_format($products_p_temp / 1.95583, 2, ".", "");
                            }
                            break;
                    }
                    $products_p .= $products_p_temp;
                    $products_p .= '_';

                    $products_name .= str_replace('"', '', str_replace("'", "", htmlspecialchars_decode($product['name'], ENT_QUOTES)));
                    $products_name .= '_';
                    $products_cat = $this->model_catalog_product->getCategories($product['product_id']);
                    foreach ($products_cat as $product_cat) {
                        $products_c = $product_cat['category_id'];
                    }
                    $products_c .= '_';
                    $product_bnpl = $this->model_catalog_product->getProduct($product['product_id']);
                    $products_m .= $product_bnpl['manufacturer'] ?? '';
                    $products_m .= '_';
                    $bnpl_image = $this->model_tool_image->resize($product_bnpl['image'], 800, 600);
                    $bnpl_imagePath_64 = base64_encode($bnpl_image);
                    $products_i .= $bnpl_imagePath_64;
                    $products_i .= '_';
                    $ident++;
                }

                $products_id = trim($products_id, "_");
                $products_q = trim($products_q, "_");
                $products_p = trim($products_p, "_");
                $products_c = trim($products_c, "_");
                $products_m = trim($products_m, "_");
                $products_name = trim($products_name, "_");
                $products_i = trim($products_i, "_");

                $installInfo = json_decode(
                    file_get_contents(DIR_EXTENSION . 'mt_dskapi_credit/install.json'),
                    true
                );
                $dskapi_version = $installInfo['version'] ?? '';

                $dskapi_post = [
                    'unicid' => $dskapi_cid,
                    'first_name' => $dskapi_fname,
                    'last_name' => $dskapi_lastname,
                    'phone' => $dskapi_phone,
                    'email' => $dskapi_email,
                    'address2' => str_replace('"', '', str_replace("'", "", htmlspecialchars_decode($dskapi_billing_address_1, ENT_QUOTES))),
                    'address2city' => str_replace('"', '', str_replace("'", "", htmlspecialchars_decode($dskapi_billing_city, ENT_QUOTES))),
                    'postcode' => $dskapi_billing_postcode,
                    'price' => $dskapi_total,
                    'address' => str_replace('"', '', str_replace("'", "", htmlspecialchars_decode($dskapi_shipping_address_1, ENT_QUOTES))),
                    'addresscity' => str_replace('"', '', str_replace("'", "", htmlspecialchars_decode($dskapi_shipping_city, ENT_QUOTES))),
                    'products_id' => $products_id,
                    'products_name' => $products_name,
                    'products_q' => $products_q,
                    'type_client' => $this->isMobileDevice() ? 1 : 0,
                    'products_p' => $products_p,
                    'version' => $dskapi_version,
                    'shoporder_id' => $order_id,
                    'products_c' => $products_c,
                    'products_m' => $products_m,
                    'products_i' => $products_i,
                    'currency' => $dskapi_currency_code_send
                ];

                $dskapi_plaintext = json_encode($dskapi_post);
                $dskapi_publicKey = openssl_pkey_get_public(file_get_contents(DIR_EXTENSION . 'mt_dskapi_credit/system/keys/pub.pem'));
                $dskapi_a_key = openssl_pkey_get_details($dskapi_publicKey);
                $dskapi_chunkSize = ceil($dskapi_a_key['bits'] / 8) - 11;
                $dskapi_output = '';
                while ($dskapi_plaintext) {
                    $dskapi_chunk = substr($dskapi_plaintext, 0, $dskapi_chunkSize);
                    $dskapi_plaintext = substr($dskapi_plaintext, $dskapi_chunkSize);
                    $dskapi_encrypted = '';
                    if (!openssl_public_encrypt($dskapi_chunk, $dskapi_encrypted, $dskapi_publicKey)) {
                        die('Failed to encrypt data');
                    }
                    $dskapi_output .= $dskapi_encrypted;
                }
                if (version_compare(PHP_VERSION, '8.0.0', '<')) {
                    openssl_free_key($dskapi_publicKey);
                }
                $dskapi_output64 = base64_encode($dskapi_output);

                // Create dskapi order i data base
                $paramsdskapiadd = $this->makeApiRequest('/function/addorders.php', 5, ['data' => $dskapi_output64]);

                if ((!empty($paramsdskapiadd)) && isset($paramsdskapiadd['order_id']) && ($paramsdskapiadd['order_id'] != 0)) {
                    // Записваме данните в таблицата dskpayment_orders
                    $dskapi_order_current = [
                        "order_id" => $order_id,
                        "order_status" => 0
                    ];
                    $this->saveDskapiOrder($dskapi_order_current);

                    $redirect_url = $this->dskapiLiveUrl . '/application_' . ($this->isMobileDevice() ? 'm_' : '') . 'step1.php?oid=' . $paramsdskapiadd['order_id'] . '&cid=' . $dskapi_cid;

                    $data['success'] = true;
                    $data['redirect_url'] = $redirect_url;
                    // order_id вече е изтрит от сесията в началото на метода
                    $this->cart->clear();
                } else {
                    if (empty($paramsdskapiadd)) {
                        // Записваме данните в таблицата dskpayment_orders
                        $dskapi_order_current = [
                            "order_id" => $order_id,
                            "order_status" => 0
                        ];
                        $this->saveDskapiOrder($dskapi_order_current);

                        $data['comunication'] = 0;
                        $data['success'] = false;

                        if ($this->config->get('config_mail_engine')) {
                            $mail_engine = $this->config->get('config_mail_engine');
                            if ($mail_engine === 'smtp') {
                                $mail_option = [
                                    'parameter'     => $this->config->get('config_mail_parameter'),
                                    'smtp_hostname' => $this->config->get('config_mail_smtp_hostname'),
                                    'smtp_username' => $this->config->get('config_mail_smtp_username'),
                                    'smtp_password' => html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8'),
                                    'smtp_port'     => $this->config->get('config_mail_smtp_port'),
                                    'smtp_timeout'  => $this->config->get('config_mail_smtp_timeout')
                                ];
                            } else {
                                $mail_option = [
                                    'parameter' => $this->config->get('config_mail_parameter')
                                ];
                            }
                            $mail = new \Opencart\System\Library\Mail($mail_engine, $mail_option);
                            $mail->setFrom($this->config->get('config_mail_smtp_username'));
                            $mail->setSender($this->config->get('config_mail_smtp_username'));
                            $mail->setSubject('Проблем комуникация заявка КП DSK Credit');
                            $mail->setText(json_encode($dskapi_post, JSON_PRETTY_PRINT));
                            $mail->setTo($this->dskapiMail);
                        }
                    } else {
                        $data['comunication'] = 1;
                        $data['success'] = false;
                    }
                }
            }
        } else {
            $data['success'] = false;
        }

        $data['header'] = $this->load->controller('common/header');
        $data['footer'] = $this->load->controller('common/footer');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $data['order_id'] = $order_id;

        $this->response->setOutput($this->load->view('extension/mt_dskapi_credit/payment/dskapi_start', $data));
    }

    private function loadDskapiDefaults(): void
    {
        static $loaded = false;

        if ($loaded) {
            $this->dskapiLiveUrl = (string) $this->config->get('dskapi_liveurl');
            $this->dskapiMail = (string) $this->config->get('dskapi_mail');

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
        $this->dskapiMail = (string) $this->config->get('dskapi_mail');

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

    /**
     * Записва или актуализира данни за DSKAPI ордер в таблицата dskpayment_orders
     *
     * @param array $dskapi_order_current Масив с данни за ордера: order_id и order_status
     * @return void
     */
    private function saveDskapiOrder(array $dskapi_order_current): void
    {
        if (!isset($dskapi_order_current['order_id']) || !isset($dskapi_order_current['order_status'])) {
            return;
        }

        $table_dskpayment_orders = DB_PREFIX . 'dskpayment_orders';
        $this->db->query("INSERT INTO `$table_dskpayment_orders` 
            (`order_id`, `order_status`, `created_at`, `updated_at`) 
            VALUES (
                '" . (int)$dskapi_order_current['order_id'] . "', 
                '" . (int)$dskapi_order_current['order_status'] . "', 
                NOW(), 
                NULL
            ) 
            ON DUPLICATE KEY UPDATE 
                `order_status` = '" . (int)$dskapi_order_current['order_status'] . "',
                `updated_at` = NOW()");
    }
}
