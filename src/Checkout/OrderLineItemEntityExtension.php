<?php declare(strict_types=1);

namespace SwagVoucherFunding\Checkout;

use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtensionInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use SwagVoucherFunding\Checkout\SoldVoucher\SoldVoucherDefinition;

class OrderLineItemEntityExtension implements EntityExtensionInterface
{
    public function getDefinitionClass(): string
    {
        return OrderLineItemDefinition::class;
    }
    /**
     * @inheritDoc
     */
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            new OneToManyAssociationField('soldVouchers', SoldVoucherDefinition::class, 'order_line_item_id')
        );
    }
}
