<?php

declare(strict_types=1);

namespace App\Tests\Smoke;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Vérifie que le kernel démarre en env "test" et que la base PostgreSQL est joignable.
 */
final class DatabaseConnectionTest extends KernelTestCase
{
    public function testKernelBootsAndDatabaseIsReachable(): void
    {
        self::bootKernel();

        $connection = self::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $connection);

        // pdo_pgsql renvoie les entiers en type natif (PHP >= 8.1) : pas de cast nécessaire.
        self::assertSame(1, $connection->fetchOne('SELECT 1'));
    }
}
