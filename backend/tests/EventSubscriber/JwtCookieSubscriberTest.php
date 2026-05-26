<?php

declare(strict_types=1);

namespace App\Tests\EventSubscriber;

use App\Entity\User;
use App\EventSubscriber\JwtCookieSubscriber;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\User\UserInterface;

class JwtCookieSubscriberTest extends TestCase
{
    private JwtCookieSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->subscriber = new JwtCookieSubscriber();
    }

    private function createUser(): User
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setUsername('testuser');
        $user->setPassword('hashed');

        return $user;
    }

    public function testOnAuthenticationSuccessIncludesUserData(): void
    {
        $response = new JsonResponse(['success' => true]);
        $user = $this->createUser();
        $event = new AuthenticationSuccessEvent(['token' => 'test-jwt-token'], $user, $response);

        $this->subscriber->onAuthenticationSuccess($event);

        $data = $event->getData();
        $this->assertArrayHasKey('user', $data);
        $this->assertSame('test@example.com', $data['user']['email']);
        $this->assertSame('testuser', $data['user']['username']);
        $this->assertArrayHasKey('id', $data['user']);
        $this->assertArrayHasKey('createdAt', $data['user']);
        $this->assertContains('ROLE_USER', $data['user']['roles']);
    }

    public function testOnAuthenticationSuccessPreservesExistingData(): void
    {
        $response = new JsonResponse();
        $user = $this->createUser();
        $event = new AuthenticationSuccessEvent(['token' => 'test-jwt-token'], $user, $response);

        $this->subscriber->onAuthenticationSuccess($event);

        $data = $event->getData();
        $this->assertArrayHasKey('token', $data);
        $this->assertSame('test-jwt-token', $data['token']);
    }

    public function testOnAuthenticationSuccessNoCookiesSetBySubscriber(): void
    {
        $response = new JsonResponse();
        $user = $this->createUser();
        $event = new AuthenticationSuccessEvent(['token' => 'test-jwt-token'], $user, $response);

        $this->subscriber->onAuthenticationSuccess($event);

        $this->assertCount(0, $event->getResponse()->headers->getCookies());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testOnAuthenticationSuccessNoUserDataWhenUserNotInstanceOfUser(): void
    {
        $response = new JsonResponse();
        $nonAppUser = $this->createMock(UserInterface::class);
        $event = new AuthenticationSuccessEvent(['token' => 'test-jwt-token'], $nonAppUser, $response);

        $this->subscriber->onAuthenticationSuccess($event);

        $data = $event->getData();
        $this->assertArrayNotHasKey('user', $data);
    }
}
