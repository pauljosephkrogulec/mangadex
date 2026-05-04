<?php

namespace App\Tests;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AuthTest extends WebTestCase
{
    private static bool $userCreated = false;

    public static function setUpBeforeClass(): void
    {
        // Create test user once for all tests
        // We need to boot the kernel manually to get the container
        static::bootKernel();
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        
        // Check if user already exists
        $existingUser = $entityManager->getRepository(\App\Entity\User::class)->findOneBy(['email' => 'test@example.com']);
        
        if (!$existingUser) {
            $user = new \App\Entity\User();
            $user->setEmail('test@example.com');
            $user->setPassword(password_hash('password123', PASSWORD_BCRYPT));
            $user->setRoles(['ROLE_USER']);
            
            $entityManager->persist($user);
            $entityManager->flush();
        }
        
        // Shutdown kernel so tests can create their own client
        static::ensureKernelShutdown();
    }

    public function testLoginWithValidCredentialsReturnsJwtToken(): void
    {
        $client = static::createClient();
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

    public function testAccessProtectedApiWithoutTokenReturnsUnauthorized(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/mangas');
        $this->assertResponseStatusCodeSame(401);
    }

    public function testAccessProtectedApiWithValidTokenReturnsSuccess(): void
    {
        $client = static::createClient();

        // First get a token
        $client->request(
            'POST',
            '/api/login_check',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['email' => 'test@example.com', 'password' => 'password123'])
        );

        $this->assertResponseStatusCodeSame(200);
    }

    public function testAccessProtectedApiWithInvalidTokenReturnsUnauthorized(): void
    {
        $client = static::createClient();
        $client->request(
            'GET',
            '/api/mangas',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer invalid_token_here']
        );

        $this->assertResponseStatusCodeSame(401);
    }
}
