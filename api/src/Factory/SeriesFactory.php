<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Series;
use App\Enum\SeriesStatus;
use App\Enum\SeriesType;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * Fabrique Foundry (fixtures et tests). Dev/test uniquement : Foundry n'est pas installé en prod.
 *
 * @extends PersistentObjectFactory<Series>
 */
final class SeriesFactory extends PersistentObjectFactory
{
    use DerivesSlug;

    public static function class(): string
    {
        return Series::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            'title' => mb_rtrim(self::faker()->unique()->sentence(3), '.'),
            'alternativeTitles' => self::faker()->optional(0.5, [])->words(2),
            'synopsis' => self::faker()->optional(0.8)->paragraph(),
            'type' => self::faker()->randomElement(SeriesType::cases()),
            'status' => self::faker()->randomElement(SeriesStatus::cases()),
            'year' => self::faker()->optional(0.9)->numberBetween(1970, 2026),
            'coverImage' => self::faker()->boolean(70) ? 'covers/'.self::faker()->uuid().'.webp' : null,
        ];
    }

    protected function initialize(): static
    {
        return $this->deriveSlugFrom('title');
    }
}
