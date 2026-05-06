<?php

namespace App\DataFixtures;

use App\Entity\CustomList;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class CustomListFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $lists = [
            ['name' => 'My Favorite Manga', 'visibility' => 'public', 'user' => 'user1'],
            ['name' => 'Action-Packed Series', 'visibility' => 'public', 'user' => 'user1'],
            ['name' => 'Read Later', 'visibility' => 'private', 'user' => 'user1'],
            ['name' => 'Top Picks', 'visibility' => 'public', 'user' => 'user2'],
            ['name' => 'Hidden Gems', 'visibility' => 'hidden', 'user' => 'user2'],
        ];

        foreach ($lists as $index => $data) {
            $list = new CustomList();
            $list->setName($data['name']);
            $list->setVisibility($data['visibility']);
            $user = $this->getReference($data['user'], User::class);
            $list->setUser($user);

            $manager->persist($list);
            $this->addReference('custom_list_' . ($index + 1), $list);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
        ];
    }

    public static function getGroups(): array
    {
        return ['custom_lists'];
    }
}
