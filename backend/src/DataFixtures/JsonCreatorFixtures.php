<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Creator;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class JsonCreatorFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $filePath = __DIR__.'/json/creators.json';

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
            $creator = new Creator();
            $creator->setName($item['name']);
            $creator->setType($item['type']);

            $manager->persist($creator);
            $this->addReference('creator_'.$item['ref'], $creator);
        }

        $manager->flush();
    }

    public static function getGroups(): array
    {
        return ['creators'];
    }
}
