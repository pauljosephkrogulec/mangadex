<?php

declare(strict_types=1);

namespace App\Tests\EventSubscriber;

use App\Entity\User;
use App\EventSubscriber\JwtCookieSubscriber;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;

class JwtCookieSubscriberTest extends TestCase
{
    public function testOnAuthenticationSuccessSetsCookieInProd(): void
    {
        $subscriber = new JwtCookieSubscriber('prod');

        $response = new JsonResponse(['success' => true]);
        $user = new User();
        $event = new AuthenticationSuccessEvent(['token' => 'test-jwt-token'], $user, $response);

        $subscriber->onAuthenticationSuccess($event);

        $response = $event->getResponse();
        $cookies = $response->headers->getCookies();

        $this->assertCount(1, $cookies);
        $this->assertSame('mangadex_jwt_token', $cookies[0]->getName());
        $this->assertSame('test-jwt-token', $cookies[0]->getValue());
        $this->assertTrue($cookies[0]->isSecure());
        $this->assertTrue($cookies[0]->isHttpOnly());
    }

    public function testOnAuthenticationSuccessSetsCookieInDev(): void
    {
        $subscriber = new JwtCookieSubscriber('dev');

        $response = new JsonResponse(['success' => true]);
        $user = new User();
        $event = new AuthenticationSuccessEvent(['token' => 'test-jwt-token'], $user, $response);

        $subscriber->onAuthenticationSuccess($event);

        $response = $event->getResponse();
        $cookies = $response->headers->getCookies();

        $this->assertCount(1, $cookies);
        $this->assertFalse($cookies[0]->isSecure()); // Not secure in non-prod
    }

    public function testOnAuthenticationSuccessNoToken(): void
    {
        $subscriber = new JwtCookieSubscriber('prod');

        $response = new JsonResponse(['success' => true]);
        $user = new User();
        $event = new AuthenticationSuccessEvent(['user' => 'test'], $user, $response);

        $subscriber->onAuthenticationSuccess($event);

        $response = $event->getResponse();
        $cookies = $response->headers->getCookies();

        $this->assertCount(0, $cookies);
    }

    public function testOnAuthenticationSuccessNullToken(): void
    {
        $subscriber = new JwtCookieSubscriber('prod');

        $response = new JsonResponse(['success' => true]);
        $user = new User();
        $event = new AuthenticationSuccessEvent(['token' => null], $user, $response);

        $subscriber->onAuthenticationSuccess($event);

        $response = $event->getResponse();
        $cookies = $response->headers->getCookies();

        $this->assertCount(0, $cookies);
    }

    public function testCookieAttributes(): void
    {
        $subscriber = new JwtCookieSubscriber('prod');

        $response = new JsonResponse(['success' => true]);
        $user = new User();
        $event = new AuthenticationSuccessEvent(['token' => 'test-token'], $user, $response);

        $subscriber->onAuthenticationSuccess($event);

        $response = $event->getResponse();
        $cookies = $response->headers->getCookies();
        $cookie = $cookies[0];

        $this->assertSame('/', $cookie->getPath());
        $this->assertNull($cookie->getDomain());
        $this->assertSame('lax', $cookie->getSameSite());
    }
}
