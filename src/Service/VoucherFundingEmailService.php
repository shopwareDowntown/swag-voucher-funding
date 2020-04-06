<?php declare(strict_types=1);

namespace SwagVoucherFunding\Service;

use Shopware\Core\Checkout\Document\FileGenerator\FileGeneratorRegistry;
use Shopware\Core\Checkout\Document\FileGenerator\PdfGenerator;
use Shopware\Core\Checkout\Document\GeneratedDocument;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Content\MailTemplate\Service\MailService;
use Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Production\Merchants\Content\Merchant\MerchantEntity;

class VoucherFundingEmailService
{
    const VOUCHER_PDF_NAME = 'downtown-gutschein.pdf';

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    /**
     * @var MailService
     */
    private $mailService;

    /**
     * @var StringTemplateRenderer
     */
    private $templateRenderer;

    /**
     * @var FileGeneratorRegistry
     */
    private $fileGeneratorRegistry;

    public function __construct(
        SystemConfigService $systemConfigService,
        MailService $mailService,
        StringTemplateRenderer $templateRenderer,
        FileGeneratorRegistry $fileGeneratorRegistry
    ) {
        $this->systemConfigService = $systemConfigService;
        $this->mailService = $mailService;
        $this->templateRenderer = $templateRenderer;
        $this->fileGeneratorRegistry = $fileGeneratorRegistry;
    }

    public function sendEmailCustomer(
        array $templateData,
        string $salesChannelId,
        OrderCustomerEntity $customerEntity,
        Context $context
    ): void {
        $customerName = sprintf('%s %s %s',
            $customerEntity->getSalutation()->getDisplayName(),
            $customerEntity->getFirstName(),
            $customerEntity->getLastName()
        );

        $data = new DataBag();
        $data->set('salesChannelId', $salesChannelId);
        $data->set('subject', $this->getPluginConfig('customerSubject', $salesChannelId));
        $data->set('senderName', $this->getPluginConfig('customerSenderName', $salesChannelId));
        $data->set('recipients', [$customerEntity->getEmail() => $customerName]);
        $data->set('contentHtml', $this->getPluginConfig('customerHtmlTemplate', $salesChannelId));
        $data->set('contentPlain', $this->getPluginConfig('customerPlainTemplate', $salesChannelId));

        $voucherTemplate = $this->getContentTemplate('pdfTemplate', $templateData, $salesChannelId, $context);
        $voucherAttachment = $this->renderVoucherAttachment($voucherTemplate);
        $pdfGenerator = $this->fileGeneratorRegistry->getGenerator(PdfGenerator::FILE_EXTENSION);

        $data->set('binAttachments', [[
            'content' => $pdfGenerator->generate($voucherAttachment),
            'fileName' => $voucherAttachment->getFilename(),
            'mimeType' => $voucherAttachment->getContentType(),
        ]]);

        $this->mailService->send($data->all(), $context, $templateData);
    }

    public function sendEmailMerchant(
        array $templateData,
        MerchantEntity $merchant,
        Context $context
    ): void {
        $salesChannelId = $merchant->getSalesChannelId();
        $data = new DataBag();
        $data->set('salesChannelId', $salesChannelId);
        $data->set('subject', $this->getPluginConfig('merchantSubject', $salesChannelId));
        $data->set('senderName', $this->getPluginConfig('merchantSenderName', $salesChannelId));
        $data->set('recipients', [$merchant->getEmail() => $merchant->getPublicCompanyName()]);
        $data->set('contentHtml', $this->getPluginConfig('merchantHtmlTemplate', $salesChannelId));
        $data->set('contentPlain', $this->getPluginConfig('merchantPlainTemplate', $salesChannelId));

        $this->mailService->send($data->all(), $context, $templateData);
    }

    private function getPluginConfig(string $value, string $salesChannelId): string
    {
        return (string) $this->systemConfigService->get('SwagVoucherFunding.config.' . $value, $salesChannelId);
    }

    private function renderVoucherAttachment(string $htmlContent): GeneratedDocument
    {
        $generatedDocument = new GeneratedDocument();
        $generatedDocument->setHtml($htmlContent);
        $generatedDocument->setFilename(self::VOUCHER_PDF_NAME);
        $generatedDocument->setPageOrientation('landscape');
        $generatedDocument->setPageSize('a4');
        $generatedDocument->setContentType(PdfGenerator::FILE_CONTENT_TYPE);

        return $generatedDocument;
    }

    private function getContentTemplate(string $name, array $data, string $salesChannelId, Context $context): string
    {
        $config = $this->getPluginConfig($name, $salesChannelId);

        return $this->templateRenderer->render($config, $data, $context);
    }
}
