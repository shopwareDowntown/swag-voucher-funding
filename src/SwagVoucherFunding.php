<?php declare(strict_types=1);

namespace SwagVoucherFunding;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Uuid\Uuid;

class SwagVoucherFunding extends Plugin
{
    public function install(InstallContext $installContext): void
    {
        parent::install($installContext);

        $this->createConfiguration($installContext->getContext());
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);

        if ($uninstallContext->keepUserData()) {
            return;
        }

        $this->removeConfiguration($uninstallContext->getContext());
    }

    private function createConfiguration(Context $context): void
    {
        /** @var EntityRepositoryInterface $systemConfigRepository */
        $systemConfigRepository = $this->container->get('system_config.repository');
        $systemConfigRepository->create([
            [
                'id' => Uuid::randomHex(),
                'configurationKey' => $this->getName() . '.config.pdfTemplate',
                'configurationValue' => $this->getPdfTemplate(),
            ],
        ], $context);

        $systemConfigRepository->create([
            [
                'id' => Uuid::randomHex(),
                'configurationKey' => $this->getName() . '.config.customerSubject',
                'configurationValue' => 'Your vouchers with {{ merchant.publicCompanyName }}',
            ],
        ], $context);

        $systemConfigRepository->create([
            [
                'id' => Uuid::randomHex(),
                'configurationKey' => $this->getName() . '.config.customerSenderName',
                'configurationValue' => '{{ merchant.publicCompanyName }}',
            ],
        ], $context);

        $systemConfigRepository->create([
            [
                'id' => Uuid::randomHex(),
                'configurationKey' => $this->getName() . '.config.customerHtmlTemplate',
                'configurationValue' => $this->getCustomerMailHTMLTemplate(),
            ],
        ], $context);

        $systemConfigRepository->create([
            [
                'id' => Uuid::randomHex(),
                'configurationKey' => $this->getName() . '.config.customerPlainTemplate',
                'configurationValue' => $this->getCustomerMailPlainTemplate(),
            ],
        ], $context);

        $systemConfigRepository->create([
            [
                'id' => Uuid::randomHex(),
                'configurationKey' => $this->getName() . '.config.merchantSubject',
                'configurationValue' => 'We have just issued vouchers for your customer',
            ],
        ], $context);

        $systemConfigRepository->create([
            [
                'id' => Uuid::randomHex(),
                'configurationKey' => $this->getName() . '.config.merchantSenderName',
                'configurationValue' => 'Shopware AG',
            ],
        ], $context);

        $systemConfigRepository->create([
            [
                'id' => Uuid::randomHex(),
                'configurationKey' => $this->getName() . '.config.merchantHtmlTemplate',
                'configurationValue' => $this->getMerchantMailHTMLTemplate(),
            ],
        ], $context);

        $systemConfigRepository->create([
            [
                'id' => Uuid::randomHex(),
                'configurationKey' => $this->getName() . '.config.merchantPlainTemplate',
                'configurationValue' => $this->getMerchantMailPlainTemplate(),
            ],
        ], $context);
    }

    private function removeConfiguration(Context $context): void
    {
        /** @var EntityRepositoryInterface $systemConfigRepository */
        $systemConfigRepository = $this->container->get('system_config.repository');
        $criteria = (new Criteria())->addFilter(new ContainsFilter('configurationKey', $this->getName() . '.config.'));
        $idSearchResult = $systemConfigRepository->searchIds($criteria, $context);

        $ids = array_map(static function ($id) {
            return ['id' => $id];
        }, $idSearchResult->getIds());

        if ($ids === []) {
            return;
        }

        $systemConfigRepository->delete($ids, $context);
    }

    private function getPdfTemplate(): string
    {
        return file_get_contents(__DIR__ . '/Resources/views/pdf-template.html.twig');
    }

    private function getcustomerMailHTMLTemplate(): string
    {
        return file_get_contents(__DIR__ . '/Resources/views/customer-mail-html-template.html.twig');
    }

    private function getCustomerMailPlainTemplate(): string
    {
        return file_get_contents(__DIR__ . '/Resources/views/customer-mail-plain-template.html.twig');
    }

    private function getMerchantMailHTMLTemplate(): string
    {
        return file_get_contents(__DIR__ . '/Resources/views/merchant-mail-html-template.html.twig');
    }

    private function getMerchantMailPlainTemplate(): string
    {
        return file_get_contents(__DIR__ . '/Resources/views/merchant-mail-plain-template.html.twig');
    }
}
