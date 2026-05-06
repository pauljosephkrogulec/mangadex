<?php

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
        if (!$user) {
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
        
        echo "Token: " . substr($token, 0, 50) . "...\n";
        
        // Check what headers are being sent
        $headers = $client->getInternalRequest()->getServer();
        echo "Has HTTP_AUTHORIZATION: " . (isset($headers['HTTP_AUTHORIZATION']) ? 'yes' : 'no') . "\n";
        
        // Make authenticated request
        $client->request(
            'GET',
            '/api/users/' . $user->getId(),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $token],
            ''
        );

        echo "Response status: " . $client->getResponse()->getStatusCode() . "\n";
        echo "Response: " . substr($client->getResponse()->getContent(), 0, 200) . "\n";
        
        // Check request headers for the authenticated request
        $headers2 = $client->getInternalRequest()->getServer();
        echo "Auth request has HTTP_AUTHORIZATION: " . (isset($headers2['HTTP_AUTHORIZATION']) ? 'yes - ' . substr($headers2['HTTP_AUTHORIZATION'], 0, 30) : 'no') . "\n";
    }
}
