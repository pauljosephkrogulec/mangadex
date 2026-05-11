<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'manga_follow')]
#[ORM\UniqueConstraint(columns: ['user_id', 'manga_id'])]
class MangaFollow
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    #[Groups(['follow:read'])]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'followedMangas')]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Manga::class, inversedBy: 'followers')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['follow:read'])]
    private Manga $manga;

    #[ORM\Column(type: 'datetime')]
    #[Groups(['follow:read'])]
    private \DateTime $followedAt;

    public function __construct()
    {
        $this->id = Uuid::v4()->toRfc4122();
        $this->followedAt = new \DateTime();
    }

    public function getId(): ?string
    {
        return $this->id;
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

    public function getManga(): Manga
    {
        return $this->manga;
    }

    public function setManga(Manga $manga): static
    {
        $this->manga = $manga;

        return $this;
    }

    public function getFollowedAt(): \DateTime
    {
        return $this->followedAt;
    }
}
