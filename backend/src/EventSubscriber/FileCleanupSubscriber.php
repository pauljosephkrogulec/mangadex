<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Chapter;
use App\Entity\CoverArt;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;

class FileCleanupSubscriber implements EventSubscriber
{
    public function __construct(
        private readonly Filesystem $filesystem,
    ) {
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::postRemove,
        ];
    }

    /**
     * @param LifecycleEventArgs<\Doctrine\Persistence\ObjectManager> $args
     */
    public function postRemove(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof CoverArt) {
            $this->deleteCoverArtFile($entity);
        } elseif ($entity instanceof Chapter) {
            $this->deleteChapterFiles($entity);
        }
    }

    private function deleteCoverArtFile(CoverArt $coverArt): void
    {
        $imagePath = $coverArt->getImagePath();
        if (empty($imagePath)) {
            return;
        }

        $fullPath = $this->getFullPath($imagePath);

        try {
            if (file_exists($fullPath)) {
                $this->filesystem->remove($fullPath);
            }
        } catch (IOExceptionInterface $e) {
            // Log error but don't throw - entity deletion already succeeded
        }
    }

    private function deleteChapterFiles(Chapter $chapter): void
    {
        $pages = $chapter->getPages();
        if (empty($pages)) {
            return;
        }

        $baseDir = null;
        foreach ($pages as $pagePath) {
            $fullPath = $this->getFullPath($pagePath);
            if ($fullPath !== null && file_exists($fullPath)) {
                try {
                    $this->filesystem->remove($fullPath);
                    // Track the base directory for cleanup
                    if ($baseDir === null) {
                        $baseDir = dirname($fullPath);
                    }
                } catch (IOExceptionInterface $e) {
                    // Continue with other files
                }
            }
        }

        // Remove the chapter directory if it's empty
        if ($baseDir !== null && is_dir($baseDir)) {
            try {
                // Use Filesystem to remove directory only if empty
                $files = scandir($baseDir);
                if ($files !== false && count($files) <= 2) { // Only . and ..
                    $this->filesystem->remove($baseDir);
                }
            } catch (IOExceptionInterface $e) {
                // Directory not empty or can't be removed
            }
        }
    }

    private function getFullPath(string $publicPath): ?string
    {
        if (empty($publicPath)) {
            return null;
        }

        // $publicPath is like '/covers/1/cover.jpg' or '/chapters/1/1.jpg'
        // Uploads directory is at 'public/uploads/'
        $baseDir = dirname(__DIR__, 2) . '/public';
        $fullPath = $baseDir . $publicPath;

        return $fullPath;
    }
}
