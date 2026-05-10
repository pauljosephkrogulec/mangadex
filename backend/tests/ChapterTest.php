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
}
