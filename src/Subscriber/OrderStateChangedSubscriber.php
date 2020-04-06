<?php declare(strict_types=1);

namespace SwagVoucherFunding\Subscriber;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\Event\OrderStateMachineStateChangeEvent;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Api\Exception\InvalidSalesChannelIdException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
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
    private $orderRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $merchantRepository;

    public function __construct(
        VoucherFundingMerchantService $voucherFundingService,
        EntityRepositoryInterface $orderRepository,
        EntityRepositoryInterface $merchantRepository
    ) {
        $this->voucherFundingService = $voucherFundingService;
        $this->orderRepository = $orderRepository;
        $this->merchantRepository = $merchantRepository;
    }

    public static function getSubscribedEvents()
    {
        return [
            'state_enter.order.state.completed' => 'orderStateCompleted',
        ];
    }

    public function orderStateCompleted(OrderStateMachineStateChangeEvent $event): void
    {
        $context = $event->getContext();
        $order = $this->getVoucherByOrderId($event->getOrder()->getId(), $context);

        if (empty($order) || $order->getLineItems()->count() === 0) {
            return;
        }

        $merchant = $this->fetchMerchantFromSalesChannel($event->getSalesChannelId(), $context);

        $this->voucherFundingService->createSoldVoucher($merchant, $order, $context);
    }

    private function getVoucherByOrderId(string $orderId, Context $context): OrderEntity
    {
        $criteria = new Criteria([$orderId]);
        $criteria
            ->addAssociation('orderCustomer.salutation')
            ->addAssociation('lineItems.product')
            ->addAssociation('lineItems.payload')
            ->addAssociation('cartPrice.calculatedTaxes')
            ->addAssociation('currency')
            ->addAssociation('salesChannel')
            ->addAssociation('transactions.stateMachineState');

        $criteria
            ->addFilter(new EqualsFilter('lineItems.type', LineItem::PRODUCT_LINE_ITEM_TYPE))
            ->addFilter(new EqualsFilter('lineItems.product.customFields.productType', 'voucher'));

        /** @var OrderEntity $orderEntity */
        $orderEntity = $this->orderRepository->search($criteria, $context)->first();

        return $orderEntity;
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
