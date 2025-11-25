<?php

namespace Opencart\Catalog\Model\Extension\MtDskapiCredit\Payment;

/**
 * Class Dskapi
 *
 * @package Opencart\Catalog\Model\Extension\MtDskapiCredit\Payment
 */
class Dskapi extends \Opencart\System\Engine\Model
{
    public function getMethods(array $address = []): array
    {
        $this->load->language('extension/mt_dskapi_credit/payment/dskapi');

        $cart = $this->registry->get('cart');
        $session = $this->registry->get('session');

        $total = $cart->getTotal();
        if (!empty($session->data['vouchers'])) {
            $amounts = array_column($session->data['vouchers'], 'amount');
        } else {
            $amounts = [];
        }
        $total = $total + array_sum($amounts);

        if ($cart->hasSubscription()) {
            $status = false;
        } elseif (!$this->config->get('config_checkout_payment_address')) {
            $status = true;
        } elseif (!$this->config->get('payment_dskapi_geo_zone_id')) {
            $status = true;
        } else {
            $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "zone_to_geo_zone` WHERE `geo_zone_id` = '" . (int) $this->config->get('payment_dskapi_geo_zone_id') . "' AND `country_id` = '" . (int) $address['country_id'] . "' AND (`zone_id` = '" . (int) $address['zone_id'] . "' OR `zone_id` = '0')");

            if ($query->num_rows) {
                $status = true;
            } else {
                $status = false;
            }
        }

        $method_data = [];
        if ($status) {
            $option_data['dskapi'] = [
                'code' => 'dskapi.dskapi',
                'name' => $this->language->get('text_title')
            ];

            $method_data = [
                'code' => 'dskapi',
                'name' => $this->language->get('text_title'),
                'option' => $option_data,
                'sort_order' => $this->config->get('payment_dskapi_sort_order')
            ];
        }

        return $method_data;
    }
}