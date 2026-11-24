<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * L'état actuel de la ressource empêche l'opération (409), ex. un doublon.
 */
final class ConflictException extends BusinessException
{
    public function getType(): string
    {
        return '/errors/conflict';
    }

    public function getTitle(): string
    {
        return 'Conflict';
    }

    public function getStatus(): int
    {
        return 409;
    }
}
