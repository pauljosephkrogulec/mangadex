<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\Manga;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class MangaFollowStepTest extends WebTestCase
{
    // Step 1: Just login (like AuthTest) - should pass
    public function testStep1_LoginOnly(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);

        // Create test user
        $user = $entityManager->getRepository(User::class)->findOneBy(['email' => 'followtest@example.com']);
        if (! $user) {
            $user = new User();
            $user->setEmail('followtest@example.com');
            $user->setUsername('followtestuser');
            $hashedPassword = $passwordHasher->hashPassword($user, 'password123');
            $user->setPassword($hashedPassword);
            $user->setRoles(['ROLE_USER']);
            $entityManager->persist($user);
            $entityManager->flush();
        }

        // Try login
        $client->request(
            'POST',
            '/api/login_check',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['email' => 'followtest@example.com', 'password' => 'password123'])
        );

        $this->assertResponseStatusCodeSame(200);
    }

    // Step 2: Add manga creation, then login
    public function testStep2_WithManga(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);

        // Create test user
        $user = $entityManager->getRepository(User::class)->findOneBy(['email' => 'followtest@example.com']);
        if (! $user) {
            $user = new User();
            $user->setEmail('followtest@example.com');
            $user->setUsername('followtestuser');
            $hashedPassword = $passwordHasher->hashPassword($user, 'password123');
            $user->setPassword($hashedPassword);
            $user->setRoles(['ROLE_USER']);
            $entityManager->persist($user);
        }

        // Create test manga
        $manga = $entityManager->getRepository(Manga::class)->findOneBy(['title' => 'Follow Test Manga']);
        if (! $manga) {
            $manga = new Manga();
            $manga->setTitle('Follow Test Manga');
            $manga->setStatus('ongoing');
            $manga->setContentRating('safe');
            $entityManager->persist($manga);
        }

        $entityManager->flush();

        // Try login
        $client->request(
            'POST',
            '/api/login_check',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['email' => 'followtest@example.com', 'password' => 'password123'])
        );

        $this->assertResponseStatusCodeSame(200);
    }

    // Step 3: Add follow request
    public function testStep3_WithFollow(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);

        // Create test user
        $user = $entityManager->getRepository(User::class)->findOneBy(['email' => 'followtest@example.com']);
        if (! $user) {
            $user = new User();
            $user->setEmail('followtest@example.com');
            $user->setUsername('followtestuser');
            $hashedPassword = $passwordHasher->hashPassword($user, 'password123');
            $user->setPassword($hashedPassword);
            $user->setRoles(['ROLE_USER']);
            $entityManager->persist($user);
        }

        // Create test manga
        $manga = $entityManager->getRepository(Manga::class)->findOneBy(['title' => 'Follow Test Manga']);
        if (! $manga) {
            $manga = new Manga();
            $manga->setTitle('Follow Test Manga');
            $manga->setStatus('ongoing');
            $manga->setContentRating('safe');
            $entityManager->persist($manga);
        }

        $entityManager->flush();

        // Get JWT token
        $client->request(
            'POST',
            '/api/login_check',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['email' => 'followtest@example.com', 'password' => 'password123'])
        );

        $this->assertResponseStatusCodeSame(200);
        $response = json_decode($client->getResponse()->getContent(), true);
        $token = $response['token'] ?? '';

        // Follow manga
        $client->request(
            'POST',
            '/api/mangas/' . $manga->getId() . '/follow',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $token],
            ''
        );

        $this->assertResponseStatusCodeSame(201);
    }
}
