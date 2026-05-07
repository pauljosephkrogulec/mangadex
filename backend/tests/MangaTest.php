<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\Chapter;
use App\Entity\Creator;
use App\Entity\Manga;
use PHPUnit\Framework\TestCase;

class MangaTest extends TestCase
{
    public function testEntity(): void
    {
        $manga = new Manga();
        $manga->setTitle('Test Manga');
        $manga->setAltTitles(['Test Alt']);
        $manga->setDescription('Test Description');
        $manga->setStatus('ongoing');
        $manga->setYear(2024);
        $manga->setContentRating('suggestive');

        $this->assertEquals('Test Manga', $manga->getTitle());
        $this->assertEquals(['Test Alt'], $manga->getAltTitles());
        $this->assertEquals('Test Description', $manga->getDescription());
        $this->assertEquals('ongoing', $manga->getStatus());
        $this->assertEquals(2024, $manga->getYear());
        $this->assertEquals('suggestive', $manga->getContentRating());
        $this->assertNull($manga->getId());
    }

    public function testStatusChoices(): void
    {
        $choices = Manga::getStatusChoices();
        $this->assertContains('ongoing', $choices);
        $this->assertContains('completed', $choices);
        $this->assertContains('hiatus', $choices);
        $this->assertContains('cancelled', $choices);
    }

    public function testContentRatingChoices(): void
    {
        $choices = Manga::getContentRatingChoices();
        $this->assertContains('safe', $choices);
        $this->assertContains('suggestive', $choices);
        $this->assertContains('erotica', $choices);
        $this->assertContains('pornographic', $choices);
    }

    public function testAddRemoveCreator(): void
    {
        $manga = new Manga();
        $creator = new Creator();
        $creator->setName('Author');
        $creator->setType('author');

        $manga->addCreator($creator);
        $this->assertTrue($manga->getCreators()->contains($creator));

        $manga->removeCreator($creator);
        $this->assertFalse($manga->getCreators()->contains($creator));
    }

    public function testAddRemoveChapter(): void
    {
        $manga = new Manga();
        $chapter = new Chapter();
        $chapter->setManga($manga);
        $chapter->setChapterNumber('1');

        $manga->addChapter($chapter);
        $this->assertTrue($manga->getChapters()->contains($chapter));

        $manga->removeChapter($chapter);
        $this->assertFalse($manga->getChapters()->contains($chapter));
    }
}
