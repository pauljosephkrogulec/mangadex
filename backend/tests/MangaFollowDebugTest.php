<?php

namespace App\Tests;

use App\Entity\Manga;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class MangaFollowDebugTest extends WebTestCase
{
    public function testDebugFollow(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);

        // Create test user
        $user = $entityManager->getRepository(User::class)->findOneBy(['email' => 'followtest@example.com']);
        if (!$user) {
            $user = new User();
            $user->setEmail('followtest@example.com');
            $user->setUsername('followtestuser');
            $hashedPassword = $passwordHasher->hashPassword($user, 'password123');
            $user->setPassword($hashedPassword);
            $user->setRoles(['ROLE_USER']);
            $entityManager->persist($user);
            $entityManager->flush();
        }

        // Create test manga
        $manga = $entityManager->getRepository(Manga::class)->findOneBy(['title' => 'Follow Test Manga']);
        if (!$manga) {
            $manga = new Manga();
            $manga->setTitle('Follow Test Manga');
            $manga->setStatus('ongoing');
            $manga->setContentRating('safe');
            $entityManager->persist($manga);
            $entityManager->flush();
        }

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
        
        // Debug: print token (first 20 chars)
        echo "Token (first 20 chars): " . substr($token, 0, 20) . "...\n";
        echo "Token parts: " . count(explode('.', $token)) . "\n";

        // Try a simple authenticated request first
        $client->request(
            'GET',
            '/api/users/' . $user->getId(),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $token],
            ''
        );
        
        echo "GET user status: " . $client->getResponse()->getStatusCode() . "\n";

        // Now try follow request
        $client->request(
            'POST',
            '/api/mangas/' . $manga->getId() . '/follow',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $token],
            ''
        );

        echo "Follow status: " . $client->getResponse()->getStatusCode() . "\n";
        echo "Follow response: " . $client->getResponse()->getContent() . "\n";

        $this->assertResponseStatusCodeSame(201);
    }
}
