<?php

declare(strict_types=1);

namespace App\Tests\Api;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Erreurs RFC 7807 (issue #24) : toute erreur de l'API est un document problem+json
 * (type, title, status, detail), y compris hors API Platform et pour les exceptions métier.
 */
final class ProblemDetailsTest extends WebTestCase
{
    /**
     * @return iterable<string, array{string, int, string, string, string}>
     */
    public static function businessExceptions(): iterable
    {
        yield 'ressource introuvable' => ['not-found', 404, '/errors/resource-not-found', 'Resource not found', 'Le chapitre 42 est introuvable.'];
        yield 'conflit' => ['conflict', 409, '/errors/conflict', 'Conflict', 'Ce titre existe déjà.'];
        yield 'règle métier' => ['business-rule', 422, '/errors/business-rule-violation', 'Business rule violation', 'Un chapitre ne peut pas être publié avant sa série.'];
    }

    #[DataProvider('businessExceptions')]
    public function testBusinessExceptionIsMappedToAProblem(string $kind, int $status, string $type, string $title, string $detail): void
    {
        $client = self::createClient();

        $problem = $this->requestProblem($client, '/api/_test/errors/'.$kind);

        self::assertResponseStatusCodeSame($status);
        self::assertSame($type, $problem['type']);
        self::assertSame($title, $problem['title']);
        self::assertSame($status, $problem['status']);
        self::assertSame($detail, $problem['detail']);
    }

    public function testConfiguredExceptionIsMappedToItsStatus(): void
    {
        $client = self::createClient();

        $problem = $this->requestProblem($client, '/api/_test/errors/serializer');

        self::assertResponseStatusCodeSame(400);
        self::assertSame(400, $problem['status']);
        self::assertSame('Corps de requête illisible.', $problem['detail']);
    }

    public function testUnknownApiRouteIsAProblem(): void
    {
        $client = self::createClient();

        $problem = $this->requestProblem($client, '/api/does-not-exist');

        self::assertResponseStatusCodeSame(404);
        self::assertSame(404, $problem['status']);
    }

    public function testMethodNotAllowedOnASymfonyControllerIsAProblem(): void
    {
        $client = self::createClient();

        $problem = $this->requestProblem($client, '/api/health', 'POST');

        self::assertResponseStatusCodeSame(405);
        self::assertSame(405, $problem['status']);
        self::assertResponseHeaderSame('Allow', 'GET');
    }

    public function testInternalErrorDetailIsHiddenOutsideDebug(): void
    {
        $client = self::createClient(['debug' => false]);

        $problem = $this->requestProblem($client, '/api/_test/errors/unexpected');

        self::assertResponseStatusCodeSame(500);
        self::assertSame(500, $problem['status']);
        self::assertIsString($problem['detail']);
        self::assertStringNotContainsString('hunter2', $problem['detail']);
        self::assertArrayNotHasKey('trace', $problem);
    }

    public function testHydraClientsGetAJsonLdError(): void
    {
        $client = self::createClient();
        $client->request('GET', '/api/_test/errors/conflict', server: ['HTTP_ACCEPT' => 'application/ld+json']);

        self::assertResponseStatusCodeSame(409);
        self::assertStringStartsWith('application/problem+json', (string) $client->getResponse()->headers->get('Content-Type'));
        $error = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($error);
        self::assertSame('Error', $error['@type'] ?? null);
        self::assertSame('Ce titre existe déjà.', $error['detail'] ?? null);
    }

    public function testBrowsersKeepTheHtmlErrorPage(): void
    {
        $client = self::createClient();
        $client->request('GET', '/api/does-not-exist', server: ['HTTP_ACCEPT' => 'text/html']);

        self::assertResponseStatusCodeSame(404);
        self::assertStringStartsWith('text/html', (string) $client->getResponse()->headers->get('Content-Type'));
    }

    /**
     * @return array<mixed>
     */
    private function requestProblem(KernelBrowser $client, string $uri, string $method = 'GET'): array
    {
        $client->request($method, $uri, server: ['HTTP_ACCEPT' => 'application/problem+json']);

        self::assertStringStartsWith('application/problem+json', (string) $client->getResponse()->headers->get('Content-Type'));
        $problem = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($problem);
        foreach (['type', 'title', 'status', 'detail'] as $member) {
            self::assertArrayHasKey($member, $problem, \sprintf('Membre RFC 7807 "%s" manquant.', $member));
        }

        return $problem;
    }
}
