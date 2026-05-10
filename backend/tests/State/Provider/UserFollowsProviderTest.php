<?php

declare(strict_types=1);

namespace App\Tests\State\Provider;

use ApiPlatform\Metadata\Operation;
use App\Entity\MangaFollow;
use App\Entity\User;
use App\State\Provider\UserFollowsProvider;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

#[AllowMockObjectsWithoutExpectations]
class UserFollowsProviderTest extends TestCase
{
    private UserFollowsProvider $provider;
    private EntityManagerInterface $emMock;
    private Security $securityMock;
    private EntityRepository $followRepoMock;
    private QueryBuilder $qbMock;
    private Query $queryMock;

    protected function setUp(): void
    {
        $this->emMock = $this->createMock(EntityManagerInterface::class);
        $this->securityMock = $this->createMock(Security::class);
        $this->followRepoMock = $this->createMock(EntityRepository::class);
        $this->qbMock = $this->createStub(QueryBuilder::class);
        $this->queryMock = $this->createStub(Query::class);

        $this->emMock->method('getRepository')
            ->with(MangaFollow::class)
            ->willReturn($this->followRepoMock);

        $this->followRepoMock->method('createQueryBuilder')
            ->with('mf')
            ->willReturn($this->qbMock);

        $this->qbMock->method('leftJoin')->willReturnSelf();
        $this->qbMock->method('addSelect')->willReturnSelf();
        $this->qbMock->method('where')->willReturnSelf();
        $this->qbMock->method('setParameter')->willReturnSelf();
        $this->qbMock->method('orderBy')->willReturnSelf();
        $this->qbMock->method('setFirstResult')->willReturnSelf();
        $this->qbMock->method('setMaxResults')->willReturnSelf();
        $this->qbMock->method('getQuery')->willReturn($this->queryMock);

        $this->provider = new UserFollowsProvider($this->emMock, $this->securityMock);
    }

    public function testProvideReturnsFollowsForValidUser(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $follow1 = new MangaFollow();
        $follow2 = new MangaFollow();

        $this->securityMock->method('getUser')
            ->willReturn($user);
        $this->securityMock->method('isGranted')
            ->with('ROLE_ADMIN')
            ->willReturn(false);

        $this->queryMock->method('getResult')
            ->willReturn([$follow1, $follow2]);

        $operation = $this->createStub(Operation::class);
        $result = $this->provider->provide($operation, ['id' => $user->getId()]);

        $this->assertCount(2, iterator_to_array($result));
    }

    public function testProvideThrowsAccessDeniedForDifferentUser(): void
    {
        $currentUser = new User();
        $currentUser->setEmail('current@example.com');

        $this->securityMock->method('getUser')
            ->willReturn($currentUser);
        $this->securityMock->method('isGranted')
            ->with('ROLE_ADMIN')
            ->willReturn(false);

        $operation = $this->createStub(Operation::class);

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('You can only view your own follows');

        $this->provider->provide($operation, ['id' => 'some-other-uuid']);
    }

    public function testProvideWithEmptyFollows(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');

        $this->securityMock->method('getUser')
            ->willReturn($user);
        $this->securityMock->method('isGranted')
            ->with('ROLE_ADMIN')
            ->willReturn(false);

        $this->queryMock->method('getResult')
            ->willReturn([]);

        $operation = $this->createStub(Operation::class);
        $result = $this->provider->provide($operation, ['id' => $user->getId()]);

        $this->assertCount(0, iterator_to_array($result));
    }
}
