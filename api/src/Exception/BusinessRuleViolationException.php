<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Requête bien formée mais refusée par une règle métier (422).
 */
final class BusinessRuleViolationException extends BusinessException
{
    public function getType(): string
    {
        return '/errors/business-rule-violation';
    }

    public function getTitle(): string
    {
        return 'Business rule violation';
    }

    public function getStatus(): int
    {
        return 422;
    }
}
