<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Chapter;
use App\Entity\Manga;
use App\Entity\ScanlationGroup;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class JsonChapterFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $filePath = __DIR__.'/json/chapters.json';

        if (!file_exists($filePath)) {
            return;
        }

        $content = file_get_contents($filePath);

        if (false === $content) {
            return;
        }

        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($data)) {
            return;
        }

        foreach ($data as $item) {
            if (!is_array($item)) {
                continue;
            }
            $chapter = new Chapter();
            $manga = $this->getReference('manga_'.$item['manga'], Manga::class);
            $chapter->setManga($manga);
            $chapter->setVolume($item['volume'] ?? null);
            $chapter->setChapterNumber($item['chapterNumber']);
            $chapter->setTitle($item['title'] ?? null);
            $chapter->setLanguage($item['language']);
            $chapter->setPages($item['pages']);

            if (isset($item['scanlationGroup'])) {
                $scanlationGroup = $this->getReference('scanlation_group_'.$item['scanlationGroup'], ScanlationGroup::class);
                $chapter->setScanlationGroup($scanlationGroup);
            }

            $manager->persist($chapter);
            $this->addReference('chapter_'.$item['ref'], $chapter);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            JsonMangaFixtures::class,
            JsonScanlationGroupFixtures::class,
        ];
    }

    public static function getGroups(): array
    {
        return ['chapters'];
    }
}
