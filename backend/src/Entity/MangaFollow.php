<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'manga_follow')]
#[ORM\UniqueConstraint(columns: ['user_id', 'manga_id'])]
class MangaFollow
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['follow:read'])]
    private ?int $id = null;

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
        $this->followedAt = new \DateTime();
    }

    public function getId(): ?int
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
