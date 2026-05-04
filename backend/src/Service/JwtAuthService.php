<?php

namespace App\Service;

class JwtAuthService
{
    public function isTokenValid(?string $token): bool
    {
        if ($token === null || $token === '') {
            return false;
        }

        return str_starts_with($token, 'eyJ');
    }

    public function extractTokenFromHeader(?string $authHeader): ?string
    {
        if ($authHeader === null) {
            return null;
        }

        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
