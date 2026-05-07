<?php

declare(strict_types=1);

namespace App\Tests\State\Provider;

use ApiPlatform\Metadata\Operation;
use App\Entity\MangaFollow;
use App\Entity\User;
use App\State\Provider\UserFollowsProvider;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

class UserFollowsProviderTest extends TestCase
{
    private UserFollowsProvider $provider;
    private EntityManagerInterface $emMock;
    private EntityRepository $userRepoMock;
    private EntityRepository $followRepoMock;

    protected function setUp(): void
    {
        $this->emMock = $this->createMock(EntityManagerInterface::class);
        $this->userRepoMock = $this->createMock(EntityRepository::class);
        $this->followRepoMock = $this->createMock(EntityRepository::class);

        $this->emMock->method('getRepository')
            ->willReturnMap([
                [User::class, $this->userRepoMock],
                [MangaFollow::class, $this->followRepoMock],
            ]);

        $this->provider = new UserFollowsProvider($this->emMock);
    }

    public function testProvideReturnsFollowsForValidUser(): void
    {
        $user = new User();
        $follow1 = new MangaFollow();
        $follow2 = new MangaFollow();

        $this->userRepoMock->method('find')->with(1)->willReturn($user);
        $this->followRepoMock->method('findBy')
            ->with(['user' => $user], ['followedAt' => 'DESC'])
            ->willReturn([$follow1, $follow2]);

        $operation = $this->createMock(Operation::class);
        $result = $this->provider->provide($operation, ['id' => 1]);

        $this->assertCount(2, iterator_to_array($result));
    }

    public function testProvideThrowsNotFoundForInvalidUser(): void
    {
        $this->userRepoMock->method('find')->with(999)->willReturn(null);

        $operation = $this->createMock(Operation::class);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
        $this->expectExceptionMessage('User not found');

        $this->provider->provide($operation, ['id' => 999]);
    }

    public function testProvideWithEmptyFollows(): void
    {
        $user = new User();
        $this->userRepoMock->method('find')->with(1)->willReturn($user);
        $this->followRepoMock->method('findBy')->willReturn([]);

        $operation = $this->createMock(Operation::class);
        $result = $this->provider->provide($operation, ['id' => 1]);

        $this->assertCount(0, iterator_to_array($result));
    }
}
