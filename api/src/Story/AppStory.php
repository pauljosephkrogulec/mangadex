<?php

declare(strict_types=1);

namespace App\Story;

use App\Factory\SeriesFactory;
use Zenstruck\Foundry\Story;

/**
 * Jeu de données de dev, chargé par `make db-reset` (doctrine:fixtures:load → AppFixtures).
 */
final class AppStory extends Story
{
    public function build(): void
    {
        // Plus d'une page (30 par défaut) pour voir la pagination.
        SeriesFactory::createMany(45);
    }
}
