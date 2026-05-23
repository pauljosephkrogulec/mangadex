<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Comment;
use App\Entity\Manga;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class JsonCommentFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public function load(ObjectManager $manager): void
    {
        $filePath = __DIR__.'/json/comments.json';

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

            $comment = new Comment();
            $comment->setContent($item['content']);

            $user = $this->getReference('user_'.$item['user'], User::class);
            $comment->setUser($user);

            $manga = $this->getReference('manga_'.$item['manga'], Manga::class);
            $comment->setManga($manga);

            $manager->persist($comment);
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
        return ['comments'];
    }
}
