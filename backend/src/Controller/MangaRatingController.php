<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Manga;
use App\Entity\Rating;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class MangaRatingController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(Manga $manga, Request $request): JsonResponse
    {
        $method = $request->getMethod();

        if ('GET' === $method) {
            return $this->handleGet($manga);
        }

        if ('POST' === $method) {
            return $this->handlePost($manga, $request);
        }

        return new JsonResponse(['message' => 'Method not allowed'], 405);
    }

    private function handleGet(Manga $manga): JsonResponse
    {
        $stats = $this->queryStats($manga);

        $userRating = null;
        $user = $this->getUser();
        if ($user instanceof \App\Entity\User) {
            $existing = $this->entityManager->getRepository(Rating::class)->findOneBy([
                'user' => $user,
                'manga' => $manga,
            ]);
            $userRating = $existing?->getScore();
        }

        return new JsonResponse([
            'averageRating' => $stats['avg'] !== null ? round((float) $stats['avg'], 2) : null,
            'ratingCount' => (int) $stats['cnt'],
            'userRating' => $userRating,
        ]);
    }

    private function handlePost(Manga $manga, Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof \App\Entity\User) {
            throw new \Symfony\Component\Security\Core\Exception\AccessDeniedException('User not authenticated');
        }

        $data = json_decode($request->getContent(), true);
        $score = $data['score'] ?? null;

        if (!is_int($score) || $score < 1 || $score > 10) {
            return new JsonResponse(['message' => 'Score must be an integer between 1 and 10'], 422);
        }

        $existing = $this->entityManager->getRepository(Rating::class)->findOneBy([
            'user' => $user,
            'manga' => $manga,
        ]);

        if ($existing) {
            $existing->setScore($score);
        } else {
            $rating = new Rating();
            $rating->setUser($user);
            $rating->setManga($manga);
            $rating->setScore($score);
            $this->entityManager->persist($rating);
        }

        $this->entityManager->flush();

        $stats = $this->queryStats($manga);

        return new JsonResponse([
            'averageRating' => $stats['avg'] !== null ? round((float) $stats['avg'], 2) : null,
            'ratingCount' => (int) $stats['cnt'],
            'userRating' => $score,
        ], $existing ? 200 : 201);
    }

    /** @return array{avg: string|null, cnt: string} */
    private function queryStats(Manga $manga): array
    {
        return $this->entityManager->createQuery(
            'SELECT AVG(r.score) as avg, COUNT(r.id) as cnt FROM App\Entity\Rating r WHERE r.manga = :manga'
        )->setParameter('manga', $manga)->getSingleResult();
    }
}
