<?php

declare(strict_types=1);

namespace App\Tests\Doctrine\EventListener;

use App\Doctrine\EventListener\TimestampableListener;
use App\Entity\TimestampableInterface;
use App\Entity\Trait\Timestampable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PrePersistEventArgs;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class TimestampableListenerTest extends TestCase
{
    private MockClock $clock;
    private TimestampableListener $listener;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-01-15 10:00:00', 'UTC');
        $this->listener = new TimestampableListener($this->clock);
        $this->entityManager = self::createStub(EntityManagerInterface::class);
    }

    public function testPrePersistSetsBothDatesFromTheClock(): void
    {
        $entity = $this->timestampable();

        $this->listener->prePersist(new PrePersistEventArgs($entity, $this->entityManager));

        self::assertEquals(new \DateTimeImmutable('2026-01-15 10:00:00', new \DateTimeZone('UTC')), $entity->getCreatedAt());
        self::assertEquals($entity->getCreatedAt(), $entity->getUpdatedAt());
    }

    public function testPrePersistKeepsAnExplicitCreationDate(): void
    {
        $entity = $this->timestampable();
        $entity->initializeTimestamps(new \DateTimeImmutable('2020-05-01 00:00:00'));

        $this->listener->prePersist(new PrePersistEventArgs($entity, $this->entityManager));

        self::assertEquals(new \DateTimeImmutable('2020-05-01 00:00:00'), $entity->getCreatedAt());
        self::assertEquals($this->clock->now(), $entity->getUpdatedAt());
    }

    public function testOtherEntitiesAreIgnored(): void
    {
        $entity = new \stdClass();

        $this->listener->prePersist(new PrePersistEventArgs($entity, $this->entityManager));

        self::assertSame([], get_object_vars($entity));
    }

    private function timestampable(): TimestampableInterface
    {
        return new class implements TimestampableInterface {
            use Timestampable;
        };
    }
}
