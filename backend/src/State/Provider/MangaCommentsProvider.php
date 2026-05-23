<?php

declare(strict_types=1);

namespace App\State\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Comment;
use App\Entity\Manga;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @implements ProviderInterface<Comment>
 */
final class MangaCommentsProvider implements ProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     *
     * @return iterable<Comment>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): iterable
    {
        $mangaId = $uriVariables['id'] ?? null;

        $manga = $this->entityManager->getReference(Manga::class, $mangaId);

        $qb = $this->entityManager->getRepository(Comment::class)->createQueryBuilder('c')
            ->where('c.manga = :manga')
            ->setParameter('manga', $manga)
            ->orderBy('c.createdAt', 'DESC');

        $page = max(1, (int) ($context['filters']['page'] ?? 1));
        $itemsPerPage = min(100, max(1, (int) ($context['filters']['itemsPerPage'] ?? 20)));
        $qb->setFirstResult(($page - 1) * $itemsPerPage)
           ->setMaxResults($itemsPerPage);

        /** @var array<Comment> $comments */
        $comments = $qb->getQuery()->getResult();

        return $comments;
    }
}
