<?php declare(strict_types=1);

namespace YukiTFreeShippingNotice;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\PrefixFilter;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\System\Snippet\SnippetCollection;

class YukiTFreeShippingNotice extends Plugin
{
    private const SNIPPET_PREFIX = 'yukiTFreeShippingNotice.';

    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);

        if ($uninstallContext->keepUserData()) {
            return;
        }

        $this->removeSnippetOverrides($uninstallContext);
    }

    private function removeSnippetOverrides(
        UninstallContext $uninstallContext,
    ): void {
        /** @var EntityRepository<SnippetCollection> $snippetRepository */
        $snippetRepository = $this->container->get('snippet.repository');

        $criteria = (new Criteria())->addFilter(
            new PrefixFilter(
                'translationKey',
                self::SNIPPET_PREFIX,
            ),
        );

        $ids = $snippetRepository
            ->searchIds($criteria, $uninstallContext->getContext())
            ->getIds();

        if ($ids === []) {
            return;
        }

        $snippetRepository->delete(
            array_map(
                static fn (string $id): array => ['id' => $id],
                $ids,
            ),
            $uninstallContext->getContext(),
        );
    }
}
