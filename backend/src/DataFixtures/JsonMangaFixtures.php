<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Creator;
use App\Entity\Manga;
use App\Entity\Tag;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class JsonMangaFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $filePath = __DIR__ . '/json/mangas.json';

        if (! file_exists($filePath)) {
            return;
        }

        $content = file_get_contents($filePath);

        if ($content === false) {
            return;
        }

        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($data)) {
            return;
        }

        foreach ($data as $item) {
            if (! is_array($item)) {
                continue;
            }
            $manga = new Manga();
            $manga->setTitle($item['title']);
            $manga->setAltTitles($item['altTitles'] ?? null);
            $manga->setDescription($item['description'] ?? null);
            $manga->setStatus($item['status']);
            $manga->setYear($item['year'] ?? null);
            $manga->setContentRating($item['contentRating']);
            $manga->setDemographic($item['demographic'] ?? 'none');

            foreach ($item['creators'] as $creatorRef) {
                $creator = $this->getReference('creator_' . $creatorRef, Creator::class);
                $manga->addCreator($creator);
            }

            foreach ($item['tags'] as $tagRef) {
                $tag = $this->getReference('tag_' . $tagRef, Tag::class);
                $manga->addTag($tag);
            }

            $manager->persist($manga);
            $this->addReference('manga_' . $item['ref'], $manga);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            JsonCreatorFixtures::class,
            JsonTagFixtures::class,
        ];
    }

    public static function getGroups(): array
    {
        return ['mangas'];
    }
}
