<?php declare(strict_types=1);

namespace SwagVoucherFunding\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1585287205ProductVoucherOrder extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1585287205;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('CREATE TABLE IF NOT EXISTS `product_voucher_order` (
    `id` BINARY(16) NOT NULL,
    `order_line_item_id` BINARY(16) NOT NULL,
    `product_id` BINARY(16) NOT NULL,
    `code` CHAR(10) NOT NULL,
    `name` VARCHAR (255) NOT NULL,
    `invalidated_at` DATETIME(3) NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    KEY `fk.product_voucher_order.order_line_item_id` (`order_line_item_id`),
    KEY `fk.product_voucher_order.product_id` (`product_id`),
    CONSTRAINT `fk.product_voucher_order.order_line_item_id` FOREIGN KEY (`order_line_item_id`) REFERENCES `order_line_item` (`id`),
    CONSTRAINT `fk.product_voucher_order.product_id` FOREIGN KEY (`product_id`) REFERENCES `product` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->executeQuery('DROP TABLE IF EXISTS `product_voucher_order`');
    }
}
