<?php

namespace SwagVoucherFunding\Service;

use Shopware\Storefront\Page\Product\ProductLoader;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;

class VoucherFundingService
{
    /**
     * @var ProductLoader
     */
    private $productLoader;

    public function __construct(
        ProductLoader $productLoader
    ) {
        $this->productLoader = $productLoader;
    }
}
