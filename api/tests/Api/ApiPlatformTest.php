<?php

declare(strict_types=1);

namespace App\Tests\Api;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Socle API Platform (issue #20) : documentation OpenAPI, formats négociés, pagination et CORS.
 */
final class ApiPlatformTest extends WebTestCase
{
    public function testOpenApiSpecificationIsExposed(): void
    {
        $client = self::createClient();
        $client->request('GET', '/api/docs.jsonopenapi');

        self::assertResponseIsSuccessful();
        self::assertStringStartsWith('application/vnd.openapi+json', (string) $client->getResponse()->headers->get('Content-Type'));

        $spec = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($spec);
        self::assertIsString($spec['openapi'] ?? null);
        self::assertStringStartsWith('3.', $spec['openapi']);
        self::assertSame(['title' => 'Manga Reader API', 'version' => 'dev'], array_intersect_key(
            (array) ($spec['info'] ?? []),
            ['title' => true, 'version' => true],
        ));
    }

    public function testSwaggerUiIsServedToBrowsers(): void
    {
        $client = self::createClient();
        $client->request('GET', '/api/docs', server: ['HTTP_ACCEPT' => 'text/html']);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/html; charset=UTF-8');
        self::assertSelectorExists('#swagger-ui');
    }

    public function testJsonLdJsonApiAndHalFormatsAreEnabled(): void
    {
        self::bootKernel();
        $formats = self::getContainer()->getParameter('api_platform.formats');
        self::assertIsArray($formats);

        self::assertSame(['application/ld+json'], $formats['jsonld'] ?? null);
        self::assertSame(['application/vnd.api+json'], $formats['jsonapi'] ?? null);
        self::assertSame(['application/hal+json'], $formats['jsonhal'] ?? null);
    }

    public function testEntrypointIsServedInJsonLd(): void
    {
        $client = self::createClient();
        $client->request('GET', '/api', server: ['HTTP_ACCEPT' => 'application/ld+json']);

        self::assertResponseIsSuccessful();
        self::assertStringStartsWith('application/ld+json', (string) $client->getResponse()->headers->get('Content-Type'));
    }

    public function testUnsupportedFormatIsRejected(): void
    {
        $client = self::createClient();
        $client->request('GET', '/api', server: ['HTTP_ACCEPT' => 'application/xml']);

        self::assertResponseStatusCodeSame(406);
    }

    public function testPaginationDefaults(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        self::assertTrue($container->getParameter('api_platform.collection.pagination.enabled'));
        self::assertSame(30, $container->getParameter('api_platform.collection.pagination.items_per_page'));
        self::assertSame(100, $container->getParameter('api_platform.collection.pagination.maximum_items_per_page'));
        self::assertTrue($container->getParameter('api_platform.collection.pagination.client_items_per_page'));
        self::assertSame('itemsPerPage', $container->getParameter('api_platform.collection.pagination.items_per_page_parameter_name'));
    }

    public function testCorsPreflightAllowsLocalFront(): void
    {
        $client = self::createClient();
        $client->request('OPTIONS', '/api/health', server: [
            'HTTP_ORIGIN' => 'http://localhost:3000',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
        ]);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Access-Control-Allow-Origin', 'http://localhost:3000');
        self::assertResponseHasHeader('Access-Control-Allow-Methods');
    }

    public function testCorsRejectsUnknownOrigin(): void
    {
        $client = self::createClient();
        $client->request('GET', '/api/health', server: ['HTTP_ORIGIN' => 'https://evil.example']);

        self::assertResponseIsSuccessful();
        self::assertResponseNotHasHeader('Access-Control-Allow-Origin');
    }
}
