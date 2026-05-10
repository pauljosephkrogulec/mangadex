<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\FileStorageService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class FileStorageServiceTest extends TestCase
{
    private FileStorageService $service;
    private string $publicDir;

    protected function setUp(): void
    {
        $this->service = new FileStorageService();
        $this->publicDir = dirname(__DIR__, 2) . '/public';
    }

    public function testStoreCover(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_cover_');
        file_put_contents($tmpFile, 'fake image content');

        $file = new UploadedFile($tmpFile, 'cover.jpg', 'image/jpeg', null, true);

        $result = $this->service->storeCover($file, '123', '1');

        $this->assertStringStartsWith('/covers/', $result);
        $this->assertStringContainsString('manga_123', $result);

        // Cleanup
        $fullPath = $this->publicDir . $result;
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }
    }

    public function testStoreCoverWithNullVolume(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_cover_');
        file_put_contents($tmpFile, 'fake image content');

        $file = new UploadedFile($tmpFile, 'cover.jpg', 'image/jpeg', null, true);

        $result = $this->service->storeCover($file, '456', null);

        $this->assertStringStartsWith('/covers/', $result);
        $this->assertStringContainsString('manga_456', $result);
        $this->assertStringContainsString('vol_default', $result);

        // Cleanup
        $fullPath = $this->publicDir . $result;
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }
    }

    public function testStoreChapterPages(): void
    {
        $files = [];
        for ($i = 0; $i < 3; $i++) {
            $tmpFile = tempnam(sys_get_temp_dir(), 'test_page_');
            file_put_contents($tmpFile, 'fake page content');
            $files[] = new UploadedFile($tmpFile, "page{$i}.jpg", 'image/jpeg', null, true);
        }

        $result = $this->service->storeChapterPages($files, '789');

        $this->assertCount(3, $result);
        $this->assertStringStartsWith('/chapters/789/', $result[0]);
        $this->assertStringContainsString('page_001', $result[0]);

        // Cleanup
        foreach ($result as $path) {
            $fullPath = $this->publicDir . $path;
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
        }
        $chapterDir = $this->publicDir . '/uploads/chapters/789';
        if (is_dir($chapterDir)) {
            array_map('unlink', glob($chapterDir . '/*'));
            rmdir($chapterDir);
        }
    }

    public function testDeleteFile(): void
    {
        $uploadsDir = $this->publicDir . '/uploads';
        if (! is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0755, true);
        }
        $testFile = $uploadsDir . '/test_file.txt';
        file_put_contents($testFile, 'test content');

        $this->assertFileExists($testFile);
        $this->service->deleteFile('/test_file.txt');
        $this->assertFileDoesNotExist($testFile);
    }

    public function testDeleteFileNonExistent(): void
    {
        $this->service->deleteFile('/uploads/nonexistent.txt');
        $this->assertTrue(true);
    }

    public function testDeleteFiles(): void
    {
        $uploadsDir = $this->publicDir . '/uploads';
        if (! is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0755, true);
        }

        $files = [];
        for ($i = 0; $i < 3; $i++) {
            $testFile = $uploadsDir . "/test_{$i}.txt";
            file_put_contents($testFile, 'test');
            $files[] = "/test_{$i}.txt";
        }

        $this->service->deleteFiles($files);

        foreach ($files as $file) {
            $this->assertFileDoesNotExist($uploadsDir . '/' . basename($file));
        }
    }

    public function testGenerateCoverFilename(): void
    {
        $reflection = new \ReflectionClass(FileStorageService::class);
        $method = $reflection->getMethod('generateCoverFilename');

        $tmpFile = tempnam(sys_get_temp_dir(), 'test_');
        // Write minimal JPEG header
        file_put_contents($tmpFile, base64_decode('/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjLwAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QFAEBAAAAAAAAAAAAAAAAAAAAAP/EABQRAQAAAAAAAAAAAAAAAAAAAAD/2gAMAwEAAhEDEQA/AL+AAwAA'));

        $file = new UploadedFile($tmpFile, 'test.jpg', 'image/jpeg', null, true);

        $result = $method->invoke($this->service, $file, '999', '5');

        $this->assertStringContainsString('manga_999', $result);
        $this->assertStringContainsString('vol_5', $result);
        $this->assertStringEndsWith('.jpg', $result);
    }

    public function testGenerateCoverFilenameWithNullVolume(): void
    {
        $reflection = new \ReflectionClass(FileStorageService::class);
        $method = $reflection->getMethod('generateCoverFilename');

        $tmpFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tmpFile, 'fake image');

        $file = new UploadedFile($tmpFile, 'test.jpg', 'image/jpeg', null, true);

        $result = $method->invoke($this->service, $file, '111', null);

        $this->assertStringContainsString('manga_111', $result);
        $this->assertStringContainsString('vol_default', $result);
    }

    public function testGenerateCoverFilenameWithEmptyVolumeAfterSanitization(): void
    {
        $reflection = new \ReflectionClass(FileStorageService::class);
        $method = $reflection->getMethod('generateCoverFilename');

        $tmpFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tmpFile, 'fake image');

        $file = new UploadedFile($tmpFile, 'test.jpg', 'image/jpeg', null, true);

        // Volume consisting only of characters that get removed by preg_replace
        // should fall back to 'default' when the result is an empty string
        $result = $method->invoke($this->service, $file, '222', '!!!');

        $this->assertStringContainsString('manga_222', $result);
        $this->assertStringContainsString('vol_default', $result);
    }
}
