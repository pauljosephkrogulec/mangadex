<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\CoverArtController;
use App\Entity\CoverArt;
use App\Entity\Manga;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[AllowMockObjectsWithoutExpectations]
class CoverArtControllerTest extends TestCase
{
    private CoverArtController $controller;
    private EntityManagerInterface $entityManager;
    private EntityRepository $coverArtRepository;
    private EntityRepository $mangaRepository;

    protected function setUp(): void
    {
        $this->coverArtRepository = $this->createMock(EntityRepository::class);
        $this->mangaRepository = $this->createMock(EntityRepository::class);

        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->entityManager
            ->method('getRepository')
            ->willReturnCallback(fn ($class) => match ($class) {
                CoverArt::class => $this->coverArtRepository,
                Manga::class => $this->mangaRepository,
                default => null,
            });

        $this->controller = new CoverArtController();

        $projectDir = dirname(__DIR__, 2);
        $parameterBag = new ParameterBag(['kernel.project_dir' => $projectDir]);

        $container = $this->createMock(ContainerInterface::class);
        $container
            ->method('has')
            ->willReturnCallback(fn ($id) => 'parameter_bag' === $id);
        $container
            ->method('get')
            ->willReturn($parameterBag);

        $reflection = new \ReflectionClass($this->controller);
        $reflection->getProperty('container')->setValue($this->controller, $container);
    }

    public function testServeCoverReturnsImage(): void
    {
        $projectDir = dirname(__DIR__, 2);
        $uploadsDir = $projectDir.'/public/uploads';
        $coversDir = $uploadsDir.'/covers';
        if (!is_dir($coversDir)) {
            mkdir($coversDir, 0o777, true);
        }
        $testFile = $coversDir.'/test_cover.jpg';
        file_put_contents($testFile, 'cover content');

        $coverArt = $this->createMock(CoverArt::class);
        $coverArt->method('getImagePath')->willReturn('/covers/test_cover.jpg');

        $this->coverArtRepository
            ->expects($this->once())
            ->method('find')
            ->with('cover-1')
            ->willReturn($coverArt);

        $response = $this->controller->serveCover('cover-1', $this->entityManager);

        $this->assertInstanceOf(BinaryFileResponse::class, $response);

        if (file_exists($testFile)) {
            unlink($testFile);
        }
    }

    public function testServeCoverNotFound(): void
    {
        $this->coverArtRepository
            ->expects($this->once())
            ->method('find')
            ->with('invalid-id')
            ->willReturn(null);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Cover art not found');

        $this->controller->serveCover('invalid-id', $this->entityManager);
    }

    public function testServeCoverFileNotFound(): void
    {
        $coverArt = $this->createMock(CoverArt::class);
        $coverArt->method('getImagePath')->willReturn('/covers/nonexistent.jpg');

        $this->coverArtRepository
            ->expects($this->once())
            ->method('find')
            ->with('cover-1')
            ->willReturn($coverArt);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Cover art file not found');

        $this->controller->serveCover('cover-1', $this->entityManager);
    }

    public function testServeCoverBlocksDirectoryTraversal(): void
    {
        $projectDir = dirname(__DIR__, 2);
        $traversalFile = $projectDir.'/test_cover_traversal.txt';
        file_put_contents($traversalFile, 'test');

        $coverArt = $this->createMock(CoverArt::class);
        $coverArt->method('getImagePath')->willReturn('/../../test_cover_traversal.txt');

        $this->coverArtRepository
            ->expects($this->once())
            ->method('find')
            ->with('cover-1')
            ->willReturn($coverArt);

        $this->expectException(NotFoundHttpException::class);

        $this->controller->serveCover('cover-1', $this->entityManager);

        if (file_exists($traversalFile)) {
            unlink($traversalFile);
        }
    }

    public function testServeCoverWithMimeType(): void
    {
        $projectDir = dirname(__DIR__, 2);
        $coversDir = $projectDir.'/public/uploads/covers';
        if (!is_dir($coversDir)) {
            mkdir($coversDir, 0o777, true);
        }
        $testFile = $coversDir.'/test_mime.png';
        file_put_contents($testFile, 'png content');

        $coverArt = $this->createMock(CoverArt::class);
        $coverArt->method('getImagePath')->willReturn('/covers/test_mime.png');

        $this->coverArtRepository
            ->expects($this->once())
            ->method('find')
            ->with('cover-1')
            ->willReturn($coverArt);

        $response = $this->controller->serveCover('cover-1', $this->entityManager);

        $this->assertEquals('image/png', $response->headers->get('Content-Type'));

        if (file_exists($testFile)) {
            unlink($testFile);
        }
    }

