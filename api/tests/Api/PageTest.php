<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Factory\ChapterFactory;
use App\Factory\PageFactory;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Ressource Page (issue #30) : champs exposés, segments webtoon, ordre, unicité et cascade.
 */
final class PageTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    public function testGetOnePageExposesItsFields(): void
    {
        $client = self::createClient();
        $chapter = ChapterFactory::createOne();
        $page = PageFactory::createOne([
            'chapter' => $chapter,
            'position' => 3,
            'image' => 'pages/berserk-1-3.webp',
            'width' => 1100,
            'height' => 1600,
            'hash' => str_repeat('a', 64),
        ]);

        $data = $this->getJson($client, '/api/pages/'.$page->getId());

        self::assertSame('/api/chapters/'.$chapter->getId(), $data['chapter']);
        self::assertSame(3, $data['position']);
        self::assertSame('pages/berserk-1-3.webp', $data['image']);
        self::assertSame(1100, $data['width']);
        self::assertSame(1600, $data['height']);
        self::assertSame(str_repeat('a', 64), $data['hash']);
        self::assertSame([], $data['segments']);
        self::assertFalse($data['segmented']);
    }

    public function testWebtoonStripExposesItsSegmentsInReadingOrder(): void
    {
        $client = self::createClient();
        $page = PageFactory::new()->webtoonStrip(3000)->create();

        $data = $this->getJson($client, '/api/pages/'.$page->getId());

        self::assertTrue($data['segmented']);
        self::assertIsArray($data['segments']);
        self::assertSame([1280, 1280, 440], array_column($data['segments'], 'height'));
    }

    public function testPagesOfAChapterAreListedInReadingOrder(): void
    {
        $client = self::createClient();
        $chapter = ChapterFactory::createOne();
        PageFactory::createSequence([
            ['chapter' => $chapter, 'position' => 2],
            ['chapter' => $chapter, 'position' => 3],
            ['chapter' => $chapter, 'position' => 1],
        ]);

        $data = $this->getJson($client, '/api/pages');

        self::assertIsArray($data['member']);
        self::assertSame([1, 2, 3], array_column($data['member'], 'position'));
    }

    public function testPositionIsUniquePerChapterInDatabase(): void
    {
        $chapter = ChapterFactory::createOne();
        PageFactory::createOne(['chapter' => $chapter, 'position' => 1]);
        PageFactory::createOne(['position' => 1]);

        $this->expectException(UniqueConstraintViolationException::class);
        PageFactory::createOne(['chapter' => $chapter, 'position' => 1]);
    }

    public function testDeletingAChapterDeletesItsPages(): void
    {
        $chapter = ChapterFactory::createOne();
        PageFactory::createMany(3, ['chapter' => $chapter]);
        PageFactory::createOne();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->getConnection()->executeStatement('DELETE FROM chapter WHERE id = ?', [$chapter->getId()?->toRfc4122()]);

        PageFactory::assert()->count(1);
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
