<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Entité exposée dans les URL par un slug unique (minuscules ASCII, chiffres et tirets).
 *
 * Sans slug fourni, App\Doctrine\EventListener\SlugListener en génère un à la première persistance,
 * à partir de getSlugSource() (issue #33). Il ne change plus ensuite, même si le titre change :
 * les URL partagées restent valides.
 */
interface SluggableInterface
{
    /** Format attendu d'un slug : `one-piece`, `shonen`, `eiichiro-oda`. */
    public const string SLUG_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    /** Null tant que l'entité n'a pas été persistée sans slug explicite. */
    public function getSlug(): ?string;

    public function setSlug(string $slug): static;

    /** Texte dont le slug est dérivé : titre d'une série, nom d'un auteur, d'un genre… */
    public function getSlugSource(): string;
}