    public function testServeCoverWithUnknownExtension(): void
    {
        $projectDir = dirname(__DIR__, 2);
        $coversDir = $projectDir.'/public/uploads/covers';
        if (!is_dir($coversDir)) {
            mkdir($coversDir, 0o777, true);
        }
        $testFile = $coversDir.'/test_unknown.bin';
        file_put_contents($testFile, 'bin content');

        $coverArt = $this->createMock(CoverArt::class);
        $coverArt->method('getImagePath')->willReturn('/covers/test_unknown.bin');

        $this->coverArtRepository
            ->expects($this->once())
            ->method('find')
            ->with('cover-1')
            ->willReturn($coverArt);

        $response = $this->controller->serveCover('cover-1', $this->entityManager);

        $this->assertNull($response->headers->get('Content-Type'));

        if (file_exists($testFile)) {
            unlink($testFile);
        }
    }

    public function testServePrimaryCoverReturnsImage(): void
    {
        $projectDir = dirname(__DIR__, 2);
        $coversDir = $projectDir.'/public/uploads/covers';
        if (!is_dir($coversDir)) {
            mkdir($coversDir, 0o777, true);
        }
        $testFile = $coversDir.'/primary_cover.jpg';
        file_put_contents($testFile, 'primary cover');

        $manga = $this->createMock(Manga::class);
        $this->mangaRepository
            ->expects($this->once())
            ->method('find')
            ->with('manga-1')
            ->willReturn($manga);

        $coverArt = $this->createMock(CoverArt::class);
        $coverArt->method('getImagePath')->willReturn('/covers/primary_cover.jpg');

        $this->coverArtRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['manga' => $manga, 'isPrimary' => true])
            ->willReturn($coverArt);

        $response = $this->controller->servePrimaryCover('manga-1', $this->entityManager);

        $this->assertInstanceOf(BinaryFileResponse::class, $response);

        if (file_exists($testFile)) {
            unlink($testFile);
        }
    }

    public function testServePrimaryCoverMangaNotFound(): void
    {
        $this->mangaRepository
            ->expects($this->once())
            ->method('find')
            ->with('invalid-manga')
            ->willReturn(null);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Manga not found');

        $this->controller->servePrimaryCover('invalid-manga', $this->entityManager);
    }

    public function testServePrimaryCoverNoPrimaryCover(): void
    {
        $manga = $this->createMock(Manga::class);
        $this->mangaRepository
            ->expects($this->once())
            ->method('find')
            ->with('manga-1')
            ->willReturn($manga);

        $this->coverArtRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['manga' => $manga, 'isPrimary' => true])
            ->willReturn(null);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Primary cover not found for this manga');

        $this->controller->servePrimaryCover('manga-1', $this->entityManager);
    }

    public function testServePrimaryCoverFileNotFound(): void
    {
        $manga = $this->createMock(Manga::class);
        $this->mangaRepository
            ->expects($this->once())
            ->method('find')
            ->with('manga-1')
            ->willReturn($manga);

        $coverArt = $this->createMock(CoverArt::class);
        $coverArt->method('getImagePath')->willReturn('/covers/nonexistent_primary.jpg');

        $this->coverArtRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['manga' => $manga, 'isPrimary' => true])
            ->willReturn($coverArt);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Cover art file not found');

        $this->controller->servePrimaryCover('manga-1', $this->entityManager);
    }

    public function testServePrimaryCoverBlocksDirectoryTraversal(): void
    {
        $projectDir = dirname(__DIR__, 2);
        $traversalFile = $projectDir.'/test_primary_traversal.txt';
        file_put_contents($traversalFile, 'test');

        $manga = $this->createMock(Manga::class);
        $this->mangaRepository
            ->expects($this->once())
            ->method('find')
            ->with('manga-1')
            ->willReturn($manga);

        $coverArt = $this->createMock(CoverArt::class);
        $coverArt->method('getImagePath')->willReturn('/../../test_primary_traversal.txt');

        $this->coverArtRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['manga' => $manga, 'isPrimary' => true])
            ->willReturn($coverArt);

        $this->expectException(NotFoundHttpException::class);

        $this->controller->servePrimaryCover('manga-1', $this->entityManager);

        if (file_exists($traversalFile)) {
            unlink($traversalFile);
        }
    }
}
