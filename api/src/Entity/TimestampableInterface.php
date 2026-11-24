<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Entité horodatée automatiquement par App\Doctrine\EventListener\TimestampableListener (issue #23).
 * Implémentation par défaut : trait App\Entity\Trait\Timestampable.
 */
interface TimestampableInterface
{
    public function getCreatedAt(): ?\DateTimeImmutable;

    public function getUpdatedAt(): ?\DateTimeImmutable;

    /**
     * Appelé à la première persistance : date de création et de dernière modification.
     */
    public function initializeTimestamps(\DateTimeImmutable $now): void;

    /**
     * Appelé à chaque mise à jour : date de dernière modification.
     */
    public function touch(\DateTimeImmutable $now): void;
}
