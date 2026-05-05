<?php

namespace App\DataFixtures;

use App\Entity\CoverArt;
use App\Entity\Manga;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;

class CoverArtFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $coverArts = [
            ['manga' => 1, 'imagePath' => '/covers/demon-slayer-vol1.jpg', 'volume' => '1', 'isPrimary' => true],
            ['manga' => 1, 'imagePath' => '/covers/demon-slayer-vol2.jpg', 'volume' => '2', 'isPrimary' => false],
            ['manga' => 2, 'imagePath' => '/covers/one-piece-vol1.jpg', 'volume' => '1', 'isPrimary' => true],
            ['manga' => 2, 'imagePath' => '/covers/one-piece-vol2.jpg', 'volume' => '2', 'isPrimary' => false],
            ['manga' => 3, 'imagePath' => '/covers/jojo-part3-vol1.jpg', 'volume' => '1', 'isPrimary' => true],
            ['manga' => 4, 'imagePath' => '/covers/naruto-vol1.jpg', 'volume' => '1', 'isPrimary' => true],
            ['manga' => 4, 'imagePath' => '/covers/naruto-vol2.jpg', 'volume' => '2', 'isPrimary' => false],
            ['manga' => 5, 'imagePath' => '/covers/berserk-vol1.jpg', 'volume' => '1', 'isPrimary' => true],
            ['manga' => 6, 'imagePath' => '/covers/aot-vol1.jpg', 'volume' => '1', 'isPrimary' => true],
            ['manga' => 6, 'imagePath' => '/covers/aot-vol2.jpg', 'volume' => '2', 'isPrimary' => false],
        ];

        foreach ($coverArts as $index => $data) {
            $coverArt = new CoverArt();
            $manga = $this->getReference('manga_' . $data['manga'], Manga::class);
            $coverArt->setManga($manga);
            $coverArt->setImagePath($data['imagePath']);
            $coverArt->setVolume($data['volume']);
            $coverArt->setIsPrimary($data['isPrimary']);

            $manager->persist($coverArt);
            $this->addReference('cover_art_' . ($index + 1), $coverArt);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            MangaFixtures::class,
        ];
    }

    public static function getGroups(): array
    {
        return ['cover_arts'];
    }
}
