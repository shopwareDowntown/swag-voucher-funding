<?php

namespace SwagVoucherFunding\Service;

use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;

class VoucherFundingMerchantService
{
    /**
     * @var EntityRepositoryInterface
     */
    private $soldVoucherRepository;
    /**
     * @var EntityRepositoryInterface
     */
    private $orderRepository;

    public function __construct(
        EntityRepositoryInterface $soldVoucherRepository,
        EntityRepositoryInterface $orderRepository
    ) {
        $this->soldVoucherRepository = $soldVoucherRepository;
        $this->orderRepository = $orderRepository;
    }

    public function createSoldVoucher(OrderLineItemCollection $lineItemCollection, Context $context) : void
    {
        $vouchers = [];

        foreach ($lineItemCollection->getIterator() as $lineItemEntity) {
            $voucher = [];
            $voucher['order_line_item_id'] = $lineItemEntity->getId();
            $voucher['name'] = $lineItemEntity->getProduct()->getName();
            $voucher['code'] = self::generateVoucherCode(10);
            $voucher['price'] = $lineItemEntity->getPrice();

            $vouchers[] = $voucher;
        }

        $this->soldVoucherRepository->create($vouchers, $context);
    }

    public static function generateVoucherCode(int $length = 10)
    {
        return mb_strtoupper(str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(Random::getAlphanumericString($length))));
    }
}
