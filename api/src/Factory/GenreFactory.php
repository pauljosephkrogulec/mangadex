<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Genre;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * Fabrique Foundry (fixtures et tests). Dev/test uniquement : Foundry n'est pas installé en prod.
 *
 * @extends PersistentObjectFactory<Genre>
 */
final class GenreFactory extends PersistentObjectFactory
{
    use DerivesSlug;

    public static function class(): string
    {
        return Genre::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            'name' => mb_ucfirst(self::faker()->unique()->word()),
        ];
    }

    protected function initialize(): static
    {
        return $this->deriveSlugFrom('name');
    }
}
