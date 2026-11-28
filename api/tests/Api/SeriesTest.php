<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Series;
use App\Enum\SeriesStatus;
use App\Enum\SeriesType;
use App\Factory\SeriesFactory;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Ressource Series (issues #21 et #27) : persistance Doctrine, identifiant UUID v7, champs du catalogue,
 * pagination et formats.
 */
final class SeriesTest extends WebTestCase
{
    use ClockSensitiveTrait;
    use Factories;
    use ResetDatabase;

    public function testGetOneSeriesByUuid(): void
    {
        self::mockTime('2026-02-01 08:30:00');
        $client = self::createClient();
        $series = SeriesFactory::createOne(['title' => 'Berserk', 'status' => SeriesStatus::Hiatus]);
        $id = $series->getId();
        self::assertInstanceOf(Uuid::class, $id);

        $data = $this->getJson($client, '/api/series/'.$id);

        self::assertResponseIsSuccessful();
        self::assertSame('/api/series/'.$id, $data['@id']);
        self::assertSame('Berserk', $data['title']);
        self::assertSame('hiatus', $data['status']);
        self::assertSame('2026-02-01T08:30:00+00:00', $data['createdAt']);
    }

    public function testGetOneSeriesExposesCatalogueFields(): void
    {
        $client = self::createClient();
        $series = SeriesFactory::createOne([
            'title' => 'Solo Leveling',
            'alternativeTitles' => ['나 혼자만 레벨업', 'Only I Level Up'],
            'synopsis' => 'Le chasseur le plus faible du monde.',
            'type' => SeriesType::Manhwa,
            'year' => 2018,
            'coverImage' => 'covers/solo-leveling.webp',
        ]);

        $data = $this->getJson($client, '/api/series/'.$series->getId());

        self::assertSame('solo-leveling', $data['slug']);
        self::assertSame(['나 혼자만 레벨업', 'Only I Level Up'], $data['alternativeTitles']);
        self::assertSame('Le chasseur le plus faible du monde.', $data['synopsis']);
        self::assertSame('manhwa', $data['type']);
        self::assertSame(2018, $data['year']);
        self::assertSame('covers/solo-leveling.webp', $data['coverImage']);
    }

    public function testSlugIsUniqueInDatabase(): void
    {
        SeriesFactory::createOne(['title' => 'One Piece', 'slug' => 'one-piece']);

        $this->expectException(UniqueConstraintViolationException::class);
        SeriesFactory::createOne(['title' => 'One Piece (réédition)', 'slug' => 'one-piece']);
    }

    public function testUnknownSeriesReturns404(): void
    {
        $client = self::createClient();
        $client->request('GET', '/api/series/'.Uuid::v7(), server: ['HTTP_ACCEPT' => 'application/ld+json']);

        self::assertResponseStatusCodeSame(404);
    }

    public function testCollectionIsPaginatedBy30(): void
    {
        $client = self::createClient();
        SeriesFactory::createMany(35);

        $data = $this->getJson($client, '/api/series');

        self::assertSame(35, $data['totalItems']);
        self::assertIsArray($data['member']);
        self::assertCount(30, $data['member']);
        self::assertIsArray($data['view']);
        self::assertSame('/api/series?page=2', $data['view']['next'] ?? null);
    }

    public function testClientCanChooseItemsPerPageUpToTheMaximum(): void
    {
        $client = self::createClient();
        SeriesFactory::createMany(3);

        $data = $this->getJson($client, '/api/series?itemsPerPage=2');
        self::assertIsArray($data['member']);
        self::assertCount(2, $data['member']);

        $client->request('GET', '/api/series?itemsPerPage=500', server: ['HTTP_ACCEPT' => 'application/ld+json']);
        self::assertResponseIsSuccessful();
    }

    public function testCollectionIsSortedFromNewestToOldest(): void
    {
        $client = self::createClient();
        self::mockTime('2026-01-01 00:00:00');
        SeriesFactory::createOne(['title' => 'Ancien']);
        self::mockTime('2026-06-01 00:00:00');
        SeriesFactory::createOne(['title' => 'Récent']);

        $data = $this->getJson($client, '/api/series');

        self::assertIsArray($data['member']);
        self::assertSame(['Récent', 'Ancien'], array_column($data['member'], 'title'));
    }

    /**
     * @return iterable<string, array{non-empty-string}>
     */
    public static function formats(): iterable
    {
        yield 'JSON-LD (Hydra)' => ['application/ld+json'];
        yield 'JSON:API' => ['application/vnd.api+json'];
        yield 'HAL' => ['application/hal+json'];
        yield 'JSON' => ['application/json'];
    }

    /**
     * @param non-empty-string $mimeType
     */
    #[DataProvider('formats')]
    public function testCollectionIsNegotiatedInEachFormat(string $mimeType): void
    {
        $client = self::createClient();
        SeriesFactory::createOne();

        $client->request('GET', '/api/series', server: ['HTTP_ACCEPT' => $mimeType]);

        self::assertResponseIsSuccessful();
        self::assertStringStartsWith($mimeType, (string) $client->getResponse()->headers->get('Content-Type'));
    }

    public function testSeriesIsReadOnlyForNow(): void
    {
        $client = self::createClient();
        $client->request('POST', '/api/series', server: ['CONTENT_TYPE' => 'application/ld+json'], content: '{"title":"X"}');

        self::assertResponseStatusCodeSame(405);
    }

    public function testFactoryPersistsASeriesWithItsTimestamps(): void
    {
        self::mockTime('2026-04-10 12:00:00');
        SeriesFactory::createOne();

        SeriesFactory::assert()->count(1);
        $series = SeriesFactory::repository()->first();
        self::assertInstanceOf(Series::class, $series);
        self::assertEquals(new \DateTimeImmutable('2026-04-10 12:00:00'), $series->getCreatedAt());
        self::assertEquals($series->getCreatedAt(), $series->getUpdatedAt());
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
