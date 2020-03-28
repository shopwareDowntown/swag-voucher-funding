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
                'configurationValue' => 'Your voucher with {{ salesChannel.name }}'
            ]
        ], $context);

        $systemConfigRepository->create([
            [
                'id' => Uuid::randomHex(),
                'configurationKey' => $this->getName() . '.config.senderName',
                'configurationValue' => '{{ salesChannel.name }}'
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
        $templateHtml = '<table style="width: 100%;line-height: 18px;">';
        $templateHtml .= '<tbody>';
        $templateHtml .= '<tr>';
        $templateHtml .= '<td valign="top" style="background-color: #e6e7e9; text-align: center;">';
        $templateHtml .= '<div style="padding-top: 80px; font-size: 46px;">GUTSCHEIN</div>';
        $templateHtml .= '<div style="padding-top: 220px;color: #a79056;font-size: 110px;">{{ price }}</div>';
        $templateHtml .= '<div style="padding-top: 161px;">bottom</div>';
        $templateHtml .= '</td>';
        $templateHtml .= '<td style="width: 200px;padding: 30px;">';
        $templateHtml .= '<p><strong>Einlöseadresse</strong></p>';
        $templateHtml .= '<p>shopware AG<br>Ebbinghoff 10<br>48624 Schoeppingen</p>';
        $templateHtml .= '<p><br>Telefon: 00 800 746 7626 0<br>E-Mail: <a href="mailto:langeemail@shopware.com">langeemail@shopware.com</a><br>Web: <a href="http://www.shopware.com">www.shopware.com</a></p>';
        $templateHtml .= '<p><br><strong>Glütigkeit</strong><br>Sequatat ecaborrum ipid quamusa pelique re, offici beroviti dolupta tenisim latiis es offici simodit, quodit, incte quo bea quaes es simodit. Dieser Gutschein ist ausgestellt auf den Namen<br>Maximilian Mustermann</p>';
        $templateHtml .= '<p><br><strong>Ausstellungsdatum</strong></p>';
        $templateHtml .= '<p>{{ today }}</p>';
        $templateHtml .= '<p>&nbsp;</p>';
        $templateHtml .= '<p><strong>Gutscheinnummer</strong></p>';
        $templateHtml .= '<p>{{ code }}</p>';
        $templateHtml .= '</td>';
        $templateHtml .= '</tr>';
        $templateHtml .= '</tbody>';
        $templateHtml .= '</table>';
        $templateHtml .= '<style>strong {color: #a79056;}</style>';

        return $templateHtml;
    }
}
