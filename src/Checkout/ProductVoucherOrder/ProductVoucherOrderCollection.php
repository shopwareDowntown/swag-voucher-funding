<?php declare(strict_types=1);

namespace SwagVoucherFunding\Checkout\ProductVoucherOrder;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class ProductVoucherOrderCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return ProductVoucherOrderEntity::class;
    }
}
