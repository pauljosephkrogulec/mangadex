<?php

declare(strict_types=1);

namespace App\Doctrine\EventListener;

use App\Entity\SluggableInterface;
use App\Slug\UniqueSlugger;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Events;

/**
 * Attribue un slug unique aux entités SluggableInterface persistées sans slug (issue #33).
 * Uniquement à la création : un slug fourni explicitement est conservé tel quel, et un slug existant
 * n'est jamais régénéré (stabilité des URL).
 */
#[AsDoctrineListener(event: Events::prePersist)]
final readonly class SlugListener
{
    public function __construct(
        private UniqueSlugger $slugger,
    ) {
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        $entity = $args->getObject();
        if ($entity instanceof SluggableInterface && \in_array($entity->getSlug(), [null, ''], true)) {
            $entity->setSlug($this->slugger->slugFor($entity, $args->getObjectManager()));
        }
    }
}
