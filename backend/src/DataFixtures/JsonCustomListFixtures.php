<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\CustomList;
use App\Entity\Manga;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class JsonCustomListFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $filePath = __DIR__ . '/json/custom_lists.json';

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
            $list = new CustomList();
            $list->setName($item['name']);
            $list->setVisibility($item['visibility']);

            $user = $this->getReference('user_' . $item['user'], User::class);
            $list->setUser($user);

            foreach ($item['mangas'] as $mangaRef) {
                $manga = $this->getReference('manga_' . $mangaRef, Manga::class);
                $list->addManga($manga);
            }

            $manager->persist($list);
            $this->addReference('custom_list_' . $item['ref'], $list);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            JsonUserFixtures::class,
            JsonMangaFixtures::class,
        ];
    }

    public static function getGroups(): array
    {
        return ['custom_lists'];
    }
}
