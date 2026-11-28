<?php

declare(strict_types=1);

namespace App\Factory;

use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Dérive le slug d'un champ texte (titre, nom) quand le test ne le fixe pas.
 * Provisoire : la génération automatique dans l'application arrive avec #33.
 */
trait DerivesSlug
{
    protected function deriveSlugFrom(string $field): static
    {
        return $this->beforeInstantiate(static function (array $parameters) use ($field): array {
            if (!isset($parameters['slug']) && \is_string($parameters[$field] ?? null)) {
                $parameters['slug'] = new AsciiSlugger()->slug($parameters[$field])->lower()->toString();
            }

            return $parameters;
        });
    }
}
