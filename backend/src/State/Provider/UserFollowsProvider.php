<?php

namespace App\State\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\MangaFollow;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<MangaFollow>
 */
final class UserFollowsProvider implements ProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
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

        $user = $this->entityManager->getRepository(User::class)->find($userId);
        if (! $user) {
            throw new NotFoundHttpException('User not found');
        }

        $follows = $this->entityManager->getRepository(MangaFollow::class)->findBy(
            ['user' => $user],
            ['followedAt' => 'DESC']
        );

        return $follows;
    }
}
