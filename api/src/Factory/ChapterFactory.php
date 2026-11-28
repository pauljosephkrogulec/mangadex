<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Chapter;
use App\Enum\ChapterStatus;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * Fabrique Foundry (fixtures et tests). Dev/test uniquement : Foundry n'est pas installé en prod.
 *
 * @extends PersistentObjectFactory<Chapter>
 */
final class ChapterFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Chapter::class;
    }

    public function draft(): static
    {
        return $this->with(['status' => ChapterStatus::Draft, 'publishedAt' => null]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            'series' => SeriesFactory::new(),
            // Numéro unique pour éviter les collisions sur (série, langue, numéro) entre chapitres générés.
            'number' => (string) self::faker()->unique()->numberBetween(1, 99999),
            'language' => self::faker()->randomElement(['fr', 'en']),
            'title' => self::faker()->optional(0.6)->sentence(4),
            'volume' => self::faker()->optional(0.5)->numberBetween(1, 100),
            'status' => ChapterStatus::Published,
            'publishedAt' => \DateTimeImmutable::createFromMutable(self::faker()->dateTimeBetween('-5 years')),
        ];
    }
}
