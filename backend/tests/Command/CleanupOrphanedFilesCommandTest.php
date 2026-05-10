<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\CleanupOrphanedFilesCommand;
use App\Entity\Chapter;
use App\Entity\CoverArt;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

#[AllowMockObjectsWithoutExpectations]
class CleanupOrphanedFilesCommandTest extends TestCase
{
    private CleanupOrphanedFilesCommand $command;
    private EntityManagerInterface $entityManager;
    private EntityRepository $coverArtRepository;
    private EntityRepository $chapterRepository;
    private QueryBuilder $coverArtQueryBuilder;
    private QueryBuilder $chapterQueryBuilder;
    private Query $coverArtQuery;
    private Query $chapterQuery;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->coverArtRepository = $this->createMock(EntityRepository::class);
        $this->chapterRepository = $this->createMock(EntityRepository::class);
        $this->coverArtQueryBuilder = $this->createMock(QueryBuilder::class);
        $this->chapterQueryBuilder = $this->createMock(QueryBuilder::class);
        $this->coverArtQuery = $this->createMock(Query::class);
        $this->chapterQuery = $this->createMock(Query::class);

        $this->coverArtQueryBuilder->method('select')->willReturn($this->coverArtQueryBuilder);
        $this->coverArtQueryBuilder->method('getQuery')->willReturn($this->coverArtQuery);
        $this->chapterQueryBuilder->method('select')->willReturn($this->chapterQueryBuilder);
        $this->chapterQueryBuilder->method('getQuery')->willReturn($this->chapterQuery);

        $this->coverArtRepository
            ->method('createQueryBuilder')
            ->willReturn($this->coverArtQueryBuilder);
        $this->chapterRepository
            ->method('createQueryBuilder')
            ->willReturn($this->chapterQueryBuilder);

        $this->entityManager
            ->method('getRepository')
            ->willReturnCallback(function ($class) {
                return match($class) {
                    CoverArt::class => $this->coverArtRepository,
                    Chapter::class => $this->chapterRepository,
                    default => null,
                };
            });

