<?php

declare(strict_types=1);

namespace App\State\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\MangaFollow;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @implements ProviderInterface<MangaFollow>
 */
final class UserFollowsProvider implements ProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     * @return iterable<MangaFollow>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): iterable
    {
        $userId = $uriVariables['id'] ?? null;

        // Ownership check: only the user themselves or an admin can view follows
        $currentUser = $this->security->getUser();
        if ($currentUser === null || ($currentUser->getId() !== $userId && ! $this->security->isGranted('ROLE_ADMIN'))) {
            throw new AccessDeniedHttpException('You can only view your own follows');
        }

        $user = $this->entityManager->getReference(User::class, $userId);

        $qb = $this->entityManager->getRepository(MangaFollow::class)->createQueryBuilder('mf')
            ->leftJoin('mf.manga', 'm')
            ->addSelect('m')
            ->where('mf.user = :user')
            ->setParameter('user', $user)
            ->orderBy('mf.followedAt', 'DESC');

        // Apply pagination from context
        $page = max(1, (int) ($context['filters']['page'] ?? 1));
        $itemsPerPage = min(100, max(1, (int) ($context['filters']['itemsPerPage'] ?? 20)));
        $qb->setFirstResult(($page - 1) * $itemsPerPage)
           ->setMaxResults($itemsPerPage);

        /** @var array<MangaFollow> $follows */
        $follows = $qb->getQuery()->getResult();

        return $follows;
    }
}
