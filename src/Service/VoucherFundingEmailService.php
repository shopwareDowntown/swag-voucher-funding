<?php

namespace SwagVoucherFunding\Service;

use Dompdf\Options;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Content\MailTemplate\Service\MailService;
use Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\System\Currency\CurrencyEntity;
use Dompdf\Dompdf;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\System\Currency\CurrencyFormatter;
use Shopware\Core\System\SystemConfig\SystemConfigEntity;
use Shopware\Production\Merchants\Content\Merchant\MerchantEntity;

class VoucherFundingEmailService
{
    const VOUCHER_PDF_NAME = 'downtown-gutschein.pdf';

    /**
     * @var EntityRepositoryInterface
     */
    private $systemConfigRepository;

    /**
     * @var MailService
     */
    private $mailService;

    /**
     * @var StringTemplateRenderer
     */
    private $templateRenderer;

    /**
     * @var CurrencyFormatter
     */
    private $currencyFormatter;

    public function __construct(
        EntityRepositoryInterface $systemConfigRepository,
        MailService $mailService,
        StringTemplateRenderer $templateRenderer,
        CurrencyFormatter $currencyFormatter
    ) {
        $this->systemConfigRepository = $systemConfigRepository;
        $this->mailService = $mailService;
        $this->templateRenderer = $templateRenderer;
        $this->currencyFormatter = $currencyFormatter;
    }

    public function sendEmailCustomer(
        array $vouchers,
        MerchantEntity $merchant,
        OrderCustomerEntity $orderCustomer,
        CurrencyEntity $currencyEntity,
        Context $context
    ) : void
    {
        $customerName = sprintf('%s. %s %s',
            $orderCustomer->getSalutation()->getDisplayName(),
            $orderCustomer->getFirstName(),
            $orderCustomer->getLastName()
        );

        $currencyVouchers = [];
        foreach ($vouchers as $voucher) {
            $currencyVoucher['code'] = $voucher['code'];
            $currencyVoucher['price'] = $this->currencyFormatter->formatCurrencyByLanguage(
                $voucher['value']->getPrice(),
                $currencyEntity->getIsoCode(),
                $context->getLanguageId(),
                $context
            );
            $currencyVouchers[] = $currencyVoucher;
        }


        $templateData = [
            'merchant' => $merchant,
            'customerName' => $customerName,
            'vouchers' => $currencyVouchers,
            'today' => date("d.m.Y")
        ];

        $data = new DataBag();
        $data->set('salesChannelId', $merchant->getSalesChannelId());
        $data->set('subject', $this->getSystemConfig('subject', $merchant->getSalesChannelId(), $context));
        $data->set('senderName', $this->getSystemConfig('senderName', $merchant->getSalesChannelId(), $context));
        $data->set(
            'recipients', [
                $orderCustomer->getEmail() => $customerName
            ]
        );

        $contentTemplate = $this->getContentTemplate($templateData, $merchant, $context);
        $data->set('contentHtml', $contentTemplate);

        // TODO: Implement content plain
        $data->set('contentPlain', sprintf('Thank you %s! Your purchased vouchers is included in the attachments!', $customerName));

        $voucherPdf = $this->renderVoucherAttachment($contentTemplate);

        $data->set('binAttachments', [[
            'content' => $voucherPdf,
            'fileName' => self::VOUCHER_PDF_NAME,
            'mimeType' => 'application/pdf',
        ]]);

        $this->mailService->send($data->all(), $context, $templateData);
    }


    private function getContentTemplate(array $data, MerchantEntity $merchant, Context $context): string
    {
        $config = $this->getSystemConfig('pdfTemplate', $merchant->getSalesChannelId(), $context);

        return $this->templateRenderer->render($config, $data, $context);
    }

    private function renderVoucherAttachment(string $contentTemplate): string
    {
        $options = new Options();
        $options->setDefaultFont('Arial');
        $options->setIsPhpEnabled(true);
        $options->setIsRemoteEnabled(true);

        $dompdf = new Dompdf();
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->setOptions($options);
        $dompdf->loadHtml($contentTemplate);
        $dompdf->render();

        return $dompdf->output();
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
}
