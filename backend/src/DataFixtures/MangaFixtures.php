<?php

namespace App\DataFixtures;

use App\Entity\Creator;
use App\Entity\Manga;
use App\Entity\Tag;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class MangaFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $mangas = [
            [
                'title' => 'Demon Slayer: Kimetsu no Yaiba',
                'altTitles' => ['鬼滅の刃', 'Kimetsu no Yaiba'],
                'description' => 'Tanjiro Kamado sets out to become a demon slayer to avenge his family and cure his sister.',
                'status' => 'completed',
                'year' => 2016,
                'contentRating' => 'suggestive',
                'creators' => [1, 2], // Gotouge (author), Gotouge (artist)
                'tags' => [1, 2, 3, 4, 10], // Action, Adventure, Fantasy, Drama, Manga
            ],
            [
                'title' => 'One Piece',
                'altTitles' => ['ワンピース', 'Wan Pīsu'],
                'description' => 'Gol D. Roger was known as the Pirate King, the strongest and most infamous being to have sailed the Grand Line.',
                'status' => 'ongoing',
                'year' => 1997,
                'contentRating' => 'suggestive',
                'creators' => [3, 4], // Oda (author), Oda (artist)
                'tags' => [1, 2, 4, 5, 10], // Action, Adventure, Drama, Comedy, Manga
            ],
            [
                'title' => 'JoJo\'s Bizarre Adventure: Part 3 - Stardust Crusaders',
                'altTitles' => ['ジョジョの奇妙な冒険', 'JoJo no Kimyou na Bouken'],
                'description' => 'Jotaro Kujo and his companions travel to Egypt to defeat DIO.',
                'status' => 'completed',
                'year' => 1989,
                'contentRating' => 'suggestive',
                'creators' => [5, 6], // Araki (author), Araki (artist)
                'tags' => [1, 2, 3, 7, 10], // Action, Adventure, Fantasy, Supernatural, Manga
            ],
            [
                'title' => 'Naruto',
                'altTitles' => ['ナルト'],
                'description' => 'A young ninja who seeks to gain recognition and dreams to become the Hokage.',
                'status' => 'completed',
                'year' => 1999,
                'contentRating' => 'suggestive',
                'creators' => [5, 7], // Kishimoto (author), Inoue (artist)
                'tags' => [1, 2, 3, 6, 10], // Action, Adventure, Fantasy, Martial Arts, Manga
            ],
            [
                'title' => 'Berserk',
                'altTitles' => ['ベルセルク'],
                'description' => 'Guts, a former mercenary, seeks revenge against Griffith and the God Hand.',
                'status' => 'ongoing',
                'year' => 1989,
                'contentRating' => 'erotica',
                'creators' => [6, 9], // Miura (author), Araki (artist - after Miura's death)
                'tags' => [1, 3, 4, 7, 10], // Action, Fantasy, Drama, Supernatural, Manga
            ],
            [
                'title' => 'Attack on Titan',
                'altTitles' => ['進撃の巨人', 'Shingeki no Kyojin'],
                'description' => 'In a world where humanity lives inside cities surrounded by enormous walls, a young boy vows to exterminate the Titans.',
                'status' => 'completed',
                'year' => 2009,
                'contentRating' => 'suggestive',
                'creators' => [4, 8], // Isayama (author), Yamahara (artist)
                'tags' => [1, 3, 4, 7, 10], // Action, Fantasy, Drama, Supernatural, Manga
            ],
        ];

        foreach ($mangas as $index => $data) {
            $manga = new Manga();
            $manga->setTitle($data['title']);
            $manga->setAltTitles($data['altTitles']);
            $manga->setDescription($data['description']);
            $manga->setStatus($data['status']);
            $manga->setYear($data['year']);
            $manga->setContentRating($data['contentRating']);

            // Add creators
            foreach ($data['creators'] as $creatorRef) {
                $creator = $this->getReference('creator_' . $creatorRef, Creator::class);
                $manga->addCreator($creator);
            }

            // Add tags
            foreach ($data['tags'] as $tagRef) {
                $tag = $this->getReference('tag_' . $tagRef, Tag::class);
                $manga->addTag($tag);
            }

            $manager->persist($manga);
            $this->addReference('manga_' . ($index + 1), $manga);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            CreatorFixtures::class,
            TagFixtures::class,
        ];
    }

    public static function getGroups(): array
    {
        return ['mangas'];
    }
}
