<?php

declare(strict_types=1);

namespace App\Tests\Config;

use Psr\Clock\ClockInterface as PsrClockInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * L'horloge injectée par le conteneur suit le temps mocké en test (issue #23) :
 * `self::mockTime()` suffit, sans remplacer de service.
 */
final class ClockTest extends KernelTestCase
{
    use ClockSensitiveTrait;

    public function testInjectedClockFollowsTheMockedTime(): void
    {
        self::mockTime('2026-03-01 12:00:00');
        self::bootKernel();

        $clock = self::getContainer()->get(ClockInterface::class);
        self::assertInstanceOf(ClockInterface::class, $clock);
        self::assertSame('2026-03-01 12:00:00', $clock->now()->format('Y-m-d H:i:s'));

        $clock->sleep(90);
        self::assertSame('2026-03-01 12:01:30', $clock->now()->format('Y-m-d H:i:s'));
    }

    public function testPsrClockIsTheSameService(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        self::assertSame($container->get(ClockInterface::class), $container->get(PsrClockInterface::class));
    }
}
