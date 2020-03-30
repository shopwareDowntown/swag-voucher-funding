<?php declare(strict_types=1);

namespace SwagVoucherFunding\Controller;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Production\Merchants\Content\Merchant\SalesChannelContextExtension;
use Shopware\Storefront\Controller\StorefrontController;
use SwagVoucherFunding\Service\VoucherFundingMerchantService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

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

    /**
     * @Route(name="merchant-api.action.sold.vouchers.load", path="/merchant-api/v{version}/voucher-funding/sold/vouchers", methods={"GET"})
     * @throws CustomerNotLoggedInException
     */
    public function loadSoldVouchers(SalesChannelContext $context) : JsonResponse
    {
        $merchant = SalesChannelContextExtension::extract($context);

        $soldVouchers = $this->voucherFundingService->loadSoldVouchers($merchant->getId(), $context);

        return new JsonResponse([
            'data' => $soldVouchers
        ]);
    }

    /**
     * @Route("/merchant-api/v{version}/voucher-funding/voucher/redeem", name="merchant-api.action.voucher-funding.voucher.redeem", methods={"POST"}, defaults={"csrf_protected"=false})
     * @throws CustomerNotLoggedInException
     */
    public function redeemVoucher(Request $request, SalesChannelContext $context): JsonResponse
    {
        $voucherCode = $request->request->get('code');

        if (!$voucherCode) {
            throw new \InvalidArgumentException('Please input voucher code to redeem');
        }

        $merchant = SalesChannelContextExtension::extract($context);

        $this->voucherFundingService->redeemVoucher($voucherCode, $merchant, $context);

        return new JsonResponse(['code' => 200, 'Content' => 'Redeem voucher successfully']);
    }

    /**
     * @Route("/merchant-api/v{version}/voucher-funding/voucher/status", name="merchant-api.action.voucher-funding.voucher.status", methods={"GET"})
     * @throws CustomerNotLoggedInException
     */
    public function getVoucherStatus(Request $request, SalesChannelContext $context): JsonResponse
    {
        $voucherCode = $request->query->get('code');

        if (!$voucherCode) {
            throw new \InvalidArgumentException('Please input voucher code to redeem');
        }

        $merchant = SalesChannelContextExtension::extract($context);

        $data = $this->voucherFundingService->getVoucherStatus($voucherCode, $merchant, $context);

        $status = $data['status'] == 'invalid' ? 400 : 200;

        return new JsonResponse(['data' => $data], $status);
    }
}
