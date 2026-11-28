<?php

declare(strict_types=1);

namespace App\Tests\Api;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Requêtes JSON-LD avec, au besoin, un compte de test (HTTP Basic, config/packages/security.yaml en env test).
 */
trait ApiTestHelpers
{
    /**
     * @param 'admin'|'reader'|null $as
     * @param array<mixed>|null     $body
     *
     * @return array<mixed>
     */
    private function request(KernelBrowser $client, string $method, string $uri, ?array $body = null, ?string $as = null): array
    {
        $server = ['HTTP_ACCEPT' => 'application/ld+json'];
        if (null !== $body) {
            $server['CONTENT_TYPE'] = 'PATCH' === $method ? 'application/merge-patch+json' : 'application/ld+json';
        }
        if (null !== $as) {
            $server['PHP_AUTH_USER'] = $as;
            $server['PHP_AUTH_PW'] = $as;
        }

        $client->request($method, $uri, server: $server, content: null === $body ? null : json_encode($body, \JSON_THROW_ON_ERROR));

        $content = (string) $client->getResponse()->getContent();
        if ('' === $content) {
            return [];
        }

        $data = json_decode($content, true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }

    /**
     * @param array<mixed> $data
     *
     * @return list<mixed>
     */
    private static function column(array $data, string $field): array
    {
        self::assertIsArray($data['member'] ?? null);

        return array_column($data['member'], $field);
    }

    /**
     * Premier élément d'une liste de la réponse (relations embarquées, violations).
     *
     * @param array<mixed> $data
     *
     * @return array<mixed>
     */
    private static function firstOf(array $data, string $key): array
    {
        self::assertIsArray($data[$key] ?? null);
        self::assertIsArray($data[$key][0] ?? null);

        return $data[$key][0];
    }
}
