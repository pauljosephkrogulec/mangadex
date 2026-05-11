<?php

declare(strict_types=1);

namespace App\Tests;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AuthTest extends WebTestCase
{
    public function testLoginWithValidCredentialsReturnsJwtToken(): void
    {
        $client = static::createClient();
        $this->ensureTestUserExists();

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
        $this->assertArrayHasKey('token', $response);
        $this->assertNotEmpty($response['token']);
    }

    public function testLoginWithInvalidCredentialsReturnsUnauthorized(): void
    {
        $client = static::createClient();
        $this->ensureTestUserExists();

        $client->request(
            'POST',
            '/api/login_check',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['email' => 'test@example.com', 'password' => 'wrongpassword'])
        );

        $this->assertResponseStatusCodeSame(401);
    }

    public function testLoginWithEmptyPasswordReturnsBadRequest(): void
    {
        $client = static::createClient();
        $this->ensureTestUserExists();

        $client->request(
            'POST',
            '/api/login_check',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['email' => 'test@example.com', 'password' => ''])
        );

        $this->assertResponseStatusCodeSame(401);
    }

    public function testLoginWithMissingPasswordKeyReturnsBadRequest(): void
    {
        $client = static::createClient();
        $this->ensureTestUserExists();

        $client->request(
            'POST',
            '/api/login_check',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['email' => 'test@example.com'])
        );

        $this->assertResponseStatusCodeSame(400);
    }

    private function ensureTestUserExists(): void
    {
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);

        $existingUser = $entityManager->getRepository(\App\Entity\User::class)->findOneBy(['email' => 'test@example.com']);

        if (!$existingUser) {
            $user = new \App\Entity\User();
            $user->setEmail('test@example.com');
            $user->setUsername('testuser');
            $hashedPassword = $passwordHasher->hashPassword($user, 'password123');
            $user->setPassword($hashedPassword);
            $user->setRoles(['ROLE_USER']);

            $entityManager->persist($user);
            $entityManager->flush();
        }
    }
}
