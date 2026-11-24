<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Entity\Trait\Timestampable;
use App\Entity\Trait\UuidV7Id;
use App\Enum\SeriesStatus;
use App\Repository\SeriesRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Série (manga, manhwa, webtoon…). Première entité du socle (issue #21), réduite à l'essentiel :
 * auteurs, genres, couverture… arrivent avec #27, l'écriture (CRUD) avec #31 et l'authentification.
 */
#[ORM\Entity(repositoryClass: SeriesRepository::class)]
// Tri par défaut de la collection (plus récents d'abord).
#[ORM\Index(name: 'series_created_at_idx', columns: ['created_at'])]
#[ApiResource(
    operations: [new GetCollection(), new Get()],
    order: ['createdAt' => 'DESC'],
)]
class Series implements TimestampableInterface
{
    use Timestampable;
    use UuidV7Id;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 16, enumType: SeriesStatus::class)]
    private SeriesStatus $status = SeriesStatus::Ongoing;

    public function __construct(
        #[ORM\Column(length: 255)]
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        private string $title
    ) {
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

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
}
