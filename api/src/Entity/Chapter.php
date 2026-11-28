<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\ExactFilter;
use ApiPlatform\Doctrine\Orm\Filter\IriFilter;
use ApiPlatform\Doctrine\Orm\Filter\SortFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use App\Entity\Trait\Timestampable;
use App\Entity\Trait\UuidV7Id;
use App\Enum\ChapterStatus;
use App\Repository\ChapterRepository;
use App\Security\AccessRule;
use App\Validator\UniqueChapterNumber;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Chapitre d'une série, dans une langue donnée (issue #29).
 *
 * Le numéro est décimal (10.5 pour un chapitre bonus) et unique par série et par langue :
 * contrainte UNIQUE en base, vérifiée en amont par le validateur UniqueChapterNumber (#32).
 */
#[ORM\Entity(repositoryClass: ChapterRepository::class)]
#[ORM\UniqueConstraint(name: 'chapter_series_language_number_uniq', columns: ['series_id', 'language', 'number'])]
// Flux « derniers chapitres parus ».
#[ORM\Index(name: 'chapter_published_at_idx', columns: ['published_at'])]
#[UniqueChapterNumber]
#[ApiResource(
    operations: [
        new GetCollection(parameters: [
            // Chapitres d'une série : `?series=/api/series/<uuid>`.
            'series' => new QueryParameter(filter: new IriFilter(), property: 'series'),
            'language' => new QueryParameter(filter: new ExactFilter(), property: 'language'),
            'status' => new QueryParameter(filter: new ExactFilter(), property: 'status'),
            'order[number]' => new QueryParameter(filter: new SortFilter(), property: 'number'),
            'order[publishedAt]' => new QueryParameter(filter: new SortFilter(), property: 'publishedAt'),
        ]),
        new Get(),
        new Post(security: AccessRule::ADMIN),
        new Patch(security: AccessRule::ADMIN),
        new Delete(security: AccessRule::ADMIN),
    ],
    normalizationContext: ['groups' => ['common:read', 'timestamps:read', 'chapter:read']],
    denormalizationContext: ['groups' => ['chapter:write']],
    order: ['publishedAt' => 'DESC'],
)]
class Chapter implements TimestampableInterface
{
    use Timestampable;
    use UuidV7Id;

    /** Code de langue BCP 47 simplifié : `fr`, `en`, `pt-BR`. Validé contre la liste ICU avec #34. */
    public const string LANGUAGE_PATTERN = '/^[a-z]{2,3}(?:-[A-Z]{2})?$/';

    /** Numéro décimal positif : `1`, `10.5`, `1084`. */
    public const string NUMBER_PATTERN = '/^\d{1,6}(?:\.\d{1,2})?$/';

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    #[Groups(['chapter:read', 'chapter:write'])]
    private ?string $title = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    #[Assert\Positive]
    #[Groups(['chapter:read', 'chapter:write'])]
    private ?int $volume = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['chapter:read', 'chapter:write'])]
    private ?\DateTimeImmutable $publishedAt = null;

    #[ORM\Column(length: 16, enumType: ChapterStatus::class)]
    #[Groups(['chapter:read', 'chapter:write'])]
    private ChapterStatus $status = ChapterStatus::Draft;

    public function __construct(
        // Toujours un IRI, jamais l'objet embarqué.
        #[ApiProperty(readableLink: false, writableLink: false)]
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        #[Groups(['chapter:read', 'chapter:write'])]
        private Series $series,
        /** Chaîne numérique (DECIMAL côté PostgreSQL) : évite les arrondis des flottants. */
        #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2)]
        #[Assert\NotBlank]
        #[Assert\Regex(pattern: self::NUMBER_PATTERN, message: 'Le numéro de chapitre est un nombre positif (deux décimales au plus).')]
        #[Groups(['chapter:read', 'chapter:write'])]
        private string $number,
        #[ORM\Column(length: 8)]
        #[Assert\NotBlank]
        #[Assert\Regex(pattern: self::LANGUAGE_PATTERN, message: 'La langue est un code comme « fr », « en » ou « pt-BR ».')]
        #[Groups(['chapter:read', 'chapter:write'])]
        private string $language,
    ) {
    }

    public function getSeries(): Series
    {
        return $this->series;
    }

    /**
     * Forme canonique, sans zéros décimaux inutiles : PostgreSQL renvoie `10.50`, l'API expose `10.5`.
     */
    public function getNumber(): string
    {
        return str_contains($this->number, '.') ? mb_rtrim(mb_rtrim($this->number, '0'), '.') : $this->number;
    }

    public function setNumber(string $number): static
    {
        $this->number = $number;

        return $this;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function setLanguage(string $language): static
    {
        $this->language = $language;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getVolume(): ?int
    {
        return $this->volume;
    }

    public function setVolume(?int $volume): static
    {
        $this->volume = $volume;

        return $this;
    }

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?\DateTimeImmutable $publishedAt): static
    {
        $this->publishedAt = $publishedAt;

        return $this;
    }

    public function getStatus(): ChapterStatus
    {
        return $this->status;
    }

    public function setStatus(ChapterStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    /**
     * Un chapitre programmé ou publié a forcément une date de publication.
     */
    #[Assert\Callback]
    public function validatePublication(ExecutionContextInterface $context): void
    {
        if (ChapterStatus::Draft !== $this->status && !$this->publishedAt instanceof \DateTimeImmutable) {
            $context->buildViolation('Un chapitre programmé ou publié doit avoir une date de publication.')
                ->atPath('publishedAt')
                ->addViolation();
        }
    }
}
