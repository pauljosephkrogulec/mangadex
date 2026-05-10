<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class FileStorageService
{
    private const UPLOADS_DIR = 'uploads';

    public function storeCover(UploadedFile $file, string $mangaId, ?string $volume): string
    {
        $coversDir = $this->getUploadsDir('covers');
        $filename = $this->generateCoverFilename($file, $mangaId, $volume);
        $file->move($coversDir, $filename);

        return '/covers/' . $filename;
    }

    /**
     * @param array<UploadedFile> $files
     * @return array<string> Array of public file paths
     */
    public function storeChapterPages(array $files, string $chapterId): array
    {
        $chapterDir = $this->getUploadsDir('chapters/' . $chapterId);
        $pagePaths = [];

        foreach ($files as $index => $file) {
            $pageNumber = $index + 1;
            $ext = $file->guessExtension() ?? 'jpg';
            $filename = sprintf('page_%03d.%s', $pageNumber, $ext);
            $file->move($chapterDir, $filename);

            $pagePaths[] = '/chapters/' . $chapterId . '/' . $filename;
        }

        return $pagePaths;
    }

    public function deleteFile(string $publicPath): void
    {
        $fullPath = __DIR__ . '/../../public/uploads' . $publicPath;
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }
    }

    /**
     * @param array<string> $publicPaths
     */
    public function deleteFiles(array $publicPaths): void
    {
        foreach ($publicPaths as $publicPath) {
            $this->deleteFile($publicPath);
        }
    }

    private function generateCoverFilename(UploadedFile $file, string $mangaId, ?string $volume): string
    {
        $ext = $file->guessExtension() ?? 'jpg';
        $volumePart = $volume !== null ? preg_replace('/[^a-zA-Z0-9_-]/', '', $volume) : 'default';
        if ($volumePart === '') {
            $volumePart = 'default';
        }
        $timestamp = time();

        return sprintf('manga_%s_vol_%s_%d.%s', $mangaId, $volumePart, $timestamp, $ext);
    }

    private function getUploadsDir(string $subdir): string
    {
        $dir = __DIR__ . '/../../public/' . self::UPLOADS_DIR . '/' . $subdir;

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return $dir;
    }
}
