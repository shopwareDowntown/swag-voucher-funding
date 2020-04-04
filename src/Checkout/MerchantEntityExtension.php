<?php declare(strict_types=1);

namespace SwagVoucherFunding\Checkout;

use Shopware\Core\Framework\DataAbstractionLayer\EntityExtensionInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Production\Merchants\Content\Merchant\MerchantDefinition;
use SwagVoucherFunding\Checkout\SoldVoucher\SoldVoucherDefinition;

class MerchantEntityExtension implements EntityExtensionInterface
{
    public function getDefinitionClass(): string
    {
        return MerchantDefinition::class;
    }

    /**
     * {@inheritdoc}
     */
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            new OneToManyAssociationField('soldVouchers', SoldVoucherDefinition::class, 'merchant_id')
        );
    }
}
