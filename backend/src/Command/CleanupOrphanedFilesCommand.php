<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Chapter;
use App\Entity\CoverArt;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;

#[AsCommand(
    name: 'app:cleanup-orphaned-files',
    description: 'Removes files in uploads/ that are not referenced in the database',
)]
class CleanupOrphanedFilesCommand extends Command
{
    private const UPLOADS_DIR = 'uploads';

    private Filesystem $filesystem;

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
        $this->filesystem = new Filesystem();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'List files that would be deleted without actually deleting them')
            ->addOption('covers-only', null, InputOption::VALUE_NONE, 'Only clean up orphaned cover art files')
            ->addOption('chapters-only', null, InputOption::VALUE_NONE, 'Only clean up orphaned chapter page files');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $coversOnly = (bool) $input->getOption('covers-only');
        $chaptersOnly = (bool) $input->getOption('chapters-only');

        if ($dryRun) {
            $io->note('Running in dry-run mode - no files will be deleted');
        }

        $io->title('Cleaning up orphaned files in uploads/');

        $deletedCount = 0;

        if (!$chaptersOnly) {
            $deletedCount += $this->cleanupOrphanedCovers($io, $dryRun);
        }

        if (!$coversOnly) {
            $deletedCount += $this->cleanupOrphanedChapters($io, $dryRun);
        }

        if ($deletedCount > 0) {
            $io->success(sprintf('%s %d orphaned file(s)', $dryRun ? 'Would delete' : 'Deleted', $deletedCount));
        } else {
            $io->info('No orphaned files found.');
        }

        return Command::SUCCESS;
    }

    private function cleanupOrphanedCovers(SymfonyStyle $io, bool $dryRun): int
    {
        $io->section('Checking cover art files...');

        $coverArts = $this->em->getRepository(CoverArt::class)->findAll();
        $validPaths = [];
        foreach ($coverArts as $coverArt) {
            $path = $coverArt->getImagePath();
            if (!empty($path)) {
                $validPaths[$this->getFullPath($path)] = true;
            }
        }

        $coversDir = $this->getUploadsDir('covers');
        if (!is_dir($coversDir)) {
            $io->info('Covers directory does not exist yet.');
            return 0;
        }

        $deleted = 0;
        $files = scandir($coversDir);
        if ($files === false) {
            return 0;
        }

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $fullPath = $coversDir . '/' . $file;
            if (is_file($fullPath) && !isset($validPaths[$fullPath])) {
                $io->writeln(sprintf('  %s <fg=red>%s</>', $dryRun ? 'Would delete:' : 'Deleting:', $file));

                if (!$dryRun) {
                    try {
                        $this->filesystem->remove($fullPath);
                    } catch (IOExceptionInterface $e) {
                        $io->error('Failed to delete: ' . $e->getMessage());
                        continue;
                    }
                }
                $deleted++;
            }
        }

        return $deleted;
    }

    private function cleanupOrphanedChapters(SymfonyStyle $io, bool $dryRun): int
    {
        $io->section('Checking chapter page files...');

        $chapters = $this->em->getRepository(Chapter::class)->findAll();
        $validPaths = [];
        foreach ($chapters as $chapter) {
            foreach ($chapter->getPages() as $pagePath) {
                if (!empty($pagePath)) {
                    $validPaths[$this->getFullPath($pagePath)] = true;
                }
            }
        }

        $chaptersDir = $this->getUploadsDir('chapters');
        if (!is_dir($chaptersDir)) {
            $io->info('Chapters directory does not exist yet.');
            return 0;
        }

        $deleted = 0;
        $chapterDirs = scandir($chaptersDir);
        if ($chapterDirs === false) {
            return 0;
        }

        foreach ($chapterDirs as $dir) {
            if ($dir === '.' || $dir === '..') {
                continue;
            }

            $chapterPath = $chaptersDir . '/' . $dir;
            if (!is_dir($chapterPath)) {
                continue;
            }

            $files = scandir($chapterPath);
            if ($files === false) {
                continue;
            }

            foreach ($files as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }

                $fullPath = $chapterPath . '/' . $file;
                if (is_file($fullPath) && !isset($validPaths[$fullPath])) {
                    $io->writeln(sprintf('  %s <fg=red>chapters/%s/%s</>', $dryRun ? 'Would delete:' : 'Deleting:', $dir, $file));

                    if (!$dryRun) {
                        try {
                            $this->filesystem->remove($fullPath);
                        } catch (IOExceptionInterface $e) {
                            $io->error('Failed to delete: ' . $e->getMessage());
                            continue;
                        }
                    }
                    $deleted++;
                }
            }

            // Clean up empty chapter directories
            if (!$dryRun) {
                $remaining = scandir($chapterPath);
                if ($remaining !== false && count($remaining) <= 2) {
                    try {
                        $this->filesystem->remove($chapterPath);
                        $io->writeln(sprintf('  Removed empty directory: <fg=yellow>chapters/%s/</>', $dir));
                    } catch (IOExceptionInterface $e) {
                        // Ignore
                    }
                }
            }
        }

        return $deleted;
    }

    private function getFullPath(string $publicPath): string
    {
        return __DIR__ . '/../../public' . $publicPath;
    }

    private function getUploadsDir(string $subdir): string
    {
        return __DIR__ . '/../../public/' . self::UPLOADS_DIR . '/' . $subdir;
    }
}
