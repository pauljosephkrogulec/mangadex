<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\ChapterPageController;
use App\Entity\Chapter;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[AllowMockObjectsWithoutExpectations]
class ChapterPageControllerTest extends TestCase
{
    private ChapterPageController $controller;
    private EntityManagerInterface $entityManager;
    private EntityRepository $chapterRepository;

    protected function setUp(): void
    {
        $this->chapterRepository = $this->createMock(EntityRepository::class);

        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->entityManager
            ->method('getRepository')
            ->with(Chapter::class)
            ->willReturn($this->chapterRepository);

        $this->controller = new ChapterPageController();

        $projectDir = dirname(__DIR__, 2);
        $parameterBag = new ParameterBag(['kernel.project_dir' => $projectDir]);

        $container = $this->createMock(ContainerInterface::class);
        $container
            ->method('has')
            ->willReturnCallback(fn ($id) => 'parameter_bag' === $id);
        $container
            ->method('get')
            ->with('parameter_bag')
            ->willReturn($parameterBag);

        $reflection = new \ReflectionClass($this->controller);
        $reflection->getProperty('container')->setValue($this->controller, $container);
    }

    public function testServePageReturnsImage(): void
    {
        $projectDir = dirname(__DIR__, 2);
        $uploadsDir = $projectDir.'/public/uploads';
        $chapterDir = $uploadsDir.'/chapters/test_serve';
        if (!is_dir($chapterDir)) {
            mkdir($chapterDir, 0o777, true);
        }
        $testFile = $chapterDir.'/1.jpg';
        file_put_contents($testFile, 'test image content');

        $chapter = $this->createMock(Chapter::class);
        $chapter
            ->method('getPages')
            ->willReturn(['/chapters/test_serve/1.jpg']);

        $this->chapterRepository
            ->expects($this->once())
            ->method('find')
            ->with('chapter-1')
            ->willReturn($chapter);

        $response = $this->controller->servePage('chapter-1', 1, $this->entityManager);

        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        if (file_exists($testFile)) {
            unlink($testFile);
        }
        if (is_dir($chapterDir)) {
            rmdir($chapterDir);
        }
    }

    public function testServePageChapterNotFound(): void
    {
        $this->chapterRepository
            ->expects($this->once())
            ->method('find')
            ->with('invalid-id')
            ->willReturn(null);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Chapter not found');

        $this->controller->servePage('invalid-id', 1, $this->entityManager);
    }

    public function testServePageNumberOutOfRange(): void
    {
        $chapter = $this->createMock(Chapter::class);
        $chapter
            ->method('getPages')
            ->willReturn(['/chapters/1/1.jpg']);

        $this->chapterRepository
            ->expects($this->once())
            ->method('find')
            ->with('chapter-1')
            ->willReturn($chapter);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Page number out of range');

        $this->controller->servePage('chapter-1', 999, $this->entityManager);
    }

    public function testServePageNegativePageNumber(): void
    {
        $chapter = $this->createMock(Chapter::class);
        $chapter
            ->method('getPages')
            ->willReturn(['/chapters/1/1.jpg']);

        $this->chapterRepository
            ->expects($this->once())
            ->method('find')
            ->with('chapter-1')
            ->willReturn($chapter);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Page number out of range');
        $this->controller->servePage('chapter-1', 0, $this->entityManager);
    }

    public function testServePageFileNotFound(): void
    {
        $chapter = $this->createMock(Chapter::class);
        $chapter
            ->method('getPages')
            ->willReturn(['/chapters/nonexistent/1.jpg']);

        $this->chapterRepository
            ->expects($this->once())
            ->method('find')
            ->with('chapter-1')
            ->willReturn($chapter);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Page file not found');

        $this->controller->servePage('chapter-1', 1, $this->entityManager);
    }

    public function testServePageSetsCacheHeaders(): void
    {
        $projectDir = dirname(__DIR__, 2);
        $uploadsDir = $projectDir.'/public/uploads';
        $chapterDir = $uploadsDir.'/chapters/cache_test';
        if (!is_dir($chapterDir)) {
            mkdir($chapterDir, 0o777, true);
        }
        $testFile = $chapterDir.'/1.jpg';
        file_put_contents($testFile, 'cache test content');

        $chapter = $this->createMock(Chapter::class);
        $chapter
            ->method('getPages')
            ->willReturn(['/chapters/cache_test/1.jpg']);

        $this->chapterRepository
            ->method('find')
            ->with('chapter-1')
            ->willReturn($chapter);

        $response = $this->controller->servePage('chapter-1', 1, $this->entityManager);

        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $cacheControl = $response->headers->get('Cache-Control') ?? '';
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=2592000', $cacheControl);
        $this->assertStringContainsString('immutable', $cacheControl);
        $this->assertNotNull($response->headers->get('Expires'));

        if (file_exists($testFile)) {
            unlink($testFile);
        }
        if (is_dir($chapterDir)) {
            rmdir($chapterDir);
        }
    }

    public function testServePageWithMultiplePages(): void
    {
        $projectDir = dirname(__DIR__, 2);
        $chapterDir = $projectDir.'/public/uploads/chapters/multi_test';
        if (!is_dir($chapterDir)) {
            mkdir($chapterDir, 0o777, true);
        }
        $testFile1 = $chapterDir.'/1.jpg';
        $testFile2 = $chapterDir.'/2.jpg';
        $testFile3 = $chapterDir.'/3.jpg';
        file_put_contents($testFile1, 'page 1');
        file_put_contents($testFile2, 'page 2');
        file_put_contents($testFile3, 'page 3');

        $chapter = $this->createMock(Chapter::class);
        $chapter
            ->method('getPages')
            ->willReturn([
                '/chapters/multi_test/1.jpg',
                '/chapters/multi_test/2.jpg',
                '/chapters/multi_test/3.jpg',
            ]);

        $this->chapterRepository
            ->method('find')
            ->with('chapter-1')
            ->willReturn($chapter);

        $response = $this->controller->servePage('chapter-1', 2, $this->entityManager);

        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        foreach ([$testFile1, $testFile2, $testFile3] as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        if (is_dir($chapterDir)) {
            rmdir($chapterDir);
        }
    }

    public function testResolveUploadPathBlocksDirectoryTraversal(): void
    {
        $projectDir = dirname(__DIR__, 2);
        $traversalFile = $projectDir.'/test_traversal.txt';
        file_put_contents($traversalFile, 'test');

        $chapter = $this->createMock(Chapter::class);
        $chapter
            ->method('getPages')
            ->willReturn(['/../../test_traversal.txt']);

        $this->chapterRepository
            ->method('find')
            ->with('chapter-1')
            ->willReturn($chapter);

        $this->expectException(NotFoundHttpException::class);

        $this->controller->servePage('chapter-1', 1, $this->entityManager);

        if (file_exists($traversalFile)) {
            unlink($traversalFile);
        }
    }
}
