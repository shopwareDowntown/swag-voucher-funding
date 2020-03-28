<?php

namespace SwagVoucherFunding\Service;

use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Content\MailTemplate\Service\MailSender;
use Shopware\Core\Content\MailTemplate\Service\MessageFactory;
use Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Dompdf\Dompdf;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\System\SystemConfig\SystemConfigEntity;
use Shopware\Production\Merchants\Content\Merchant\MerchantEntity;

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
     * @var EntityRepositoryInterface
     */
    private $merchantRepository;

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

    public function __construct(
        EntityRepositoryInterface $soldVoucherRepository,
        EntityRepositoryInterface $systemConfigRepository,
        MessageFactory $messageFactory,
        MailSender $mailSender,
        StringTemplateRenderer $templateRenderer
    ) {
        $this->soldVoucherRepository = $soldVoucherRepository;
        $this->systemConfigRepository = $systemConfigRepository;
        $this->messageFactory = $messageFactory;
        $this->mailSender = $mailSender;
        $this->templateRenderer = $templateRenderer;
    }

    public function loadSoldVouchers(string $merchantId, SalesChannelContext $context) : array
    {
        $criteria = new Criteria([$merchantId]);
        $criteria->addAssociation('merchants');

        $soldVouchers[] = $this->soldVoucherRepository->search($criteria, $context->getContext());

        return $soldVouchers;
    }

    /**
     * @param  string  $merchantId
     * @param  EntityCollection  $lineItemCollection
     * @param  Context  $context
     */
    public function createSoldVoucher(string $merchantId, EntityCollection $lineItemCollection, Context $context) : void
    {
        $vouchers = [];

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
    }

    public function redeemVoucher(MerchantEntity $merchant, SalesChannelContext $context)
    {
        $this->sendEmailCustomer($merchant, $context);
    }

    private function sendEmailCustomer(MerchantEntity $merchant, SalesChannelContext $context)
    {
        // TODO set `price` and `code` from SoldVoucher
        $data = new DataBag();
        $data->set('price', rand(100, 300) . '€');
        $data->set('today', date("d.m.Y"));
        $data->set('code', self::generateVoucherCode(10));
        $data->set('subject', $this->getSubjectTemplate($context));
        $data->set('sendFrom', $merchant->getEmail());
        $data->set('sendName', $this->getSenderNameTemplate($context));
        $data->set('sender', [$data->get('sendFrom') => $data->get('sendName')]);

        // TODO update buyer recipients here
        $data->set(
            'recipients', [
                $context->getCustomer()->getEmail() => $context->getCustomer()->getFirstName() . ' ' . $context->getCustomer()->getLastName()
            ]
        );

        $contentTemplate = $this->getContentTemplate($data->all(), $context);

        // Use array here for support a mail can have many voucher
        $voucherUrls[] = $this->renderVoucherAttachment($contentTemplate);
        $this->sendMail($data, $contentTemplate, $voucherUrls);

        foreach ($voucherUrls as $voucherUrl) {
            unlink($voucherUrl);
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

    private function getSubjectTemplate(SalesChannelContext $context): string
    {
        $config = $this->getSystemConfig('subject', $context);

        $templateData['salesChannel'] = $context->getSalesChannel();
        return $this->templateRenderer->render($config, $templateData, $context->getContext());
    }

    private function getSenderNameTemplate(SalesChannelContext $context): string
    {
        $config = $this->getSystemConfig('senderName', $context);

        $templateData['salesChannel'] = $context->getSalesChannel();
        return $this->templateRenderer->render($config, $templateData, $context->getContext());
    }

    private function getContentTemplate(array $data, SalesChannelContext $context): string
    {
        $config = $this->getSystemConfig('pdfTemplate', $context);

        return $this->templateRenderer->render($config, $data, $context->getContext());
    }

    private function renderVoucherAttachment(string $contentTemplate): string
    {
        $dompdf = new Dompdf();
        $dompdf->loadHtml($contentTemplate);
        $dompdf->render();
        $output = $dompdf->output();
        $voucherPath = self::TEMP_DIR . '/' . Uuid::randomHex() . '.pdf';
        $voucherUrl = __DIR__ . '/../../../../../public/' . $voucherPath;
        file_put_contents($voucherUrl, $output);

        return $voucherPath;
    }

    private function getSystemConfig(string $value, SalesChannelContext $context): String
    {
        $criteria = new Criteria();
        $criteria->addFilter(new ContainsFilter('configurationKey', 'SwagVoucherFunding.config.' . $value));

        $systemConfigs = $this->systemConfigRepository->search($criteria, $context->getContext())->getEntities();
        if (empty($systemConfigs)) {
            throw new \InvalidArgumentException('Error');
        }

        $systemConfigs->filter(function (SystemConfigEntity $systemConfig) use ($context) {
            return $systemConfig->getSalesChannelId() === $context->getSalesChannel()->getId();
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
