<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class JsonUserFixtures extends Fixture implements FixtureGroupInterface
{
    private UserPasswordHasherInterface $passwordHasher;

    #[Autowire]
    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        $filePath = __DIR__.'/json/users.json';

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
            $user = new User();
            $user->setEmail($item['email']);
            $user->setUsername($item['username']);
            $user->setRoles($item['roles'] ?? ['ROLE_USER']);

            $hashedPassword = $this->passwordHasher->hashPassword($user, $item['password']);
            $user->setPassword($hashedPassword);

            $manager->persist($user);
            $this->addReference('user_'.$item['ref'], $user);
        }

        $manager->flush();
    }

    public static function getGroups(): array
    {
        return ['users'];
    }
}
