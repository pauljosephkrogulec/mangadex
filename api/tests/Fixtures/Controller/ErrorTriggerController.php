<?php

declare(strict_types=1);

namespace App\Tests\Fixtures\Controller;

use App\Exception\BusinessRuleViolationException;
use App\Exception\ConflictException;
use App\Exception\ResourceNotFoundException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;

/**
 * Route de test uniquement (chargée en env "test") : déclenche chaque type d'erreur pour vérifier
 * son rendu problem+json de bout en bout.
 */
final class ErrorTriggerController
{
    #[Route('/api/_test/errors/{kind}', name: 'test_error_trigger', methods: ['GET'])]
    public function __invoke(string $kind): Response
    {
        throw match ($kind) {
            'not-found' => new ResourceNotFoundException('Le chapitre 42 est introuvable.'),
            'conflict' => new ConflictException('Ce titre existe déjà.'),
            'business-rule' => new BusinessRuleViolationException('Un chapitre ne peut pas être publié avant sa série.'),
            'serializer' => new UnexpectedValueException('Corps de requête illisible.'),
            default => new \RuntimeException('Erreur interne : secret=hunter2'),
        };
    }
}
