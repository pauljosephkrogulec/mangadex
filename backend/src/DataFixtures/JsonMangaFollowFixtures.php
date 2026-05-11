<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Manga;
use App\Entity\MangaFollow;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class JsonMangaFollowFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $filePath = __DIR__.'/json/manga_follows.json';

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
            $follow = new MangaFollow();
            $user = $this->getReference('user_'.$item['user'], User::class);
            $manga = $this->getReference('manga_'.$item['manga'], Manga::class);
            $follow->setUser($user);
            $follow->setManga($manga);

            $manager->persist($follow);
            $this->addReference('follow_'.$item['ref'], $follow);
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
        return ['manga_follows'];
    }
}
