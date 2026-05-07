<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class UserFixtures extends Fixture
{
    private \Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface $passwordHasher;

    #[Autowire]
    public function __construct(\Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        // Admin user
        $admin = new User();
        $admin->setEmail('admin@example.com');
        $admin->setUsername('admin');
        $admin->setRoles(['ROLE_ADMIN']);
        $hashedPassword = $this->passwordHasher->hashPassword($admin, 'admin123');
        $admin->setPassword($hashedPassword);
        $manager->persist($admin);
        $this->addReference('admin-user', $admin);

        // Regular user 1
        $user1 = new User();
        $user1->setEmail('user1@example.com');
        $user1->setUsername('mangareader1');
        $hashedPassword = $this->passwordHasher->hashPassword($user1, 'password123');
        $user1->setPassword($hashedPassword);
        $manager->persist($user1);
        $this->addReference('user1', $user1);

        // Regular user 2
        $user2 = new User();
        $user2->setEmail('user2@example.com');
        $user2->setUsername('mangareader2');
        $hashedPassword = $this->passwordHasher->hashPassword($user2, 'password123');
        $user2->setPassword($hashedPassword);
        $manager->persist($user2);
        $this->addReference('user2', $user2);

        $manager->flush();
    }

    public static function getGroups(): array
    {
        return ['users'];
    }
}
