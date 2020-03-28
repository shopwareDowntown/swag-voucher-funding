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
                'configurationValue' => $this->pdfTemplate()
            ]
        ], $context);

        $systemConfigRepository->create([
            [
                'id' => Uuid::randomHex(),
                'configurationKey' => $this->getName() . '.config.subject',
                'configurationValue' => 'Your voucher with {{ merchant.publicCompanyName }}'
            ]
        ], $context);

        $systemConfigRepository->create([
            [
                'id' => Uuid::randomHex(),
                'configurationKey' => $this->getName() . '.config.senderName',
                'configurationValue' => '{{ merchant.publicCompanyName }}'
            ]
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

    private function pdfTemplate(): string
    {
        return file_get_contents(__DIR__ . '/Resources/views/pdf_template.html.twig');
    }
}
