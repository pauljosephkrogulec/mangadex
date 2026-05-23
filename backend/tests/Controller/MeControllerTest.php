<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\MeController;
use App\Entity\User;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

#[AllowMockObjectsWithoutExpectations]
class MeControllerTest extends TestCase
{
    public function testMeReturnsUserData(): void
    {
        $user = new User();
        $user->setEmail('user@example.com');
        $user->setUsername('testuser');
        $user->setPassword('hashed');

        $reflection = new \ReflectionClass($user);
        $createdAtProp = $reflection->getProperty('createdAt');
        $createdAtProp->setValue($user, new \DateTime('2024-01-01T00:00:00+00:00'));

        $controller = $this->createControllerWithUser($user);

        $response = $controller->me();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertSame($user->getId(), $data['id']);
        $this->assertSame('user@example.com', $data['email']);
        $this->assertSame('testuser', $data['username']);
        $this->assertSame('2024-01-01T00:00:00+00:00', $data['createdAt']);
        $this->assertContains('ROLE_USER', $data['roles']);
    }

    public function testMeWithoutAuthReturns401(): void
    {
        $controller = $this->createControllerWithUser(null);

        $response = $controller->me();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(401, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertSame('Not authenticated', $data['error']);
    }

    public function testMeWithoutTokenReturns401(): void
    {
        $controller = $this->createControllerWithoutToken();

        $response = $controller->me();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(401, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertSame('Not authenticated', $data['error']);
    }

    private function createControllerWithUser(?User $user): MeController
    {
        $controller = new MeController();

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        $container = $this->createMock(\Symfony\Component\DependencyInjection\ContainerInterface::class);
        $container->method('has')->willReturnCallback(fn (string $id) => match ($id) {
            'security.token_storage' => true,
            default => false,
        });
        $container->method('get')->willReturn($tokenStorage);

        $reflection = new \ReflectionClass($controller);
        $property = $reflection->getProperty('container');
        $property->setValue($controller, $container);

        return $controller;
    }

    private function createControllerWithoutToken(): MeController
    {
        $controller = new MeController();

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn(null);

        $container = $this->createMock(\Symfony\Component\DependencyInjection\ContainerInterface::class);
        $container->method('has')->willReturnCallback(fn (string $id) => match ($id) {
            'security.token_storage' => true,
            default => false,
        });
        $container->method('get')->willReturn($tokenStorage);

        $reflection = new \ReflectionClass($controller);
        $property = $reflection->getProperty('container');
        $property->setValue($controller, $container);

        return $controller;
    }
}
