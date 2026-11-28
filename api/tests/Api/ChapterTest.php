<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Enum\ChapterStatus;
use App\Factory\ChapterFactory;
use App\Factory\SeriesFactory;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Ressource Chapter (issue #29) : champs exposés, tri, unicité et suppression en cascade.
 */
final class ChapterTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    public function testGetOneChapterExposesItsFields(): void
    {
        $client = self::createClient();
        $series = SeriesFactory::createOne();
        $chapter = ChapterFactory::createOne([
            'series' => $series,
            'number' => '10.5',
            'language' => 'fr',
            'title' => 'Le chevalier noir',
            'volume' => 2,
            'status' => ChapterStatus::Published,
            'publishedAt' => new \DateTimeImmutable('2026-03-01 18:00:00'),
        ]);

        $data = $this->getJson($client, '/api/chapters/'.$chapter->getId());

        self::assertSame('/api/series/'.$series->getId(), $data['series']);
        self::assertSame('10.5', $data['number']);
        self::assertSame('fr', $data['language']);
        self::assertSame('Le chevalier noir', $data['title']);
        self::assertSame(2, $data['volume']);
        self::assertSame('published', $data['status']);
        self::assertSame('2026-03-01T18:00:00+00:00', $data['publishedAt']);
    }

    public function testCollectionListsLatestPublishedFirst(): void
    {
        $client = self::createClient();
        ChapterFactory::createOne(['title' => 'Ancien', 'publishedAt' => new \DateTimeImmutable('2025-01-01')]);
        ChapterFactory::createOne(['title' => 'Récent', 'publishedAt' => new \DateTimeImmutable('2026-01-01')]);

        $data = $this->getJson($client, '/api/chapters');

        self::assertIsArray($data['member']);
        self::assertSame(['Récent', 'Ancien'], array_column($data['member'], 'title'));
    }

    public function testSameNumberIsAllowedInAnotherLanguageOrSeries(): void
    {
        $series = SeriesFactory::createOne();
        ChapterFactory::createOne(['series' => $series, 'number' => '1', 'language' => 'fr']);
        ChapterFactory::createOne(['series' => $series, 'number' => '1', 'language' => 'en']);
        ChapterFactory::createOne(['number' => '1', 'language' => 'fr']);

        ChapterFactory::assert()->count(3);
    }

    public function testNumberIsUniquePerSeriesAndLanguageInDatabase(): void
    {
        $series = SeriesFactory::createOne();
        ChapterFactory::createOne(['series' => $series, 'number' => '1', 'language' => 'fr']);

        $this->expectException(UniqueConstraintViolationException::class);
        // « 1.00 » et « 1 » sont le même nombre pour la colonne DECIMAL.
        ChapterFactory::createOne(['series' => $series, 'number' => '1.00', 'language' => 'fr']);
    }

    public function testDeletingASeriesDeletesItsChapters(): void
    {
        $series = SeriesFactory::createOne();
        ChapterFactory::createMany(3, ['series' => $series]);
        ChapterFactory::createOne();

        // Suppression en SQL : vérifie le ON DELETE CASCADE de la base, pas une cascade Doctrine.
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->getConnection()->executeStatement('DELETE FROM series WHERE id = ?', [$series->getId()?->toRfc4122()]);

        ChapterFactory::assert()->count(1);
    }

    /**
     * @return array<mixed>
     */
    private function getJson(KernelBrowser $client, string $uri): array
    {
        $client->request('GET', $uri, server: ['HTTP_ACCEPT' => 'application/ld+json']);
        self::assertResponseIsSuccessful();

        $data = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }
}
