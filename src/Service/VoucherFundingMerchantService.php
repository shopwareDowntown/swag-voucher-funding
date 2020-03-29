<?php

namespace SwagVoucherFunding\Service;

use League\Flysystem\FileNotFoundException;
use League\Flysystem\FilesystemInterface;
use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\MailTemplate\Service\MailSender;
use Shopware\Core\Content\MailTemplate\Service\MessageFactory;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Dompdf\Dompdf;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\SystemConfig\SystemConfigEntity;
use Shopware\Production\Merchants\Content\Merchant\MerchantEntity;
use SwagVoucherFunding\Checkout\SoldVoucher\SoldVoucherEntity;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class VoucherFundingMerchantService
{
    const TEMP_DIR = 'bundles/storefront';
    /**
     * @var EntityRepositoryInterface
     */
    private $soldVoucherRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $systemConfigRepository;

    /**
     * @var MessageFactory
     */
    private $messageFactory;

    /**
     * @var MailSender
     */
    private $mailSender;

    /**
     * @var StringTemplateRenderer
     */
    private $templateRenderer;
    /**
     * @var EntityRepositoryInterface
     */
    private $currencyRepository;
    /**
     * @var FilesystemInterface
     */
    private $publicFilesystem;

    public function __construct(
        EntityRepositoryInterface $soldVoucherRepository,
        EntityRepositoryInterface $systemConfigRepository,
        EntityRepositoryInterface $currencyRepository,
        MessageFactory $messageFactory,
        MailSender $mailSender,
        StringTemplateRenderer $templateRenderer,
        FilesystemInterface $publicFilesystem
    ) {
        $this->soldVoucherRepository = $soldVoucherRepository;
        $this->systemConfigRepository = $systemConfigRepository;
        $this->currencyRepository = $currencyRepository;
        $this->messageFactory = $messageFactory;
        $this->mailSender = $mailSender;
        $this->templateRenderer = $templateRenderer;
        $this->publicFilesystem = $publicFilesystem;
    }

    public function loadSoldVouchers(string $merchantId, SalesChannelContext $context) : array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('merchantId', $merchantId));

        return $this->soldVoucherRepository->search($criteria, $context->getContext())->getElements();
    }

    /**
     * @param  MerchantEntity  $merchant
     * @param  OrderEntity $orderEntity
     * @param  EntityCollection  $lineItemCollection
     * @param  Context  $context
     */
    public function createSoldVoucher(
        MerchantEntity $merchant,
        OrderEntity $orderEntity,
        EntityCollection $lineItemCollection,
        Context $context
    ) : void
    {
        $vouchers = [];
        $merchantId = $merchant->getId();
        $currencyId = $orderEntity->getCurrencyId();
        $currency = $this->currencyRepository->search(new Criteria([$currencyId]), $context)->get($currencyId);

        /** @var OrderLineItemEntity $lineItemEntity */
        foreach ($lineItemCollection as $lineItemEntity) {
            $voucherNum = $lineItemEntity->getQuantity();
            $voucherName = $lineItemEntity->getProduct()->getName();
            $voucherLineItemId = $lineItemEntity->getId();
            $lineItemPrice = $lineItemEntity->getPriceDefinition();
            $voucherValue = new AbsolutePriceDefinition($lineItemPrice->getPrice(), $lineItemPrice->getPrecision());

            for ($i = 0; $i < $voucherNum; $i++) {
                $code =  $this->generateUniqueVoucherCode($merchantId, $context);

                $voucher = [];
                $voucher['merchantId'] = $merchantId;
                $voucher['orderLineItemId'] = $voucherLineItemId;
                $voucher['name'] = $voucherName;
                $voucher['code'] = $code;
                $voucher['value'] = $voucherValue;

                $vouchers[] = $voucher;
            }
        }

        $this->soldVoucherRepository->create($vouchers, $context);
        $this->sendEmailCustomer($vouchers, $merchant, $orderEntity->getOrderCustomer(), $currency, $context);
    }

    public function redeemVoucher(string $voucherCode, MerchantEntity $merchant, SalesChannelContext $context) : void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('code', $voucherCode));
        $criteria->addFilter(new EqualsFilter('redeemedAt', null));
        $criteria->addFilter(new EqualsFilter('merchantId', $merchant->getId()));

        /** @var SoldVoucherEntity $voucher */
        $voucher = $this->soldVoucherRepository->search($criteria, $context->getContext())->first();

        if(!$voucher) {
            throw new NotFoundHttpException(sprintf('Cannot find valid voucher with code %s', $voucherCode));
        }

        $this->soldVoucherRepository->update([[
            'id' => $voucher->getId(),
            'redeemedAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)
        ]], $context->getContext());
    }

    public function getVoucherStatus(string $voucherCode, MerchantEntity $merchant, SalesChannelContext $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('code', $voucherCode));
        $criteria->addFilter(new EqualsFilter('merchantId', $merchant->getId()));
        $criteria->addAssociation('orderLineItem.order.customer');

        /** @var SoldVoucherEntity $voucher */
        $voucher = $this->soldVoucherRepository->search($criteria, $context->getContext())->first();

        $data = [
            'status' => 'invalid',
            'customer' => null
        ];

        if(!$voucher) {
            return $data;
        }

        $data['customer'] = $voucher->getOrderLineItem()->getOrder()->getOrderCustomer();

        $data['status'] = $voucher->getRedeemedAt() ? 'used' : 'valid';

        return $data;
    }

    private function sendEmailCustomer(
        array $vouchers,
        MerchantEntity $merchant,
        OrderCustomerEntity $orderCustomer,
        CurrencyEntity $currencyEntity,
        Context $context)
    {
        $customerName = sprintf('%s. %s %s',
            $orderCustomer->getSalutation()->getDisplayName(),
            $orderCustomer->getFirstName(),
            $orderCustomer->getLastName()
        );

        $data = new DataBag();
        $data->set('vouchers', $vouchers);
        $data->set('today', date("d.m.Y"));
        $data->set('currency', $currencyEntity->getSymbol());
        $data->set('merchant', $merchant);
        $data->set('subject', $this->getSubjectTemplate($merchant, $context));
        $data->set('customerName', $customerName);
        $data->set('sendFrom', $merchant->getEmail());
        $data->set('sendName', $this->getSenderNameTemplate($merchant, $context));
        $data->set('sender', [$data->get('sendFrom') => $data->get('sendName')]);
        $data->set(
            'recipients', [
                $orderCustomer->getEmail() => $customerName
            ]
        );

        $contentTemplate = $this->getContentTemplate($data->all(), $merchant, $context);

        // Use array here for support a mail can have many voucher
        $voucherUrls[] = $this->renderVoucherAttachment($contentTemplate);
        $this->sendMail($data, $contentTemplate, $voucherUrls);

        foreach ($voucherUrls as $voucherUrl) {
            try {
                $this->publicFilesystem->delete($voucherUrl);
            } catch (FileNotFoundException $e) {
                // TODO: Handle FileNotFound
            }
        }
    }

    private function generateUniqueVoucherCode(string $merchantId, Context $context)
    {
        $code = $this->generateVoucherCode();

        if($this->checkCodeUnique($merchantId, $code, $context)) {
            return $code;
        }

        return $this->generateUniqueVoucherCode($merchantId, $context);
    }

    private function checkCodeUnique(string $merchantId, string $code, Context $context)
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('merchantId', $merchantId));
        $criteria->addFilter(new EqualsFilter('code', $code));

        return $this->soldVoucherRepository->search($criteria, $context)->count() === 0;
    }

    private function generateVoucherCode(int $length = 10)
    {
        return mb_strtoupper(Random::getAlphanumericString($length));
    }

    private function getSubjectTemplate(MerchantEntity $merchant, Context $context): string
    {
        $config = $this->getSystemConfig('subject', $merchant->getSalesChannelId(), $context);

        $templateData['merchant'] = $merchant;

        return $this->templateRenderer->render($config, $templateData, $context);
    }

    private function getSenderNameTemplate(MerchantEntity $merchant, Context $context): string
    {
        $config = $this->getSystemConfig('senderName', $merchant->getSalesChannelId(), $context);

        $templateData['merchant'] = $merchant;

        return $this->templateRenderer->render($config, $templateData, $context);
    }

    private function getContentTemplate(array $data, MerchantEntity $merchant, Context $context): string
    {
        $config = $this->getSystemConfig('pdfTemplate', $merchant->getSalesChannelId(), $context);

        return $this->templateRenderer->render($config, $data, $context);
    }

    private function renderVoucherAttachment(string $contentTemplate): string
    {
        $dompdf = new Dompdf();
        $dompdf->loadHtml($contentTemplate);
        $dompdf->render();
        $output = $dompdf->output();
        $voucherPath = self::TEMP_DIR . DIRECTORY_SEPARATOR . Uuid::randomHex() . '.pdf';

        $this->publicFilesystem->put($voucherPath, $output);

        return $voucherPath;
    }

    private function getSystemConfig(string $value, string $salesChannelId, Context $context): String
    {
        $criteria = new Criteria();
        $criteria->addFilter(new ContainsFilter('configurationKey', 'SwagVoucherFunding.config.' . $value));

        $systemConfigs = $this->systemConfigRepository->search($criteria, $context)->getEntities();
        if (empty($systemConfigs)) {
            throw new \InvalidArgumentException('Error');
        }

        $systemConfigs->filter(function (SystemConfigEntity $systemConfig) use ($salesChannelId) {
            return $systemConfig->getSalesChannelId() === $salesChannelId;
        });

        if (empty($systemConfig)) {
            $systemConfig = $systemConfigs->first();
        }

        return $systemConfig->getConfigurationValue();
    }

    private function sendMail(DataBag $data, string $contentTemplate, array $voucherUrls): void
    {
        $contents = [
            'text/html' => $contentTemplate,
            'text/plain' => 'Hello' // TODO update content plain later
        ];

        $binAttachments = $data->get('binAttachments') ?? null;

        $message = $this->messageFactory->createMessage(
            $data->get('subject'),
            $data->get('sender'),
            $data->get('recipients'),
            $contents,
            $voucherUrls,
            $binAttachments
        );

        $this->mailSender->send($message);
    }
}
