<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class JwtHeaderDebugTest extends WebTestCase
{
    public function testJwtHeader(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);

        // Create test user
        $user = $entityManager->getRepository(User::class)->findOneBy(['email' => 'test@example.com']);
        if (! $user) {
            $user = new User();
            $user->setEmail('test@example.com');
            $user->setUsername('testuser');
            $hashedPassword = $passwordHasher->hashPassword($user, 'password123');
            $user->setPassword($hashedPassword);
            $user->setRoles(['ROLE_USER']);
            $entityManager->persist($user);
            $entityManager->flush();
        }

        // Login
        $client->request(
            'POST',
            '/api/login_check',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['email' => 'test@example.com', 'password' => 'password123'])
        );

        $this->assertResponseStatusCodeSame(200);
        $response = json_decode($client->getResponse()->getContent(), true);
        $token = $response['token'] ?? '';
        $this->assertNotEmpty($token);

        // Make authenticated request
        $client->request(
            'GET',
            '/api/users/' . $user->getId(),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $token],
            ''
        );
    }
}
