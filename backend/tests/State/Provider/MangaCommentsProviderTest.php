<?php

declare(strict_types=1);

namespace App\Tests\State\Provider;

use ApiPlatform\Metadata\Operation;
use App\Entity\Comment;
use App\Entity\Manga;
use App\State\Provider\MangaCommentsProvider;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class MangaCommentsProviderTest extends TestCase
{
    private MangaCommentsProvider $provider;
    private EntityManagerInterface $emMock;
    private EntityRepository $repoMock;
    private QueryBuilder $qbMock;
    private Query $queryMock;

    protected function setUp(): void
    {
        $this->emMock = $this->createMock(EntityManagerInterface::class);
        $this->repoMock = $this->createMock(EntityRepository::class);
        $this->qbMock = $this->createStub(QueryBuilder::class);
        $this->queryMock = $this->createStub(Query::class);

        $this->emMock->method('getRepository')
            ->willReturn($this->repoMock);

        $this->emMock->method('getReference')
            ->willReturn(new Manga());

        $this->repoMock->method('createQueryBuilder')
            ->willReturn($this->qbMock);

        $this->qbMock->method('where')->willReturnSelf();
        $this->qbMock->method('setParameter')->willReturnSelf();
        $this->qbMock->method('orderBy')->willReturnSelf();
        $this->qbMock->method('setFirstResult')->willReturnSelf();
        $this->qbMock->method('setMaxResults')->willReturnSelf();
        $this->qbMock->method('getQuery')->willReturn($this->queryMock);

        $this->provider = new MangaCommentsProvider($this->emMock);
    }

    public function testProvideReturnsComments(): void
    {
        $c1 = new Comment();
        $c2 = new Comment();
        $this->queryMock->method('getResult')->willReturn([$c1, $c2]);

        $operation = $this->createStub(Operation::class);
        $result = $this->provider->provide($operation, ['id' => 'm-1']);

        $this->assertCount(2, iterator_to_array($result));
    }

    public function testProvideReturnsEmptyWhenNoComments(): void
    {
        $this->queryMock->method('getResult')->willReturn([]);

        $operation = $this->createStub(Operation::class);
        $result = $this->provider->provide($operation, ['id' => 'm-1']);

        $this->assertCount(0, iterator_to_array($result));
    }

    public function testProvideWithCustomPagination(): void
    {
        $this->queryMock->method('getResult')->willReturn([]);

        $operation = $this->createStub(Operation::class);
        $result = $this->provider->provide(
            $operation,
            ['id' => 'm-1'],
            ['filters' => ['page' => 3, 'itemsPerPage' => 5]],
        );

        $this->assertIsArray(iterator_to_array($result));
    }
}
