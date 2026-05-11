<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\CustomListMangaController;
use App\Entity\CustomList;
use App\Entity\Manga;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

#[AllowMockObjectsWithoutExpectations]
class CustomListMangaControllerTest extends TestCase
{
    private CustomListMangaController $controller;
    private EntityManagerInterface $entityManager;
    private EntityRepository $mangaRepository;
    private User $user;
    private CustomList $customList;
    private Manga $manga;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->mangaRepository = $this->createMock(EntityRepository::class);

        $this->entityManager
            ->method('getRepository')
            ->willReturn($this->mangaRepository);

        $this->controller = new CustomListMangaController($this->entityManager);

        $this->user = new User();
        $this->user->setEmail('test@example.com');

        $this->customList = new CustomList();
        $this->customList->setUser($this->user);
        $this->customList->setName('Test List');

        $this->manga = new Manga();

        $this->mockSecurityContext($this->user, false);
    }

    private function mockSecurityContext(?User $user, bool $isAdmin = false): void
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        $authorizationChecker = $this->createMock(\Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')->willReturn($isAdmin);

        $container = $this->createMock(\Symfony\Component\DependencyInjection\ContainerInterface::class);
        $container->method('has')->willReturnCallback(function ($id) {
            return in_array($id, ['security.token_storage', 'security.authorization_checker']);
        });
        $container->method('get')->willReturnCallback(function ($id) use ($tokenStorage, $authorizationChecker) {
            return match ($id) {
                'security.token_storage' => $tokenStorage,
                'security.authorization_checker' => $authorizationChecker,
                default => null,
            };
        });

        $reflection = new \ReflectionClass($this->controller);
        $property = $reflection->getProperty('container');
        $property->setValue($this->controller, $container);
    }

    private function createRequest(string $method, array $attributes = []): Request
    {
        $request = new Request();
        $request->setMethod($method);
        foreach ($attributes as $key => $value) {
            $request->attributes->set($key, $value);
        }

        return $request;
    }

    public function testInvokeWithPostMethodAddsManga(): void
    {
        $this->mangaRepository
            ->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($this->manga);

        $request = $this->createRequest('POST', ['mangaId' => 1]);

        $response = $this->controller->__invoke($this->customList, $request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($this->customList->getMangas()->contains($this->manga));
    }

    public function testInvokeWithPostMethodMangaAlreadyInList(): void
    {
        $this->customList->addManga($this->manga);

        $this->mangaRepository
            ->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($this->manga);

        $request = $this->createRequest('POST', ['mangaId' => 1]);

        $response = $this->controller->__invoke($this->customList, $request);

        $this->assertEquals(409, $response->getStatusCode());
    }

    public function testInvokeWithDeleteMethodRemovesManga(): void
    {
        $this->customList->addManga($this->manga);

        $this->mangaRepository
            ->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($this->manga);

        $request = $this->createRequest('DELETE', ['mangaId' => 1]);

        $response = $this->controller->__invoke($this->customList, $request);

        $this->assertEquals(204, $response->getStatusCode());
        $this->assertFalse($this->customList->getMangas()->contains($this->manga));
    }

    public function testInvokeWithDeleteMethodMangaNotInList(): void
    {
        $this->mangaRepository
            ->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($this->manga);

        $request = $this->createRequest('DELETE', ['mangaId' => 1]);

        $response = $this->controller->__invoke($this->customList, $request);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testInvokeWithUnsupportedMethod(): void
    {
        $this->mangaRepository
            ->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($this->manga);

        $request = $this->createRequest('PUT', ['mangaId' => 1]);

        $response = $this->controller->__invoke($this->customList, $request);

        $this->assertEquals(405, $response->getStatusCode());
    }

    public function testInvokeWithoutAuthenticatedUser(): void
    {
        $this->mockSecurityContext(null);

        $request = $this->createRequest('POST', ['mangaId' => 1]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException::class);
        $this->expectExceptionMessage('User not authenticated');

        $this->controller->__invoke($this->customList, $request);
    }

    public function testInvokeWithAccessDeniedForNonOwner(): void
    {
        $otherUser = new User();
        $otherUser->setEmail('other@example.com');
        $customList = new CustomList();
        $customList->setUser($otherUser);
        $customList->setName('Other List');

        $this->mangaRepository
            ->method('find')
            ->willReturn($this->manga);

        $request = $this->createRequest('POST', ['mangaId' => 1]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException::class);
        $this->expectExceptionMessage('You can only modify your own lists');

        $this->controller->__invoke($customList, $request);
    }

    public function testInvokeWithAdminCanModifyAnyList(): void
    {
        // Re-mock with admin privileges
        $this->mockSecurityContext($this->user, true);

        $otherUser = new User();
        $otherUser->setEmail('other@example.com');
        $customList = new CustomList();
        $customList->setUser($otherUser);
        $customList->setName('Other List');

        $this->mangaRepository
            ->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($this->manga);

        $request = $this->createRequest('POST', ['mangaId' => 1]);

        $response = $this->controller->__invoke($customList, $request);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testInvokeWithMangaIdMissing(): void
    {
        $request = $this->createRequest('POST');

        $response = $this->controller->__invoke($this->customList, $request);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testInvokeWithMangaNotFound(): void
    {
        $this->mangaRepository
            ->expects($this->once())
            ->method('find')
            ->with(999)
            ->willReturn(null);

        $request = $this->createRequest('POST', ['mangaId' => 999]);

        $this->expectException(NotFoundHttpException::class);

        $this->controller->__invoke($this->customList, $request);
    }
}
