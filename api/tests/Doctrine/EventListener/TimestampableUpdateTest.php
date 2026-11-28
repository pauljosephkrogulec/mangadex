<?php

declare(strict_types=1);

namespace App\Tests\Doctrine\EventListener;

use App\Factory\SeriesFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Mise à jour d'une entité horodatée avec un vrai EntityManager : le changeset de Doctrine ne contient
 * que les champs modifiés, jamais `updatedAt` (régression du listener en preUpdate).
 */
final class TimestampableUpdateTest extends KernelTestCase
{
    use ClockSensitiveTrait;
    use Factories;
    use ResetDatabase;

    public function testUpdatingAFieldMovesOnlyTheModificationDate(): void
    {
        self::mockTime('2026-01-15 10:00:00');
        $series = SeriesFactory::createOne(['title' => 'Shingeki no Kyojin']);
        $id = $series->getId();

        self::mockTime('2026-01-15 11:00:00');
        $series->setTitle('Attack on Titan');
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->flush();
        $entityManager->clear();

        $row = $entityManager->getConnection()->fetchAssociative('SELECT title, created_at, updated_at FROM series WHERE id = ?', [$id?->toRfc4122()]);
        self::assertSame(['title' => 'Attack on Titan', 'created_at' => '2026-01-15 10:00:00', 'updated_at' => '2026-01-15 11:00:00'], $row);
    }

    public function testFlushWithoutChangesDoesNotTouchTheModificationDate(): void
    {
        self::mockTime('2026-01-15 10:00:00');
        $series = SeriesFactory::createOne();

        self::mockTime('2026-01-15 11:00:00');
        self::getContainer()->get(EntityManagerInterface::class)->flush();

        self::assertEquals(new \DateTimeImmutable('2026-01-15 10:00:00'), $series->getUpdatedAt());
    }
}
