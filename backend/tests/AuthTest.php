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

    public function testMeWithoutAuthReturnsUnauthorized(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/me');

        $this->assertResponseStatusCodeSame(401);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $response);
        $this->assertSame('Not authenticated', $response['error']);
    }

    public function testMeWithValidTokenReturnsUser(): void
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
        $data = json_decode($client->getResponse()->getContent(), true);
        $token = $data['token'];

        $client->request(
            'GET',
            '/api/me',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer '.$token],
        );

        $this->assertResponseStatusCodeSame(200);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('id', $response);
        $this->assertArrayHasKey('email', $response);
        $this->assertArrayHasKey('username', $response);
        $this->assertArrayHasKey('createdAt', $response);
        $this->assertSame('test@example.com', $response['email']);
        $this->assertSame('testuser', $response['username']);
    }

    public function testMeWithInvalidTokenReturnsUnauthorized(): void
    {
        $client = static::createClient();

        $client->request(
            'GET',
            '/api/me',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer invalid-token'],
        );

        $this->assertResponseStatusCodeSame(401);
    }

    public function testLogoutClearsCookie(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/logout');

        $this->assertResponseStatusCodeSame(200);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('Logged out successfully', $response['message']);

        $cookies = $client->getResponse()->headers->getCookies();
        $logoutCookie = null;
        foreach ($cookies as $cookie) {
            if ('mangadex_jwt_token' === $cookie->getName()) {
                $logoutCookie = $cookie;
                break;
            }
        }

        $this->assertNotNull($logoutCookie);
        $this->assertSame('', $logoutCookie->getValue());
        $this->assertTrue($logoutCookie->getExpiresTime() < time());
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
