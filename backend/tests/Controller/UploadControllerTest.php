<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\UploadController;
use App\Entity\Chapter;
use App\Entity\CoverArt;
use App\Entity\Manga;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationList;

#[AllowMockObjectsWithoutExpectations]
class UploadControllerTest extends TestCase
{
    private UploadController $controller;
    private $uploadValidator;
    private $storageService;
    private EntityManagerInterface $entityManager;
    private EntityRepository $mangaRepository;
    private EntityRepository $chapterRepository;
    private User $adminUser;
    private Manga $manga;
    private Chapter $chapter;

    protected function setUp(): void
    {
        $this->uploadValidator = $this->createMock(\App\Service\FileUploadValidator::class);
        $this->storageService = $this->createMock(\App\Service\FileStorageService::class);

        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->mangaRepository = $this->createMock(EntityRepository::class);
        $this->chapterRepository = $this->createMock(EntityRepository::class);

        $this->entityManager
            ->method('getRepository')
            ->willReturnCallback(function ($entityClass) {
                return match($entityClass) {
                    Manga::class => $this->mangaRepository,
                    Chapter::class => $this->chapterRepository,
                    default => null,
                };
            });

        $this->controller = new UploadController(
            $this->uploadValidator,
            $this->storageService
        );

        $this->adminUser = new User();
        $this->adminUser->setEmail('admin@example.com');
        $this->adminUser->setRoles(['ROLE_ADMIN']);

        $this->manga = $this->createMock(Manga::class);
        $this->manga->method('getId')->willReturn('1');
        $this->manga->method('getTitle')->willReturn('Test Manga');

        $this->chapter = $this->createMock(Chapter::class);
        $this->chapter->method('getId')->willReturn('1');

        $this->mockSecurityContext($this->adminUser);
    }

    private function mockSecurityContext(?User $user): void
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->method('serialize')->willReturnCallback(fn ($data) => json_encode($data, JSON_THROW_ON_ERROR));

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnCallback(function ($id) {
            return in_array($id, ['security.token_storage', 'serializer']);
        });
        $container->method('get')->willReturnCallback(function ($id) use ($tokenStorage, $serializer) {
            return match($id) {
                'security.token_storage' => $tokenStorage,
                'serializer' => $serializer,
                default => null,
            };
        });

