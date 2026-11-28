<?php

declare(strict_types=1);

namespace App\Slug;

use App\Entity\SluggableInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Génère un slug unique par type d'entité : `one-piece`, puis `one-piece-2`, `one-piece-3`….
 *
 * Le slugger Symfony translittère via Intl (`進撃の巨人` → `jin-ji-no-ju-ren`). L'unicité est vérifiée
 * en base et parmi les entités en attente d'insertion dans la même transaction. Deux requêtes concurrentes
 * peuvent encore choisir le même slug : la contrainte UNIQUE de la table reste le dernier rempart.
 */
final readonly class UniqueSlugger
{
    /** Laisse la place au suffixe numérique dans une colonne de 255 caractères. */
    public const int MAX_BASE_LENGTH = 200;

    public function __construct(
        private SluggerInterface $slugger,
    ) {
    }

    public function slugFor(SluggableInterface $entity, EntityManagerInterface $entityManager): string
    {
        $base = $this->base($entity);
        $taken = $this->takenSlugs($entity, $base, $entityManager);

        $candidate = $base;
        $suffix = 1;
        while (isset($taken[$candidate])) {
            $candidate = $base.'-'.++$suffix;
        }

        return $candidate;
    }

    private function base(SluggableInterface $entity): string
    {
        $slug = $this->slugger->slug($entity->getSlugSource())->lower()->truncate(self::MAX_BASE_LENGTH)->trimEnd('-')->toString();

        // Source vide ou sans aucun caractère translittérable : repli sur le type d'entité (`series`, `tag`…).
        return '' !== $slug ? $slug : mb_strtolower(new \ReflectionClass($entity)->getShortName());
    }

    /**
     * Slugs déjà pris qui pourraient entrer en collision : la base elle-même et ses variantes suffixées.
     *
     * @return array<string, true>
     */
    private function takenSlugs(SluggableInterface $entity, string $base, EntityManagerInterface $entityManager): array
    {
        $class = $entityManager->getClassMetadata($entity::class)->getName();

        /** @var list<string> $slugs */
        $slugs = $entityManager->createQueryBuilder()
            ->select('e.slug')
            ->from($class, 'e')
            ->where('e.slug = :base OR e.slug LIKE :variants')
            ->setParameter('base', $base)
            ->setParameter('variants', addcslashes($base, '%_').'-%')
            ->getQuery()
            ->getSingleColumnResult();

        foreach ($entityManager->getUnitOfWork()->getScheduledEntityInsertions() as $pending) {
            if ($pending !== $entity && $pending instanceof $class && null !== $pending->getSlug()) {
                $slugs[] = $pending->getSlug();
            }
        }

        return array_fill_keys($slugs, true);
    }
}
