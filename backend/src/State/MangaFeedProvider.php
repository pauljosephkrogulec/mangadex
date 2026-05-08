<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Chapter;
use App\Entity\Manga;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
     * @return iterable<Chapter>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): iterable
    {
        $mangaId = $uriVariables['id'] ?? null;

        $manga = $this->entityManager->getRepository(Manga::class)->find($mangaId);
        if (! $manga) {
            throw new NotFoundHttpException('Manga not found');
        }

        /** @var array<string, mixed> $filters */
        $filters = $context['filters'] ?? [];
        $orderBy = [];

        if (isset($filters['order']) && is_array($filters['order'])) {
            foreach ($filters['order'] as $field => $direction) {
                if (is_string($field) && is_string($direction)) {
                    if (in_array($field, ['chapterNumber', 'volume', 'createdAt', 'language'], true)) {
                        $orderBy[$field] = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
                    }
                }
            }
        }

        if (empty($orderBy)) {
            $orderBy = ['chapterNumber' => 'ASC'];
        }

        $allowedFields = ['chapterNumber', 'volume', 'createdAt', 'language'];
        $field = array_key_first($orderBy) ?? 'chapterNumber';
        $direction = reset($orderBy);
        $direction = in_array($direction, ['ASC', 'DESC'], true) ? $direction : 'ASC';

        $qb = $this->entityManager->getRepository(Chapter::class)->createQueryBuilder('c')
            ->leftJoin('c.scanlationGroup', 'sg')
            ->addSelect('sg')
            ->leftJoin('c.manga', 'm')
            ->addSelect('m')
            ->where('c.manga = :manga')
            ->setParameter('manga', $manga)
            ->orderBy('c.' . $field, $direction);

        if (isset($filters['language']) && is_string($filters['language'])) {
            $qb->andWhere('c.language = :language')
               ->setParameter('language', $filters['language']);
        }

        /** @var array<Chapter> $result */
        $result = $qb->getQuery()->getResult();

        return $result ?? [];
    }
}
