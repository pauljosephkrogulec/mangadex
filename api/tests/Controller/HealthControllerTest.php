<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HealthControllerTest extends WebTestCase
{
    public function testHealthReturnsOkWithVersion(): void
    {
        $client = self::createClient();
        $client->request('GET', '/api/health');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertResponseHeaderSame('Cache-Control', 'no-store, private');

        $payload = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame(['status' => 'ok', 'version' => 'dev'], $payload);
    }

    public function testHealthOnlyAcceptsGet(): void
    {
        $client = self::createClient();
        $client->request('POST', '/api/health');

        self::assertResponseStatusCodeSame(405);
    }
}
