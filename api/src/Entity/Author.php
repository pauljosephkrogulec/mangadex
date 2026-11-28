<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Entity\Trait\NameAndSlug;
use App\Entity\Trait\Timestampable;
use App\Entity\Trait\UuidV7Id;
use App\Repository\AuthorRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Auteur ou artiste d'une série (scénario et/ou dessin).
 * Relié aux séries par une relation N-N portée par Series (issue #28).
 */
#[ORM\Entity(repositoryClass: AuthorRepository::class)]
#[ORM\UniqueConstraint(name: 'author_slug_uniq', columns: ['slug'])]
#[ApiResource(
    operations: [new GetCollection(), new Get()],
    order: ['name' => 'ASC'],
)]
class Author implements SluggableInterface, TimestampableInterface
{
    use NameAndSlug;
    use Timestampable;
    use UuidV7Id;

    public function __construct(string $name)
    {
        $this->name = $name;
    }
}
