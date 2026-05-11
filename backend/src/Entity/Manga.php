<?php

declare(strict_types=1);

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
use App\Controller\MangaFollowController;
use App\State\MangaFeedProvider;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'manga')]
#[ORM\Index(columns: ['status'])]
#[ORM\Index(columns: ['year'])]
#[ORM\Index(columns: ['content_rating'])]
#[ORM\Index(columns: ['demographic'])]
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
        new GetCollection(
            uriTemplate: '/mangas/{id}/feed',
            provider: MangaFeedProvider::class,
            normalizationContext: ['groups' => ['chapter:read']],
            name: 'feed'
        ),
        new Post(
            uriTemplate: '/mangas/{id}/follow',
            controller: MangaFollowController::class,
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            name: 'follow'
        ),
        new Delete(
            uriTemplate: '/mangas/{id}/follow',
            controller: MangaFollowController::class,
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            name: 'unfollow'
        ),
        new Get(
            uriTemplate: '/mangas/{id}/follow',
            controller: MangaFollowController::class,
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            name: 'follow_status'
        ),
        new Put(security: "is_granted('ROLE_ADMIN')"),
        new Delete(security: "is_granted('ROLE_ADMIN')"),
        new Patch(security: "is_granted('ROLE_ADMIN')"),
        new Post(security: "is_granted('ROLE_ADMIN')"),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'title' => 'partial',
    'status' => 'exact',
    'contentRating' => 'exact',
    'demographic' => 'exact',
    'year' => 'exact',
    'tags.id' => 'exact',
    'tags.name' => 'partial',
])]
#[ApiFilter(OrderFilter::class, properties: ['title', 'year', 'status', 'createdAt'])]
#[ApiFilter(RangeFilter::class, properties: ['year'])]
class Manga
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    #[Groups(['manga:read', 'manga:list', 'chapter:read', 'custom_list:read'])]
    private ?string $id = null;

    #[ORM\Column(type: 'datetime')]
    #[Groups(['manga:read', 'manga:list', 'custom_list:read'])]
    private \DateTime $createdAt;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Groups(['manga:read', 'manga:write', 'manga:list', 'creator:read', 'custom_list:read'])]
    private string $title;

    /** @var array<string>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['manga:read', 'manga:write'])]
    private ?array $altTitles = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['manga:read', 'manga:write'])]
    private ?string $description = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank]
    #[Assert\Choice(callback: ['App\Entity\Manga', 'getStatusChoices'])]
    #[Groups(['manga:read', 'manga:write', 'manga:list', 'custom_list:read'])]
    private string $status;

    #[ORM\Column(nullable: true)]
    #[Assert\Range(min: 1900, max: 2100)]
    #[Groups(['manga:read', 'manga:write', 'manga:list', 'custom_list:read'])]
    private ?int $year = null;

    #[ORM\Column(length: 30)]
    #[Assert\NotBlank]
    #[Assert\Choice(callback: ['App\Entity\Manga', 'getContentRatingChoices'])]
    #[Groups(['manga:read', 'manga:write', 'manga:list', 'custom_list:read'])]
    private string $contentRating;

    #[ORM\Column(length: 20, options: ['default' => 'none'])]
    #[Assert\Choice(callback: ['App\Entity\Manga', 'getDemographicChoices'])]
    #[Groups(['manga:read', 'manga:write', 'manga:list', 'custom_list:read'])]
    private string $demographic = 'none';

    #[ORM\ManyToMany(targetEntity: Creator::class, inversedBy: 'manga')]
    #[ORM\JoinTable(name: 'manga_creator')]
    #[Groups(['manga:write', 'manga:include:creators'])]
    /** @var Collection<int, Creator> */
    private Collection $creators;

    #[ORM\ManyToMany(targetEntity: Tag::class, inversedBy: 'manga')]
    #[ORM\JoinTable(name: 'manga_tag')]
    #[Groups(['manga:write', 'manga:include:tags'])]
    /** @var Collection<int, Tag> */
    private Collection $tags;

    #[ORM\OneToMany(targetEntity: Chapter::class, mappedBy: 'manga', cascade: ['persist', 'remove'])]
    #[Groups(['manga:include:chapters'])]
    /** @var Collection<int, Chapter> */
    private Collection $chapters;

    #[ORM\OneToMany(targetEntity: CoverArt::class, mappedBy: 'manga', cascade: ['persist', 'remove'])]
    #[Groups(['manga:include:coverArt'])]
    /** @var Collection<int, CoverArt> */
    private Collection $coverArts;

    #[ORM\OneToMany(targetEntity: MangaFollow::class, mappedBy: 'manga', cascade: ['persist', 'remove'])]
    /** @var Collection<int, MangaFollow> */
    private Collection $followers;

    #[ORM\ManyToMany(targetEntity: CustomList::class, mappedBy: 'mangas')]
    /** @var Collection<int, CustomList> */
    private Collection $customLists;

    public function __construct()
    {
        $this->id = Uuid::v4()->toRfc4122();
        $this->createdAt = new \DateTime();
        $this->creators = new ArrayCollection();
        $this->tags = new ArrayCollection();
        $this->chapters = new ArrayCollection();
        $this->coverArts = new ArrayCollection();
        $this->followers = new ArrayCollection();
        $this->customLists = new ArrayCollection();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
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

    /**
     * @return array<string>|null
     */
    public function getAltTitles(): ?array
    {
        return $this->altTitles;
    }

    /**
     * @param array<string>|null $altTitles
     */
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

    /**
     * @return array<string>
     */
    public static function getStatusChoices(): array
    {
        return ['ongoing', 'completed', 'hiatus', 'cancelled'];
    }

    /**
     * @return array<string>
     */
    public static function getContentRatingChoices(): array
    {
        return ['safe', 'suggestive', 'erotica', 'pornographic'];
    }

    /**
     * @return array<string>
     */
    public static function getDemographicChoices(): array
    {
        return ['shounen', 'shoujo', 'josei', 'seinen', 'none'];
    }

    public function setContentRating(string $contentRating): static
    {
        $this->contentRating = $contentRating;

        return $this;
    }

    public function getDemographic(): string
    {
        return $this->demographic;
    }

    public function setDemographic(string $demographic): static
    {
        $this->demographic = $demographic;

        return $this;
    }

    /**
     * @return Collection<int, Creator>
     */
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
            $tag->addManga($this);
        }

        return $this;
    }

    public function removeTag(Tag $tag): static
    {
        $this->tags->removeElement($tag);

        return $this;
    }

    /**
     * @return Collection<int, Chapter>
     */
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

    /**
     * @return Collection<int, CoverArt>
     */
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

    /**
     * @return Collection<int, MangaFollow>
     */
    public function getFollowers(): Collection
    {
        return $this->followers;
    }

    public function addFollower(MangaFollow $follow): static
    {
        if (!$this->followers->contains($follow)) {
            $this->followers->add($follow);
            $follow->setManga($this);
        }

        return $this;
    }

    public function removeFollower(MangaFollow $follow): static
    {
        if ($this->followers->removeElement($follow)) {
            // Note: MangaFollow has nullable=false on manga
        }

        return $this;
    }
}
