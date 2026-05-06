<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\CoverArt;
use App\Entity\Manga;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class CoverArtController extends AbstractController
{
    #[Route('/covers/{id}', methods: ['GET'])]
    public function serveCover(int $id, EntityManagerInterface $em): Response
    {
        $coverArt = $em->getRepository(CoverArt::class)->find($id);

        if (!$coverArt) {
            throw $this->createNotFoundException('Cover art not found');
        }

        $fullPath = $this->getParameter('kernel.project_dir') . '/public' . $coverArt->getImagePath();

        if (!file_exists($fullPath)) {
            throw $this->createNotFoundException('Cover art file not found');
        }

        return $this->createBinaryResponse($fullPath);
    }

    #[Route('/mangas/{id}/primary-cover', methods: ['GET'])]
    public function servePrimaryCover(int $id, EntityManagerInterface $em): Response
    {
        $manga = $em->getRepository(Manga::class)->find($id);

        if (!$manga) {
            throw $this->createNotFoundException('Manga not found');
        }

        $coverArt = $em->getRepository(CoverArt::class)->findOneBy([
            'manga' => $manga,
            'isPrimary' => true,
        ]);

        if (!$coverArt) {
            throw $this->createNotFoundException('Primary cover not found for this manga');
        }

        $fullPath = $this->getParameter('kernel.project_dir') . '/public' . $coverArt->getImagePath();

        if (!file_exists($fullPath)) {
            throw $this->createNotFoundException('Cover art file not found');
        }

        return $this->createBinaryResponse($fullPath);
    }

    private function createBinaryResponse(string $fullPath): BinaryFileResponse
    {
        $response = new BinaryFileResponse($fullPath);
        $response->setPublic();
        $response->setMaxAge(2592000);
        $response->headers->addCacheControlDirective('immutable');
        $response->setExpires(new \DateTime('+30 days'));

        // Set content type based on file extension to avoid requiring symfony/mime
        $mimeType = $this->getMimeType($fullPath);
        if ($mimeType !== null) {
            $response->headers->set('Content-Type', $mimeType);
        }

        return $response;
    }

    private function getMimeType(string $path): ?string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
        ];

        return $mimeTypes[$ext] ?? null;
    }
}
