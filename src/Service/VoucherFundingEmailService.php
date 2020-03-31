<?php

namespace SwagVoucherFunding\Service;

use Dompdf\Options;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\MailTemplate\Service\MailService;
use Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Dompdf\Dompdf;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
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

    public function __construct(
        EntityRepositoryInterface $systemConfigRepository,
        MailService $mailService,
        StringTemplateRenderer $templateRenderer
    ) {
        $this->systemConfigRepository = $systemConfigRepository;
        $this->mailService = $mailService;
        $this->templateRenderer = $templateRenderer;
    }

    public function sendEmailCustomer(
        array $vouchers,
        MerchantEntity $merchant,
        OrderEntity $order,
        Context $context
    ) : void
    {
        $customerName = sprintf('%s %s %s',
            $order->getOrderCustomer()->getSalutation()->getDisplayName(),
            $order->getOrderCustomer()->getFirstName(),
            $order->getOrderCustomer()->getLastName()
        );

        $templateData = [
            'merchant' => $merchant,
            'order' => $order,
            'customerName' => $customerName,
            'vouchers' => $vouchers,
            'today' => date("d.m.Y")
        ];

        $data = new DataBag();
        $data->set('salesChannelId', $merchant->getSalesChannelId());
        $data->set('subject', $this->getSystemConfig('customerSubject', $merchant->getSalesChannelId(), $context));
        $data->set('senderName', $this->getSystemConfig('customerSenderName', $merchant->getSalesChannelId(), $context));
        $data->set('recipients', [$order->getOrderCustomer()->getEmail() => $customerName]);
        $data->set('contentHtml', $this->getContentTemplate('customerHtmlTemplate', $templateData, $merchant, $context));
        $data->set('contentPlain', $this->getContentTemplate('customerPlainTemplate', $templateData, $merchant, $context));

        $pdfTemplate = $this->getContentTemplate('pdfTemplate', $templateData, $merchant, $context);
        $voucherPdf = $this->renderVoucherAttachment($pdfTemplate);

        $data->set('binAttachments', [[
            'content' => $voucherPdf,
            'fileName' => self::VOUCHER_PDF_NAME,
            'mimeType' => 'application/pdf',
        ]]);

        $this->mailService->send($data->all(), $context, $templateData);
    }

    public function sendEmailMerchant(
        array $vouchers,
        MerchantEntity $merchant,
        OrderEntity $order,
        Context $context
    ): void
    {
        $data = new DataBag();
        $data->set('salesChannelId', $merchant->getSalesChannelId());
        $data->set('subject', $this->getSystemConfig('merchantSubject', $merchant->getSalesChannelId(), $context));
        $data->set('senderName', $this->getSystemConfig('merchantSenderName', $merchant->getSalesChannelId(), $context));
        $data->set('recipients', [$merchant->getEmail() => $merchant->getPublicCompanyName()]);

        $templateData = [
            'merchant' => $merchant,
            'order' => $order,
            'vouchers' => $vouchers,
            'today' => date("d.m.Y")
        ];
        $data->set('contentHtml', $this->getContentTemplate('merchantHtmlTemplate', $templateData, $merchant, $context));
        $data->set('contentPlain', $this->getContentTemplate('merchantPlainTemplate', $templateData, $merchant, $context));

        $this->mailService->send($data->all(), $context);
    }

    private function getContentTemplate(string $name, array $data, MerchantEntity $merchant, Context $context): string
    {
        $config = $this->getSystemConfig($name, $merchant->getSalesChannelId(), $context);

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
