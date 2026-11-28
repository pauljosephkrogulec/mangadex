<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Series;
use App\Factory\AuthorFactory;
use App\Factory\GenreFactory;
use App\Factory\SeriesFactory;
use App\Factory\TagFactory;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Auteurs, genres et tags (issue #28) : ressources en lecture et relations N-N avec Series.
 */
final class CatalogTermTest extends WebTestCase
{
    use ApiTestHelpers;
    use Factories;
    use ResetDatabase;

    /**
     * @return iterable<string, array{class-string<AuthorFactory|GenreFactory|TagFactory>, string}>
     */
    public static function terms(): iterable
    {
        yield 'auteurs' => [AuthorFactory::class, '/api/authors'];
        yield 'genres' => [GenreFactory::class, '/api/genres'];
        yield 'tags' => [TagFactory::class, '/api/tags'];
    }

    /**
     * @param class-string<AuthorFactory|GenreFactory|TagFactory> $factory
     */
    #[DataProvider('terms')]
    public function testCollectionIsSortedByName(string $factory, string $uri): void
    {
        $client = self::createClient();
        $factory::createSequence([['name' => 'Zeta'], ['name' => 'Alpha'], ['name' => 'Mu']]);

        $data = $this->getJson($client, $uri);

        self::assertIsArray($data['member']);
        self::assertSame(['Alpha', 'Mu', 'Zeta'], array_column($data['member'], 'name'));
        self::assertSame(['alpha', 'mu', 'zeta'], array_column($data['member'], 'slug'));
    }

    /**
     * @return iterable<string, array{class-string<AuthorFactory|GenreFactory|TagFactory>}>
     */
    public static function factories(): iterable
    {
        foreach (self::terms() as $name => [$factory]) {
            yield $name => [$factory];
        }
    }

    /**
     * @param class-string<AuthorFactory|GenreFactory|TagFactory> $factory
     */
    #[DataProvider('factories')]
    public function testSlugIsUniqueInDatabase(string $factory): void
    {
        $factory::createOne(['name' => 'Shōnen', 'slug' => 'shonen']);

        $this->expectException(UniqueConstraintViolationException::class);
        $factory::createOne(['name' => 'Shonen', 'slug' => 'shonen']);
    }

    public function testSeriesEmbedsTheNameAndSlugOfItsRelations(): void
    {
        $client = self::createClient();
        $oda = AuthorFactory::createOne(['name' => 'Eiichirō Oda']);
        $action = GenreFactory::createOne(['name' => 'Action']);
        $pirates = TagFactory::createOne(['name' => 'Pirates']);
        $series = SeriesFactory::createOne([
            'title' => 'One Piece',
            'authors' => [$oda],
            'artists' => [$oda],
            'genres' => [$action],
            'tags' => [$pirates],
        ]);

        $data = $this->getJson($client, '/api/series/'.$series->getId());

        $expected = [
            'authors' => ['/api/authors/'.$oda->getId(), 'Eiichirō Oda', 'eiichiro-oda'],
            'artists' => ['/api/authors/'.$oda->getId(), 'Eiichirō Oda', 'eiichiro-oda'],
            'genres' => ['/api/genres/'.$action->getId(), 'Action', 'action'],
            'tags' => ['/api/tags/'.$pirates->getId(), 'Pirates', 'pirates'],
        ];
        foreach ($expected as $relation => [$iri, $name, $slug]) {
            self::assertIsArray($data[$relation]);
            self::assertCount(1, $data[$relation]);
            $term = self::firstOf($data, $relation);
            self::assertSame([$iri, $name, $slug], [$term['@id'], $term['name'], $term['slug']]);
        }
    }

    public function testDeletingAGenreDetachesItFromSeries(): void
    {
        $action = GenreFactory::createOne();
        $drama = GenreFactory::createOne();
        $series = SeriesFactory::createOne(['genres' => [$action, $drama]]);

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->remove($action);
        $entityManager->flush();
        $entityManager->clear();

        $reloaded = $entityManager->find(Series::class, $series->getId());
        self::assertInstanceOf(Series::class, $reloaded);
        self::assertSame([$drama->getId()?->toRfc4122()], $reloaded->getGenres()->map(static fn ($genre): ?string => $genre->getId()?->toRfc4122())->getValues());
        SeriesFactory::assert()->count(1);
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
