<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\State\Processor\CommentProcessor;
use App\State\Provider\MangaCommentsProvider;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'comment')]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/mangas/{id}/comments',
            provider: MangaCommentsProvider::class,
        ),
        new Post(
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            processor: CommentProcessor::class,
        ),
        new Delete(security: "object.getUser() == user or is_granted('ROLE_ADMIN')"),
    ],
    normalizationContext: ['groups' => ['comment:read']],
    denormalizationContext: ['groups' => ['comment:write']],
    order: ['createdAt' => 'DESC'],
)]
class Comment
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    #[Groups(['comment:read'])]
    private ?string $id = null;

    #[ORM\Column(type: 'datetime')]
    #[Groups(['comment:read'])]
    private \DateTime $createdAt;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank]
    #[Assert\Length(max: 2000)]
    #[Groups(['comment:read', 'comment:write'])]
    private string $content;

    #[ORM\ManyToOne(targetEntity: Manga::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['comment:write'])]
    private ?Manga $manga = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['comment:read'])]
    private ?User $user = null;

    public function __construct()
    {
        $this->id = Uuid::v4()->toRfc4122();
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function getManga(): ?Manga
    {
        return $this->manga;
    }

    public function setManga(Manga $manga): static
    {
        $this->manga = $manga;

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
}
