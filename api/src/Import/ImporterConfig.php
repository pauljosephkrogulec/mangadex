<?php

declare(strict_types=1);

namespace App\Import;

/**
 * Réglages d'une source d'import, validés (cf. ImportersConfig).
 */
final readonly class ImporterConfig
{
    /**
     * @param non-empty-string       $name
     * @param non-empty-string       $baseUrl   sans « / » final
     * @param list<non-empty-string> $languages codes ISO 639-1
     */
    public function __construct(
        public string $name,
        public bool $enabled,
        public string $baseUrl,
        public float $requestsPerSecond,
        public int $timeout,
        public array $languages,
    ) {
    }
}
