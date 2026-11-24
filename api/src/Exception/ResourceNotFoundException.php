<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Ressource métier introuvable (404), ex. un chapitre absent d'une série.
 */
final class ResourceNotFoundException extends BusinessException
{
    public function getType(): string
    {
        return '/errors/resource-not-found';
    }

    public function getTitle(): string
    {
        return 'Resource not found';
    }

    public function getStatus(): int
    {
        return 404;
    }
}
