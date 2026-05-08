<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Chapter;
use App\Entity\CoverArt;
use App\Entity\Manga;
use App\Service\FileStorageService;
use App\Service\FileUploadValidator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api')]
#[IsGranted('ROLE_ADMIN')]
class UploadController extends AbstractController
{
    public function __construct(
        private readonly FileUploadValidator $uploadValidator,
        private readonly FileStorageService $storageService,
    ) {
    }

    #[Route('/covers/upload', name: 'cover_upload', methods: ['POST'])]
    public function uploadCover(Request $request, EntityManagerInterface $em): JsonResponse
    {
        /** @var UploadedFile|null $file */
        $file = $request->files->get('cover');
        $mangaId = $request->request->get('mangaId');
        $volume = $request->request->get('volume');
        $volume = $volume !== null ? (string) $volume : null;
        $isPrimary = $request->request->getBoolean('isPrimary', false);

        if (! $file) {
            return $this->json(['error' => 'No cover file provided'], Response::HTTP_BAD_REQUEST);
        }

        if (! is_string($mangaId) || $mangaId === '') {
            return $this->json(['error' => 'Valid mangaId is required'], Response::HTTP_BAD_REQUEST);
        }

        $manga = $em->getRepository(Manga::class)->find($mangaId);
        if (! $manga) {
            return $this->json(['error' => 'Manga not found'], Response::HTTP_NOT_FOUND);
        }

        $violations = $this->uploadValidator->validateImage($file);
        if (count($violations) > 0) {
            return $this->json(['error' => (string) $violations->get(0)->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        $mangaId = $manga->getId();
        if ($mangaId === null) {
            return $this->json(['error' => 'Manga ID is null'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $imagePath = $this->storageService->storeCover($file, $mangaId, $volume);

        $coverArt = new CoverArt();
        $coverArt->setManga($manga);
        $coverArt->setImagePath($imagePath);
        $coverArt->setVolume($volume);
        $coverArt->setIsPrimary($isPrimary);

        try {
            $em->persist($coverArt);
            $em->flush();
        } catch (\Throwable $e) {
            $this->storageService->deleteFile($imagePath);
            throw $e;
        }

        return $this->json([
            'id' => $coverArt->getId(),
            'imagePath' => $coverArt->getImagePath(),
            'volume' => $coverArt->getVolume(),
            'isPrimary' => $coverArt->isPrimary(),
            'manga' => ['id' => $manga->getId(), 'title' => $manga->getTitle()],
        ], Response::HTTP_CREATED);
    }

    #[Route('/chapters/{id}/upload-pages', name: 'chapter_upload_pages', methods: ['POST'])]
    public function uploadChapterPages(string $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        /** @var array<UploadedFile> $files */
        $files = $request->files->all('pages');

        if (empty($files)) {
            return $this->json(['error' => 'No page files provided'], Response::HTTP_BAD_REQUEST);
        }

        $chapter = $em->getRepository(Chapter::class)->find($id);
        if (! $chapter) {
            return $this->json(['error' => 'Chapter not found'], Response::HTTP_NOT_FOUND);
        }

        $validationErrors = $this->uploadValidator->validateMultipleImages($files);
        if (! empty($validationErrors)) {
            return $this->json(['errors' => $validationErrors], Response::HTTP_BAD_REQUEST);
        }

        $chapterId = $chapter->getId();
        if ($chapterId === null) {
            return $this->json(['error' => 'Chapter ID is null'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $pagePaths = $this->storageService->storeChapterPages($files, $chapterId);

        $chapter->setPages($pagePaths);

        try {
            $em->flush();
        } catch (\Throwable $e) {
            $this->storageService->deleteFiles($pagePaths);
            throw $e;
        }

        return $this->json([
            'chapterId' => $chapter->getId(),
            'pages' => $chapter->getPages(),
            'pageCount' => count($chapter->getPages()),
        ], Response::HTTP_OK);
    }
}
