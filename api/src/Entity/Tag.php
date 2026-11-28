<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\PartialSearchFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use App\Entity\Trait\NameAndSlug;
use App\Entity\Trait\Timestampable;
use App\Entity\Trait\UuidV7Id;
use App\Repository\TagRepository;
use App\Security\AccessRule;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * Thème ou caractéristique d'une série (isekai, voyage dans le temps, couleur…).
 * Relié aux séries par une relation N-N portée par Series (issue #28).
 */
#[ORM\Entity(repositoryClass: TagRepository::class)]
#[ORM\UniqueConstraint(name: 'tag_slug_uniq', columns: ['slug'])]
#[ApiResource(
    operations: [
        new GetCollection(parameters: [
            'name' => new QueryParameter(filter: new PartialSearchFilter(), property: 'name'),
        ]),
        new Get(),
        new Post(security: AccessRule::ADMIN),
        new Patch(security: AccessRule::ADMIN),
        new Delete(security: AccessRule::ADMIN),
    ],
    normalizationContext: ['groups' => ['common:read', 'timestamps:read', 'term:read']],
    denormalizationContext: ['groups' => ['term:write']],
    order: ['name' => 'ASC'],
)]
#[UniqueEntity(fields: ['slug'], message: 'Ce slug est déjà utilisé par un autre tag.')]
class Tag implements SluggableInterface, TimestampableInterface
{
    use NameAndSlug;
    use Timestampable;
    use UuidV7Id;

    public function __construct(string $name)
    {
        $this->name = $name;
    }
}
