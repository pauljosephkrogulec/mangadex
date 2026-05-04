<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\RangeFilter;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Attribute\Groups;
#[ORM\Entity]
#[ORM\Table(name: 'chapter')]
#[ORM\Index(columns: ['language'])]
#[ORM\Index(columns: ['chapter_number'])]
#[ApiResource(
    normalizationContext: ['groups' => ['chapter:read']],
    denormalizationContext: ['groups' => ['chapter:write']],
    order: ['chapterNumber' => 'ASC']
)]
#[ApiFilter(SearchFilter::class, properties: [
    'manga' => 'exact',
    'language' => 'exact',
    'volume' => 'exact'
])]
#[ApiFilter(OrderFilter::class, properties: ['chapterNumber', 'volume', 'language'])]
#[ApiFilter(RangeFilter::class, properties: ['chapterNumber'])]
class Chapter
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['chapter:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Manga::class, inversedBy: 'chapters', fetch: 'EAGER')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['chapter:read', 'chapter:write'])]
    private Manga $manga;

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

    #[ORM\Column(type: 'json')]
    #[Assert\NotNull]
    #[Assert\Count(min: 1)]
    #[Groups(['chapter:read', 'chapter:write'])]
    private array $pages = [];

    public function getId(): ?int
    {
        return $this->id;
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

    public function getPages(): array
    {
        return $this->pages;
    }

    public function setPages(array $pages): static
    {
        $this->pages = $pages;
        return $this;
    }
}
