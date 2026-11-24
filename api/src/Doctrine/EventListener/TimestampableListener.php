<?php

declare(strict_types=1);

namespace App\Doctrine\EventListener;

use App\Entity\TimestampableInterface;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\Clock\ClockInterface;

/**
 * Horodate les entités TimestampableInterface avec l'horloge Symfony (issue #23), jamais avec
 * `new \DateTimeImmutable()` : les tests figent ou avancent le temps via MockClock.
 */
#[AsDoctrineListener(event: Events::prePersist)]
#[AsDoctrineListener(event: Events::preUpdate)]
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
     * En preUpdate, Doctrine a déjà calculé le changeset : modifier une propriété ne suffit pas,
     * il faut passer par setNewValue() pour que la nouvelle date soit écrite.
     */
    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof TimestampableInterface) {
            return;
        }

        $entity->touch($this->clock->now());
        $args->setNewValue('updatedAt', $entity->getUpdatedAt());
    }
}
