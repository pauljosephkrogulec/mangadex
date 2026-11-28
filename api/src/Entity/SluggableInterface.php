<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Entité exposée dans les URL par un slug unique (minuscules ASCII, chiffres et tirets).
 */
interface SluggableInterface
{
    /** Format attendu d'un slug : `one-piece`, `shonen`, `eiichiro-oda`. */
    public const string SLUG_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    public function getSlug(): string;

    public function setSlug(string $slug): static;
}
