<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Author;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * Fabrique Foundry (fixtures et tests). Dev/test uniquement : Foundry n'est pas installé en prod.
 *
 * @extends PersistentObjectFactory<Author>
 */
final class AuthorFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Author::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            'name' => self::faker()->unique()->name(),
        ];
    }
}
