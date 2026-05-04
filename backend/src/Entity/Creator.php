<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Attribute\Groups;
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
    'type' => 'exact'
])]
#[ApiFilter(OrderFilter::class, properties: ['name', 'type'])]
class Creator
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['creator:read', 'manga:read'])]
    private ?int $id = null;

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

    #[ORM\ManyToMany(targetEntity: Manga::class, mappedBy: 'creators', fetch: 'EAGER')]
    #[Groups(['creator:read'])]
    private Collection $manga;

    public function __construct()
    {
        $this->manga = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public static function getTypeChoices(): array
    {
        return ['author', 'artist'];
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getManga(): Collection
    {
        return $this->manga;
    }

    public function addManga(Manga $manga): static
    {
        if (!$this->manga->contains($manga)) {
            $this->manga->add($manga);
            if (!$manga->getCreators()->contains($this)) {
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
