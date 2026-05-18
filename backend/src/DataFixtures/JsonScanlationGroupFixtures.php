<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\ScanlationGroup;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

class JsonScanlationGroupFixtures extends Fixture implements FixtureGroupInterface
{
    public function load(ObjectManager $manager): void
    {
        $filePath = __DIR__.'/json/scanlation_groups.json';

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
            $group = new ScanlationGroup();
            $group->setName($item['name']);
            $group->setWebsite($item['website'] ?? null);

            $manager->persist($group);
            $this->addReference('scanlation_group_'.$item['ref'], $group);
        }

        $manager->flush();
    }

    public static function getGroups(): array
    {
        return ['scanlation_groups'];
    }
}
