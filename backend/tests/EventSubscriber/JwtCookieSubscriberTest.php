<?php

declare(strict_types=1);

namespace App\Tests\EventSubscriber;

use App\Entity\User;
use App\EventSubscriber\JwtCookieSubscriber;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\User\UserInterface;

class JwtCookieSubscriberTest extends TestCase
{
    private function createUser(): User
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setUsername('testuser');
        $user->setPassword('hashed');

        return $user;
    }

    public function testOnAuthenticationSuccessSetsCookieInProd(): void
    {
        $subscriber = new JwtCookieSubscriber('prod');

        $response = new JsonResponse(['success' => true]);
        $user = $this->createUser();
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
        $user = $this->createUser();
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
        $user = $this->createUser();
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
        $user = $this->createUser();
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
        $user = $this->createUser();
        $event = new AuthenticationSuccessEvent(['token' => 'test-token'], $user, $response);

        $subscriber->onAuthenticationSuccess($event);

        $response = $event->getResponse();
        $cookies = $response->headers->getCookies();
        $cookie = $cookies[0];

        $this->assertSame('/', $cookie->getPath());
        $this->assertNull($cookie->getDomain());
        $this->assertSame('lax', $cookie->getSameSite());
    }

    public function testOnAuthenticationSuccessIncludesUserData(): void
    {
        $subscriber = new JwtCookieSubscriber('dev');

        $response = new JsonResponse(['success' => true]);
        $user = $this->createUser();
        $event = new AuthenticationSuccessEvent(['token' => 'test-jwt-token'], $user, $response);

        $subscriber->onAuthenticationSuccess($event);

        $data = $event->getData();
        $this->assertArrayHasKey('user', $data);
        $this->assertSame('test@example.com', $data['user']['email']);
        $this->assertSame('testuser', $data['user']['username']);
        $this->assertArrayHasKey('id', $data['user']);
        $this->assertArrayHasKey('createdAt', $data['user']);
    }

    public function testOnAuthenticationSuccessNoUserDataWhenUserNotInstanceOfUser(): void
    {
        $subscriber = new JwtCookieSubscriber('dev');

        $response = new JsonResponse(['success' => true]);
        $nonAppUser = $this->createMock(UserInterface::class);
        $event = new AuthenticationSuccessEvent(['token' => 'test-jwt-token'], $nonAppUser, $response);

        $subscriber->onAuthenticationSuccess($event);

        $data = $event->getData();
        $this->assertArrayNotHasKey('user', $data);
    }
}
