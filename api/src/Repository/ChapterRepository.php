<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Chapter;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Chapter>
 */
final class ChapterRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Chapter::class);
    }

    /**
     * Autre chapitre de la même série, dans la même langue, avec le même numéro. La comparaison se fait
     * sur la colonne DECIMAL : « 1 » et « 1.00 » sont le même numéro.
     */
    public function findDuplicate(Chapter $chapter): ?Chapter
    {
        // Série pas encore enregistrée : elle n'a aucun chapitre en base.
        if (!$chapter->getSeries()->getId() instanceof Uuid) {
            return null;
        }

        $query = $this->createQueryBuilder('c')
            ->where('c.series = :series')
            ->andWhere('c.language = :language')
            ->andWhere('c.number = :number')
            ->setParameter('series', $chapter->getSeries())
            ->setParameter('language', $chapter->getLanguage())
            ->setParameter('number', $chapter->getNumber())
            ->setMaxResults(1);

        if ($chapter->getId() instanceof Uuid) {
            $query->andWhere('c.id != :id')->setParameter('id', $chapter->getId(), 'uuid');
        }

        $duplicate = $query->getQuery()->getOneOrNullResult();
        \assert(null === $duplicate || $duplicate instanceof Chapter);

        return $duplicate;
    }
}
