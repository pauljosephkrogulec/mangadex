<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Attribute\Groups;
#[ORM\Entity]
#[ORM\Table(name: 'custom_list')]
#[ApiResource(
    normalizationContext: ['groups' => ['custom_list:read']],
    denormalizationContext: ['groups' => ['custom_list:write']],
    order: ['name' => 'ASC'],
    operations: [
        new GetCollection(security: "is_granted('ROLE_ADMIN')"),
        new Get(security: "object.getUser() == user or is_granted('ROLE_ADMIN')"),
        new Put(security: "object.getUser() == user or is_granted('ROLE_ADMIN')"),
        new Delete(security: "object.getUser() == user or is_granted('ROLE_ADMIN')"),
        new Patch(security: "object.getUser() == user or is_granted('ROLE_ADMIN')"),
        new Post(security: "is_granted('IS_AUTHENTICATED_FULLY')")
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'name' => 'partial',
    'visibility' => 'exact',
    'user' => 'exact'
])]
#[ApiFilter(OrderFilter::class, properties: ['name', 'visibility'])]
class CustomList
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['custom_list:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Groups(['custom_list:read', 'custom_list:write'])]
    private string $name;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank]
    #[Assert\Choice(callback: ['App\Entity\CustomList', 'getVisibilityChoices'])]
    #[Groups(['custom_list:read', 'custom_list:write'])]
    private string $visibility;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'customLists')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['custom_list:read', 'custom_list:write'])]
    private User $user;

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

    public function getVisibility(): string
    {
        return $this->visibility;
    }

    public static function getVisibilityChoices(): array
    {
        return ['public', 'private', 'hidden'];
    }

    public function setVisibility(string $visibility): static
    {
        $this->visibility = $visibility;
        return $this;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;
        return $this;
    }
}
