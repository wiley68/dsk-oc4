<?php

namespace Opencart\Admin\Model\Module\MtDskapiCredit;

/**
 * Class MtDskapiCredit
 *
 * @package Opencart\Admin\Model\Module\MtDskapiCredit
 */
class MtDskapiCredit extends \Opencart\System\Engine\Model
{

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

    public function uninstall(): void
    {
        $table_dskpayment_orders = \DB_PREFIX . 'dskpayment_orders';
        $this->db->query("DROP TABLE IF EXISTS `$table_dskpayment_orders`;");
    }
}
