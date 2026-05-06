<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\RangeFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'chapter')]
#[ORM\Index(columns: ['language'])]
#[ORM\Index(columns: ['chapter_number'])]
#[ApiResource(
    normalizationContext: ['groups' => ['chapter:read']],
    denormalizationContext: ['groups' => ['chapter:write']],
    order: ['chapterNumber' => 'ASC'],
    operations: [
        new GetCollection(),
        new Get(),
        new Put(security: "is_granted('ROLE_ADMIN')"),
        new Delete(security: "is_granted('ROLE_ADMIN')"),
        new Patch(security: "is_granted('ROLE_ADMIN')"),
        new Post(security: "is_granted('ROLE_ADMIN')"),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'manga' => 'exact',
    'language' => 'exact',
    'volume' => 'exact',
])]
#[ApiFilter(OrderFilter::class, properties: ['chapterNumber', 'volume', 'language', 'createdAt'])]
#[ApiFilter(RangeFilter::class, properties: ['chapterNumber'])]
class Chapter
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['chapter:read'])]
    private ?int $id = null;

    #[ORM\Column(type: 'datetime')]
    #[Groups(['chapter:read'])]
    private \DateTime $createdAt;

    #[ORM\ManyToOne(targetEntity: Manga::class, inversedBy: 'chapters')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['chapter:read', 'chapter:write'])]
    private Manga $manga;

    #[ORM\ManyToOne(targetEntity: ScanlationGroup::class, inversedBy: 'chapters')]
    #[Groups(['chapter:read', 'chapter:write'])]
    private ?ScanlationGroup $scanlationGroup = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Length(max: 50)]
    #[Groups(['chapter:read', 'chapter:write'])]
    private ?string $volume = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 50)]
    #[Groups(['chapter:read', 'chapter:write'])]
    private string $chapterNumber;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    #[Groups(['chapter:read', 'chapter:write'])]
    private ?string $title = null;

    #[ORM\Column(length: 10)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 10)]
    #[Groups(['chapter:read', 'chapter:write'])]
    private string $language;

    /** @var array<string> */
    #[ORM\Column(type: 'json')]
    #[Assert\NotNull]
    #[Assert\Count(min: 1)]
    #[Groups(['chapter:read', 'chapter:write'])]
    private array $pages = [];

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
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

    public function getScanlationGroup(): ?ScanlationGroup
    {
        return $this->scanlationGroup;
    }

    public function setScanlationGroup(?ScanlationGroup $scanlationGroup): static
    {
        $this->scanlationGroup = $scanlationGroup;
        return $this;
    }

    public function getVolume(): ?string
    {
        return $this->volume;
    }

    public function setVolume(?string $volume): static
    {
        $this->volume = $volume;
        return $this;
    }

    public function getChapterNumber(): string
    {
        return $this->chapterNumber;
    }

    public function setChapterNumber(string $chapterNumber): static
    {
        $this->chapterNumber = $chapterNumber;
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

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function setLanguage(string $language): static
    {
        $this->language = $language;
        return $this;
    }

    /**
     * @return array<string>
     */
    public function getPages(): array
    {
        return $this->pages;
    }

    /**
     * @param array<string> $pages
     */
    public function setPages(array $pages): static
    {
        $this->pages = $pages;
        return $this;
    }
}
