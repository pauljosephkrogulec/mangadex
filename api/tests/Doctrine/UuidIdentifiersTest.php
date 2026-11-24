<?php

declare(strict_types=1);

namespace App\Tests\Doctrine;

use App\Entity\Trait\UuidV7Id;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Factory\UuidFactory;
use Symfony\Component\Uid\UuidV7;

/**
 * Toutes les entités sont identifiées par un UUID v7 (issue #22).
 */
final class UuidIdentifiersTest extends KernelTestCase
{
    public function testUuidFactoryGeneratesV7(): void
    {
        self::bootKernel();
        $factory = self::getContainer()->get(UuidFactory::class);
        self::assertInstanceOf(UuidFactory::class, $factory);

        self::assertInstanceOf(UuidV7::class, $factory->create());
        self::assertInstanceOf(UuidV7::class, $factory->timeBased()->create());
    }

    public function testUuidV7AreSortedByCreationTime(): void
    {
        self::bootKernel();
        $factory = self::getContainer()->get(UuidFactory::class);
        self::assertInstanceOf(UuidFactory::class, $factory);

        $first = $factory->create();
        $second = $factory->create();

        self::assertLessThan(0, $first->compare($second));
    }

    public function testTraitMapsAnUuidIdentifierGeneratedByDoctrine(): void
    {
        $entity = new class {
            use UuidV7Id;
        };
        $property = new \ReflectionProperty($entity, 'id');

        self::assertCount(1, $property->getAttributes(ORM\Id::class));

        $column = $property->getAttributes(ORM\Column::class)[0]->newInstance();
        self::assertSame(UuidType::NAME, $column->type);
        self::assertTrue($column->unique);

        self::assertSame('CUSTOM', $property->getAttributes(ORM\GeneratedValue::class)[0]->newInstance()->strategy);
        self::assertSame('doctrine.uuid_generator', $property->getAttributes(ORM\CustomIdGenerator::class)[0]->newInstance()->class);

        self::assertNull($entity->getId(), "L'identifiant n'est attribué qu'au persist().");
    }

    public function testDoctrineUuidGeneratorUsesTheV7Factory(): void
    {
        self::bootKernel();
        $generator = self::getContainer()->get('doctrine.uuid_generator');
        self::assertInstanceOf(UuidGenerator::class, $generator);

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        self::assertInstanceOf(UuidV7::class, $generator->generateId($entityManager, null));
    }
}
