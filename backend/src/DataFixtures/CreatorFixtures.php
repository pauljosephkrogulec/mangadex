<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Creator;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class CreatorFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $creators = [
            ['name' => 'Koyoharu Gotouge', 'type' => 'author'],
            ['name' => 'Eiichiro Oda', 'type' => 'author'],
            ['name' => 'Hirohiko Araki', 'type' => 'author'],
            ['name' => 'Yoshihiro Togashi', 'type' => 'author'],
            ['name' => 'Masashi Kishimoto', 'type' => 'author'],
            ['name' => 'Kentaro Miura', 'type' => 'author'],
            ['name' => 'Takehiko Inoue', 'type' => 'author'],
            ['name' => 'Yoshito Yamahara', 'type' => 'artist'],
            ['name' => 'Bose', 'type' => 'artist'],
            ['name' => 'Hirohiko Araki', 'type' => 'artist'],
        ];

        foreach ($creators as $index => $data) {
            $creator = new Creator();
            $creator->setName($data['name']);
            $creator->setType($data['type']);
            $manager->persist($creator);
            $this->addReference('creator_' . ($index + 1), $creator);
        }

        $manager->flush();
    }

    public static function getGroups(): array
    {
        return ['creators'];
    }
}
