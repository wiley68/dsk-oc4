<?php

namespace Opencart\Admin\Model\Extension\MtDskapiCredit\Module;

/**
 * Class MtDskapiCredit
 *
 * @package Opencart\Admin\Model\Extension\MtDskapiCredit\Module
 */
class MtDskapiCredit extends \Opencart\System\Engine\Model
{
    /**
     * Installs the module - creates necessary database tables
     *
     * @return void
     */
    public function install(): void
    {
        $table_dskpayment_orders = \DB_PREFIX . 'dskpayment_orders';
        $this->db->query("CREATE TABLE IF NOT EXISTS `$table_dskpayment_orders` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `order_id` INT(11) NOT NULL,
            `order_status` TINYINT(4) NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `order_id` (`order_id`),
            KEY `order_status` (`order_status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;");
    }

    /**
     * Uninstalls the module - drops database tables
     *
     * @return void
     */
    public function uninstall(): void
    {
        $table_dskpayment_orders = \DB_PREFIX . 'dskpayment_orders';
        $this->db->query("DROP TABLE IF EXISTS `$table_dskpayment_orders`;");
    }

    /**
     * Returns the bank status for a given order
     *
     * @param int $order_id
     * @return array|null
     */
    public function getBankStatus(int $order_id): ?array
    {
        $table_dskpayment_orders = \DB_PREFIX . 'dskpayment_orders';
        $query = $this->db->query("SELECT * FROM `$table_dskpayment_orders` WHERE `order_id` = '" . (int)$order_id . "' LIMIT 1");

        if ($query->num_rows) {
            return $query->row;
        }

        return null;
    }

    /**
     * Returns an array with all bank statuses
     *
     * @return array
     */
    public function getBankStatuses(): array
    {
        return [
            0 => 'Създадена Апликация',
            1 => 'Избрана финансова схема',
            2 => 'Попълнена Апликация',
            3 => 'Изпратен Банка',
            4 => 'Неуспешен контакт с клиента',
            5 => 'Анулирана апликация',
            6 => 'Отказана апликация',
            7 => 'Подписан договор',
            8 => 'Усвоен кредит'
        ];
    }
}
