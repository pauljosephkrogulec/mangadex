<?php

namespace App\Tests\Service;

use App\Service\JwtAuthService;
use PHPUnit\Framework\TestCase;

class JwtAuthServiceTest extends TestCase
{
    private JwtAuthService $service;

    protected function setUp(): void
    {
        $this->service = new JwtAuthService();
    }

    public function testIsTokenValidReturnsTrueForValidJwt(): void
    {
        $this->assertTrue($this->service->isTokenValid('eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...'));
    }

    public function testIsTokenValidReturnsFalseForNull(): void
    {
        $this->assertFalse($this->service->isTokenValid(null));
    }

    public function testIsTokenValidReturnsFalseForEmptyString(): void
    {
        $this->assertFalse($this->service->isTokenValid(''));
    }

    public function testIsTokenValidReturnsFalseForInvalidPrefix(): void
    {
        $this->assertFalse($this->service->isTokenValid('invalid_token'));
    }

    public function testExtractTokenFromHeaderReturnsToken(): void
    {
        $this->assertEquals(
            'my-jwt-token',
            $this->service->extractTokenFromHeader('Bearer my-jwt-token')
        );
    }

    public function testExtractTokenFromHeaderReturnsNullForNull(): void
    {
        $this->assertNull($this->service->extractTokenFromHeader(null));
    }

    public function testExtractTokenFromHeaderReturnsNullForInvalidHeader(): void
    {
        $this->assertNull($this->service->extractTokenFromHeader('Basic some-other-token'));
    }

    public function testExtractTokenFromHeaderIsCaseInsensitive(): void
    {
        $this->assertEquals(
            'my-token',
            $this->service->extractTokenFromHeader('bearer my-token')
        );
    }
}
