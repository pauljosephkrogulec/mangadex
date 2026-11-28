<?php

declare(strict_types=1);

namespace App\Security;

/**
 * Expressions `security:` partagées par les opérations API Platform (issue #31).
 * Les rôles fins (UPLOADER, MODERATOR) et les Voters arrivent avec #38, les expressions métier avec #39.
 */
final class AccessRule
{
    /** Écriture du catalogue : réservée aux administrateurs tant que les rôles de #38 n'existent pas. */
    public const string ADMIN = "is_granted('ROLE_ADMIN')";

    private function __construct()
    {
    }
}
