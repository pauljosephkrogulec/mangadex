<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Entity\Trait\Timestampable;
use App\Entity\Trait\UuidV7Id;
use App\Repository\PageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Page d'un chapitre (issue #30) : image stockée dans le stockage objet, dimensions et empreinte.
 *
 * Une page de webtoon est une longue bande verticale : elle peut être découpée en segments empilés
 * de haut en bas, chargés un à un par le lecteur. Sans segment, l'image entière est affichée.
 */
#[ORM\Entity(repositoryClass: PageRepository::class)]
#[ORM\UniqueConstraint(name: 'page_chapter_position_uniq', columns: ['chapter_id', 'position'])]
#[ORM\Index(name: 'page_hash_idx', columns: ['hash'])]
#[ApiResource(
    operations: [new GetCollection(), new Get()],
    order: ['chapter' => 'ASC', 'position' => 'ASC'],
)]
class Page implements TimestampableInterface
{
    use Timestampable;
    use UuidV7Id;

    /** Empreinte SHA-256 du fichier, en hexadécimal minuscule. */
    public const string HASH_PATTERN = '/^[a-f0-9]{64}$/';

    /**
     * Segments verticaux, de haut en bas : clé de l'image et hauteur en pixels (même largeur que la page).
     *
     * @var list<array{image: string, height: int}>
     */
    #[ORM\Column(type: Types::JSON, options: ['jsonb' => true, 'default' => '[]'])]
    private array $segments = [];

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Chapter $chapter,
        /** Ordre de lecture dans le chapitre, à partir de 1. */
        #[ORM\Column(type: Types::SMALLINT)]
        #[Assert\Positive]
        private int $position,
        /** Clé de l'image dans le stockage objet (S3). */
        #[ORM\Column(length: 255)]
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        private string $image,
        #[ORM\Column]
        #[Assert\Positive]
        private int $width,
        #[ORM\Column]
        #[Assert\Positive]
        private int $height,
        #[ORM\Column(length: 64)]
        #[Assert\Regex(pattern: self::HASH_PATTERN, message: "L'empreinte est un SHA-256 hexadécimal (64 caractères).")]
        private string $hash,
    ) {
    }

    public function getChapter(): Chapter
    {
        return $this->chapter;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function getImage(): string
    {
        return $this->image;
    }

    public function getWidth(): int
    {
        return $this->width;
    }

    public function getHeight(): int
    {
        return $this->height;
    }

    public function getHash(): string
    {
        return $this->hash;
    }

    /**
     * Remplace l'image : ses dimensions et son empreinte changent avec elle, et les anciens segments
     * ne correspondent plus.
     */
    public function replaceImage(string $image, int $width, int $height, string $hash): static
    {
        $this->image = $image;
        $this->width = $width;
        $this->height = $height;
        $this->hash = $hash;
        $this->segments = [];

        return $this;
    }

    /**
     * @return list<array{image: string, height: int}>
     */
    public function getSegments(): array
    {
        return $this->segments;
    }

    /**
     * @param list<array{image: string, height: int}> $segments
     */
    public function setSegments(array $segments): static
    {
        $this->segments = $segments;

        return $this;
    }

    public function isSegmented(): bool
    {
        return [] !== $this->segments;
    }

    /**
     * Les segments recouvrent exactement la page : chacun a une image et une hauteur positive,
     * et leurs hauteurs s'additionnent pour donner celle de la page.
     */
    #[Assert\Callback]
    public function validateSegments(ExecutionContextInterface $context): void
    {
        if (!$this->isSegmented()) {
            return;
        }

        $total = 0;
        foreach ($this->segments as $index => $segment) {
            if ('' === mb_trim($segment['image'])) {
                $context->buildViolation('Chaque segment doit avoir une image.')
                    ->atPath(\sprintf('segments[%d].image', $index))
                    ->addViolation();
            }

            if ($segment['height'] <= 0) {
                $context->buildViolation('La hauteur d\'un segment doit être positive.')
                    ->atPath(\sprintf('segments[%d].height', $index))
                    ->addViolation();
            }

            $total += $segment['height'];
        }

        if ($total !== $this->height) {
            $context->buildViolation('Les segments mesurent {{ total }} px au total, la page {{ height }} px.')
                ->setParameter('{{ total }}', (string) $total)
                ->setParameter('{{ height }}', (string) $this->height)
                ->atPath('segments')
                ->addViolation();
        }
    }
}
