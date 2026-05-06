<?php

namespace App\Controller;

use App\Entity\Manga;
use App\Entity\MangaFollow;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MangaFollowController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(Manga $manga, Request $request): JsonResponse|MangaFollow
    {
        $user = $this->getUser();
        if (! $user) {
            throw new \Symfony\Component\Security\Core\Exception\AccessDeniedException('User not authenticated');
        }

        $existingFollow = $this->entityManager->getRepository(MangaFollow::class)->findOneBy([
            'user' => $user,
            'manga' => $manga,
        ]);

        $method = $request->getMethod();

        if ($method === 'POST') {
            if ($existingFollow) {
                return new JsonResponse(['message' => 'Already following this manga'], 409);
            }

            $follow = new MangaFollow();
            $follow->setUser($user);
            $follow->setManga($manga);

            $this->entityManager->persist($follow);
            $this->entityManager->flush();

            return $follow;
        }

        if ($method === 'DELETE') {
            if (! $existingFollow) {
                return new JsonResponse(['message' => 'Not following this manga'], 404);
            }

            $this->entityManager->remove($existingFollow);
            $this->entityManager->flush();

            return new JsonResponse(null, 204);
        }

        if ($method === 'GET') {
            if (! $existingFollow) {
                return new JsonResponse(['following' => false], 200);
            }

            return new JsonResponse(['following' => true, 'followedAt' => $existingFollow->getFollowedAt()->format('c')], 200);
        }

        return new JsonResponse(['message' => 'Method not allowed'], 405);
    }
}
