<?php

declare(strict_types=1);

namespace App\Doctrine\Extension;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Chapter;
use App\Entity\Page;
use App\Enum\ChapterStatus;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Brouillons et chapitres programmés ne sont visibles que des administrateurs, ainsi que leurs pages
 * (issue #31) : ailleurs, ils répondent 404 comme s'ils n'existaient pas. Les règles plus fines
 * (auteur de l'upload, modérateurs) arrivent avec les Voters de #38.
 */
final readonly class PublishedChapterExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function __construct(
        private Security $security,
    ) {
    }

    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $this->onlyPublished($queryBuilder, $queryNameGenerator, $resourceClass);
    }

    public function applyToItem(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, array $identifiers, ?Operation $operation = null, array $context = []): void
    {
        $this->onlyPublished($queryBuilder, $queryNameGenerator, $resourceClass);
    }

    private function onlyPublished(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass): void
    {
        if (!\in_array($resourceClass, [Chapter::class, Page::class], true) || $this->security->isGranted('ROLE_ADMIN')) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];
        if (Page::class === $resourceClass) {
            $chapterAlias = $queryNameGenerator->generateJoinAlias('chapter');
            $queryBuilder->innerJoin(\sprintf('%s.chapter', $alias), $chapterAlias);
            $alias = $chapterAlias;
        }

        $status = $queryNameGenerator->generateParameterName('status');
        $queryBuilder
            ->andWhere(\sprintf('%s.status = :%s', $alias, $status))
            ->setParameter($status, ChapterStatus::Published);
    }
}
