<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'rating')]
#[ORM\UniqueConstraint(columns: ['user_id', 'manga_id'])]
class Rating
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Manga::class, inversedBy: 'ratings')]
    #[ORM\JoinColumn(nullable: false)]
    private Manga $manga;

    #[ORM\Column(type: 'integer')]
    #[Assert\Range(min: 1, max: 10)]
    private int $score;

    #[ORM\Column(type: 'datetime')]
    private \DateTime $ratedAt;

    public function __construct()
    {
        $this->id = Uuid::v4()->toRfc4122();
        $this->ratedAt = new \DateTime();
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

    public function getScore(): int
    {
        return $this->score;
    }

    public function setScore(int $score): static
    {
        $this->score = $score;

        return $this;
    }

    public function getRatedAt(): \DateTime
    {
        return $this->ratedAt;
    }
}
