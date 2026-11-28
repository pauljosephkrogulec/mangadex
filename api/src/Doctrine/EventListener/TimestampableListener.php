<?php

declare(strict_types=1);

namespace App\Doctrine\EventListener;

use App\Entity\TimestampableInterface;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\Clock\ClockInterface;

/**
 * Horodate les entités TimestampableInterface avec l'horloge Symfony (issue #23), jamais avec
 * `new \DateTimeImmutable()` : les tests figent ou avancent le temps via MockClock.
 */
#[AsDoctrineListener(event: Events::prePersist)]
#[AsDoctrineListener(event: Events::onFlush)]
final readonly class TimestampableListener
{
    public function __construct(
        private ClockInterface $clock,
    ) {
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        $entity = $args->getObject();
        if ($entity instanceof TimestampableInterface) {
            $entity->initializeTimestamps($this->clock->now());
        }
    }

    /**
     * Mise à jour : en onFlush plutôt qu'en preUpdate. En preUpdate, le changeset est figé et
     * setNewValue() refuse un champ qui n'y figure pas déjà : or `updatedAt` n'y est jamais quand
     * seul le titre change. Ici, on modifie l'entité puis on demande à Doctrine de recalculer son changeset.
     */
    public function onFlush(OnFlushEventArgs $args): void
    {
        $entityManager = $args->getObjectManager();
        $unitOfWork = $entityManager->getUnitOfWork();

        foreach ($unitOfWork->getScheduledEntityUpdates() as $entity) {
            if (!$entity instanceof TimestampableInterface) {
                continue;
            }

            $entity->touch($this->clock->now());
            $unitOfWork->recomputeSingleEntityChangeSet($entityManager->getClassMetadata($entity::class), $entity);
        }
    }
}
