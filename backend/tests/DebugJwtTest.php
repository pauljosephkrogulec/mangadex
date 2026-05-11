<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class DebugJwtTest extends WebTestCase
{
    public function testJwtAuth(): void
    {
        $client = static::createClient();
        $container = $client->getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $hasher = $container->get(UserPasswordHasherInterface::class);

        // Create user
        $user = $em->getRepository(User::class)->findOneBy(['email' => 'test@example.com']);
        if (!$user) {
            $user = new User();
            $user->setEmail('test@example.com');
            $user->setUsername('testuser');
            $hashed = $hasher->hashPassword($user, 'password123');
            $user->setPassword($hashed);
            $user->setRoles(['ROLE_USER']);
            $em->persist($user);
            $em->flush();
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

        $token = json_decode($client->getResponse()->getContent(), true)['token'] ?? '';

        $this->assertNotEmpty($token, 'JWT token should not be empty');

        // Try authenticated request with the same client
        $client->request(
            'GET',
            '/api/users/'.$user->getId(),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer '.$token],
            ''
        );

        $this->assertEquals(200, $client->getResponse()->getStatusCode());
    }
}
