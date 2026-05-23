<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\MangaRatingController;
use App\Entity\Manga;
use App\Entity\Rating;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

#[AllowMockObjectsWithoutExpectations]
class MangaRatingControllerTest extends TestCase
{
    private MangaRatingController $controller;
    private EntityManagerInterface $entityManager;
    private EntityRepository $repository;
    private Query $query;
    /** @var array{avg: string|null, cnt: string} */
    private array $queryResult = ['avg' => '7.5', 'cnt' => '4'];
    private User $user;
    private Manga $manga;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->repository = $this->createMock(EntityRepository::class);
        $this->query = $this->createMock(Query::class);
        $this->user = new User();
        $this->manga = new Manga();

        $this->entityManager
            ->method('getRepository')
            ->willReturn($this->repository);

        $this->query->method('setParameter')->willReturnSelf();
        $this->query->method('getSingleResult')->willReturnCallback(fn () => $this->queryResult);

        $this->entityManager
            ->method('createQuery')
            ->willReturn($this->query);

        $this->controller = new MangaRatingController($this->entityManager);
        $this->mockSecurityContext($this->user);
    }

    private function mockSecurityContext(mixed $userOrNull): void
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($userOrNull);

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        $container = $this->createMock(\Symfony\Component\DependencyInjection\ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->willReturn($tokenStorage);

        $reflection = new \ReflectionClass($this->controller);
        $property = $reflection->getProperty('container');
        $property->setValue($this->controller, $container);
    }

    private function createRequest(string $method, mixed $body = null): Request
    {
        $content = $body !== null ? json_encode($body) : '';

        return new Request([], [], [], [], [], ['REQUEST_METHOD' => $method], $content);
    }

    public function testGetReturnsAverageAndUserRating(): void
    {
        $rating = new Rating();
        $rating->setScore(8);
        $rating->setUser($this->user);
        $rating->setManga($this->manga);

        $this->repository->method('findOneBy')->willReturn($rating);

        $response = $this->controller->__invoke($this->manga, $this->createRequest('GET'));

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals(7.5, $data['averageRating']);
        $this->assertEquals(4, $data['ratingCount']);
        $this->assertEquals(8, $data['userRating']);
    }

    public function testGetReturnsNullUserRatingWhenNotRated(): void
    {
        $this->repository->method('findOneBy')->willReturn(null);

        $response = $this->controller->__invoke($this->manga, $this->createRequest('GET'));

        $data = json_decode($response->getContent(), true);
        $this->assertNull($data['userRating']);
    }

    public function testGetWithNullAvgReturnsNullAverage(): void
    {
        $this->queryResult = ['avg' => null, 'cnt' => '0'];
        $this->repository->method('findOneBy')->willReturn(null);

        $response = $this->controller->__invoke($this->manga, $this->createRequest('GET'));

        $data = json_decode($response->getContent(), true);
        $this->assertNull($data['averageRating']);
        $this->assertEquals(0, $data['ratingCount']);
    }

    public function testPostCreatesNewRating(): void
    {
        $this->repository->method('findOneBy')->willReturn(null);

        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $response = $this->controller->__invoke($this->manga, $this->createRequest('POST', ['score' => 7]));

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(201, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals(7, $data['userRating']);
        $this->assertEquals(7.5, $data['averageRating']);
    }

    public function testPostUpdatesExistingRating(): void
    {
        $existing = new Rating();
        $existing->setScore(5);
        $existing->setUser($this->user);
        $existing->setManga($this->manga);

        $this->repository->method('findOneBy')->willReturn($existing);

        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $response = $this->controller->__invoke($this->manga, $this->createRequest('POST', ['score' => 9]));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(9, $existing->getScore());
    }

    public function testPostRejectsInvalidScore(): void
    {
        $response = $this->controller->__invoke($this->manga, $this->createRequest('POST', ['score' => 11]));

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testPostRejectsNonIntegerScore(): void
    {
        $response = $this->controller->__invoke($this->manga, $this->createRequest('POST', ['score' => 'high']));

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testPostWithUnauthenticatedUserThrowsException(): void
    {
        $controller = new MangaRatingController($this->entityManager);
        $this->mockSecurityContextOnController($controller, null);

        $this->expectException(\Symfony\Component\Security\Core\Exception\AccessDeniedException::class);

        $controller->__invoke($this->manga, $this->createRequest('POST', ['score' => 5]));
    }

    public function testUnsupportedMethodReturns405(): void
    {
        $response = $this->controller->__invoke($this->manga, $this->createRequest('DELETE'));

        $this->assertEquals(405, $response->getStatusCode());
    }

    private function mockSecurityContextOnController(MangaRatingController $controller, mixed $userOrNull): void
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($userOrNull);

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        $container = $this->createMock(\Symfony\Component\DependencyInjection\ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->willReturn($tokenStorage);

        $reflection = new \ReflectionClass($controller);
        $property = $reflection->getProperty('container');
        $property->setValue($controller, $container);
    }
}
