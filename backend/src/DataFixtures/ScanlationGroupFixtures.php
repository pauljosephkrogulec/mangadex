<?php

namespace App\DataFixtures;

use App\Entity\ScanlationGroup;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class ScanlationGroupFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $groups = [
            ['name' => 'MangaPlus', 'website' => 'https://mangaplus.shueisha.co.jp'],
            ['name' => 'VIZ Media', 'website' => 'https://www.viz.com'],
            ['name' => 'Crunchyroll Manga', 'website' => 'https://www.crunchyroll.com/comics/manga'],
            ['name' => 'Scanlation Group X', 'website' => null],
            ['name' => 'MangaDex', 'website' => 'https://mangadex.org'],
        ];

        foreach ($groups as $index => $data) {
            $group = new ScanlationGroup();
            $group->setName($data['name']);
            $group->setWebsite($data['website']);
            $manager->persist($group);
            $this->addReference('scanlation_group_' . ($index + 1), $group);
        }

        $manager->flush();
    }

    public static function getGroups(): array
    {
        return ['scanlation_groups'];
    }
}
