<?php

namespace SwagVoucherFunding\Service;

use League\Flysystem\FileNotFoundException;
use League\Flysystem\FilesystemInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Content\MailTemplate\Service\MailSender;
use Shopware\Core\Content\MailTemplate\Service\MessageFactory;
use Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\System\Currency\CurrencyEntity;
use Dompdf\Dompdf;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\System\SystemConfig\SystemConfigEntity;
use Shopware\Production\Merchants\Content\Merchant\MerchantEntity;

class VoucherFundingEmailService
{
    const TEMP_DIR = 'bundles/storefront';

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
     * @var FilesystemInterface
     */
    private $publicFilesystem;

    public function __construct(
        EntityRepositoryInterface $systemConfigRepository,
        MessageFactory $messageFactory,
        MailSender $mailSender,
        StringTemplateRenderer $templateRenderer,
        FilesystemInterface $publicFilesystem
    ) {
        $this->systemConfigRepository = $systemConfigRepository;
        $this->messageFactory = $messageFactory;
        $this->mailSender = $mailSender;
        $this->templateRenderer = $templateRenderer;
        $this->publicFilesystem = $publicFilesystem;
    }

    public function sendEmailCustomer(
        array $vouchers,
        MerchantEntity $merchant,
        OrderCustomerEntity $orderCustomer,
        CurrencyEntity $currencyEntity,
        Context $context
    ) : string
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

        return $contentTemplate;
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
