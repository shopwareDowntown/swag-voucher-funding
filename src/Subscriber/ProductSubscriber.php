<?php

namespace SwagVoucherFunding\Subscriber;

use Shopware\Storefront\Page\Product\ProductPageLoadedEvent;
use SwagVoucherFunding\Service\VoucherFundingService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProductSubscriber implements EventSubscriberInterface
{
    private $voucherFundingService;

    public function __construct(VoucherFundingService $voucherFundingService)
    {
        $this->voucherFundingService = $voucherFundingService;
    }

    public static function getSubscribedEvents()
    {
        return [
            ProductPageLoadedEvent::class => 'onProductsLoaded'
        ];
    }

    public function onProductsLoaded(ProductPageLoadedEvent $event)
    {
    }
}
