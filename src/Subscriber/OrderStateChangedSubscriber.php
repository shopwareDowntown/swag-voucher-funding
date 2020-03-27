<?php

namespace SwagVoucherFunding\Subscriber;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Event\StateMachineTransitionEvent;
use SwagVoucherFunding\Service\VoucherFundingMerchantService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderStateChangedSubscriber implements EventSubscriberInterface
{
    private $voucherFundingService;
    /**
     * @var EntityRepositoryInterface
     */
    private $lineItemRepository;

    public function __construct(
        VoucherFundingMerchantService $voucherFundingService,
        EntityRepositoryInterface $lineItemRepository
    )
    {
        $this->voucherFundingService = $voucherFundingService;
        $this->lineItemRepository = $lineItemRepository;
    }

    public static function getSubscribedEvents()
    {
        return [
            StateMachineTransitionEvent::class => 'stateChanged',
        ];
    }

    public function stateChanged(StateMachineTransitionEvent $event) : void
    {
        if($event->getEntityName() !== OrderDefinition::ENTITY_NAME || $event->getToPlace()->getTechnicalName() !== OrderStates::STATE_COMPLETED) {
            return;
        }

        $voucherLineItems = $this->getVoucherLineItemsOfOrder($event->getEntityId(), $event->getContext());

        if(empty($voucherLineItems) || $voucherLineItems->count() === 0) {
            return;
        }

        $this->voucherFundingService->createSoldVoucher($voucherLineItems->getEntities(), $event->getContext());
    }

    private function getVoucherLineItemsOfOrder(string $orderId, Context $context): OrderLineItemCollection
    {
        $lineItemCriteria = new Criteria();
        $lineItemCriteria->addAssociation('product');
        $lineItemCriteria
            // TODO: Add filter product type = voucher
            // ->addFilter(new EqualsFilter('product.product_type', 'voucher')
            ->addFilter(new EqualsFilter('type', LineItem::PRODUCT_LINE_ITEM_TYPE))
            ->addFilter(new EqualsFilter('order_id', Uuid::fromHexToBytes($orderId)));


        /** @var OrderLineItemCollection $lineItemCollection */
        $lineItemCollection = $this->lineItemRepository->search($lineItemCriteria, $context);

        return $lineItemCollection->filter(function(OrderLineItemEntity $orderLineItemEntity) {
            return $orderLineItemEntity->getProduct()->getCustomFields()
                && $orderLineItemEntity->getProduct()->getCustomFields()['product_type'] === 'voucher';
        });
    }
}
