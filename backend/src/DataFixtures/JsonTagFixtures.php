<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Tag;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class JsonTagFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $filePath = __DIR__ . '/json/tags.json';

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
            $tag = new Tag();
            $tag->setName($item['name']);
            $tag->setGroupName($item['groupName']);
            $tag->setDescription($item['description'] ?? null);
            $tag->setIsPrimary($item['isPrimary']);

            $manager->persist($tag);
            $this->addReference('tag_' . $item['ref'], $tag);
        }

        $manager->flush();
    }

    public static function getGroups(): array
    {
        return ['tags'];
    }
}
