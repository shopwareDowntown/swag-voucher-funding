<?php declare(strict_types=1);

namespace SwagVoucherFunding\Controller;

use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Storefront\Controller\StorefrontController;
use SwagVoucherFunding\Service\VoucherFundingService;

/**
 * @RouteScope(scopes={"storefront"})
 */
class VoucherFundingController extends StorefrontController
{
    private $voucherFundingService;

    public function __construct(VoucherFundingService $voucherFundingService)
    {
        $this->voucherFundingService = $voucherFundingService;
    }
}
