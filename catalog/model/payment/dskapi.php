<?php

namespace Opencart\Catalog\Model\Extension\MtDskapiCredit\Payment;

/**
 * Class Dskapi
 *
 * Payment method model for DSKAPI credit - retrieves available payment methods based on cart total and geo zone
 *
 * @package Opencart\Catalog\Model\Extension\MtDskapiCredit\Payment
 */
class Dskapi extends \Opencart\System\Engine\Model
{
    /**
     * Module code identifier
     *
     * @var string
     */
    private $module = 'module_mt_dskapi_credit';

    /**
     * Retrieves available payment methods for DSKAPI credit
     *
     * Checks cart total, vouchers, subscriptions, geo zone restrictions, and module status
     * to determine if DSKAPI payment method should be available
     *
     * @param array $address Customer address data (country_id, zone_id)
     * @return array Payment method data array or empty array if not available
     */
    public function getMethods(array $address = []): array
    {
        $this->load->language('extension/mt_dskapi_credit/payment/dskapi');

        // Get cart and session instances
        $cart = $this->registry->get('cart');
        $session = $this->registry->get('session');

        // Calculate total including vouchers
        $total = $cart->getTotal();
        if (!empty($session->data['vouchers'])) {
            $amounts = array_column($session->data['vouchers'], 'amount');
        } else {
            $amounts = [];
        }
        $total = $total + array_sum($amounts);

        // Check if payment method should be available based on various conditions
        if ($cart->hasSubscription()) {
            // Subscriptions are not supported
            $status = false;
        } elseif (!$this->config->get('config_checkout_payment_address')) {
            // Payment address not required, allow payment method
            $status = true;
        } elseif (!$this->config->get('payment_dskapi_geo_zone_id')) {
            // No geo zone restriction configured, allow payment method
            $status = true;
        } else {
            // Check if address is within configured geo zone
            $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "zone_to_geo_zone` WHERE `geo_zone_id` = '" . (int) $this->config->get('payment_dskapi_geo_zone_id') . "' AND `country_id` = '" . (int) $address['country_id'] . "' AND (`zone_id` = '" . (int) $address['zone_id'] . "' OR `zone_id` = '0')");

            if ($query->num_rows) {
                $status = true;
            } else {
                $status = false;
            }
        }

        // Check if module is enabled
        $dskapi_status = $this->config->get($this->module . '_status');
        if (!$dskapi_status) {
            return [];
        }

        // Build payment method data if available
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
