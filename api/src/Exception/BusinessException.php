<?php

declare(strict_types=1);

namespace App\Exception;

use ApiPlatform\Metadata\Exception\ProblemExceptionInterface;

/**
 * Exception métier rendue en problem+json (RFC 7807, issue #24) par l'ErrorListener d'API Platform.
 *
 * Chaque sous-classe fixe un type de problème stable (`type`, `title`, `status`) ; le message de
 * l'exception devient le `detail`. Il est montré au client, même en prod : il ne doit contenir
 * aucune donnée interne (SQL, chemin, secret…).
 */
abstract class BusinessException extends \RuntimeException implements ProblemExceptionInterface
{
    public function __construct(string $detail, ?\Throwable $previous = null)
    {
        parent::__construct($detail, 0, $previous);
    }

    /**
     * URI du type de problème, ex. `/errors/conflict`.
     */
    abstract public function getType(): string;

    abstract public function getTitle(): string;

    abstract public function getStatus(): int;

    public function getDetail(): string
    {
        return $this->getMessage();
    }

    public function getInstance(): ?string
    {
        return null;
    }
}
