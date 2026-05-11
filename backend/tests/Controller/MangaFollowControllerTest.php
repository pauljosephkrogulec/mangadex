<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\MangaFollowController;
use App\Entity\Manga;
use App\Entity\MangaFollow;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

#[AllowMockObjectsWithoutExpectations]
class MangaFollowControllerTest extends TestCase
{
    private MangaFollowController $controller;
    private EntityManagerInterface $entityManager;
    private EntityRepository $repository;
    private User $user;
    private Manga $manga;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->repository = $this->createMock(EntityRepository::class);
        $this->user = new User();
        $this->manga = new Manga();

        $this->entityManager
            ->method('getRepository')
            ->willReturnCallback(function ($className) {
                return match ($className) {
                    MangaFollow::class => $this->repository,
                    default => throw new \RuntimeException("Unexpected repository: $className"),
                };
            });

        $this->controller = new MangaFollowController($this->entityManager);

        $this->mockSecurityContext();
    }

    private function mockSecurityContext(): void
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($this->user);

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        $container = $this->createMock(\Symfony\Component\DependencyInjection\ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->willReturn($tokenStorage);

        $reflection = new \ReflectionClass($this->controller);
        $property = $reflection->getProperty('container');
        $property->setValue($this->controller, $container);
    }

    private function createRequest(string $method): Request
    {
        return new Request([], [], [], [], [], ['REQUEST_METHOD' => $method]);
    }

    public function testInvokeWithPostMethodCreatesFollow(): void
    {
        $this->repository
            ->method('findOneBy')
            ->willReturn(null);

        $this->entityManager
            ->expects($this->once())
            ->method('persist');
        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $request = $this->createRequest('POST');
        $response = $this->controller->__invoke($this->manga, $request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(201, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['following']);
        $this->assertArrayHasKey('followedAt', $data);
    }

    public function testInvokeWithPostMethodAlreadyFollowing(): void
    {
        $existingFollow = new MangaFollow();
        $this->repository
            ->method('findOneBy')
            ->willReturn($existingFollow);

        $request = $this->createRequest('POST');
        $response = $this->controller->__invoke($this->manga, $request);

        $this->assertEquals(409, $response->getStatusCode());
    }

    public function testInvokeWithDeleteMethodRemovesFollow(): void
    {
        $existingFollow = new MangaFollow();
        $this->repository
            ->method('findOneBy')
            ->willReturn($existingFollow);

        $this->entityManager
            ->expects($this->once())
            ->method('remove');
        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $request = $this->createRequest('DELETE');
        $response = $this->controller->__invoke($this->manga, $request);

        $this->assertEquals(204, $response->getStatusCode());
    }

    public function testInvokeWithDeleteMethodNotFollowing(): void
    {
        $this->repository
            ->method('findOneBy')
            ->willReturn(null);

        $request = $this->createRequest('DELETE');
        $response = $this->controller->__invoke($this->manga, $request);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testInvokeWithGetMethodReturnsFollowing(): void
    {
        $existingFollow = new MangaFollow();
        $this->repository
            ->method('findOneBy')
            ->willReturn($existingFollow);

        $request = $this->createRequest('GET');
        $response = $this->controller->__invoke($this->manga, $request);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['following']);
    }

    public function testInvokeWithGetMethodReturnsNotFollowing(): void
    {
        $this->repository
            ->method('findOneBy')
            ->willReturn(null);

        $request = $this->createRequest('GET');
        $response = $this->controller->__invoke($this->manga, $request);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['following']);
    }

    public function testInvokeWithUnsupportedMethod(): void
    {
        $request = $this->createRequest('PUT');
        $response = $this->controller->__invoke($this->manga, $request);

        $this->assertEquals(405, $response->getStatusCode());
    }

    public function testInvokeWithoutAuthenticatedUser(): void
    {
        // Create a controller with container that returns null user
        $controller = new MangaFollowController($this->entityManager);

        // Mock token that returns null for user (unauthenticated)
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn(null);

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        $container = $this->createMock(\Symfony\Component\DependencyInjection\ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->willReturn($tokenStorage);

        $reflection = new \ReflectionClass($controller);
        $property = $reflection->getProperty('container');
        $property->setValue($controller, $container);

        $request = $this->createRequest('POST');

        $this->expectException(\Symfony\Component\Security\Core\Exception\AccessDeniedException::class);
        $this->expectExceptionMessage('User not authenticated');

        $controller->__invoke($this->manga, $request);
    }
}
