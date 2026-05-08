<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'creator')]
#[ORM\Index(columns: ['type'])]
#[ApiResource(
    normalizationContext: ['groups' => ['creator:read']],
    denormalizationContext: ['groups' => ['creator:write']],
    order: ['name' => 'ASC']
)]
#[ApiFilter(SearchFilter::class, properties: [
    'name' => 'partial',
    'type' => 'exact',
])]
#[ApiFilter(OrderFilter::class, properties: ['name', 'type', 'createdAt'])]
class Creator
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    #[Groups(['creator:read', 'manga:read'])]
    private ?string $id = null;

    #[ORM\Column(type: 'datetime')]
    #[Groups(['creator:read'])]
    private \DateTime $createdAt;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Groups(['creator:read', 'creator:write', 'manga:read'])]
    private string $name;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank]
    #[Assert\Choice(callback: ['App\Entity\Creator', 'getTypeChoices'])]
    #[Groups(['creator:read', 'creator:write', 'manga:read'])]
    private string $type;

    #[ORM\ManyToMany(targetEntity: Manga::class, mappedBy: 'creators')]
    #[Groups(['creator:read'])]
    /** @var Collection<int, Manga> */
    private Collection $manga;

    public function __construct()
    {
        $this->id = Uuid::v4()->toRfc4122();
        $this->createdAt = new \DateTime();
        $this->manga = new ArrayCollection();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return array<string>
     */
    public static function getTypeChoices(): array
    {
        return ['author', 'artist'];
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    /**
     * @return Collection<int, Manga>
     */
    public function getManga(): Collection
    {
        return $this->manga;
    }

    public function addManga(Manga $manga): static
    {
        if (! $this->manga->contains($manga)) {
            $this->manga->add($manga);
            if (! $manga->getCreators()->contains($this)) {
                $manga->getCreators()->add($this);
            }
        }
        return $this;
    }

    public function removeManga(Manga $manga): static
    {
        if ($this->manga->removeElement($manga)) {
            $manga->removeCreator($this);
        }
        return $this;
    }
}
