<?php declare(strict_types=1);

namespace SwagVoucherFunding\Checkout;

use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtensionInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use SwagVoucherFunding\Checkout\ProductVoucherOrder\ProductVoucherOrderDefinition;

class ProductEntityExtension implements EntityExtensionInterface
{
    public function getDefinitionClass(): string
    {
        return ProductDefinition::class;
    }
    /**
     * @inheritDoc
     */
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            new OneToManyAssociationField('productVoucherOrder', ProductVoucherOrderDefinition::class, 'product_id'),
        );
    }
}
