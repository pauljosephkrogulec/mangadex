<?php

declare(strict_types=1);

namespace App\Doctrine\EventListener;

use App\HtmlSanitizer\SanitizeHtml;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

/**
 * Nettoie les propriétés marquées #[SanitizeHtml] avant toute écriture en base (issue #35).
 *
 * Au niveau de la persistance plutôt qu'à l'entrée de l'API : le HTML arrive aussi par les imports
 * (Epic 7) et le back-office. Un contenu vide une fois nettoyé (`<script>…</script>`) devient null.
 */
#[AsDoctrineListener(event: Events::onFlush)]
final class HtmlSanitizerListener
{
    /** @var array<class-string, list<\ReflectionProperty>> */
    private array $properties = [];

    public function __construct(
        #[Target('app.rich_text')]
        private readonly HtmlSanitizerInterface $sanitizer,
    ) {
    }

    /**
     * En onFlush, juste avant l'écriture : couvre aussi une valeur modifiée entre persist() et flush().
     * On nettoie, puis on recalcule le changeset (comme TimestampableListener).
     */
    public function onFlush(OnFlushEventArgs $args): void
    {
        $entityManager = $args->getObjectManager();
        $unitOfWork = $entityManager->getUnitOfWork();

        foreach ([...$unitOfWork->getScheduledEntityInsertions(), ...$unitOfWork->getScheduledEntityUpdates()] as $entity) {
            if ($this->sanitize($entity)) {
                $this->recompute($entityManager, $entity);
            }
        }
    }

    /**
     * @return bool vrai si au moins une propriété a changé
     */
    private function sanitize(object $entity): bool
    {
        $changed = false;

        foreach ($this->propertiesOf($entity) as $property) {
            $html = $property->getValue($entity);
            if (!\is_string($html)) {
                continue;
            }

            $clean = mb_trim($this->sanitizer->sanitize($html));
            $clean = '' === $clean ? null : $clean;

            if ($clean !== $html) {
                $property->setValue($entity, $clean);
                $changed = true;
            }
        }

        return $changed;
    }

    /**
     * @return list<\ReflectionProperty>
     */
    private function propertiesOf(object $entity): array
    {
        return $this->properties[$entity::class] ??= array_values(array_filter(
            new \ReflectionObject($entity)->getProperties(),
            static fn (\ReflectionProperty $property): bool => [] !== $property->getAttributes(SanitizeHtml::class),
        ));
    }

    private function recompute(EntityManagerInterface $entityManager, object $entity): void
    {
        $entityManager->getUnitOfWork()->recomputeSingleEntityChangeSet($entityManager->getClassMetadata($entity::class), $entity);
    }
}