        $this->command = new CleanupOrphanedFilesCommand($this->entityManager);
    }

    public function testExecuteDryRun(): void
    {
        $this->coverArtQuery->method('toIterable')->willReturn([]);
        $this->chapterQuery->method('toIterable')->willReturn([]);

        $input = new ArrayInput(['--dry-run' => true]);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        $this->assertEquals(0, $result);
        $outputText = $output->fetch();
        $this->assertStringContainsString('Running in dry-run mode', $outputText);
    }

    public function testExecuteCoversOnly(): void
    {
        $this->chapterQuery
            ->expects($this->never())
            ->method('toIterable');

        $this->coverArtQuery->method('toIterable')->willReturn([]);

        $input = new ArrayInput(['--covers-only' => true]);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        $this->assertEquals(0, $result);
    }

    public function testExecuteChaptersOnly(): void
    {
        $this->coverArtQuery
            ->expects($this->never())
            ->method('toIterable');

        $this->chapterQuery->method('toIterable')->willReturn([]);

        $input = new ArrayInput(['--chapters-only' => true]);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        $this->assertEquals(0, $result);
    }

    public function testCommandName(): void
    {
        $this->assertEquals('app:cleanup-orphaned-files', $this->command->getName());
    }

    public function testCleanupOrphanedCoversSectionOutput(): void
    {
        $this->coverArtQuery->method('toIterable')->willReturn([]);

        $input = new ArrayInput(['--covers-only' => true]);
        $output = new BufferedOutput();

        $this->command->run($input, $output);

        $outputText = $output->fetch();
        $this->assertStringContainsString('Checking cover art files', $outputText);
    }

    public function testCleanupOrphanedChaptersSectionOutput(): void
    {
        $this->chapterQuery->method('toIterable')->willReturn([]);

        $input = new ArrayInput(['--chapters-only' => true]);
        $output = new BufferedOutput();

        $this->command->run($input, $output);

        $outputText = $output->fetch();
        $this->assertStringContainsString('Checking chapter page files', $outputText);
    }

    public function testExecuteWithNoOptions(): void
    {
        $this->coverArtQuery->method('toIterable')->willReturn([]);
        $this->chapterQuery->method('toIterable')->willReturn([]);

        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        $this->assertEquals(0, $result);
    }

    public function testCleanupOrphanedCoversNoDirectory(): void
    {
        $publicDir = dirname(__DIR__, 2) . '/public';
        $coversDir = $publicDir . '/uploads/covers';
        // Remove the covers directory to test the "does not exist" path
        if (is_dir($coversDir)) {
            // Recursively remove all files and subdirectories
            $it = new \RecursiveDirectoryIterator($coversDir, \RecursiveDirectoryIterator::SKIP_DOTS);
            $files = new \RecursiveIteratorIterator($it, \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $f) {
                $f->isFile() ? unlink($f->getPathname()) : rmdir($f->getPathname());
            }
            rmdir($coversDir);
        }

        $this->coverArtQuery->method('toIterable')->willReturn([]);

        $input = new ArrayInput(['--covers-only' => true]);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        $this->assertEquals(0, $result);

        $outputText = $output->fetch();
        $this->assertStringContainsString('does not exist', $outputText);

        // Restore for other tests
        if (! is_dir($coversDir)) {
            mkdir($coversDir, 0777, true);
        }
    }

    public function testCleanupOrphanedChaptersNoDirectory(): void
    {
        $publicDir = dirname(__DIR__, 2) . '/public';
        $chaptersDir = $publicDir . '/uploads/chapters';
        // Remove the chapters directory to test the "does not exist" path
        if (is_dir($chaptersDir)) {
            $it = new \RecursiveDirectoryIterator($chaptersDir, \RecursiveDirectoryIterator::SKIP_DOTS);
            $files = new \RecursiveIteratorIterator($it, \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $f) {
                $f->isFile() ? unlink($f->getPathname()) : rmdir($f->getPathname());
            }
            rmdir($chaptersDir);
        }

        $this->chapterQuery->method('toIterable')->willReturn([]);

        $input = new ArrayInput(['--chapters-only' => true]);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        $this->assertEquals(0, $result);

        $outputText = $output->fetch();
        $this->assertStringContainsString('does not exist', $outputText);

        // Restore for other tests
        if (! is_dir($chaptersDir)) {
            mkdir($chaptersDir, 0777, true);
        }
    }

    public function testCleanupOrphanedChaptersWithNonDirectoryEntry(): void
    {
        $publicDir = dirname(__DIR__, 2) . '/public';
        $chaptersDir = $publicDir . '/uploads/chapters';
        if (! is_dir($chaptersDir)) {
            mkdir($chaptersDir, 0777, true);
        }
        // Create a regular file in the chapters root (not a directory)
        // This tests the `is_dir` check at line 157
        $nonDirFile = $chaptersDir . '/not_a_dir.txt';
        file_put_contents($nonDirFile, 'this is a file, not a directory');

        $this->chapterQuery->method('toIterable')->willReturn([]);

        $input = new ArrayInput(['--chapters-only' => true]);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        $this->assertEquals(0, $result);

        // Cleanup
        if (file_exists($nonDirFile)) {
            unlink($nonDirFile);
        }
    }

    public function testCleanupOrphanedCoversDeletesFile(): void
    {
        $publicDir = dirname(__DIR__, 2) . '/public';
        $coversDir = $publicDir . '/uploads/covers';
        if (! is_dir($coversDir)) {
            mkdir($coversDir, 0777, true);
        }
        $orphanedFile = $coversDir . '/orphaned_cover.jpg';
        file_put_contents($orphanedFile, 'orphaned');

        // Configure the query to return a valid cover path
        // orphaned_cover.jpg is NOT in this list, so it will be deleted
        $this->coverArtQuery
            ->method('toIterable')
            ->willReturn([['imagePath' => '/uploads/covers/valid.jpg']]);

        $input = new ArrayInput(['--covers-only' => true]);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        $this->assertEquals(0, $result);
        $this->assertFileDoesNotExist($orphanedFile);

        $outputText = $output->fetch();
        $this->assertStringContainsString('Deleting:', $outputText);
        $this->assertStringContainsString('orphaned_cover.jpg', $outputText);

        if (file_exists($orphanedFile)) {
            unlink($orphanedFile);
        }
    }

    public function testCleanupOrphanedCoversDryRun(): void
    {
        $publicDir = dirname(__DIR__, 2) . '/public';
        $coversDir = $publicDir . '/uploads/covers';
        if (! is_dir($coversDir)) {
            mkdir($coversDir, 0777, true);
        }
        $orphanedFile = $coversDir . '/dry_run_cover.jpg';
        file_put_contents($orphanedFile, 'orphaned');

        $this->coverArtQuery
            ->method('toIterable')
            ->willReturn([['imagePath' => '/uploads/covers/valid.jpg']]);

        $input = new ArrayInput(['--covers-only' => true, '--dry-run' => true]);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        $this->assertEquals(0, $result);
        // File should still exist after dry run
        $this->assertFileExists($orphanedFile);

        $outputText = $output->fetch();
        $this->assertStringContainsString('Would delete:', $outputText);

        if (file_exists($orphanedFile)) {
            unlink($orphanedFile);
        }
    }

    public function testCleanupOrphanedCoversSuccessMessage(): void
    {
        $publicDir = dirname(__DIR__, 2) . '/public';
        $coversDir = $publicDir . '/uploads/covers';
        if (! is_dir($coversDir)) {
            mkdir($coversDir, 0777, true);
        }
        $orphanedFile = $coversDir . '/success_orphan.jpg';
        file_put_contents($orphanedFile, 'orphaned');

        $this->coverArtQuery
            ->method('toIterable')
            ->willReturn([['imagePath' => '/uploads/covers/valid.jpg']]);

        $input = new ArrayInput(['--covers-only' => true]);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        $this->assertEquals(0, $result);

        $outputText = $output->fetch();
        // deletedCount > 0 triggers the success message
        $this->assertStringContainsString('orphaned file(s)', $outputText);

        if (file_exists($orphanedFile)) {
            unlink($orphanedFile);
        }
    }

    public function testCleanupOrphanedChaptersDeletesOrphanedFile(): void
    {
        $publicDir = dirname(__DIR__, 2) . '/public';
        $chaptersDir = $publicDir . '/uploads/chapters/orphaned_chapter_test';
        if (! is_dir($chaptersDir)) {
            mkdir($chaptersDir, 0777, true);
        }
        $orphanedFile = $chaptersDir . '/1.jpg';
        file_put_contents($orphanedFile, 'orphaned page');

        // Configure the query to return valid page paths
        // orphaned_chapter_test/1.jpg is NOT in this list, so it will be deleted
        $this->chapterQuery
            ->method('toIterable')
            ->willReturn([['pages' => ['/uploads/chapters/valid/1.jpg']]]);

        $input = new ArrayInput(['--chapters-only' => true]);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        $this->assertEquals(0, $result);
        $this->assertFileDoesNotExist($orphanedFile);

        $outputText = $output->fetch();
        $this->assertStringContainsString('Deleting:', $outputText);

        // Cleanup
        if (file_exists($orphanedFile)) {
            unlink($orphanedFile);
        }
        if (is_dir($chaptersDir)) {
            rmdir($chaptersDir);
        }
    }

    public function testCleanupOrphanedChaptersRemovesEmptyDirectory(): void
    {
        $publicDir = dirname(__DIR__, 2) . '/public';
        $chapterDir = $publicDir . '/uploads/chapters/empty_dir_test';
        if (! is_dir($chapterDir)) {
            mkdir($chapterDir, 0777, true);
        }
        // Put a file in the directory — it will be deleted by the command
        $orphanedFile = $chapterDir . '/page.jpg';
        file_put_contents($orphanedFile, 'page');

        $this->chapterQuery
            ->method('toIterable')
            ->willReturn([['pages' => ['/uploads/chapters/valid/1.jpg']]]);

        $input = new ArrayInput(['--chapters-only' => true]);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        $this->assertEquals(0, $result);
        // The directory should be removed since it was emptied
        $this->assertFileDoesNotExist($chapterDir);

        $outputText = $output->fetch();
        $this->assertStringContainsString('Removed empty directory', $outputText);

        // Cleanup
        if (is_dir($chapterDir)) {
            if (file_exists($orphanedFile)) {
                unlink($orphanedFile);
            }
            rmdir($chapterDir);
        }
    }

    public function testCleanupOrphanedChaptersIOExceptionOnDirectoryCleanup(): void
    {
        $publicDir = dirname(__DIR__, 2) . '/public';
        $chapterDir = $publicDir . '/uploads/chapters/dir_io_test';
        if (! is_dir($chapterDir)) {
            mkdir($chapterDir, 0777, true);
        }
        $orphanedFile = $chapterDir . '/page.jpg';
        file_put_contents($orphanedFile, 'orphaned');

        $this->chapterQuery
            ->method('toIterable')
            ->willReturn([['pages' => ['/uploads/chapters/valid/1.jpg']]]);

        // Create a real filesystem for the first (file) deletion
        $realFilesystem = new \Symfony\Component\Filesystem\Filesystem();
        $callCount = 0;
        $mockFilesystem = $this->createMock(\Symfony\Component\Filesystem\Filesystem::class);
        $mockFilesystem
            ->method('remove')
            ->willReturnCallback(function ($path) use ($realFilesystem, &$callCount) {
                ++$callCount;
                if ($callCount === 1) {
                    // First call: remove the orphaned file (succeeds)
                    if (file_exists($path)) {
                        $realFilesystem->remove($path);
                    }
                    return;
                }
                // Second call: remove the directory (throws IOException)
                throw new \Symfony\Component\Filesystem\Exception\IOException('Cannot remove directory');
            });

        $reflection = new \ReflectionProperty(CleanupOrphanedFilesCommand::class, 'filesystem');
        $reflection->setValue($this->command, $mockFilesystem);

        $input = new ArrayInput(['--chapters-only' => true]);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        // The command should still succeed even if directory cleanup fails
        $this->assertEquals(0, $result);

        if (file_exists($orphanedFile)) {
            unlink($orphanedFile);
        }
        if (is_dir($chapterDir)) {
            rmdir($chapterDir);
        }
    }

    public function testCleanupOrphanedCoversIOException(): void
    {
        $publicDir = dirname(__DIR__, 2) . '/public';
        $coversDir = $publicDir . '/uploads/covers';
        if (! is_dir($coversDir)) {
            mkdir($coversDir, 0777, true);
        }
        $orphanedFile = $coversDir . '/io_cover_test.jpg';
        file_put_contents($orphanedFile, 'orphaned');

        $this->coverArtQuery
            ->method('toIterable')
            ->willReturn([['imagePath' => '/uploads/covers/valid.jpg']]);

        $mockFilesystem = $this->createMock(\Symfony\Component\Filesystem\Filesystem::class);
        $mockFilesystem
            ->method('remove')
            ->willThrowException(new \Symfony\Component\Filesystem\Exception\IOException('Test IO Error'));

        $reflection = new \ReflectionProperty(CleanupOrphanedFilesCommand::class, 'filesystem');
        $reflection->setValue($this->command, $mockFilesystem);

        $input = new ArrayInput(['--covers-only' => true]);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        $this->assertEquals(0, $result);
        $outputText = $output->fetch();
        $this->assertStringContainsString('Failed to delete', $outputText);

        if (file_exists($orphanedFile)) {
            unlink($orphanedFile);
        }
    }

    public function testCleanupOrphanedChaptersIOException(): void
    {
        $publicDir = dirname(__DIR__, 2) . '/public';
        $chapterDir = $publicDir . '/uploads/chapters/io_chapter_test';
        if (! is_dir($chapterDir)) {
            mkdir($chapterDir, 0777, true);
        }
        $orphanedFile = $chapterDir . '/page.jpg';
        file_put_contents($orphanedFile, 'orphaned');

        $this->chapterQuery
            ->method('toIterable')
            ->willReturn([['pages' => ['/uploads/chapters/valid/1.jpg']]]);

        $mockFilesystem = $this->createMock(\Symfony\Component\Filesystem\Filesystem::class);
        $mockFilesystem
            ->method('remove')
            ->willThrowException(new \Symfony\Component\Filesystem\Exception\IOException('Test IO Error'));

        $reflection = new \ReflectionProperty(CleanupOrphanedFilesCommand::class, 'filesystem');
        $reflection->setValue($this->command, $mockFilesystem);

        $input = new ArrayInput(['--chapters-only' => true]);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        $this->assertEquals(0, $result);
        $outputText = $output->fetch();
        $this->assertStringContainsString('Failed to delete', $outputText);

        if (file_exists($orphanedFile)) {
            unlink($orphanedFile);
        }
        if (is_dir($chapterDir)) {
            rmdir($chapterDir);
        }
    }

    public function testCleanupOrphanedCoversIOExceptionInUuidSubdirectory(): void
    {
        $publicDir = dirname(__DIR__, 2) . '/public';
        $uuidDir = $publicDir . '/uploads/covers/io-uuid-test';
        $orphanedFile = $uuidDir . '/cover.jpg';

        if (! is_dir($uuidDir)) {
            mkdir($uuidDir, 0777, true);
        }
        file_put_contents($orphanedFile, 'orphaned');

        $this->coverArtQuery
            ->method('toIterable')
            ->willReturn([['imagePath' => '/uploads/covers/valid.jpg']]);

        $mockFilesystem = $this->createMock(\Symfony\Component\Filesystem\Filesystem::class);
        $mockFilesystem
            ->method('remove')
            ->willThrowException(new \Symfony\Component\Filesystem\Exception\IOException('Test IO Error on uuid file'));

        $reflection = new \ReflectionProperty(CleanupOrphanedFilesCommand::class, 'filesystem');
        $reflection->setValue($this->command, $mockFilesystem);

        $input = new ArrayInput(['--covers-only' => true]);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        $this->assertEquals(0, $result);

        $outputText = $output->fetch();
        $this->assertStringContainsString('Failed to delete', $outputText);

        if (file_exists($orphanedFile)) {
            unlink($orphanedFile);
        }
        if (is_dir($uuidDir)) {
            foreach (scandir($uuidDir) ?: [] as $f) {
                if ($f !== '.' && $f !== '..') {
                    unlink($uuidDir . '/' . $f);
                }
            }
            rmdir($uuidDir);
        }
    }

    public function testCleanupOrphanedCoversIOExceptionOnEmptyDirectoryCleanup(): void
    {
        $publicDir = dirname(__DIR__, 2) . '/public';
        $uuidDir = $publicDir . '/uploads/covers/dir-io-test';
        $orphanedFile = $uuidDir . '/page.jpg';

        if (! is_dir($uuidDir)) {
            mkdir($uuidDir, 0777, true);
        }
        file_put_contents($orphanedFile, 'orphaned');

        $this->coverArtQuery
            ->method('toIterable')
            ->willReturn([['imagePath' => '/uploads/covers/valid.jpg']]);

        $realFilesystem = new \Symfony\Component\Filesystem\Filesystem();
        $callCount = 0;
        $mockFilesystem = $this->createMock(\Symfony\Component\Filesystem\Filesystem::class);
        $mockFilesystem
            ->method('remove')
            ->willReturnCallback(function ($path) use ($realFilesystem, &$callCount) {
                ++$callCount;
                if ($callCount === 1) {
                    // First call: remove the orphaned file (succeeds)
                    if (file_exists($path)) {
                        $realFilesystem->remove($path);
                    }
                    return;
                }
                // Second call: remove the empty UUID directory (throws)
                throw new \Symfony\Component\Filesystem\Exception\IOException('Cannot remove uuid directory');
            });

        $reflection = new \ReflectionProperty(CleanupOrphanedFilesCommand::class, 'filesystem');
        $reflection->setValue($this->command, $mockFilesystem);

        $input = new ArrayInput(['--covers-only' => true]);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        $this->assertEquals(0, $result);

        // File should be gone (first remove succeeded)
        $this->assertFileDoesNotExist($orphanedFile);
        // Directory should still exist (second remove failed)
        $this->assertDirectoryExists($uuidDir);

        if (is_dir($uuidDir)) {
            foreach (scandir($uuidDir) ?: [] as $f) {
                if ($f !== '.' && $f !== '..') {
                    unlink($uuidDir . '/' . $f);
                }
            }
            rmdir($uuidDir);
        }
    }

    public function testCleanupOrphanedCoversDeletesFileInUuidSubdirectory(): void
    {
        $publicDir = dirname(__DIR__, 2) . '/public';
        $uuidDir = $publicDir . '/uploads/covers/550e8400-e29b-41d4-a716-446655440000';
        $orphanedFile = $uuidDir . '/cover.jpg';

        if (! is_dir($uuidDir)) {
            mkdir($uuidDir, 0777, true);
        }
        file_put_contents($orphanedFile, 'orphaned cover in uuid dir');

        $this->coverArtQuery
            ->method('toIterable')
            ->willReturn([['imagePath' => '/uploads/covers/valid.jpg']]);

        $input = new ArrayInput(['--covers-only' => true]);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        $this->assertEquals(0, $result);
        $this->assertFileDoesNotExist($orphanedFile);
        $this->assertDirectoryDoesNotExist($uuidDir);

        $outputText = $output->fetch();
        $this->assertStringContainsString('Deleting:', $outputText);
        $this->assertStringContainsString('550e8400-e29b-41d4-a716-446655440000/cover.jpg', $outputText);
        $this->assertStringContainsString('Removed empty directory', $outputText);

        // Cleanup
        if (file_exists($orphanedFile)) {
            unlink($orphanedFile);
        }
        if (is_dir($uuidDir)) {
            // Remove any remaining files in the UUID dir first
            foreach (scandir($uuidDir) ?: [] as $f) {
                if ($f !== '.' && $f !== '..') {
                    unlink($uuidDir . '/' . $f);
                }
            }
            rmdir($uuidDir);
        }
    }

    public function testCleanupOrphanedCoversDryRunInUuidSubdirectory(): void
    {
        $publicDir = dirname(__DIR__, 2) . '/public';
        $uuidDir = $publicDir . '/uploads/covers/dry-run-uuid-test';
        $orphanedFile = $uuidDir . '/page.png';

        if (! is_dir($uuidDir)) {
            mkdir($uuidDir, 0777, true);
        }
        file_put_contents($orphanedFile, 'dry run orphaned cover');

        $this->coverArtQuery
            ->method('toIterable')
            ->willReturn([['imagePath' => '/uploads/covers/valid.jpg']]);

        $input = new ArrayInput(['--covers-only' => true, '--dry-run' => true]);
        $output = new BufferedOutput();

        $result = $this->command->run($input, $output);

        $this->assertEquals(0, $result);
        $this->assertFileExists($orphanedFile);
        $this->assertDirectoryExists($uuidDir);

        $outputText = $output->fetch();
        $this->assertStringContainsString('Would delete:', $outputText);
        // Dry-run should NOT say "Removed empty directory"
        $this->assertStringNotContainsString('Removed empty directory', $outputText);

        // Cleanup
        if (file_exists($orphanedFile)) {
            unlink($orphanedFile);
        }
        if (is_dir($uuidDir)) {
            foreach (scandir($uuidDir) ?: [] as $f) {
                if ($f !== '.' && $f !== '..') {
                    unlink($uuidDir . '/' . $f);
                }
            }
            rmdir($uuidDir);
        }
    }
}
