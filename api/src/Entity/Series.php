<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Common\Filter\OrderFilterInterface;
use ApiPlatform\Doctrine\Orm\Filter\ExactFilter;
use ApiPlatform\Doctrine\Orm\Filter\PartialSearchFilter;
use ApiPlatform\Doctrine\Orm\Filter\SortFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use App\Entity\Trait\Timestampable;
use App\Entity\Trait\UuidV7Id;
use App\Enum\SeriesStatus;
use App\Enum\SeriesType;
use App\HtmlSanitizer\SanitizeHtml;
use App\Repository\SeriesRepository;
use App\Security\AccessRule;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Série (manga, manhwa, manhua, webtoon), avec ses auteurs, artistes, genres et tags (relations N-N
 * unidirectionnelles). Le slug est généré à partir du titre à la première persistance (SlugListener).
 */
#[ORM\Entity(repositoryClass: SeriesRepository::class)]
// Tri par défaut de la collection (plus récents d'abord).
#[ORM\Index(name: 'series_created_at_idx', columns: ['created_at'])]
#[ORM\UniqueConstraint(name: 'series_slug_uniq', columns: ['slug'])]
#[ApiResource(
    operations: [
        new GetCollection(parameters: [
            // Recherche insensible à la casse dans le titre : `?title=piece`.
            'title' => new QueryParameter(filter: new PartialSearchFilter(), property: 'title'),
            'type' => new QueryParameter(filter: new ExactFilter(), property: 'type'),
            'status' => new QueryParameter(filter: new ExactFilter(), property: 'status'),
            // Par slug, plusieurs valeurs possibles : `?genre[]=action&genre[]=drame` (l'une OU l'autre).
            'genre' => new QueryParameter(filter: new ExactFilter(), property: 'genres.slug'),
            'tag' => new QueryParameter(filter: new ExactFilter(), property: 'tags.slug'),
            'author' => new QueryParameter(filter: new ExactFilter(), property: 'authors.slug'),
            'order[title]' => new QueryParameter(filter: new SortFilter(), property: 'title'),
            'order[year]' => new QueryParameter(filter: new SortFilter(nullsComparison: OrderFilterInterface::NULLS_ALWAYS_LAST), property: 'year'),
            'order[createdAt]' => new QueryParameter(filter: new SortFilter(), property: 'createdAt'),
        ]),
        new Get(normalizationContext: ['groups' => ['common:read', 'timestamps:read', 'series:read', 'series:item']]),
        new Post(normalizationContext: ['groups' => ['common:read', 'timestamps:read', 'series:read', 'series:item']], security: AccessRule::ADMIN),
        new Patch(normalizationContext: ['groups' => ['common:read', 'timestamps:read', 'series:read', 'series:item']], security: AccessRule::ADMIN),
        new Delete(security: AccessRule::ADMIN),
    ],
    // Liste : champs essentiels ; fiche (Get) : synopsis, titres alternatifs, auteurs, artistes et tags en plus.
    normalizationContext: ['groups' => ['common:read', 'series:read']],
    denormalizationContext: ['groups' => ['series:write']],
    order: ['createdAt' => 'DESC'],
)]
#[UniqueEntity(fields: ['slug'], message: 'slug.taken.series')]
class Series implements SluggableInterface, TimestampableInterface
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
    #[Groups(['series:item', 'series:write'])]
    private array $alternativeTitles = [];

    /** HTML limité (paragraphes, emphase, listes, liens externes), nettoyé avant l'écriture en base. */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[SanitizeHtml]
    #[Groups(['series:item', 'series:write'])]
    private ?string $synopsis = null;

    #[ORM\Column(length: 16, enumType: SeriesType::class)]
    #[Groups(['series:read', 'series:write'])]
    private SeriesType $type = SeriesType::Manga;

    #[ORM\Column(length: 16, enumType: SeriesStatus::class)]
    #[Groups(['series:read', 'series:write'])]
    private SeriesStatus $status = SeriesStatus::Ongoing;

    /** Année de première publication. */
    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    #[Assert\Range(min: self::MIN_YEAR, max: self::MAX_YEAR)]
    #[Groups(['series:read', 'series:write'])]
    private ?int $year = null;

    /** Clé de l'image de couverture dans le stockage objet (S3). */
    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    #[Groups(['series:read', 'series:write'])]
    private ?string $coverImage = null;

    /** Généré à partir du titre à la première persistance s'il n'est pas fourni (SlugListener). */
    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(allowNull: true)]
    #[Assert\Length(max: 255)]
    #[Assert\Regex(pattern: self::SLUG_PATTERN, message: 'slug.invalid')]
    #[Groups(['series:read', 'series:write'])]
    private ?string $slug = null;

    /**
     * Scénaristes.
     *
     * @var Collection<int, Author>
     */
    #[ORM\ManyToMany(targetEntity: Author::class)]
    #[ORM\JoinTable(name: 'series_author')]
    #[ORM\OrderBy(['name' => 'ASC'])]
    #[Groups(['series:item', 'series:write'])]
    private Collection $authors;

    /**
     * Dessinateurs : souvent les mêmes personnes que les auteurs, d'où la même entité Author.
     *
     * @var Collection<int, Author>
     */
    #[ORM\ManyToMany(targetEntity: Author::class)]
    #[ORM\JoinTable(name: 'series_artist')]
    #[ORM\OrderBy(['name' => 'ASC'])]
    #[Groups(['series:item', 'series:write'])]
    private Collection $artists;

    /**
     * @var Collection<int, Genre>
     */
    #[ORM\ManyToMany(targetEntity: Genre::class)]
    #[ORM\JoinTable(name: 'series_genre')]
    #[ORM\OrderBy(['name' => 'ASC'])]
    #[Groups(['series:read', 'series:write'])]
    private Collection $genres;

    /**
     * @var Collection<int, Tag>
     */
    #[ORM\ManyToMany(targetEntity: Tag::class)]
    #[ORM\JoinTable(name: 'series_tag')]
    #[ORM\OrderBy(['name' => 'ASC'])]
    #[Groups(['series:item', 'series:write'])]
    private Collection $tags;

    public function __construct(
        #[ORM\Column(length: 255)]
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        #[Groups(['series:read', 'series:write'])]
        private string $title,
    ) {
        $this->authors = new ArrayCollection();
        $this->artists = new ArrayCollection();
        $this->genres = new ArrayCollection();
        $this->tags = new ArrayCollection();
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
        return $this->title;
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
     * @return Collection<int, Author>
     */
    public function getAuthors(): Collection
    {
        return $this->authors;
    }

    public function addAuthor(Author $author): static
    {
        if (!$this->authors->contains($author)) {
            $this->authors->add($author);
        }

        return $this;
    }

    public function removeAuthor(Author $author): static
    {
        $this->authors->removeElement($author);

        return $this;
    }

    /**
     * @return Collection<int, Author>
     */
    public function getArtists(): Collection
    {
        return $this->artists;
    }

    public function addArtist(Author $artist): static
    {
        if (!$this->artists->contains($artist)) {
            $this->artists->add($artist);
        }

        return $this;
    }

    public function removeArtist(Author $artist): static
    {
        $this->artists->removeElement($artist);

        return $this;
    }

    /**
     * @return Collection<int, Genre>
     */
    public function getGenres(): Collection
    {
        return $this->genres;
    }

    public function addGenre(Genre $genre): static
    {
        if (!$this->genres->contains($genre)) {
            $this->genres->add($genre);
        }

        return $this;
    }

    public function removeGenre(Genre $genre): static
    {
        $this->genres->removeElement($genre);

        return $this;
    }

    /**
     * @return Collection<int, Tag>
     */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function addTag(Tag $tag): static
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
        }

        return $this;
    }

    public function removeTag(Tag $tag): static
    {
        $this->tags->removeElement($tag);

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
