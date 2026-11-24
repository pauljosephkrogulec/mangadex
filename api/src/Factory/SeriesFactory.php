<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Series;
use App\Enum\SeriesStatus;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * Fabrique Foundry (fixtures et tests). Dev/test uniquement : Foundry n'est pas installé en prod.
 *
 * @extends PersistentObjectFactory<Series>
 */
final class SeriesFactory extends PersistentObjectFactory
{
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
            'description' => self::faker()->optional(0.8)->paragraph(),
            'status' => self::faker()->randomElement(SeriesStatus::cases()),
        ];
    }
}
