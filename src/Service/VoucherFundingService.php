<?php

namespace SwagVoucherFunding\Service;

use Shopware\Storefront\Page\Product\ProductLoader;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;

class VoucherFundingService
{
    /**
     * @var EntityRepositoryInterface
     */
    private $entityRepository;

    public function __construct(
        EntityRepositoryInterface $entityRepository
    ) {
        $this->entityRepository = $entityRepository;
    }
}
