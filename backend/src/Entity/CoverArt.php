<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'cover_art')]
#[ORM\Index(columns: ['volume'])]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Put(security: "is_granted('ROLE_ADMIN')"),
        new Delete(security: "is_granted('ROLE_ADMIN')"),
        new Patch(security: "is_granted('ROLE_ADMIN')"),
        new Post(security: "is_granted('ROLE_ADMIN')"),
    ],
    normalizationContext: ['groups' => ['cover_art:read']],
    denormalizationContext: ['groups' => ['cover_art:write']],
    order: ['volume' => 'ASC']
)]
#[ApiFilter(SearchFilter::class, properties: [
    'manga' => 'exact',
    'volume' => 'exact',
    'isPrimary' => 'exact',
])]
#[ApiFilter(OrderFilter::class, properties: ['volume', 'isPrimary', 'createdAt'])]
class CoverArt
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['cover_art:read', 'manga:read'])]
    private ?int $id = null;

    #[ORM\Column(type: 'datetime')]
    #[Groups(['cover_art:read'])]
    private \DateTime $createdAt;

    #[ORM\ManyToOne(targetEntity: Manga::class, inversedBy: 'coverArts')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['cover_art:read', 'cover_art:write'])]
    private Manga $manga;

    #[ORM\Column(length: 500)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 500)]
    #[Groups(['cover_art:read', 'cover_art:write', 'manga:read'])]
    private string $imagePath;

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Length(max: 50)]
    #[Groups(['cover_art:read', 'cover_art:write', 'manga:read'])]
    private ?string $volume = null;

    #[ORM\Column(name: 'is_primary', type: 'boolean', options: ['default' => false])]
    #[Groups(['cover_art:read', 'cover_art:write', 'manga:read'])]
    private bool $isPrimary = false;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function getManga(): Manga
    {
        return $this->manga;
    }

    public function setManga(Manga $manga): static
    {
        $this->manga = $manga;
        return $this;
    }

    public function getImagePath(): string
    {
        return $this->imagePath;
    }

    public function setImagePath(string $imagePath): static
    {
        $this->imagePath = $imagePath;
        return $this;
    }

    public function getVolume(): ?string
    {
        return $this->volume;
    }

    public function setVolume(?string $volume): static
    {
        $this->volume = $volume;
        return $this;
    }

    public function isPrimary(): bool
    {
        return $this->isPrimary;
    }

    public function setIsPrimary(bool $isPrimary): static
    {
        $this->isPrimary = $isPrimary;
        return $this;
    }
}
