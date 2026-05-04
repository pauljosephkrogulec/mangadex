<?php

namespace App\Tests;

use App\Entity\Creator;
use App\Entity\Manga;
use PHPUnit\Framework\TestCase;

class CreatorTest extends TestCase
{
    public function testEntity(): void
    {
        $creator = new Creator();
        $creator->setName('Test Creator');
        $creator->setType('author');

        $this->assertEquals('Test Creator', $creator->getName());
        $this->assertEquals('author', $creator->getType());
        $this->assertNull($creator->getId());
    }

    public function testTypeChoices(): void
    {
        $choices = Creator::getTypeChoices();
        $this->assertContains('author', $choices);
        $this->assertContains('artist', $choices);
    }

    public function testAddManga(): void
    {
        $creator = new Creator();
        $creator->setName('Author');
        $creator->setType('author');

        $manga = new Manga();
        $manga->setTitle('Test Manga');
        $manga->setStatus('ongoing');
        $manga->setContentRating('suggestive');

        $creator->addManga($manga);

        $this->assertTrue($creator->getManga()->contains($manga));
        $this->assertTrue($manga->getCreators()->contains($creator));
    }

    public function testRemoveManga(): void
    {
        $creator = new Creator();
        $creator->setName('Author');
        $creator->setType('author');

        $manga = new Manga();
        $manga->setTitle('Test Manga');
        $manga->setStatus('ongoing');
        $manga->setContentRating('suggestive');

        $creator->addManga($manga);
        $this->assertTrue($creator->getManga()->contains($manga));

        $creator->removeManga($manga);
        $this->assertFalse($creator->getManga()->contains($manga));
        $this->assertFalse($manga->getCreators()->contains($creator));
    }
}
