<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\CoverArt;
use App\Entity\Manga;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class JsonCoverArtFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $filePath = __DIR__.'/json/cover_arts.json';

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
            $coverArt = new CoverArt();
            $manga = $this->getReference('manga_'.$item['manga'], Manga::class);
            $coverArt->setManga($manga);
            $coverArt->setImagePath($item['imagePath']);
            $coverArt->setVolume($item['volume'] ?? null);
            $coverArt->setIsPrimary($item['isPrimary']);

            $manager->persist($coverArt);
            $this->addReference('cover_art_'.$item['ref'], $coverArt);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            JsonMangaFixtures::class,
        ];
    }

    public static function getGroups(): array
    {
        return ['cover_arts'];
    }
}
