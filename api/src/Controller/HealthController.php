<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Sonde de santé : utilisée par le health check de Render et par les smoke tests du déploiement.
 * Volontairement sans accès à la base : un ping ne doit pas réveiller Neon (heures de calcul limitées).
 * `version` = commit déployé (APP_VERSION, injecté au build de l'image), pour vérifier qu'un
 * déploiement a bien remplacé la version précédente.
 */
final class HealthController
{
    public function __construct(
        #[Autowire(env: 'APP_VERSION')]
        private readonly string $version,
    ) {
    }

    #[Route('/api/health', name: 'api_health', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $response = new JsonResponse(['status' => 'ok', 'version' => $this->version]);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
