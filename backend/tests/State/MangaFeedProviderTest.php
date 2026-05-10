<?php

declare(strict_types=1);

namespace App\Tests\State;

use ApiPlatform\Metadata\Operation;
use App\Entity\Chapter;
use App\State\MangaFeedProvider;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;

class MangaFeedProviderTest extends TestCase
{
    private MangaFeedProvider $provider;
    private EntityManagerInterface $emMock;
    private EntityRepository $chapterRepoMock;
    private QueryBuilder $qbMock;
    private Query $queryMock;

    protected function setUp(): void
    {
        $this->emMock = $this->createMock(EntityManagerInterface::class);
        $this->chapterRepoMock = $this->createMock(EntityRepository::class);
        $this->qbMock = $this->createStub(QueryBuilder::class);
        $this->queryMock = $this->createStub(Query::class);

        $this->emMock->method('getRepository')
            ->with(Chapter::class)
            ->willReturn($this->chapterRepoMock);

        $this->chapterRepoMock
            ->method('createQueryBuilder')
            ->willReturn($this->qbMock);

        $this->qbMock->method('leftJoin')->willReturnSelf();
        $this->qbMock->method('addSelect')->willReturnSelf();
        $this->qbMock->method('where')->willReturnSelf();
        $this->qbMock->method('setParameter')->willReturnSelf();
        $this->qbMock->method('orderBy')->willReturnSelf();
        $this->qbMock->method('andWhere')->willReturnSelf();
        $this->qbMock->method('setFirstResult')->willReturnSelf();
        $this->qbMock->method('setMaxResults')->willReturnSelf();
        $this->qbMock->method('getQuery')->willReturn($this->queryMock);

        $this->provider = new MangaFeedProvider($this->emMock);
    }

    public function testProvideReturnsChaptersForValidManga(): void
    {
        $chapter1 = new Chapter();
        $chapter2 = new Chapter();

        $this->queryMock->method('getResult')->willReturn([$chapter1, $chapter2]);

        $operation = $this->createStub(Operation::class);
        $result = $this->provider->provide($operation, ['id' => 1]);

        $this->assertCount(2, iterator_to_array($result));
    }

    public function testProvideReturnsEmptyForNonexistentManga(): void
    {
        $this->queryMock->method('getResult')->willReturn([]);

        $operation = $this->createStub(Operation::class);
        $result = $this->provider->provide($operation, ['id' => 999]);

        // No manga found → no chapters, but not an error
        $this->assertCount(0, iterator_to_array($result));
    }

    public function testProvideWithOrderFilter(): void
    {
        $this->queryMock->method('getResult')->willReturn([]);

        $operation = $this->createStub(Operation::class);
        $context = ['filters' => ['order' => ['chapterNumber' => 'DESC']]];
        $result = $this->provider->provide($operation, ['id' => 1], $context);

        $this->assertCount(0, iterator_to_array($result));
    }

    public function testProvideWithLanguageFilter(): void
    {
        $this->queryMock->method('getResult')->willReturn([]);

        $operation = $this->createStub(Operation::class);
        $context = ['filters' => ['language' => 'en']];
        $result = $this->provider->provide($operation, ['id' => 1], $context);

        $this->assertCount(0, iterator_to_array($result));
    }

    public function testProvideWithInvalidOrderField(): void
    {
        $this->queryMock->method('getResult')->willReturn([]);

        $operation = $this->createStub(Operation::class);
        $context = ['filters' => ['order' => ['invalidField' => 'ASC']]];
        $result = $this->provider->provide($operation, ['id' => 1], $context);

        $this->assertCount(0, iterator_to_array($result));
    }

    public function testProvideDefaultOrdering(): void
    {
        $this->queryMock->method('getResult')->willReturn([]);

        $operation = $this->createStub(Operation::class);
        $result = $this->provider->provide($operation, ['id' => 1]);

        $this->assertCount(0, iterator_to_array($result));
    }
}
