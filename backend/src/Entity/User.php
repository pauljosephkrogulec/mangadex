<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Dto\UserRegistrationDto;
use App\Dto\UserUpdateDto;
use App\State\Processor\UserRegistrationProcessor;
use App\State\Processor\UserUpdateProcessor;
use App\State\Provider\UserFollowsProvider;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'app_user')]
#[ApiResource(
    normalizationContext: ['groups' => ['user:read']],
    denormalizationContext: ['groups' => ['user:write']],
    operations: [
        new GetCollection(security: "is_granted('ROLE_ADMIN')"),
        new Get(security: "object == user or is_granted('ROLE_ADMIN')"),
        new Put(
            security: "object == user or is_granted('ROLE_ADMIN')",
            input: UserUpdateDto::class,
            processor: UserUpdateProcessor::class
        ),
        new Delete(security: "object == user or is_granted('ROLE_ADMIN')"),
        new Post(
            security: "is_granted('IS_AUTHENTICATED_ANONYMOUSLY')",
            input: UserRegistrationDto::class,
            processor: UserRegistrationProcessor::class
        ),
        new GetCollection(
            uriTemplate: '/users/{id}/follows',
            provider: UserFollowsProvider::class,
            normalizationContext: ['groups' => ['follow:read']],
            security: "object == user or is_granted('ROLE_ADMIN')",
            name: 'follows'
        ),
    ]
)]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    #[Groups(['user:read'])]
    private ?string $id = null;

    #[ORM\Column(type: 'datetime')]
    #[Groups(['user:read'])]
    private \DateTime $createdAt;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Groups(['user:read', 'user:write'])]
    private string $email;

    #[ORM\Column(length: 255, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 3, max: 255)]
    #[Groups(['user:read', 'user:write'])]
    private string $username;

    /** @var array<string> */
    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private string $password;

    #[ORM\OneToMany(targetEntity: CustomList::class, mappedBy: 'user', cascade: ['persist', 'remove'])]
    #[Groups(['user:read'])]
    /** @var Collection<int, CustomList> */
    private Collection $customLists;

    #[ORM\OneToMany(targetEntity: MangaFollow::class, mappedBy: 'user', cascade: ['persist', 'remove'])]
    /** @var Collection<int, MangaFollow> */
    private Collection $followedMangas;

    public function __construct()
    {
        $this->id = Uuid::v4()->toRfc4122();
        $this->createdAt = new \DateTime();
        $this->customLists = new ArrayCollection();
        $this->followedMangas = new ArrayCollection();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;
        return $this;
    }

    /**
     * @return non-empty-string
     */
    public function getUserIdentifier(): string
    {
        /** @var non-empty-string $email */
        $email = $this->email;
        return $email;
    }

    /**
     * @return array<string>
     */
    public function getRoles(): array
    {
        /** @var array<string> $roles */
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';
        /** @var array<string> $uniqueRoles */
        $uniqueRoles = array_unique($roles);
        return $uniqueRoles;
    }

    /**
     * @param array<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;
        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
    }

    /**
     * @return Collection<int, MangaFollow>
     */
    public function getFollowedMangas(): Collection
    {
        return $this->followedMangas;
    }

    public function addFollowedManga(MangaFollow $follow): static
    {
        if (! $this->followedMangas->contains($follow)) {
            $this->followedMangas->add($follow);
            $follow->setUser($this);
        }
        return $this;
    }

    public function removeFollowedManga(MangaFollow $follow): static
    {
        if ($this->followedMangas->removeElement($follow)) {
            // Note: MangaFollow has nullable=false on user, so we don't set to null
        }
        return $this;
    }
}
