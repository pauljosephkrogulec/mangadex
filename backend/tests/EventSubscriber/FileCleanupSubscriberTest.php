<?php

declare(strict_types=1);

namespace App\Tests\EventSubscriber;

use App\Entity\Chapter;
use App\Entity\CoverArt;
use App\EventSubscriber\FileCleanupSubscriber;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class FileCleanupSubscriberTest extends TestCase
{
    private FileCleanupSubscriber $subscriber;
    private $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = $this->createMock(\Symfony\Component\Filesystem\Filesystem::class);
        $this->subscriber = new FileCleanupSubscriber($this->filesystem);
    }

    public function testGetSubscribedEvents(): void
    {
        $events = $this->subscriber->getSubscribedEvents();

        $this->assertIsArray($events);
        $this->assertContains('postRemove', $events);
    }

    public function testPostRemoveWithCoverArt(): void
    {
        $coverArt = new CoverArt();
        $coverArt->setImagePath('/uploads/covers/1/cover.jpg');

        $args = $this->createMock(LifecycleEventArgs::class);
        $args->method('getObject')->willReturn($coverArt);

        // The file doesn't exist in test env, so remove() won't be called
        // Just verify no exception is thrown
        $this->subscriber->postRemove($args);
        $this->assertTrue(true);
    }

    public function testPostRemoveWithCoverArtEmptyPath(): void
    {
        $coverArt = new CoverArt();
        $coverArt->setImagePath('');

        $args = $this->createMock(LifecycleEventArgs::class);
        $args->method('getObject')->willReturn($coverArt);

        $this->filesystem
            ->expects($this->never())
            ->method('remove');

        $this->subscriber->postRemove($args);
    }

    public function testPostRemoveWithChapter(): void
    {
        $chapter = new Chapter();
        $chapter->setPages([
            '/uploads/chapters/1/1.jpg',
            '/uploads/chapters/1/2.jpg',
        ]);

        $args = $this->createMock(LifecycleEventArgs::class);
        $args->method('getObject')->willReturn($chapter);

        // Files don't exist in test env
        $this->subscriber->postRemove($args);
        $this->assertTrue(true);
    }

    public function testPostRemoveWithChapterEmptyPages(): void
    {
        $chapter = new Chapter();
        $chapter->setPages([]);

        $args = $this->createMock(LifecycleEventArgs::class);
        $args->method('getObject')->willReturn($chapter);

        $this->filesystem
            ->expects($this->never())
            ->method('remove');

        $this->subscriber->postRemove($args);
    }

    public function testPostRemoveWithUnsupportedEntity(): void
    {
        $entity = new \stdClass();

        $args = $this->createMock(LifecycleEventArgs::class);
        $args->method('getObject')->willReturn($entity);

        $this->filesystem
            ->expects($this->never())
            ->method('remove');

        $this->subscriber->postRemove($args);
    }

    public function testPostRemoveHandlesFilesystemExceptionForCoverArt(): void
    {
        $publicDir = dirname(__DIR__, 2).'/public';
        $uploadsDir = $publicDir.'/uploads/covers';
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0o777, true);
        }
        $testFile = $uploadsDir.'/cover_exception.jpg';
        file_put_contents($testFile, 'test content');

        $coverArt = new CoverArt();
        $coverArt->setImagePath('/uploads/covers/cover_exception.jpg');

        $args = $this->createMock(LifecycleEventArgs::class);
        $args->method('getObject')->willReturn($coverArt);

        $this->filesystem
            ->method('remove')
            ->willThrowException(new \Symfony\Component\Filesystem\Exception\IOException('Test exception'));

        // Should not throw exception
        $this->subscriber->postRemove($args);

        if (file_exists($testFile)) {
            unlink($testFile);
        }

        $this->assertTrue(true);
    }

    public function testPostRemoveHandlesFilesystemExceptionForChapter(): void
    {
        $publicDir = dirname(__DIR__, 2).'/public';
        $testFile = $publicDir.'/uploads/chapters/exception_test/1.jpg';
        $uploadsDir = dirname($testFile);
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0o777, true);
        }
        file_put_contents($testFile, 'test content');

        $chapter = new Chapter();
        $chapter->setPages(['/uploads/chapters/exception_test/1.jpg']);

        $args = $this->createMock(LifecycleEventArgs::class);
        $args->method('getObject')->willReturn($chapter);

        $this->filesystem
            ->method('remove')
            ->willThrowException(new \Symfony\Component\Filesystem\Exception\IOException('Test exception'));

        // Should not throw exception and continue with other files
        $this->subscriber->postRemove($args);

        if (file_exists($testFile)) {
            unlink($testFile);
        }

        $this->assertTrue(true);
    }

    public function testGetFullPathWithInvalidPath(): void
    {
        $coverArt = new CoverArt();
        $coverArt->setImagePath('/other/path/cover.jpg'); // Doesn't start with /uploads/

        $args = $this->createMock(LifecycleEventArgs::class);
        $args->method('getObject')->willReturn($coverArt);

        $this->filesystem
            ->expects($this->never())
            ->method('remove');

        $this->subscriber->postRemove($args);
    }

    public function testPostRemoveWithNullPages(): void
    {
        $chapter = new Chapter();
        // Don't set pages, so it's null

        $args = $this->createMock(LifecycleEventArgs::class);
        $args->method('getObject')->willReturn($chapter);

        $this->filesystem
            ->expects($this->never())
            ->method('remove');

        $this->subscriber->postRemove($args);
    }

    public function testPostRemoveWithSingleChapterPage(): void
    {
        $chapter = new Chapter();
        $chapter->setPages(['/uploads/chapters/999/test.jpg']);

        $args = $this->createMock(LifecycleEventArgs::class);
        $args->method('getObject')->willReturn($chapter);

        // File doesn't exist
        $this->subscriber->postRemove($args);
        $this->assertTrue(true);
    }

    public function testDeleteCoverArtFileWithExistingFile(): void
    {
        // Create a temp file in the uploads directory
        $publicDir = dirname(__DIR__, 2).'/public';
        $uploadsDir = $publicDir.'/uploads/covers/test';
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0o777, true);
        }
        $testFile = $uploadsDir.'/cover.jpg';
        file_put_contents($testFile, 'test image content');

        // The getFullPath returns unresolved path like:
        // /app/src/EventSubscriber/../../public/uploads/covers/test/cover.jpg
        // PHP resolves this automatically in file_exists() and Filesystem::remove()
        $coverArt = new CoverArt();
        $coverArt->setImagePath('/uploads/covers/test/cover.jpg');

        $args = $this->createMock(LifecycleEventArgs::class);
        $args->method('getObject')->willReturn($coverArt);

        // Use callback to verify the path ends with the correct file
        $this->filesystem
            ->expects($this->once())
            ->method('remove')
            ->with($this->callback(function ($path) use ($testFile) {
                // The path should resolve to our test file
                $realPath = realpath($path);

                return $realPath === $testFile;
            }));

        $this->subscriber->postRemove($args);

        // Cleanup
        if (file_exists($testFile)) {
            unlink($testFile);
        }
        if (is_dir($uploadsDir)) {
            rmdir($uploadsDir);
        }
    }

    public function testDeleteChapterFilesWithExistingFiles(): void
    {
        // Create temp files in the uploads directory
        $publicDir = dirname(__DIR__, 2).'/public';
        $uploadsDir = $publicDir.'/uploads/chapters/test';
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0o777, true);
        }
        $testFile1 = $uploadsDir.'/1.jpg';
        $testFile2 = $uploadsDir.'/2.jpg';
        file_put_contents($testFile1, 'page 1');
        file_put_contents($testFile2, 'page 2');

        $chapter = new Chapter();
        $chapter->setPages([
            '/uploads/chapters/test/1.jpg',
            '/uploads/chapters/test/2.jpg',
        ]);

        $args = $this->createMock(LifecycleEventArgs::class);
        $args->method('getObject')->willReturn($chapter);

        // Use real filesystem but wrap it to track calls
        $realFilesystem = new \Symfony\Component\Filesystem\Filesystem();
        $removedPaths = [];

        // Create a partial mock that actually deletes files
        $this->filesystem = $this->getMockBuilder(\Symfony\Component\Filesystem\Filesystem::class)
            ->onlyMethods(['remove'])
            ->getMock();
        $this->filesystem
            ->method('remove')
            ->willReturnCallback(function ($path) use (&$removedPaths, $realFilesystem) {
                $removedPaths[] = $path;
                // Actually remove the file/directory
                if (file_exists($path)) {
                    $realFilesystem->remove($path);
                }
            });

        // Recreate subscriber with the partial mock
        $this->subscriber = new FileCleanupSubscriber($this->filesystem);

        $this->subscriber->postRemove($args);

        // Should have removed 2 files + 1 directory = 3 calls
        $this->assertCount(3, $removedPaths);

        // Cleanup (in case something failed)
        foreach ([$testFile1, $testFile2] as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        if (is_dir($uploadsDir)) {
            rmdir($uploadsDir);
        }
    }

    public function testDeleteChapterFilesWithNonEmptyDirectory(): void
    {
        // Create temp files - directory will NOT be empty after removing pages
        $uploadsDir = __DIR__.'/../../public/uploads/chapters/test2';
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0o777, true);
        }
        $testFile1 = $uploadsDir.'/1.jpg';
        $testFile2 = $uploadsDir.'/2.jpg';
        file_put_contents($testFile1, 'page 1');
        file_put_contents($testFile2, 'page 2');

        // Add a file that's NOT a page to keep directory non-empty
        $otherFile = $uploadsDir.'/other.txt';
        file_put_contents($otherFile, 'not a page');

        $chapter = new Chapter();
        $chapter->setPages([
            '/uploads/chapters/test2/1.jpg',
            '/uploads/chapters/test2/2.jpg',
        ]);

        $args = $this->createMock(LifecycleEventArgs::class);
        $args->method('getObject')->willReturn($chapter);

        // Should remove only the 2 page files, NOT the directory (because other.txt exists)
        $this->filesystem
            ->expects($this->exactly(2))
            ->method('remove');

        $this->subscriber->postRemove($args);

        // Cleanup
        foreach ([$testFile1, $testFile2, $otherFile] as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        if (is_dir($uploadsDir)) {
            rmdir($uploadsDir);
        }
    }

    public function testDeleteCoverArtFileWithIOException(): void
    {
        $publicDir = dirname(__DIR__, 2).'/public';
        $testFile = $publicDir.'/uploads/covers/test_cover_path.jpg';
        $uploadsDir = dirname($testFile);
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0o777, true);
        }
        file_put_contents($testFile, 'test content');

        $coverArt = new CoverArt();
        $coverArt->setImagePath('/uploads/covers/test_cover_path.jpg');

        $args = $this->createMock(LifecycleEventArgs::class);
        $args->method('getObject')->willReturn($coverArt);

        // Mock filesystem to throw IOException
        $this->filesystem
            ->method('remove')
            ->willThrowException(new \Symfony\Component\Filesystem\Exception\IOException('Test exception'));

        // Should not throw exception
        $this->subscriber->postRemove($args);

        if (file_exists($testFile)) {
            unlink($testFile);
        }

        $this->assertTrue(true);
    }

    public function testDeleteChapterFilesWithIOException(): void
    {
        $publicDir = dirname(__DIR__, 2).'/public';
        $testFile = $publicDir.'/uploads/chapters/io_test/1.jpg';
        $uploadsDir = dirname($testFile);
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0o777, true);
        }
        file_put_contents($testFile, 'test content');

        $chapter = new Chapter();
        $chapter->setPages(['/uploads/chapters/io_test/1.jpg']);

        $args = $this->createMock(LifecycleEventArgs::class);
        $args->method('getObject')->willReturn($chapter);

        // Mock filesystem to throw IOException
        $this->filesystem
            ->method('remove')
            ->willThrowException(new \Symfony\Component\Filesystem\Exception\IOException('Test exception'));

        // Should not throw exception and continue with other files
        $this->subscriber->postRemove($args);

        if (file_exists($testFile)) {
            unlink($testFile);
        }

        $this->assertTrue(true);
    }

    public function testGetFullPathWithEmptyPagePath(): void
    {
        $chapter = new Chapter();
        $chapter->setPages(['']);

        $args = $this->createMock(LifecycleEventArgs::class);
        $args->method('getObject')->willReturn($chapter);

        $this->filesystem
            ->expects($this->never())
            ->method('remove');

        $this->subscriber->postRemove($args);
    }

    public function testDeleteChapterFilesWithEmptyDirectory(): void
    {
        // Create temp files and then delete them to test directory cleanup
        $uploadsDir = __DIR__.'/../../public/uploads/chapters/test_empty';
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0o777, true);
        }

        $chapter = new Chapter();
        $chapter->setPages(['/uploads/chapters/test_empty/1.jpg']);

        // Create the file so it can be deleted
        $testFile = $uploadsDir.'/1.jpg';
        file_put_contents($testFile, 'page 1');

        $args = $this->createMock(LifecycleEventArgs::class);
        $args->method('getObject')->willReturn($chapter);

        // After deleting the file, the directory should be removed (if empty)
        // We need to mock filesystem to actually delete the file
        $realFilesystem = new \Symfony\Component\Filesystem\Filesystem();
        $this->filesystem = $this->getMockBuilder(\Symfony\Component\Filesystem\Filesystem::class)
            ->onlyMethods(['remove'])
            ->getMock();

        // Create a partial mock that actually deletes files
        $this->filesystem
            ->method('remove')
            ->willReturnCallback(function ($path) use ($realFilesystem) {
                if (file_exists($path)) {
                    $realFilesystem->remove($path);
                }
            });

        // Recreate subscriber with the partial mock
        $this->subscriber = new FileCleanupSubscriber($this->filesystem);

        $this->subscriber->postRemove($args);

        // The directory should be removed (since it's empty after deleting the file)
        $this->assertFileDoesNotExist($testFile);

        // Cleanup
        if (file_exists($testFile)) {
            unlink($testFile);
        }
        if (is_dir($uploadsDir)) {
            rmdir($uploadsDir);
        }
    }

    public function testDeleteChapterFilesWithIOExceptionOnDirectoryCleanup(): void
    {
        $publicDir = dirname(__DIR__, 2).'/public';
        $uploadsDir = $publicDir.'/uploads/chapters/dir_io_test';
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0o777, true);
        }
        $testFile = $uploadsDir.'/1.jpg';
        file_put_contents($testFile, 'page 1');

        $chapter = new Chapter();
        $chapter->setPages(['/uploads/chapters/dir_io_test/1.jpg']);

        $args = $this->createMock(LifecycleEventArgs::class);
        $args->method('getObject')->willReturn($chapter);

        $realFilesystem = new \Symfony\Component\Filesystem\Filesystem();
        $this->filesystem = $this->getMockBuilder(\Symfony\Component\Filesystem\Filesystem::class)
            ->onlyMethods(['remove'])
            ->getMock();

        $callCount = 0;
        $this->filesystem
            ->method('remove')
            ->willReturnCallback(function ($path) use ($realFilesystem, &$callCount) {
                ++$callCount;
                // First call: remove the file (succeeds)
                if (1 === $callCount) {
                    if (file_exists($path)) {
                        $realFilesystem->remove($path);
                    }

                    return;
                }
                // Second call: remove the directory (throws IOException)
                throw new \Symfony\Component\Filesystem\Exception\IOException('Cannot remove directory');
            });

        $this->subscriber = new FileCleanupSubscriber($this->filesystem);

        // Should not throw - the IOException on directory cleanup should be caught
        $this->subscriber->postRemove($args);

        // Cleanup
        if (file_exists($testFile)) {
            unlink($testFile);
        }
        if (is_dir($uploadsDir)) {
            rmdir($uploadsDir);
        }

        $this->assertTrue(true);
    }
}
