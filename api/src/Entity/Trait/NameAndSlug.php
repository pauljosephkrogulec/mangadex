<?php

declare(strict_types=1);

namespace App\Entity\Trait;

use App\Entity\SluggableInterface;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Nom affiché et slug unique, communs aux entités de classement du catalogue (auteurs, genres, tags).
 * Chaque entité pose la contrainte d'unicité du slug sur sa propre table.
 *
 * @phpstan-require-implements SluggableInterface
 */
trait NameAndSlug
{
    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Groups(['term:read', 'term:write', 'series:read'])]
    private string $name;

    /** Généré à la première persistance s'il n'est pas fourni (SlugListener). */
    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(allowNull: true)]
    #[Assert\Length(max: 255)]
    #[Assert\Regex(pattern: SluggableInterface::SLUG_PATTERN, message: 'Le slug ne contient que des minuscules, chiffres et tirets.')]
    #[Groups(['term:read', 'term:write', 'series:read'])]
    private ?string $slug = null;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getSlugSource(): string
    {
        return $this->name;
    }
}
