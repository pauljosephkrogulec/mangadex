<?php

declare(strict_types=1);

namespace App\Tests\State;

use ApiPlatform\Metadata\Operation;
use App\Entity\Chapter;
use App\Entity\Manga;
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
    private EntityRepository $mangaRepoMock;
    private EntityRepository $chapterRepoMock;
    private QueryBuilder $qbMock;
    private Query $queryMock;

    protected function setUp(): void
    {
        $this->emMock = $this->createMock(EntityManagerInterface::class);
        $this->mangaRepoMock = $this->createMock(EntityRepository::class);
        $this->chapterRepoMock = $this->createMock(EntityRepository::class);
        $this->qbMock = $this->createMock(QueryBuilder::class);
        $this->queryMock = $this->createMock(Query::class);

        $this->emMock->method('getRepository')
            ->willReturnMap([
                [Manga::class, $this->mangaRepoMock],
                [Chapter::class, $this->chapterRepoMock],
            ]);

        $this->chapterRepoMock->method('createQueryBuilder')->willReturn($this->qbMock);

        $this->qbMock->method('where')->willReturnSelf();
        $this->qbMock->method('setParameter')->willReturnSelf();
        $this->qbMock->method('orderBy')->willReturnSelf();
        $this->qbMock->method('andWhere')->willReturnSelf();
        $this->qbMock->method('getQuery')->willReturn($this->queryMock);

        $this->provider = new MangaFeedProvider($this->emMock);
    }

    public function testProvideReturnsChaptersForValidManga(): void
    {
        $manga = new Manga();
        $chapter1 = new Chapter();
        $chapter2 = new Chapter();

        $this->mangaRepoMock->method('find')->with(1)->willReturn($manga);
        $this->queryMock->method('getResult')->willReturn([$chapter1, $chapter2]);

        $operation = $this->createMock(Operation::class);
        $result = $this->provider->provide($operation, ['id' => 1]);

        $this->assertCount(2, iterator_to_array($result));
    }

    public function testProvideThrowsNotFoundForInvalidManga(): void
    {
        $this->mangaRepoMock->method('find')->with(999)->willReturn(null);

        $operation = $this->createMock(Operation::class);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
        $this->expectExceptionMessage('Manga not found');

        $this->provider->provide($operation, ['id' => 999]);
    }

    public function testProvideWithOrderFilter(): void
    {
        $manga = new Manga();

        $this->mangaRepoMock->method('find')->with(1)->willReturn($manga);
        $this->queryMock->method('getResult')->willReturn([]);

        $operation = $this->createMock(Operation::class);
        $context = ['filters' => ['order' => ['chapterNumber' => 'DESC']]];
        $result = $this->provider->provide($operation, ['id' => 1], $context);

        $this->assertCount(0, iterator_to_array($result));
    }

    public function testProvideWithLanguageFilter(): void
    {
        $manga = new Manga();

        $this->mangaRepoMock->method('find')->with(1)->willReturn($manga);
        $this->queryMock->method('getResult')->willReturn([]);

        $operation = $this->createMock(Operation::class);
        $context = ['filters' => ['language' => 'en']];
        $result = $this->provider->provide($operation, ['id' => 1], $context);

        $this->assertCount(0, iterator_to_array($result));
    }

    public function testProvideWithInvalidOrderField(): void
    {
        $manga = new Manga();

        $this->mangaRepoMock->method('find')->with(1)->willReturn($manga);
        $this->queryMock->method('getResult')->willReturn([]);

        $operation = $this->createMock(Operation::class);
        $context = ['filters' => ['order' => ['invalidField' => 'ASC']]];
        $result = $this->provider->provide($operation, ['id' => 1], $context);

        // Should use default ordering
        $this->assertCount(0, iterator_to_array($result));
    }

    public function testProvideDefaultOrdering(): void
    {
        $manga = new Manga();

        $this->mangaRepoMock->method('find')->with(1)->willReturn($manga);
        $this->queryMock->method('getResult')->willReturn([]);

        $operation = $this->createMock(Operation::class);
        $result = $this->provider->provide($operation, ['id' => 1]);

        // Should use default chapterNumber ASC ordering
        $this->assertCount(0, iterator_to_array($result));
    }
}
