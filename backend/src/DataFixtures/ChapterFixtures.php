<?php

namespace App\DataFixtures;

use App\Entity\Chapter;
use App\Entity\Manga;
use App\Entity\ScanlationGroup;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class ChapterFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $chapters = [
            // Demon Slayer
            ['manga' => 1, 'volume' => '1', 'chapterNumber' => '1', 'title' => 'Cruelty', 'language' => 'en', 'pages' => ['001.jpg', '002.jpg', '003.jpg'], 'scanlationGroup' => 1],
            ['manga' => 1, 'volume' => '1', 'chapterNumber' => '2', 'title' => 'A Fragrant Flower', 'language' => 'en', 'pages' => ['001.jpg', '002.jpg', '003.jpg'], 'scanlationGroup' => 1],
            ['manga' => 1, 'volume' => '1', 'chapterNumber' => '3', 'title' => 'Return to Tatara', 'language' => 'en', 'pages' => ['001.jpg', '002.jpg', '003.jpg'], 'scanlationGroup' => 2],

            // One Piece
            ['manga' => 2, 'volume' => '1', 'chapterNumber' => '1', 'title' => 'Romance Dawn', 'language' => 'en', 'pages' => ['001.jpg', '002.jpg', '003.jpg'], 'scanlationGroup' => 3],
            ['manga' => 2, 'volume' => '1', 'chapterNumber' => '2', 'title' => 'They Call Him Luffy', 'language' => 'en', 'pages' => ['001.jpg', '002.jpg', '003.jpg'], 'scanlationGroup' => 3],
            ['manga' => 2, 'volume' => '2', 'chapterNumber' => '13', 'title' => 'The Promise', 'language' => 'en', 'pages' => ['001.jpg', '002.jpg', '003.jpg'], 'scanlationGroup' => 4],

            // JoJo Part 3
            ['manga' => 3, 'volume' => '1', 'chapterNumber' => '1', 'title' => 'Jotaro Kujo', 'language' => 'en', 'pages' => ['001.jpg', '002.jpg', '003.jpg'], 'scanlationGroup' => 5],
            ['manga' => 3, 'volume' => '1', 'chapterNumber' => '2', 'title' => 'The Journey Begins', 'language' => 'en', 'pages' => ['001.jpg', '002.jpg', '003.jpg'], 'scanlationGroup' => 5],

            // Naruto
            ['manga' => 4, 'volume' => '1', 'chapterNumber' => '1', 'title' => 'Uzumaki Naruto', 'language' => 'en', 'pages' => ['001.jpg', '002.jpg', '003.jpg'], 'scanlationGroup' => 3],
            ['manga' => 4, 'volume' => '1', 'chapterNumber' => '2', 'title' => 'Konohamaru', 'language' => 'en', 'pages' => ['001.jpg', '002.jpg', '003.jpg'], 'scanlationGroup' => 2],

            // Berserk
            ['manga' => 5, 'volume' => '1', 'chapterNumber' => '1', 'title' => 'The Brand', 'language' => 'en', 'pages' => ['001.jpg', '002.jpg', '003.jpg'], 'scanlationGroup' => 5],
            ['manga' => 5, 'volume' => '1', 'chapterNumber' => '2', 'title' => 'The Golden Age', 'language' => 'en', 'pages' => ['001.jpg', '002.jpg', '003.jpg'], 'scanlationGroup' => 5],

            // Attack on Titan
            ['manga' => 6, 'volume' => '1', 'chapterNumber' => '1', 'title' => 'To You, 2000 Years Later', 'language' => 'en', 'pages' => ['001.jpg', '002.jpg', '003.jpg'], 'scanlationGroup' => 3],
            ['manga' => 6, 'volume' => '1', 'chapterNumber' => '2', 'title' => 'That Day', 'language' => 'en', 'pages' => ['001.jpg', '002.jpg', '003.jpg'], 'scanlationGroup' => 4],
        ];

        foreach ($chapters as $index => $data) {
            $chapter = new Chapter();
            $manga = $this->getReference('manga_' . $data['manga'], Manga::class);
            $chapter->setManga($manga);
            $chapter->setVolume($data['volume']);
            $chapter->setChapterNumber($data['chapterNumber']);
            $chapter->setTitle($data['title']);
            $chapter->setLanguage($data['language']);
            $chapter->setPages($data['pages']);

            if (isset($data['scanlationGroup'])) {
                $scanlationGroup = $this->getReference('scanlation_group_' . $data['scanlationGroup'], ScanlationGroup::class);
                $chapter->setScanlationGroup($scanlationGroup);
            }

            $manager->persist($chapter);
            $this->addReference('chapter_' . ($index + 1), $chapter);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            MangaFixtures::class,
            ScanlationGroupFixtures::class,
        ];
    }

    public static function getGroups(): array
    {
        return ['chapters'];
    }
}
