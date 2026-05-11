<?php

declare(strict_types=1);

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
use App\Controller\CustomListMangaController;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'custom_list')]
#[ApiResource(
    operations: [
        new GetCollection(security: "is_granted('ROLE_ADMIN')"),
        new Get(security: "object.getUser() == user or is_granted('ROLE_ADMIN')"),
        new Put(security: "object.getUser() == user or is_granted('ROLE_ADMIN')"),
        new Delete(security: "object.getUser() == user or is_granted('ROLE_ADMIN')"),
        new Patch(security: "object.getUser() == user or is_granted('ROLE_ADMIN')"),
        new Post(security: "is_granted('IS_AUTHENTICATED_FULLY')"),
        new Post(
            uriTemplate: '/custom_lists/{id}/mangas/{mangaId}',
            controller: CustomListMangaController::class,
            security: "object.getUser() == user or is_granted('ROLE_ADMIN')",
            name: 'add_manga'
        ),
        new Delete(
            uriTemplate: '/custom_lists/{id}/mangas/{mangaId}',
            controller: CustomListMangaController::class,
            security: "object.getUser() == user or is_granted('ROLE_ADMIN')",
            name: 'remove_manga'
        ),
    ],
    normalizationContext: ['groups' => ['custom_list:read']],
    denormalizationContext: ['groups' => ['custom_list:write']],
    order: ['name' => 'ASC']
)]
#[ApiFilter(SearchFilter::class, properties: [
    'name' => 'partial',
    'visibility' => 'exact',
    'user' => 'exact',
])]
#[ApiFilter(OrderFilter::class, properties: ['name', 'visibility', 'createdAt'])]
class CustomList
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    #[Groups(['custom_list:read'])]
    private ?string $id = null;

    #[ORM\Column(type: 'datetime')]
    #[Groups(['custom_list:read'])]
    private \DateTime $createdAt;

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
    #[Groups(['custom_list:read'])]
    private ?User $user = null;

    #[ORM\ManyToMany(targetEntity: Manga::class, inversedBy: 'customLists')]
    #[ORM\JoinTable(name: 'custom_list_manga')]
    #[Groups(['custom_list:read'])]
    /** @var Collection<int, Manga> */
    private Collection $mangas;

    public function __construct()
    {
        $this->id = Uuid::v4()->toRfc4122();
        $this->createdAt = new \DateTime();
        $this->mangas = new ArrayCollection();
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

    public function getVisibility(): string
    {
        return $this->visibility;
    }

    /**
     * @return array<string>
     */
    public static function getVisibilityChoices(): array
    {
        return ['public', 'private', 'hidden'];
    }

    public function setVisibility(string $visibility): static
    {
        $this->visibility = $visibility;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @return Collection<int, Manga>
     */
    public function getMangas(): Collection
    {
        return $this->mangas;
    }

    public function addManga(Manga $manga): static
    {
        if (!$this->mangas->contains($manga)) {
            $this->mangas->add($manga);
        }

        return $this;
    }

    public function removeManga(Manga $manga): static
    {
        $this->mangas->removeElement($manga);

        return $this;
    }
}
