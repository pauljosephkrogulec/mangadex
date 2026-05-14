<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\Chapter;
use App\Entity\Manga;
use PHPUnit\Framework\TestCase;

class ChapterTest extends TestCase
{
    public function testEntity(): void
    {
        $manga = new Manga();
        $chapter = new Chapter();
        $chapter->setManga($manga);
        $chapter->setVolume('1');
        $chapter->setChapterNumber('1');
        $chapter->setTitle('Test Chapter');
        $chapter->setLanguage('en');
        $chapter->setPages(['page1.jpg']);

        $this->assertEquals($manga, $chapter->getManga());
        $this->assertEquals('1', $chapter->getVolume());
        $this->assertEquals('1', $chapter->getChapterNumber());
        $this->assertEquals('Test Chapter', $chapter->getTitle());
        $this->assertEquals('en', $chapter->getLanguage());
        $this->assertEquals(['page1.jpg'], $chapter->getPages());
    }

    public function testOptionalFields(): void
    {
        $chapter = new Chapter();
        $chapter->setManga(new Manga());
        $chapter->setChapterNumber('1.5');
        $chapter->setLanguage('ja');

        $this->assertNull($chapter->getVolume());
        $this->assertNull($chapter->getTitle());
    }

    public function testGetPageUrlsReturnsCdnUrlsDirectly(): void
    {
        $chapter = new Chapter();
        $chapter->setPages([
            'https://uploads.mangadex.org/data/abc123/01.jpg',
            'https://uploads.mangadex.org/data/abc123/02.jpg',
        ]);

        $this->assertSame([
            'https://uploads.mangadex.org/data/abc123/01.jpg',
            'https://uploads.mangadex.org/data/abc123/02.jpg',
        ], $chapter->getPageUrls());
    }

    public function testGetPageUrlsReturnsEmptyForNoPages(): void
    {
        $chapter = new Chapter();
        $chapter->setPages([]);

        $this->assertSame([], $chapter->getPageUrls());
    }

    public function testGetPageUrlsReturnsLegacyApiUrlsForLocalPaths(): void
    {
        $chapter = new Chapter();
        $chapter->setManga(new Manga());
        $chapter->setPages(['/chapters/1/page_001.jpg', '/chapters/1/page_002.jpg']);

        // Use reflection to set the ID since it's auto-generated
        $ref = new \ReflectionProperty(Chapter::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($chapter, 'test-chapter-uuid');

        $urls = $chapter->getPageUrls();
        $this->assertCount(2, $urls);
        $this->assertStringStartsWith('/api/chapters/test-chapter-uuid/pages/', $urls[0]);
        $this->assertStringEndsWith('/1', $urls[0]);
        $this->assertStringEndsWith('/2', $urls[1]);
    }
}
