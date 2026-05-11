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
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;

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

        // Use streaming query to avoid loading all entities into memory
        $qb = $this->em->getRepository(CoverArt::class)->createQueryBuilder('c')
            ->select('c.imagePath');
        $validPaths = [];
        foreach ($qb->getQuery()->toIterable() as $row) {
            $path = $row['imagePath'];
            if (!empty($path)) {
                $validPaths[$this->getFullPath($path)] = true;
            }
        }
        $this->em->clear();

        $coversDir = $this->getUploadsDir('covers');
        if (!is_dir($coversDir)) {
            $io->info('Covers directory does not exist yet.');

            return 0;
        }

        $deleted = 0;
        $entries = scandir($coversDir);

        foreach ($entries ?: [] as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }

            $fullPath = $coversDir.'/'.$entry;

            // Covers are stored in UUID subdirectories: covers/{uuid}/{filename}
            if (is_dir($fullPath)) {
                $files = scandir($fullPath);
                foreach ($files ?: [] as $file) {
                    if ('.' === $file || '..' === $file) {
                        continue;
                    }
                    $filePath = $fullPath.'/'.$file;
                    if (is_file($filePath) && !isset($validPaths[$filePath])) {
                        $io->writeln(sprintf('  %s <fg=red>%s/%s</>', $dryRun ? 'Would delete:' : 'Deleting:', $entry, $file));

                        if (!$dryRun) {
                            try {
                                $this->filesystem->remove($filePath);
                            } catch (IOExceptionInterface $e) {
                                $io->error('Failed to delete: '.$e->getMessage());
                                continue;
                            }
                        }
                        ++$deleted;
                    }
                }

                // Remove empty UUID directories
                if (!$dryRun) {
                    $remaining = scandir($fullPath);
                    if (false !== $remaining && count($remaining) <= 2) {
                        try {
                            $this->filesystem->remove($fullPath);
                            $io->writeln(sprintf('  Removed empty directory: <fg=yellow>covers/%s/</>', $entry));
                        } catch (IOExceptionInterface) {
                            // Ignore
                        }
                    }
                }
            } elseif (is_file($fullPath) && !isset($validPaths[$fullPath])) {
                $io->writeln(sprintf('  %s <fg=red>%s</>', $dryRun ? 'Would delete:' : 'Deleting:', $entry));

                if (!$dryRun) {
                    try {
                        $this->filesystem->remove($fullPath);
                    } catch (IOExceptionInterface $e) {
                        $io->error('Failed to delete: '.$e->getMessage());
                        continue;
                    }
                }
                ++$deleted;
            }
        }

        return $deleted;
    }

    private function cleanupOrphanedChapters(SymfonyStyle $io, bool $dryRun): int
    {
        $io->section('Checking chapter page files...');

        // Use streaming query to avoid loading all entities into memory
        $qb = $this->em->getRepository(Chapter::class)->createQueryBuilder('ch')
            ->select('ch.pages');
        $validPaths = [];
        foreach ($qb->getQuery()->toIterable() as $row) {
            $pages = $row['pages'];
            if (is_array($pages)) {
                foreach ($pages as $pagePath) {
                    if (!empty($pagePath)) {
                        $validPaths[$this->getFullPath($pagePath)] = true;
                    }
                }
            }
        }
        $this->em->clear();

        $chaptersDir = $this->getUploadsDir('chapters');
        if (!is_dir($chaptersDir)) {
            $io->info('Chapters directory does not exist yet.');

            return 0;
        }

        $deleted = 0;
        $chapterDirs = scandir($chaptersDir);

        foreach ($chapterDirs ?: [] as $dir) {
            if ('.' === $dir || '..' === $dir) {
                continue;
            }

            $chapterPath = $chaptersDir.'/'.$dir;
            if (!is_dir($chapterPath)) {
                continue;
            }

            $files = scandir($chapterPath);

            foreach ($files ?: [] as $file) {
                if ('.' === $file || '..' === $file) {
                    continue;
                }

                $fullPath = $chapterPath.'/'.$file;
                if (is_file($fullPath) && !isset($validPaths[$fullPath])) {
                    $io->writeln(sprintf('  %s <fg=red>chapters/%s/%s</>', $dryRun ? 'Would delete:' : 'Deleting:', $dir, $file));

                    if (!$dryRun) {
                        try {
                            $this->filesystem->remove($fullPath);
                        } catch (IOExceptionInterface $e) {
                            $io->error('Failed to delete: '.$e->getMessage());
                            continue;
                        }
                    }
                    ++$deleted;
                }
            }

            // Clean up empty chapter directories
            if (!$dryRun) {
                $remaining = scandir($chapterPath);
                if (false !== $remaining && count($remaining) <= 2) {
                    try {
                        $this->filesystem->remove($chapterPath);
                        $msg = sprintf('  Removed empty directory: <fg=yellow>chapters/%s/</>', $dir);
                        $io->writeln($msg);
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
        // $publicPath is like '/covers/1/cover.jpg' (without /uploads prefix)
        // Uploads directory is at 'public/uploads/'
        $baseDir = dirname(__DIR__, 2).'/public/uploads';
        $fullPath = $baseDir.$publicPath;

        return $fullPath;
    }

    private function getUploadsDir(string $subdir): string
    {
        // $subdir is 'covers' or 'chapters'
        $baseDir = dirname(__DIR__, 2).'/public/uploads';

        return $baseDir.'/'.$subdir;
    }
}
