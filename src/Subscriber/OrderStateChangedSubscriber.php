<?php

namespace SwagVoucherFunding\Subscriber;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\Event\OrderStateMachineStateChangeEvent;
use Shopware\Core\Framework\Api\Exception\InvalidSalesChannelIdException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Production\Merchants\Content\Merchant\MerchantEntity;
use SwagVoucherFunding\Service\VoucherFundingMerchantService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderStateChangedSubscriber implements EventSubscriberInterface
{
    private $voucherFundingService;
    /**
     * @var EntityRepositoryInterface
     */
    private $lineItemRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $merchantRepository;

    public function __construct(
        VoucherFundingMerchantService $voucherFundingService,
        EntityRepositoryInterface $lineItemRepository,
        EntityRepositoryInterface $merchantRepository
    )
    {
        $this->voucherFundingService = $voucherFundingService;
        $this->lineItemRepository = $lineItemRepository;
        $this->merchantRepository = $merchantRepository;
    }

    public static function getSubscribedEvents()
    {
        return [
            'state_enter.order_transaction.state.paid' => 'orderTransactionStatePaid',
        ];
    }

    public function orderTransactionStatePaid(OrderStateMachineStateChangeEvent $event) : void
    {
        $order = $event->getOrder();

        $context = $event->getContext();

        $voucherLineItems = $this->getVoucherLineItemsOfOrder($order->getId(), $context);

        if(empty($voucherLineItems) || $voucherLineItems->count() === 0) {
            return;
        }

        $merchant = $this->fetchMerchantFromSalesChannel($event->getSalesChannelId(), $context);

        $this->voucherFundingService->createSoldVoucher($merchant, $order, $voucherLineItems->getEntities(), $context);
    }

    private function getVoucherLineItemsOfOrder(string $orderId, Context $context): EntitySearchResult
    {
        $lineItemCriteria = new Criteria();
        $lineItemCriteria->addAssociation('product');

        $lineItemCriteria
            ->addFilter(new EqualsFilter('type', LineItem::PRODUCT_LINE_ITEM_TYPE))
            ->addFilter(new EqualsFilter('orderId', $orderId));


        $lineItemCollection = $this->lineItemRepository->search($lineItemCriteria, $context);

        return $lineItemCollection->filter(function(OrderLineItemEntity $orderLineItemEntity) {
            $customFields = $orderLineItemEntity->getProduct()->getCustomFields();

            return $customFields && $customFields['productType'] && $customFields['productType'] === 'voucher';
        });
    }

    /**
     * @throws InconsistentCriteriaIdsException
     * @throws InvalidSalesChannelIdException
     */
    private function fetchMerchantFromSalesChannel(string $salesChannelId, Context $context): MerchantEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('salesChannelId', $salesChannelId));

        /** @var MerchantEntity|null $merchant */
        $merchant = $this->merchantRepository->search($criteria, $context)->first();

        if ($merchant === null) {
            throw new InvalidSalesChannelIdException($salesChannelId);
        }

        return $merchant;
    }
}
