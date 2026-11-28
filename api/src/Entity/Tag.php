<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Entity\Trait\NameAndSlug;
use App\Entity\Trait\Timestampable;
use App\Entity\Trait\UuidV7Id;
use App\Repository\TagRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Thème ou caractéristique d'une série (isekai, voyage dans le temps, couleur…).
 * Relié aux séries par une relation N-N portée par Series (issue #28).
 */
#[ORM\Entity(repositoryClass: TagRepository::class)]
#[ORM\UniqueConstraint(name: 'tag_slug_uniq', columns: ['slug'])]
#[ApiResource(
    operations: [new GetCollection(), new Get()],
    order: ['name' => 'ASC'],
)]
class Tag implements SluggableInterface, TimestampableInterface
{
    use NameAndSlug;
    use Timestampable;
    use UuidV7Id;

    public function __construct(string $name, string $slug)
    {
        $this->name = $name;
        $this->slug = $slug;
    }
}
