<?php declare(strict_types=1);

namespace SwagVoucherFunding\Checkout\SoldVoucher;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class SoldVoucherCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return SoldVoucherEntity::class;
    }
}
