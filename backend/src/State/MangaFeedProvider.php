<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Chapter;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @implements ProviderInterface<Chapter>
 */
final class MangaFeedProvider implements ProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     *
     * @return iterable<Chapter>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): iterable
    {
        $mangaId = $uriVariables['id'] ?? null;

        /** @var array<string, mixed> $filters */
        $filters = $context['filters'] ?? [];

        // Map allowed sort fields to DQL-safe field paths
        $allowedFields = ['chapterNumber' => 'c.chapterNumber', 'volume' => 'c.volume', 'createdAt' => 'c.createdAt', 'language' => 'c.language', 'title' => 'c.title'];
        $field = 'c.chapterNumber';
        $direction = 'ASC';

        if (isset($filters['order']) && is_array($filters['order'])) {
            foreach ($filters['order'] as $f => $d) {
                if (is_string($f) && is_string($d) && isset($allowedFields[$f])) {
                    $field = $allowedFields[$f];
                    $direction = 'DESC' === strtoupper($d) ? 'DESC' : 'ASC';
                    break;
                }
            }
        }

        $qb = $this->entityManager->getRepository(Chapter::class)->createQueryBuilder('c')
            ->leftJoin('c.scanlationGroup', 'sg')
            ->addSelect('sg')
            ->leftJoin('c.manga', 'm')
            ->addSelect('m')
            ->where('c.manga = :manga')
            ->setParameter('manga', $mangaId)
            ->orderBy($field, $direction);

        if (isset($filters['language']) && is_string($filters['language'])) {
            $qb->andWhere('c.language = :language')
               ->setParameter('language', $filters['language']);
        }

        // Count query (simpler — no joins or ordering needed)
        $countQb = $this->entityManager->getRepository(Chapter::class)->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.manga = :manga')
            ->setParameter('manga', $mangaId);

        if (isset($filters['language']) && is_string($filters['language'])) {
            $countQb->andWhere('c.language = :language')
                    ->setParameter('language', $filters['language']);
        }

        $totalItems = (int) $countQb->getQuery()->getSingleScalarResult();

        // Apply pagination from context
        $page = max(1, (int) ($filters['page'] ?? 1));
        $itemsPerPage = min(100, max(1, (int) ($filters['itemsPerPage'] ?? 20)));
        $qb->setFirstResult(($page - 1) * $itemsPerPage)
           ->setMaxResults($itemsPerPage);

        /** @var array<Chapter> $result */
        $result = $qb->getQuery()->getResult();

        return new TraversablePaginator(
            new \ArrayIterator($result ?? []),
            (float) $page,
            (float) $itemsPerPage,
            (float) $totalItems
        );
    }
}
