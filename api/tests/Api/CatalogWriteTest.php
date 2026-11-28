<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Enum\ChapterStatus;
use App\Factory\AuthorFactory;
use App\Factory\ChapterFactory;
use App\Factory\GenreFactory;
use App\Factory\PageFactory;
use App\Factory\SeriesFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Écriture du catalogue (issue #31) : CRUD réservé aux administrateurs, validation en 422.
 */
final class CatalogWriteTest extends WebTestCase
{
    use ApiTestHelpers;
    use ClockSensitiveTrait;
    use Factories;
    use ResetDatabase;

    public function testAdminCreatesASeriesWithItsRelations(): void
    {
        $client = self::createClient();
        $author = AuthorFactory::createOne(['name' => 'Kentarō Miura']);
        $genre = GenreFactory::createOne(['name' => 'Dark fantasy']);

        $data = $this->request($client, 'POST', '/api/series', [
            'title' => 'Berserk',
            'alternativeTitles' => ['ベルセルク'],
            'synopsis' => '<p>Guts, un mercenaire.</p><script>alert(1)</script>',
            'type' => 'manga',
            'status' => 'hiatus',
            'year' => 1989,
            'authors' => ['/api/authors/'.$author->getId()],
            'artists' => ['/api/authors/'.$author->getId()],
            'genres' => ['/api/genres/'.$genre->getId()],
        ], as: 'admin');

        self::assertResponseStatusCodeSame(201);
        self::assertSame('berserk', $data['slug'], 'Slug généré (#33).');
        self::assertSame('<p>Guts, un mercenaire.</p>', $data['synopsis'], 'Synopsis nettoyé (#35).');
        self::assertSame(['ベルセルク'], $data['alternativeTitles']);
        self::assertSame('Kentarō Miura', self::firstOf($data, 'authors')['name']);
        self::assertSame('dark-fantasy', self::firstOf($data, 'genres')['slug']);
    }

    public function testAdminUpdatesASeriesWithoutChangingItsSlug(): void
    {
        self::mockTime('2026-01-01 10:00:00');
        $client = self::createClient();
        $series = SeriesFactory::createOne(['title' => 'Shingeki no Kyojin']);

        self::mockTime('2026-01-02 10:00:00');
        $data = $this->request($client, 'PATCH', '/api/series/'.$series->getId(), ['title' => 'Attack on Titan', 'status' => 'completed'], as: 'admin');

        self::assertResponseIsSuccessful();
        self::assertSame('Attack on Titan', $data['title']);
        self::assertSame('completed', $data['status']);
        self::assertSame('shingeki-no-kyojin', $data['slug']);
        self::assertSame('2026-01-02T10:00:00+00:00', $data['updatedAt']);
    }

    public function testAdminDeletesASeriesAndItsChapters(): void
    {
        $client = self::createClient();
        $series = SeriesFactory::createOne();
        ChapterFactory::createMany(2, ['series' => $series]);

        $this->request($client, 'DELETE', '/api/series/'.$series->getId(), as: 'admin');

        self::assertResponseStatusCodeSame(204);
        SeriesFactory::assert()->empty();
        ChapterFactory::assert()->empty();
    }

    /**
     * @return iterable<string, array{string, string, array<string, mixed>, string}>
     */
    public static function invalidPayloads(): iterable
    {
        yield 'titre vide' => ['POST', '/api/series', ['title' => ''], 'title'];
        yield 'année hors bornes' => ['POST', '/api/series', ['title' => 'X', 'year' => 1800], 'year'];
        yield 'slug invalide' => ['POST', '/api/series', ['title' => 'X', 'slug' => 'Pas Un Slug'], 'slug'];
        yield 'nom de genre vide' => ['POST', '/api/genres', ['name' => ''], 'name'];
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('invalidPayloads')]
    public function testInvalidPayloadIsRejectedWith422(string $method, string $uri, array $payload, string $path): void
    {
        $client = self::createClient();

        $data = $this->request($client, $method, $uri, $payload, as: 'admin');

        self::assertResponseStatusCodeSame(422);
        self::assertIsArray($data['violations']);
        self::assertContains($path, array_column($data['violations'], 'propertyPath'));
    }

    public function testDuplicateSlugIsRejectedWith422InsteadOfASqlError(): void
    {
        $client = self::createClient();
        SeriesFactory::createOne(['title' => 'One Piece', 'slug' => 'one-piece']);

        $data = $this->request($client, 'POST', '/api/series', ['title' => 'One Piece', 'slug' => 'one-piece'], as: 'admin');

        self::assertResponseStatusCodeSame(422);
        self::assertIsArray($data['violations']);
        self::assertSame('slug', self::firstOf($data, 'violations')['propertyPath']);
        self::assertSame('Ce slug est déjà utilisé par une autre série.', self::firstOf($data, 'violations')['message']);
    }

    public function testAdminCreatesAChapterAndADuplicateNumberIsRejected(): void
    {
        $client = self::createClient();
        $series = SeriesFactory::createOne();
        $payload = ['series' => '/api/series/'.$series->getId(), 'number' => '1', 'language' => 'fr', 'status' => 'published', 'publishedAt' => '2026-01-01T00:00:00+00:00'];

        $data = $this->request($client, 'POST', '/api/chapters', $payload, as: 'admin');
        self::assertResponseStatusCodeSame(201);
        self::assertSame('/api/series/'.$series->getId(), $data['series']);

        $data = $this->request($client, 'POST', '/api/chapters', ['number' => '1.00'] + $payload, as: 'admin');
        self::assertResponseStatusCodeSame(422);
        self::assertIsArray($data['violations']);
        self::assertSame('Le chapitre 1 existe déjà en « fr » pour cette série.', self::firstOf($data, 'violations')['message']);
    }

    public function testDuplicatePagePositionIsRejectedWith422(): void
    {
        $client = self::createClient();
        $chapter = ChapterFactory::createOne();
        PageFactory::createOne(['chapter' => $chapter, 'position' => 1]);

        $data = $this->request($client, 'POST', '/api/pages', [
            'chapter' => '/api/chapters/'.$chapter->getId(),
            'position' => 1,
            'image' => 'pages/x.webp',
            'width' => 800,
            'height' => 1200,
            'hash' => str_repeat('f', 64),
        ], as: 'admin');

        self::assertResponseStatusCodeSame(422);
        self::assertIsArray($data['violations']);
        self::assertSame('position', self::firstOf($data, 'violations')['propertyPath']);
    }

    public function testAdminCreatesAGenreWithAGeneratedSlug(): void
    {
        $client = self::createClient();

        $data = $this->request($client, 'POST', '/api/genres', ['name' => 'Science-fiction'], as: 'admin');

        self::assertResponseStatusCodeSame(201);
        self::assertSame('science-fiction', $data['slug']);
    }

    /**
     * @return iterable<string, array{string, string, array<string, mixed>|null}>
     */
    public static function writeOperations(): iterable
    {
        yield 'créer une série' => ['POST', '/api/series', ['title' => 'X']];
        yield 'modifier une série' => ['PATCH', '/api/series/{series}', ['title' => 'X']];
        yield 'supprimer une série' => ['DELETE', '/api/series/{series}', null];
        yield 'créer un genre' => ['POST', '/api/genres', ['name' => 'X']];
        yield 'créer un chapitre' => ['POST', '/api/chapters', ['number' => '1', 'language' => 'fr']];
        yield 'supprimer un chapitre' => ['DELETE', '/api/chapters/{chapter}', null];
        yield 'supprimer une page' => ['DELETE', '/api/pages/{page}', null];
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    #[DataProvider('writeOperations')]
    public function testAnonymousVisitorGets401(string $method, string $uri, ?array $payload): void
    {
        $client = self::createClient();

        $this->request($client, $method, $this->resolve($uri), $payload);

        self::assertResponseStatusCodeSame(401);
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    #[DataProvider('writeOperations')]
    public function testReaderWithoutAdminRoleGets403(string $method, string $uri, ?array $payload): void
    {
        $client = self::createClient();

        $this->request($client, $method, $this->resolve($uri), $payload, as: 'reader');

        self::assertResponseStatusCodeSame(403);
    }

    private function resolve(string $uri): string
    {
        $page = PageFactory::createOne(['chapter' => ChapterFactory::new(['status' => ChapterStatus::Published])]);
        $chapter = $page->getChapter();

        return strtr($uri, [
            '{series}' => (string) $chapter->getSeries()->getId(),
            '{chapter}' => (string) $chapter->getId(),
            '{page}' => (string) $page->getId(),
        ]);
    }
}
