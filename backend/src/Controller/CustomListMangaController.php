<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\CustomList;
use App\Entity\Manga;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CustomListMangaController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(CustomList $customList, Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            throw new AccessDeniedHttpException('User not authenticated');
        }

        $listUserId = $customList->getUser()->getId();
        $currentUserId = $user instanceof \App\Entity\User ? $user->getId() : null;

        if ($listUserId !== $currentUserId && !$this->isGranted('ROLE_ADMIN')) {
            throw new AccessDeniedHttpException('You can only modify your own lists');
        }

        $mangaId = $request->attributes->get('mangaId');
        if (!$mangaId) {
            return new JsonResponse(['message' => 'Manga ID is required'], 400);
        }

        $manga = $this->entityManager->getRepository(Manga::class)->find($mangaId);
        if (!$manga) {
            throw new NotFoundHttpException('Manga not found');
        }

        $method = $request->getMethod();

        if ('POST' === $method) {
            if ($customList->getMangas()->contains($manga)) {
                return new JsonResponse(['message' => 'Manga already in list'], 409);
            }

            $customList->addManga($manga);
            $this->entityManager->flush();

            return new JsonResponse(['message' => 'Manga added to list'], 200);
        }

        if ('DELETE' === $method) {
            if (!$customList->getMangas()->contains($manga)) {
                return new JsonResponse(['message' => 'Manga not in list'], 404);
            }

            $customList->removeManga($manga);
            $this->entityManager->flush();

            return new JsonResponse(null, 204);
        }

        return new JsonResponse(['message' => 'Method not allowed'], 405);
    }
}
