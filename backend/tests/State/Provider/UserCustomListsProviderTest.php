<?php

declare(strict_types=1);

namespace App\Tests\State\Provider;

use ApiPlatform\Metadata\Operation;
use App\Entity\CustomList;
use App\Entity\User;
use App\State\Provider\UserCustomListsProvider;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

#[AllowMockObjectsWithoutExpectations]
class UserCustomListsProviderTest extends TestCase
{
    private UserCustomListsProvider $provider;
    private EntityManagerInterface $emMock;
    private Security $securityMock;
    private EntityRepository $repoMock;
    private QueryBuilder $qbMock;
    private Query $queryMock;

    protected function setUp(): void
    {
        $this->emMock = $this->createMock(EntityManagerInterface::class);
        $this->securityMock = $this->createMock(Security::class);
        $this->repoMock = $this->createMock(EntityRepository::class);
        $this->qbMock = $this->createStub(QueryBuilder::class);
        $this->queryMock = $this->createStub(Query::class);

        $this->emMock->method('getRepository')
            ->willReturn($this->repoMock);

        $this->emMock->method('getReference')
            ->willReturn(new User());

        $this->repoMock->method('createQueryBuilder')
            ->willReturn($this->qbMock);

        $this->qbMock->method('where')->willReturnSelf();
        $this->qbMock->method('setParameter')->willReturnSelf();
        $this->qbMock->method('orderBy')->willReturnSelf();
        $this->qbMock->method('setFirstResult')->willReturnSelf();
        $this->qbMock->method('setMaxResults')->willReturnSelf();
        $this->qbMock->method('getQuery')->willReturn($this->queryMock);

        $this->provider = new UserCustomListsProvider($this->emMock, $this->securityMock);
    }

    public function testProvideThrowsWhenUnauthenticated(): void
    {
        $this->securityMock->method('getUser')->willReturn(null);

        $operation = $this->createStub(Operation::class);

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('You can only view your own lists');

        $this->provider->provide($operation, ['id' => 'some-user-id']);
    }

    public function testProvideThrowsForDifferentNonAdminUser(): void
    {
        $currentUser = new User();
        $this->securityMock->method('getUser')->willReturn($currentUser);
        $this->securityMock->method('isGranted')->willReturn(false);

        $operation = $this->createStub(Operation::class);

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('You can only view your own lists');

        $this->provider->provide($operation, ['id' => 'some-other-uuid']);
    }

    public function testProvideReturnsListsForOwnUser(): void
    {
        $user = new User();
        $this->securityMock->method('getUser')->willReturn($user);
        $this->securityMock->method('isGranted')->willReturn(false);

        $list1 = new CustomList();
        $list2 = new CustomList();
        $this->queryMock->method('getResult')->willReturn([$list1, $list2]);

        $operation = $this->createStub(Operation::class);
        $result = $this->provider->provide($operation, ['id' => $user->getId()]);

        $this->assertCount(2, iterator_to_array($result));
    }

    public function testAdminCanAccessOtherUserLists(): void
    {
        $currentUser = new User();
        $this->securityMock->method('getUser')->willReturn($currentUser);
        $this->securityMock->method('isGranted')->willReturn(true);

        $this->queryMock->method('getResult')->willReturn([]);

        $operation = $this->createStub(Operation::class);
        $result = $this->provider->provide($operation, ['id' => 'another-user-id']);

        $this->assertCount(0, iterator_to_array($result));
    }

    public function testProvideWithCustomPagination(): void
    {
        $user = new User();
        $this->securityMock->method('getUser')->willReturn($user);
        $this->securityMock->method('isGranted')->willReturn(false);

        $this->queryMock->method('getResult')->willReturn([]);

        $operation = $this->createStub(Operation::class);
        $result = $this->provider->provide(
            $operation,
            ['id' => $user->getId()],
            ['filters' => ['page' => 2, 'itemsPerPage' => 10]],
        );

        $this->assertIsArray(iterator_to_array($result));
    }
}
