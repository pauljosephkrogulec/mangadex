<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Tag;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * Fabrique Foundry (fixtures et tests). Dev/test uniquement : Foundry n'est pas installé en prod.
 *
 * @extends PersistentObjectFactory<Tag>
 */
final class TagFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Tag::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            'name' => mb_ucfirst(self::faker()->unique()->word().' '.self::faker()->word()),
        ];
    }
}
