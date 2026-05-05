<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\RangeFilter;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Attribute\Groups;
#[ORM\Entity]
#[ORM\Table(name: 'manga')]
#[ORM\Index(columns: ['status'])]
#[ORM\Index(columns: ['year'])]
#[ORM\Index(columns: ['content_rating'])]
#[ApiResource(
    normalizationContext: ['groups' => ['manga:list']],
    denormalizationContext: ['groups' => ['manga:write']],
    order: ['title' => 'ASC'],
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['manga:list']]
        ),
        new Get(
            normalizationContext: ['groups' => ['manga:read']]
        ),
        new Put(security: "is_granted('ROLE_ADMIN')"),
        new Delete(security: "is_granted('ROLE_ADMIN')"),
        new Patch(security: "is_granted('ROLE_ADMIN')"),
        new Post(security: "is_granted('ROLE_ADMIN')")
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'title' => 'partial',
    'status' => 'exact',
    'contentRating' => 'exact',
    'year' => 'exact'
])]
#[ApiFilter(OrderFilter::class, properties: ['title', 'year', 'status'])]
#[ApiFilter(RangeFilter::class, properties: ['year'])]
class Manga
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['manga:read', 'chapter:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Groups(['manga:read', 'manga:write', 'manga:list', 'creator:read'])]
    private string $title;

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['manga:read', 'manga:write'])]
    private ?array $altTitles = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['manga:read', 'manga:write'])]
    private ?string $description = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank]
    #[Assert\Choice(callback: ['App\Entity\Manga', 'getStatusChoices'])]
    #[Groups(['manga:read', 'manga:write', 'manga:list'])]
    private string $status;

    #[ORM\Column(nullable: true)]
    #[Assert\Range(min: 1900, max: 2100)]
    #[Groups(['manga:read', 'manga:write', 'manga:list'])]
    private ?int $year = null;

    #[ORM\Column(length: 30)]
    #[Assert\NotBlank]
    #[Assert\Choice(callback: ['App\Entity\Manga', 'getContentRatingChoices'])]
    #[Groups(['manga:read', 'manga:write', 'manga:list'])]
    private string $contentRating;

    #[ORM\ManyToMany(targetEntity: Creator::class, inversedBy: 'manga', fetch: 'EAGER')]
    #[ORM\JoinTable(name: 'manga_creator')]
    #[Groups(['manga:read', 'manga:write'])]
    private Collection $creators;

    #[ORM\ManyToMany(targetEntity: Tag::class, inversedBy: 'manga', fetch: 'EAGER')]
    #[ORM\JoinTable(name: 'manga_tag')]
    #[Groups(['manga:read', 'manga:write'])]
    private Collection $tags;

    #[ORM\OneToMany(targetEntity: Chapter::class, mappedBy: 'manga', cascade: ['persist', 'remove'])]
    #[Groups(['manga:read'])]
    private Collection $chapters;

    #[ORM\OneToMany(targetEntity: CoverArt::class, mappedBy: 'manga', cascade: ['persist', 'remove'])]
    #[Groups(['manga:read'])]
    private Collection $coverArts;

    public function __construct()
    {
        $this->creators = new ArrayCollection();
        $this->tags = new ArrayCollection();
        $this->chapters = new ArrayCollection();
        $this->coverArts = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getAltTitles(): ?array
    {
        return $this->altTitles;
    }

    public function setAltTitles(?array $altTitles): static
    {
        $this->altTitles = $altTitles;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
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

    public function getContentRating(): string
    {
        return $this->contentRating;
    }

    public static function getStatusChoices(): array
    {
        return ['ongoing', 'completed', 'hiatus', 'cancelled'];
    }

    public static function getContentRatingChoices(): array
    {
        return ['safe', 'suggestive', 'erotica', 'pornographic'];
    }

    public function setContentRating(string $contentRating): static
    {
        $this->contentRating = $contentRating;
        return $this;
    }

    public function getCreators(): Collection
    {
        return $this->creators;
    }

    public function addCreator(Creator $creator): static
    {
        if (!$this->creators->contains($creator)) {
            $this->creators->add($creator);
            $creator->addManga($this);
        }
        return $this;
    }

    public function removeCreator(Creator $creator): static
    {
        $this->creators->removeElement($creator);
        return $this;
    }

    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function addTag(Tag $tag): static
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
            $tag->addManga($this);
        }
        return $this;
    }

    public function removeTag(Tag $tag): static
    {
        $this->tags->removeElement($tag);
        return $this;
    }

    public function getChapters(): Collection
    {
        return $this->chapters;
    }

    public function addChapter(Chapter $chapter): static
    {
        if (!$this->chapters->contains($chapter)) {
            $this->chapters->add($chapter);
            $chapter->setManga($this);
        }
        return $this;
    }

    public function removeChapter(Chapter $chapter): static
    {
        if ($this->chapters->removeElement($chapter)) {
            // Note: We don't set manga to null because JoinColumn(nullable: false)
            // If you need to orphan chapters, set nullable: true in the mapping
        }
        return $this;
    }

    public function getCoverArts(): Collection
    {
        return $this->coverArts;
    }

    public function addCoverArt(CoverArt $coverArt): static
    {
        if (!$this->coverArts->contains($coverArt)) {
            $this->coverArts->add($coverArt);
            $coverArt->setManga($this);
        }
        return $this;
    }

    public function removeCoverArt(CoverArt $coverArt): static
    {
        if ($this->coverArts->removeElement($coverArt)) {
            // Note: We don't set manga to null because JoinColumn(nullable: false)
        }
        return $this;
    }
}
