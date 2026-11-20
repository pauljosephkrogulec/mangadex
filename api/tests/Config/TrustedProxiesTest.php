<?php

declare(strict_types=1);

namespace App\Tests\Config;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Vérifie que la variable TRUSTED_PROXIES pilote la prise en compte des en-têtes X-Forwarded-*.
 * Derrière le load balancer de Render, la requête arrive en HTTP : sans proxy de confiance,
 * Symfony générerait des URL en http:// et verrait l'IP du load balancer au lieu de celle du client.
 */
final class TrustedProxiesTest extends KernelTestCase
{
    private const string LOAD_BALANCER_IP = '10.0.0.1';
    private const string CLIENT_IP = '203.0.113.7';

    /** @var array{env: mixed, server: mixed, remote_addr: mixed} */
    private array $backup;

    protected function setUp(): void
    {
        $this->backup = [
            'env' => $_ENV['TRUSTED_PROXIES'] ?? null,
            'server' => $_SERVER['TRUSTED_PROXIES'] ?? null,
            'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? null,
        ];
        // Le pair direct (REMOTE_ADDR) est le load balancer.
        $_SERVER['REMOTE_ADDR'] = self::LOAD_BALANCER_IP;
        Request::setTrustedProxies([], 0);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->restore('TRUSTED_PROXIES', $this->backup['env'], $this->backup['server']);
        if (null === $this->backup['remote_addr']) {
            unset($_SERVER['REMOTE_ADDR']);
        } else {
            $_SERVER['REMOTE_ADDR'] = $this->backup['remote_addr'];
        }
        Request::setTrustedProxies([], 0);
    }

    public function testForwardedHeadersAreIgnoredWithoutTrustedProxies(): void
    {
        $this->setTrustedProxies('');
        self::bootKernel();

        $request = $this->forwardedRequest();

        self::assertFalse($request->isSecure());
        self::assertSame(self::LOAD_BALANCER_IP, $request->getClientIp());
    }

    public function testForwardedHeadersAreTrustedFromRemoteAddr(): void
    {
        $this->setTrustedProxies('REMOTE_ADDR');
        self::bootKernel();

        $request = $this->forwardedRequest();

        self::assertTrue($request->isSecure());
        self::assertSame(self::CLIENT_IP, $request->getClientIp());
        self::assertSame('mangadex.example', $request->getHost());
    }

    private function forwardedRequest(): Request
    {
        return Request::create('http://php:8080/api', server: [
            'REMOTE_ADDR' => self::LOAD_BALANCER_IP,
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_FOR' => self::CLIENT_IP,
            'HTTP_X_FORWARDED_HOST' => 'mangadex.example',
            'HTTP_X_FORWARDED_PORT' => '443',
        ]);
    }

    private function setTrustedProxies(string $value): void
    {
        $_ENV['TRUSTED_PROXIES'] = $value;
        $_SERVER['TRUSTED_PROXIES'] = $value;
    }

    private function restore(string $name, mixed $env, mixed $server): void
    {
        if (null === $env) {
            unset($_ENV[$name]);
        } else {
            $_ENV[$name] = $env;
        }
        if (null === $server) {
            unset($_SERVER[$name]);
        } else {
            $_SERVER[$name] = $server;
        }
    }
}
