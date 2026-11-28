<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Enum\ChapterStatus;
use App\Enum\SeriesStatus;
use App\Enum\SeriesType;
use App\Factory\AuthorFactory;
use App\Factory\ChapterFactory;
use App\Factory\GenreFactory;
use App\Factory\PageFactory;
use App\Factory\SeriesFactory;
use App\Factory\TagFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Filtres, tris et contextes de sérialisation du catalogue (issue #31).
 */
final class CatalogFilterTest extends WebTestCase
{
    use ApiTestHelpers;
    use Factories;
    use ResetDatabase;

    public function testSeriesAreSearchedByTitleCaseInsensitively(): void
    {
        $client = self::createClient();
        SeriesFactory::createSequence([['title' => 'One Piece'], ['title' => 'One-Punch Man'], ['title' => 'Berserk']]);

        $data = $this->request($client, 'GET', '/api/series?title=ONE&order[title]=asc');

        self::assertSame(['One Piece', 'One-Punch Man'], self::column($data, 'title'));
    }

    public function testSeriesAreFilteredByGenreTagAndAuthorSlugs(): void
    {
        $client = self::createClient();
        $action = GenreFactory::createOne(['name' => 'Action']);
        $drama = GenreFactory::createOne(['name' => 'Drame']);
        $pirates = TagFactory::createOne(['name' => 'Pirates']);
        $oda = AuthorFactory::createOne(['name' => 'Eiichirō Oda']);
        SeriesFactory::createOne(['title' => 'One Piece', 'genres' => [$action], 'tags' => [$pirates], 'authors' => [$oda]]);
        SeriesFactory::createOne(['title' => 'Nana', 'genres' => [$drama]]);
        SeriesFactory::createOne(['title' => 'Mushishi']);

        self::assertSame(['One Piece'], self::column($this->request($client, 'GET', '/api/series?genre=action'), 'title'));
        self::assertSame(['One Piece'], self::column($this->request($client, 'GET', '/api/series?tag=pirates'), 'title'));
        self::assertSame(['One Piece'], self::column($this->request($client, 'GET', '/api/series?author=eiichiro-oda'), 'title'));
        self::assertSame(
            ['Nana', 'One Piece'],
            self::column($this->request($client, 'GET', '/api/series?genre[]=action&genre[]=drame&order[title]=asc'), 'title'),
        );
    }

    public function testSeriesAreFilteredByStatusAndType(): void
    {
        $client = self::createClient();
        SeriesFactory::createOne(['title' => 'Berserk', 'status' => SeriesStatus::Hiatus, 'type' => SeriesType::Manga]);
        SeriesFactory::createOne(['title' => 'Solo Leveling', 'status' => SeriesStatus::Completed, 'type' => SeriesType::Manhwa]);
        SeriesFactory::createOne(['title' => 'Monster', 'status' => SeriesStatus::Completed, 'type' => SeriesType::Manga]);

        $data = $this->request($client, 'GET', '/api/series?status=completed&type=manga');

        self::assertSame(['Monster'], self::column($data, 'title'));
    }

    public function testSeriesAreSortedByYearWithUnknownYearsLast(): void
    {
        $client = self::createClient();
        SeriesFactory::createSequence([['title' => 'A', 'year' => 2001], ['title' => 'B', 'year' => null], ['title' => 'C', 'year' => 1989]]);

        self::assertSame(['C', 'A', 'B'], self::column($this->request($client, 'GET', '/api/series?order[year]=asc'), 'title'));
        self::assertSame(['A', 'C', 'B'], self::column($this->request($client, 'GET', '/api/series?order[year]=desc'), 'title'));
    }

    public function testListShowsEssentialsAndItemShowsDetails(): void
    {
        $client = self::createClient();
        $series = SeriesFactory::createOne(['synopsis' => '<p>Résumé</p>', 'alternativeTitles' => ['Alt']]);

        $list = $this->request($client, 'GET', '/api/series');
        self::assertIsArray($list['member']);
        self::assertIsArray($list['member'][0]);
        self::assertArrayNotHasKey('synopsis', $list['member'][0]);
        self::assertArrayNotHasKey('authors', $list['member'][0]);
        self::assertArrayHasKey('genres', $list['member'][0]);

        $item = $this->request($client, 'GET', '/api/series/'.$series->getId());
        self::assertSame('<p>Résumé</p>', $item['synopsis']);
        self::assertSame(['Alt'], $item['alternativeTitles']);
        self::assertArrayHasKey('authors', $item);
        self::assertArrayHasKey('createdAt', $item);
    }

    public function testChaptersOfASeriesAreFilteredByLanguageAndSortedNumerically(): void
    {
        $client = self::createClient();
        $series = SeriesFactory::createOne();
        ChapterFactory::createSequence([
            ['series' => $series, 'language' => 'fr', 'number' => '10'],
            ['series' => $series, 'language' => 'fr', 'number' => '2'],
            ['series' => $series, 'language' => 'fr', 'number' => '2.5'],
            ['series' => $series, 'language' => 'en', 'number' => '1'],
        ]);
        ChapterFactory::createOne(['language' => 'fr', 'number' => '1']);

        $data = $this->request($client, 'GET', '/api/chapters?series=/api/series/'.$series->getId().'&language=fr&order[number]=asc');

        self::assertSame(['2', '2.5', '10'], self::column($data, 'number'));
    }

    public function testPagesOfAChapterAreFiltered(): void
    {
        $client = self::createClient();
        $chapter = ChapterFactory::createOne();
        PageFactory::createSequence([['chapter' => $chapter, 'position' => 2], ['chapter' => $chapter, 'position' => 1]]);
        PageFactory::createOne();

        $data = $this->request($client, 'GET', '/api/pages?chapter=/api/chapters/'.$chapter->getId());

        self::assertSame([1, 2], self::column($data, 'position'));
    }

    public function testTermsAreSearchedByName(): void
    {
        $client = self::createClient();
        GenreFactory::createSequence([['name' => 'Science-fiction'], ['name' => 'Fantasy'], ['name' => 'Fiction historique']]);

        $data = $this->request($client, 'GET', '/api/genres?name=fiction');

        self::assertSame(['Fiction historique', 'Science-fiction'], self::column($data, 'name'));
    }

    public function testUnpublishedChaptersAndTheirPagesAreHiddenFromVisitors(): void
    {
        $client = self::createClient();
        $published = ChapterFactory::createOne(['status' => ChapterStatus::Published]);
        $draft = ChapterFactory::new()->draft()->create();
        $scheduled = ChapterFactory::createOne(['status' => ChapterStatus::Scheduled, 'publishedAt' => new \DateTimeImmutable('+1 week')]);
        PageFactory::createOne(['chapter' => $published, 'position' => 1]);
        $draftPage = PageFactory::createOne(['chapter' => $draft, 'position' => 1]);

        self::assertSame([(string) $published->getId()], self::column($this->request($client, 'GET', '/api/chapters'), 'id'));
        self::assertCount(1, self::column($this->request($client, 'GET', '/api/pages'), 'id'));

        foreach (['/api/chapters/'.$draft->getId(), '/api/chapters/'.$scheduled->getId(), '/api/pages/'.$draftPage->getId()] as $uri) {
            $this->request($client, 'GET', $uri);
            self::assertResponseStatusCodeSame(404, $uri);
        }
    }

    public function testAdminSeesUnpublishedChaptersAndTheirPages(): void
    {
        $client = self::createClient();
        ChapterFactory::createOne(['status' => ChapterStatus::Published]);
        $draft = ChapterFactory::new()->draft()->create();
        $draftPage = PageFactory::createOne(['chapter' => $draft]);

        self::assertCount(2, self::column($this->request($client, 'GET', '/api/chapters', as: 'admin'), 'id'));

        $this->request($client, 'GET', '/api/pages/'.$draftPage->getId(), as: 'admin');
        self::assertResponseIsSuccessful();
    }
}
