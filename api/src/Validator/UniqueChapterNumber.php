<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * Un numéro de chapitre n'existe qu'une fois par série et par langue (issue #32).
 *
 * La contrainte UNIQUE de la table garantit la règle ; ce validateur la vérifie avant l'écriture
 * pour renvoyer une 422 lisible (RFC 7807) au lieu d'une erreur SQL.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class UniqueChapterNumber extends Constraint
{
    /** Clé du domaine `validators` (translations/validators.*.yaml). */
    public string $message = 'chapter.number.taken';

    public function __construct(?string $message = null, ?array $groups = null, mixed $payload = null)
    {
        parent::__construct(groups: $groups, payload: $payload);

        $this->message = $message ?? $this->message;
    }

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
