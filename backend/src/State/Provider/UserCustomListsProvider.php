<?php

declare(strict_types=1);

namespace App\State\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\CustomList;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @implements ProviderInterface<CustomList>
 */
final class UserCustomListsProvider implements ProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     *
     * @return iterable<CustomList>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): iterable
    {
        $userId = $uriVariables['id'] ?? null;

        $currentUser = $this->security->getUser();
        if (null === $currentUser || ($currentUser->getId() !== $userId && !$this->security->isGranted('ROLE_ADMIN'))) {
            throw new AccessDeniedHttpException('You can only view your own lists');
        }

        $user = $this->entityManager->getReference(User::class, $userId);

        $qb = $this->entityManager->getRepository(CustomList::class)->createQueryBuilder('cl')
            ->where('cl.user = :user')
            ->setParameter('user', $user)
            ->orderBy('cl.name', 'ASC');

        $page = max(1, (int) ($context['filters']['page'] ?? 1));
        $itemsPerPage = min(100, max(1, (int) ($context['filters']['itemsPerPage'] ?? 20)));
        $qb->setFirstResult(($page - 1) * $itemsPerPage)
           ->setMaxResults($itemsPerPage);

        /** @var array<CustomList> $lists */
        $lists = $qb->getQuery()->getResult();

        return $lists;
    }
}
