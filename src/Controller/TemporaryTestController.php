<?php declare(strict_types=1);

namespace SwagVoucherFunding\Controller;

use Faker\Factory;
use Faker\Generator;
use Faker\Guesser\Name;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Content\MailTemplate\Service\MailService;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\Salutation\SalutationEntity;
use Shopware\Production\Merchants\Content\Merchant\MerchantEntity;
use Shopware\Production\Merchants\Content\Merchant\SalesChannelContextExtension;
use Shopware\Storefront\Controller\StorefrontController;
use SwagVoucherFunding\Service\VoucherFundingEmailService;
use SwagVoucherFunding\Service\VoucherFundingMerchantService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"storefront"})
 */
class TemporaryTestController extends StorefrontController
{
    private $voucherFundingEmailService;
    /**
     * @var MailService
     */
    private $mailService;

    public function __construct(
        VoucherFundingEmailService $voucherFundingEmailService
    )
    {
        $this->voucherFundingEmailService = $voucherFundingEmailService;
    }

    /**
     * @Route(name="merchant-api.action.test", path="/merchant-api/v{version}/voucher-funding/test", methods={"GET"})
     * @throws CustomerNotLoggedInException
     */
    public function test(SalesChannelContext $context) : JsonResponse
    {
        $merchant = SalesChannelContextExtension::extract($context);

        $vouchers = $this->createFakeVouchers();
        $merchant = $this->createFakeMerchant($context->getSalesChannel());
        $orderCustomer = $this->createFakeCustomer();
        $currency = $this->createFakeCurrency();
        
        $this->voucherFundingEmailService->sendEmailCustomer($vouchers, $merchant, $orderCustomer, $currency, $context->getContext());

        return new JsonResponse([]);
    }

    private function createFakeVouchers() : array
    {
        $vouchers = [];

        $voucherNum = rand(1, 5);

        for($i = 0; $i < $voucherNum; $i++) {
            $vouchers[] = [
                'code' => $this->randomVoucherCode(),
                'name' => 'This is test voucher ' . ($i + 1),
                'value' => [
                    'price' => rand(100, 1000)
                ]
            ];
        }

        return $vouchers;
    }

    private function createFakeMerchant(SalesChannelEntity $salesChannelEntity) : MerchantEntity
    {
        $faker = Factory::create();
        $merchant = new MerchantEntity();

        $merchant->setEmail($faker->email);
        $merchant->setPublicEmail($faker->email);
        $merchant->setPublicCompanyName($faker->company);
        $merchant->setPublicPhoneNumber($faker->phoneNumber);
        $merchant->setCountry($faker->country);
        $merchant->setCity($faker->city);
        $merchant->setStreet($faker->streetAddress);
        $merchant->setPublicWebsite($faker->url);
        $merchant->setSalesChannel($salesChannelEntity);
        $merchant->setSalesChannelId($salesChannelEntity->getId());

        return $merchant;
    }

    private function createFakeCustomer() : OrderCustomerEntity
    {
        $faker = Factory::create();
        $customer = new OrderCustomerEntity();
        $salutation = new SalutationEntity();
        $salutation->setDisplayName(array_rand(array_flip(['Mr', 'Mrs'])));
        $customer->setSalutation($salutation);
        $customer->setFirstName($faker->firstName);
        $customer->setLastName($faker->lastName);
        $customer->setEmail($faker->email);

        return $customer;
    }

    private function createFakeCurrency() : CurrencyEntity
    {
        $currency = new CurrencyEntity();
        $currency->setSymbol("€");
        return $currency;
    }

    private function randomVoucherCode()
    {
        return mb_strtoupper(Random::getAlphanumericString(10));
    }

}
