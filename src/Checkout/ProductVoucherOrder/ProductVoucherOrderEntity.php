<?php declare(strict_types=1);

namespace SwagVoucherFunding\Checkout\ProductVoucherOrder;

use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceDefinitionField;

class ProductVoucherOrderEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string
     */
    protected $orderLineItemId;

    /**
     * @var string
     */
    protected $productId;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $code;

    /**
     * @var PriceDefinitionField
     */
    protected $value;

    /**
     * @var \DateTimeInterface|null
     */
    protected $invalidated_at;

    /**
     * @var ProductEntity|null
     */
    protected $product;

    /**
     * @var OrderLineItemEntity
     */
    protected $orderLineItem;

    /**
     * @return \DateTimeInterface|null
     */
    public function getInvalidatedAt(): ?\DateTimeInterface
    {
        return $this->invalidated_at;
    }

    /**
     * @param  \DateTimeInterface|null  $invalidated_at
     */
    public function setInvalidatedAt(?\DateTimeInterface $invalidated_at): void
    {
        $this->invalidated_at = $invalidated_at;
    }

    /**
     * @return PriceDefinitionField
     */
    public function getValue(): PriceDefinitionField
    {
        return $this->value;
    }

    /**
     * @param  PriceDefinitionField  $value
     */
    public function setValue(PriceDefinitionField $value): void
    {
        $this->value = $value;
    }

    /**
     * @return string
     */
    public function getOrderLineItemId(): string
    {
        return $this->orderLineItemId;
    }

    /**
     * @param  string  $orderLineItemId
     */
    public function setOrderLineItemId(string $orderLineItemId): void
    {
        $this->orderLineItemId = $orderLineItemId;
    }

    /**
     * @return string
     */
    public function getProductId(): string
    {
        return $this->productId;
    }

    /**
     * @param  string  $productId
     */
    public function setProductId(string $productId): void
    {
        $this->productId = $productId;
    }

    /**
     * @return ProductEntity|null
     */
    public function getProduct(): ?ProductEntity
    {
        return $this->product;
    }

    /**
     * @param  ProductEntity|null  $product
     */
    public function setProduct(?ProductEntity $product): void
    {
        $this->product = $product;
    }

    /**
     * @return OrderLineItemEntity
     */
    public function getOrderLineItem(): OrderLineItemEntity
    {
        return $this->orderLineItem;
    }

    /**
     * @param  OrderLineItemEntity  $orderLineItem
     */
    public function setOrderLineItem(OrderLineItemEntity $orderLineItem): void
    {
        $this->orderLineItem = $orderLineItem;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param  string  $name
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }
}
