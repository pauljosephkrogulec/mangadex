<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Entity\Trait\Timestampable;
use App\Entity\Trait\UuidV7Id;
use App\Enum\SeriesStatus;
use App\Enum\SeriesType;
use App\Repository\SeriesRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Série (manga, manhwa, manhua, webtoon). Auteurs, genres et tags arrivent avec #28, la génération
 * automatique du slug avec #33, l'écriture (CRUD) avec #31.
 */
#[ORM\Entity(repositoryClass: SeriesRepository::class)]
// Tri par défaut de la collection (plus récents d'abord).
#[ORM\Index(name: 'series_created_at_idx', columns: ['created_at'])]
#[ORM\UniqueConstraint(name: 'series_slug_uniq', columns: ['slug'])]
#[ApiResource(
    operations: [new GetCollection(), new Get()],
    order: ['createdAt' => 'DESC'],
)]
class Series implements TimestampableInterface
{
    use Timestampable;
    use UuidV7Id;

    public const int MIN_YEAR = 1900;
    public const int MAX_YEAR = 2100;

    /**
     * Titres alternatifs (romanisation, titre original, traductions), sans doublon ni le titre principal.
     *
     * @var list<string>
     */
    #[ORM\Column(type: Types::JSON, options: ['jsonb' => true, 'default' => '[]'])]
    #[Assert\All([new Assert\NotBlank(), new Assert\Length(max: 255)])]
    private array $alternativeTitles = [];

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $synopsis = null;

    #[ORM\Column(length: 16, enumType: SeriesType::class)]
    private SeriesType $type = SeriesType::Manga;

    #[ORM\Column(length: 16, enumType: SeriesStatus::class)]
    private SeriesStatus $status = SeriesStatus::Ongoing;

    /** Année de première publication. */
    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    #[Assert\Range(min: self::MIN_YEAR, max: self::MAX_YEAR)]
    private ?int $year = null;

    /** Clé de l'image de couverture dans le stockage objet (S3). */
    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $coverImage = null;

    public function __construct(
        #[ORM\Column(length: 255)]
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        private string $title,
        #[ORM\Column(length: 255)]
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        #[Assert\Regex(pattern: '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', message: 'Le slug ne contient que des minuscules, chiffres et tirets.')]
        private string $slug,
    ) {
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        $this->alternativeTitles = $this->normalizeAlternativeTitles($this->alternativeTitles);

        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getAlternativeTitles(): array
    {
        return $this->alternativeTitles;
    }

    /**
     * @param array<string> $alternativeTitles
     */
    public function setAlternativeTitles(array $alternativeTitles): static
    {
        $this->alternativeTitles = $this->normalizeAlternativeTitles($alternativeTitles);

        return $this;
    }

    public function getSynopsis(): ?string
    {
        return $this->synopsis;
    }

    public function setSynopsis(?string $synopsis): static
    {
        $this->synopsis = $synopsis;

        return $this;
    }

    public function getType(): SeriesType
    {
        return $this->type;
    }

    public function setType(SeriesType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getStatus(): SeriesStatus
    {
        return $this->status;
    }

    public function setStatus(SeriesStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getYear(): ?int
    {
        return $this->year;
    }

    public function setYear(?int $year): static
    {
        $this->year = $year;

        return $this;
    }

    public function getCoverImage(): ?string
    {
        return $this->coverImage;
    }

    public function setCoverImage(?string $coverImage): static
    {
        $this->coverImage = $coverImage;

        return $this;
    }

    /**
     * Supprime les espaces superflus, les entrées vides, les doublons (insensibles à la casse)
     * et le titre principal.
     *
     * @param array<string> $titles
     *
     * @return list<string>
     */
    private function normalizeAlternativeTitles(array $titles): array
    {
        $seen = [mb_strtolower(mb_trim($this->title)) => true];
        $normalized = [];

        foreach ($titles as $title) {
            $title = mb_trim($title);
            $key = mb_strtolower($title);

            if ('' === $title || isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $normalized[] = $title;
        }

        return $normalized;
    }
}