        $reflection = new \ReflectionClass($this->controller);
        $property = $reflection->getProperty('container');
        $property->setValue($this->controller, $container);
    }

    public function testUploadCoverSuccess(): void
    {
        $this->mangaRepository
            ->expects($this->once())
            ->method('find')
            ->with('1')
            ->willReturn($this->manga);

        $this->uploadValidator
            ->method('validateImage')
            ->willReturn(new ConstraintViolationList());

        $this->storageService
            ->method('storeCover')
            ->willReturn('/uploads/covers/1/cover.jpg');

        $this->entityManager
            ->expects($this->once())
            ->method('persist');
        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $file = $this->createMock(UploadedFile::class);

        $request = new Request();
        $request->setMethod('POST');
        $request->request->set('mangaId', '1');
        $request->request->set('volume', '3');
        $request->request->set('isPrimary', 'true');
        $request->files->set('cover', $file);

        $response = $this->controller->uploadCover($request, $this->entityManager);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(201, $response->getStatusCode());
    }

    public function testUploadCoverNoFile(): void
    {
        $request = new Request();
        $request->setMethod('POST');
        $request->request->set('mangaId', '1');

        $response = $this->controller->uploadCover($request, $this->entityManager);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testUploadCoverInvalidMangaId(): void
    {
        $request = new Request();
        $request->setMethod('POST');
        $request->request->set('mangaId', 'invalid');

        $response = $this->controller->uploadCover($request, $this->entityManager);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testUploadCoverMissingMangaId(): void
    {
        $file = $this->createMock(UploadedFile::class);
        $request = new Request();
        $request->setMethod('POST');
        $request->request->set('mangaId', '');
        $request->files->set('cover', $file);

        $response = $this->controller->uploadCover($request, $this->entityManager);

        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Valid mangaId is required', $data['error']);
    }

    public function testUploadCoverMangaNotFound(): void
    {
        $this->mangaRepository
            ->expects($this->once())
            ->method('find')
            ->with('999')
            ->willReturn(null);

        $file = $this->createMock(UploadedFile::class);
        $request = new Request();
        $request->setMethod('POST');
        $request->request->set('mangaId', '999');
        $request->files->set('cover', $file);

        $response = $this->controller->uploadCover($request, $this->entityManager);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testUploadChapterPagesSuccess(): void
    {
        $this->chapterRepository
            ->expects($this->once())
            ->method('find')
            ->with('1')
            ->willReturn($this->chapter);

        $this->uploadValidator
            ->method('validateMultipleImages')
            ->willReturn([]);

        $this->storageService
            ->method('storeChapterPages')
            ->willReturn(['/uploads/chapters/1/1.jpg', '/uploads/chapters/1/2.jpg']);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $file1 = $this->createMock(UploadedFile::class);
        $file2 = $this->createMock(UploadedFile::class);

        $request = new Request();
        $request->setMethod('POST');
        $request->files->set('pages', [$file1, $file2]);
        $request->attributes->set('id', '1');

        $response = $this->controller->uploadChapterPages('1', $request, $this->entityManager);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testUploadChapterPagesNoFiles(): void
    {
        $request = new Request();
        $request->setMethod('POST');
        $request->attributes->set('id', '1');

        $response = $this->controller->uploadChapterPages('1', $request, $this->entityManager);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testUploadChapterNotFound(): void
    {
        $this->chapterRepository
            ->expects($this->once())
            ->method('find')
            ->with('999')
            ->willReturn(null);

        $file = $this->createMock(UploadedFile::class);
        $request = new Request();
        $request->setMethod('POST');
        $request->attributes->set('id', '999');
        $request->files->set('pages', [$file]);

        $response = $this->controller->uploadChapterPages('999', $request, $this->entityManager);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testUploadCoverWithInvalidMangaIdType(): void
    {
        $request = new Request();
        $request->setMethod('POST');
        $request->request->set('mangaId', 'invalid');
        $request->files->set('cover', $this->createMock(UploadedFile::class));

        $response = $this->controller->uploadCover($request, $this->entityManager);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testUploadCoverValidationViolation(): void
    {
        $this->mangaRepository
            ->expects($this->once())
            ->method('find')
            ->with('1')
            ->willReturn($this->manga);

        $violation = $this->createMock(ConstraintViolationInterface::class);
        $violation->method('getMessage')->willReturn('File too large');

        $this->uploadValidator
            ->method('validateImage')
            ->willReturn(new ConstraintViolationList([$violation]));

        $file = $this->createMock(UploadedFile::class);
        $request = new Request();
        $request->setMethod('POST');
        $request->request->set('mangaId', '1');
        $request->files->set('cover', $file);

        $response = $this->controller->uploadCover($request, $this->entityManager);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testUploadCoverMangaWithNullId(): void
    {
        $mangaWithNullId = $this->createMock(Manga::class);
        $mangaWithNullId->method('getId')->willReturn(null);

        $this->mangaRepository
            ->expects($this->once())
            ->method('find')
            ->with('1')
            ->willReturn($mangaWithNullId);

        $this->uploadValidator
            ->method('validateImage')
            ->willReturn(new ConstraintViolationList());

        $this->storageService
            ->method('storeCover')
            ->willReturn('/uploads/covers/1/cover.jpg');

        $file = $this->createMock(UploadedFile::class);
        $request = new Request();
        $request->setMethod('POST');
        $request->request->set('mangaId', '1');
        $request->files->set('cover', $file);

        $response = $this->controller->uploadCover($request, $this->entityManager);

        $this->assertEquals(500, $response->getStatusCode());
    }

    public function testUploadCoverFlushFails(): void
    {
        $this->mangaRepository
            ->expects($this->once())
            ->method('find')
            ->with('1')
            ->willReturn($this->manga);

        $this->uploadValidator
            ->method('validateImage')
            ->willReturn(new ConstraintViolationList());

        $this->storageService
            ->method('storeCover')
            ->willReturn('/uploads/covers/1/cover.jpg');

        $this->storageService
            ->expects($this->once())
            ->method('deleteFile')
            ->with('/uploads/covers/1/cover.jpg');

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(CoverArt::class));

        $this->entityManager
            ->method('flush')
            ->willThrowException(new \Exception('Database error'));

        $file = $this->createMock(UploadedFile::class);
        $request = new Request();
        $request->setMethod('POST');
        $request->request->set('mangaId', '1');
        $request->files->set('cover', $file);

        // Use try/catch so PCOV records the catch block execution
        try {
            $this->controller->uploadCover($request, $this->entityManager);
            $this->fail('Expected exception was not thrown');
        } catch (\Exception $e) {
            $this->assertEquals('Database error', $e->getMessage());
        }
    }

    public function testUploadChapterPagesValidationErrors(): void
    {
        $this->chapterRepository
            ->expects($this->once())
            ->method('find')
            ->with('1')
            ->willReturn($this->chapter);

        $this->uploadValidator
            ->method('validateMultipleImages')
            ->willReturn(['Uploaded file exceeds maximum allowed size']);

        $file = $this->createMock(UploadedFile::class);
        $request = new Request();
        $request->setMethod('POST');
        $request->files->set('pages', [$file]);
        $request->attributes->set('id', '1');

        $response = $this->controller->uploadChapterPages('1', $request, $this->entityManager);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testUploadChapterPagesWithNullId(): void
    {
        $chapterWithNullId = $this->createMock(Chapter::class);
        $chapterWithNullId->method('getId')->willReturn(null);

        $this->chapterRepository
            ->expects($this->once())
            ->method('find')
            ->with('1')
            ->willReturn($chapterWithNullId);

        $this->uploadValidator
            ->method('validateMultipleImages')
            ->willReturn([]);

        $this->storageService
            ->method('storeChapterPages')
            ->willReturn(['/uploads/chapters/1/1.jpg']);

        $file = $this->createMock(UploadedFile::class);
        $request = new Request();
        $request->setMethod('POST');
        $request->files->set('pages', [$file]);
        $request->attributes->set('id', '1');

        $response = $this->controller->uploadChapterPages('1', $request, $this->entityManager);

        $this->assertEquals(500, $response->getStatusCode());
    }

    public function testUploadChapterPagesFlushFails(): void
    {
        $this->chapterRepository
            ->expects($this->once())
            ->method('find')
            ->with('1')
            ->willReturn($this->chapter);

        $this->uploadValidator
            ->method('validateMultipleImages')
            ->willReturn([]);

        $this->storageService
            ->method('storeChapterPages')
            ->willReturn(['/uploads/chapters/1/1.jpg']);

        $this->storageService
            ->expects($this->once())
            ->method('deleteFiles')
            ->with(['/uploads/chapters/1/1.jpg']);

        $this->entityManager
            ->method('flush')
            ->willThrowException(new \Exception('Database error'));

        $file = $this->createMock(UploadedFile::class);
        $request = new Request();
        $request->setMethod('POST');
        $request->files->set('pages', [$file]);
        $request->attributes->set('id', '1');

        // Use try/catch so PCOV records the catch block execution
        try {
            $this->controller->uploadChapterPages('1', $request, $this->entityManager);
            $this->fail('Expected exception was not thrown');
        } catch (\Exception $e) {
            $this->assertEquals('Database error', $e->getMessage());
        }
    }
}
