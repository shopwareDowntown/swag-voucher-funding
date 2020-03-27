<?php declare(strict_types=1);

namespace SwagVoucherFunding\Controller;

use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Storefront\Controller\StorefrontController;
use SwagVoucherFunding\Service\VoucherFundingMerchantService;

/**
 * @RouteScope(scopes={"storefront"})
 */
class VoucherFundingMerchantController extends StorefrontController
{
    private $voucherFundingService;

    public function __construct(VoucherFundingMerchantService $voucherFundingService)
    {
        $this->voucherFundingService = $voucherFundingService;
    }
}
