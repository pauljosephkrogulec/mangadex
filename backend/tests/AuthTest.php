<?php

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AuthTest extends WebTestCase
{
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
        $client->request('GET', '/api/docs.jsonld');
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

        $response = json_decode($client->getResponse()->getContent(), true);
        $token = $response['token'];

        // Use token to access protected endpoint (same client)
        $client->request(
            'GET',
            '/api',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );

        $this->assertResponseStatusCodeSame(200);
    }

    public function testAccessProtectedApiWithInvalidTokenReturnsUnauthorized(): void
    {
        $client = static::createClient();
        $client->request(
            'GET',
            '/api',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer invalid_token_here']
        );

        $this->assertResponseStatusCodeSame(401);
    }
}
