<?php

namespace App\DataFixtures;

use App\Entity\Tag;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class TagFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $tags = [
            // Genre tags
            ['name' => 'Action', 'groupName' => 'genre', 'description' => 'Fast-paced action scenes', 'isPrimary' => true],
            ['name' => 'Adventure', 'groupName' => 'genre', 'description' => 'Journey and exploration themes', 'isPrimary' => true],
            ['name' => 'Fantasy', 'groupName' => 'genre', 'description' => 'Magic and supernatural elements', 'isPrimary' => true],
            ['name' => 'Drama', 'groupName' => 'genre', 'description' => 'Emotional character development', 'isPrimary' => false],
            ['name' => 'Comedy', 'groupName' => 'genre', 'description' => 'Humorous content', 'isPrimary' => false],
            ['name' => 'Horror', 'groupName' => 'genre', 'description' => 'Scary and frightening content', 'isPrimary' => false],

            // Theme tags
            ['name' => 'Supernatural', 'groupName' => 'theme', 'description' => 'Ghosts, demons, magic', 'isPrimary' => false],
            ['name' => 'Martial Arts', 'groupName' => 'theme', 'description' => 'Fighting and combat', 'isPrimary' => false],
            ['name' => 'School Life', 'groupName' => 'theme', 'description' => 'Set in school environment', 'isPrimary' => false],
            ['name' => 'Reverse Harem', 'groupName' => 'theme', 'description' => 'One male, multiple female interests', 'isPrimary' => false],

            // Format tags
            ['name' => 'Manga', 'groupName' => 'format', 'description' => 'Japanese comic format', 'isPrimary' => true],
            ['name' => 'Oneshot', 'groupName' => 'format', 'description' => 'Single chapter story', 'isPrimary' => false],
            ['name' => 'Long Strip', 'groupName' => 'format', 'description' => 'Ongoing series', 'isPrimary' => false],
        ];

        foreach ($tags as $index => $data) {
            $tag = new Tag();
            $tag->setName($data['name']);
            $tag->setGroupName($data['groupName']);
            $tag->setDescription($data['description'] ?? null);
            $tag->setIsPrimary($data['isPrimary']);
            $manager->persist($tag);
            $this->addReference('tag_' . ($index + 1), $tag);
        }

        $manager->flush();
    }

    public static function getGroups(): array
    {
        return ['tags'];
    }
}
