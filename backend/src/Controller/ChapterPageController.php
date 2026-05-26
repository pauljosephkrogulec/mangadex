<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Chapter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class ChapterPageController extends AbstractController
{
    private const UPLOADS_BASE = '/public/uploads';

    private function resolveUploadPath(string $relativePath): ?string
    {
        $fullPath = $this->getParameter('kernel.project_dir').'/public/uploads'.$relativePath;
        $realPath = realpath($fullPath);

        if (false === $realPath) {
            return null;
        }

        $uploadsDir = realpath($this->getParameter('kernel.project_dir').self::UPLOADS_BASE);

        if (!str_starts_with($realPath, $uploadsDir)) {
            return null;
        }

        return $realPath;
    }

    #[Route('/chapters/{id}/pages/{pageNum}', name: 'chapter_page_serve', methods: ['GET'])]
    public function servePage(string $id, int $pageNum, EntityManagerInterface $em): Response
    {
        $chapter = $em->getRepository(Chapter::class)->find($id);
        if (!$chapter) {
            throw $this->createNotFoundException('Chapter not found');
        }

        $pages = $chapter->getPages();
        $totalPages = count($pages);

        if ($pageNum < 1 || $pageNum > $totalPages) {
            throw $this->createNotFoundException('Page number out of range');
        }

        $pageRef = $pages[$pageNum - 1];

        $fullPath = $this->resolveUploadPath($pageRef);

        if (null === $fullPath) {
            throw $this->createNotFoundException('Page file not found');
        }

        $response = new BinaryFileResponse($fullPath);
        $response->setPublic();
        $response->setMaxAge(2592000);
        $response->headers->addCacheControlDirective('immutable');
        $response->setExpires(new \DateTime('+30 days'));

        return $response;
    }
}
