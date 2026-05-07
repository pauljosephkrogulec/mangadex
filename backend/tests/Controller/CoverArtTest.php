<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\CoverArt;
use App\Entity\Manga;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CoverArtTest extends WebTestCase
{
    private string $uploadsDir;
    private ?Manga $testManga = null;
    private ?CoverArt $testCover = null;
    private ?CoverArt $testPrimaryCover = null;
    /** @var array<string> */
    private array $createdFiles = [];

    protected function setUp(): void
    {
        // Will be initialized when we have access to the container
    }

    private function getUploadsDir(): string
    {
        if (! isset($this->uploadsDir)) {
            $this->uploadsDir = static::getContainer()->getParameter('kernel.project_dir') . '/public/uploads/covers';
        }

        if (! is_dir($this->uploadsDir)) {
            mkdir($this->uploadsDir, 0755, true);
        }

        return $this->uploadsDir;
    }

    public function testServeCoverReturnsImage(): void
    {
        $client = static::createClient();
        $this->createTestCover();

        $client->request('GET', '/api/covers/' . $this->testCover->getId());

        $this->assertResponseStatusCodeSame(200);
        $this->assertStringContainsString('image/', $client->getResponse()->headers->get('Content-Type') ?? '');
        $cacheControl = $client->getResponse()->headers->get('Cache-Control') ?? '';
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=2592000', $cacheControl);
    }

    public function testServeCoverNotFound(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/covers/999999');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testServeCoverFileMissing(): void
    {
        $client = static::createClient();
        $this->createTestCover();

        // Delete the file to simulate missing file
        $fullPath = $this->getUploadsDir() . '/test_cover.jpg';
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }

        $client->request('GET', '/api/covers/' . $this->testCover->getId());

        $this->assertResponseStatusCodeSame(404);
    }

    public function testServePrimaryCover(): void
    {
        $client = static::createClient();
        $this->createTestPrimaryCover();

        $client->request('GET', '/api/mangas/' . $this->testManga->getId() . '/primary-cover');

        $this->assertResponseStatusCodeSame(200);
        $this->assertStringContainsString('image/', $client->getResponse()->headers->get('Content-Type') ?? '');
        $cacheControl = $client->getResponse()->headers->get('Cache-Control') ?? '';
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=2592000', $cacheControl);
    }

    public function testServePrimaryCoverNoPrimary(): void
    {
        $client = static::createClient();
        $this->createTestManga();
        // Don't create a primary cover

        $client->request('GET', '/api/mangas/' . $this->testManga->getId() . '/primary-cover');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testServePrimaryCoverMangaNotFound(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/mangas/999999/primary-cover');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testCoverCacheHeaders(): void
    {
        $client = static::createClient();
        $this->createTestCover();

        $client->request('GET', '/api/covers/' . $this->testCover->getId());

        $this->assertResponseStatusCodeSame(200);

        $response = $client->getResponse();
        $cacheControl = $response->headers->get('Cache-Control') ?? '';
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=2592000', $cacheControl);
        $this->assertStringContainsString('immutable', $cacheControl);

        $expires = $response->headers->get('Expires');
        $this->assertNotNull($expires);
    }

    private function createTestManga(): void
    {
        if ($this->testManga !== null) {
            return;
        }

        $entityManager = $this->getEntityManager();

        $manga = new Manga();
        $manga->setTitle('Cover Art Test Manga');
        $manga->setStatus('ongoing');
        $manga->setContentRating('safe');

        $entityManager->persist($manga);
        $entityManager->flush();

        $this->testManga = $manga;
    }

    private function createTestCover(): void
    {
        if ($this->testCover !== null) {
            return;
        }

        $this->createTestManga();
        $entityManager = $this->getEntityManager();
        $uploadsDir = $this->getUploadsDir();

        // Create a test image file (minimal JPEG)
        $filename = 'test_cover.jpg';
        $fullPath = $uploadsDir . '/' . $filename;
        $this->createTestImage($fullPath);
        $this->createdFiles[] = $fullPath;

        $coverArt = new CoverArt();
        $coverArt->setManga($this->testManga);
        // Use a relative path that the controller can resolve
        $coverArt->setImagePath('/uploads/covers/test_cover.jpg');
        $coverArt->setIsPrimary(false);

        $entityManager->persist($coverArt);
        $entityManager->flush();

        $this->testCover = $coverArt;
    }

    private function createTestPrimaryCover(): void
    {
        if ($this->testPrimaryCover !== null) {
            return;
        }

        $this->createTestManga();
        $entityManager = $this->getEntityManager();
        $uploadsDir = $this->getUploadsDir();

        // Create a test image file
        $filename = 'test_primary_cover.jpg';
        $fullPath = $uploadsDir . '/' . $filename;
        $this->createTestImage($fullPath);
        $this->createdFiles[] = $fullPath;

        $coverArt = new CoverArt();
        $coverArt->setManga($this->testManga);
        $coverArt->setImagePath('/uploads/covers/test_primary_cover.jpg');
        $coverArt->setIsPrimary(true);

        $entityManager->persist($coverArt);
        $entityManager->flush();

        $this->testPrimaryCover = $coverArt;
    }

    private function createTestImage(string $path): void
    {
        // Create a minimal valid JPEG file without GD extension
        // This is a minimal JPEG file (JFIF structure)
        $jpegData = base64_decode(
            '/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0a' .
            'HBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIy' .
            'MjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAABAAE' .
            'DASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QA' .
            'FAEBAAAAAAAAAAAAAAAAAAAAAP/EABQRAQAAAAAAAAAAAAAAAAAAAAD/2gAMAwEAAhEDEQA/AL+AAwAA'
        );

        if ($jpegData === false) {
            // Fallback: write a minimal file with .jpg extension
            $jpegData = "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00\xFF\xD9";
        }

        file_put_contents($path, $jpegData);
    }

    private function getEntityManager(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    protected function tearDown(): void
    {
        // Clean up files
        foreach ($this->createdFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }

        // Clean up database entities
        $entityManager = $this->getEntityManager();

        if ($this->testCover) {
            $entityManager->remove($this->testCover);
        }
        if ($this->testPrimaryCover) {
            $entityManager->remove($this->testPrimaryCover);
        }
        if ($this->testManga) {
            $entityManager->remove($this->testManga);
        }

        $entityManager->flush();

        parent::tearDown();
    }
}
