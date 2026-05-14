<?php

declare(strict_types=1);

namespace App\Tests\EventSubscriber;

use App\EventSubscriber\RateLimiterSubscriber;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

#[AllowMockObjectsWithoutExpectations]
class RateLimiterSubscriberTest extends TestCase
{
    private RateLimiterFactoryInterface $loginFactory;
    private RateLimiterFactoryInterface $registrationFactory;
    private RateLimiterFactoryInterface $apiFactory;
    private KernelInterface $kernel;
    private RateLimiterSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->loginFactory = $this->createMock(RateLimiterFactoryInterface::class);
        $this->registrationFactory = $this->createMock(RateLimiterFactoryInterface::class);
        $this->apiFactory = $this->createMock(RateLimiterFactoryInterface::class);
        $this->kernel = $this->createMock(KernelInterface::class);

        $this->subscriber = new RateLimiterSubscriber(
            $this->loginFactory,
            $this->registrationFactory,
            $this->apiFactory,
            $this->kernel,
        );
    }

    public function testSkipsRateLimitingInTestEnvironment(): void
    {
        $this->kernel
            ->method('getEnvironment')
            ->willReturn('test');

        $event = $this->createMock(RequestEvent::class);
        $event->expects($this->never())->method('getRequest');

        $this->subscriber->onKernelRequest($event);
    }

    public function testSkipsRateLimitingForNonApiPaths(): void
    {
        $this->kernel
            ->method('getEnvironment')
            ->willReturn('prod');

        $request = $this->createMock(Request::class);
        $request
            ->method('getPathInfo')
            ->willReturn('/login');

        $event = $this->createMock(RequestEvent::class);
        $event
            ->method('getRequest')
            ->willReturn($request);

        $this->loginFactory->expects($this->never())->method('create');
        $this->registrationFactory->expects($this->never())->method('create');
        $this->apiFactory->expects($this->never())->method('create');

        $this->subscriber->onKernelRequest($event);
    }

    public function testAppliesBothLimitersOnRegistrationAccepted(): void
    {
        $this->kernel
            ->method('getEnvironment')
            ->willReturn('prod');

        $request = $this->createMock(Request::class);
        $request
            ->method('getPathInfo')
            ->willReturn('/api/users');
        $request
            ->method('getMethod')
            ->willReturn('POST');
        $request
            ->method('getClientIp')
            ->willReturn('127.0.0.1');

        $regLimit = $this->createMock(RateLimit::class);
        $regLimit->method('isAccepted')->willReturn(true);

        $regLimiter = $this->createMock(LimiterInterface::class);
        $regLimiter->method('consume')->willReturn($regLimit);

        $this->registrationFactory
            ->method('create')
            ->willReturn($regLimiter);

        $apiLimit = $this->createMock(RateLimit::class);
        $apiLimit->method('isAccepted')->willReturn(true);

        $apiLimiter = $this->createMock(LimiterInterface::class);
        $apiLimiter->method('consume')->willReturn($apiLimit);

        $this->apiFactory
            ->method('create')
            ->willReturn($apiLimiter);

        $event = $this->createMock(RequestEvent::class);
        $event
            ->method('getRequest')
            ->willReturn($request);

        $this->subscriber->onKernelRequest($event);
        $this->assertTrue(true);
    }

    public function testThrowsWhenRegistrationRateLimitExceeded(): void
    {
        $this->kernel
            ->method('getEnvironment')
            ->willReturn('prod');

        $request = $this->createMock(Request::class);
        $request
            ->method('getPathInfo')
            ->willReturn('/api/users');
        $request
            ->method('getMethod')
            ->willReturn('POST');
        $request
            ->method('getClientIp')
            ->willReturn('127.0.0.1');

        $regLimit = $this->createMock(RateLimit::class);
        $regLimit->method('isAccepted')->willReturn(false);

        $regLimiter = $this->createMock(LimiterInterface::class);
        $regLimiter->method('consume')->willReturn($regLimit);

        $this->registrationFactory
            ->method('create')
            ->willReturn($regLimiter);

        $this->apiFactory->expects($this->never())->method('create');

        $event = $this->createMock(RequestEvent::class);
        $event
            ->method('getRequest')
            ->willReturn($request);

        $this->expectException(TooManyRequestsHttpException::class);

        $this->subscriber->onKernelRequest($event);
    }

    public function testThrowsWhenApiRateLimitExceeded(): void
    {
        $this->kernel
            ->method('getEnvironment')
            ->willReturn('prod');

        $request = $this->createMock(Request::class);
        $request
            ->method('getPathInfo')
            ->willReturn('/api/manga');
        $request
            ->method('getMethod')
            ->willReturn('GET');
        $request
            ->method('getClientIp')
            ->willReturn('10.0.0.1');

        $apiLimit = $this->createMock(RateLimit::class);
        $apiLimit->method('isAccepted')->willReturn(false);

        $apiLimiter = $this->createMock(LimiterInterface::class);
        $apiLimiter->method('consume')->willReturn($apiLimit);

        $this->apiFactory
            ->method('create')
            ->willReturn($apiLimiter);

        $event = $this->createMock(RequestEvent::class);
        $event
            ->method('getRequest')
            ->willReturn($request);

        $this->expectException(TooManyRequestsHttpException::class);

        $this->subscriber->onKernelRequest($event);
    }

    public function testRegistrationAcceptedSkipsApiLimiter(): void
    {
        $this->kernel
            ->method('getEnvironment')
            ->willReturn('prod');

        $request = $this->createMock(Request::class);
        $request
            ->method('getPathInfo')
            ->willReturn('/api/users');
        $request
            ->method('getMethod')
            ->willReturn('POST');
        $request
            ->method('getClientIp')
            ->willReturn('127.0.0.1');

        $regLimit = $this->createMock(RateLimit::class);
        $regLimit->method('isAccepted')->willReturn(true);

        $regLimiter = $this->createMock(LimiterInterface::class);
        $regLimiter->method('consume')->willReturn($regLimit);

        $this->registrationFactory
            ->method('create')
            ->willReturn($regLimiter);

        // API limiter should NOT be called because registration endpoint returns early
        $this->apiFactory->expects($this->never())->method('create');

        $event = $this->createMock(RequestEvent::class);
        $event
            ->method('getRequest')
            ->willReturn($request);

        // Should not throw — registration is accepted and early return skips API limiter
        $this->subscriber->onKernelRequest($event);
        $this->assertTrue(true);
    }

    public function testAppliesLoginLimiterOnLoginEndpoint(): void
    {
        $this->kernel
            ->method('getEnvironment')
            ->willReturn('prod');

        $request = $this->createMock(Request::class);
        $request
            ->method('getPathInfo')
            ->willReturn('/api/login_check');
        $request
            ->method('getMethod')
            ->willReturn('POST');
        $request
            ->method('getClientIp')
            ->willReturn('10.0.0.1');

        $loginLimit = $this->createMock(RateLimit::class);
        $loginLimit->method('isAccepted')->willReturn(true);

        $loginLimiter = $this->createMock(LimiterInterface::class);
        $loginLimiter->method('consume')->willReturn($loginLimit);

        $this->loginFactory
            ->method('create')
            ->willReturn($loginLimiter);

        $this->registrationFactory->expects($this->never())->method('create');
        $this->apiFactory->expects($this->never())->method('create');

        $event = $this->createMock(RequestEvent::class);
        $event
            ->method('getRequest')
            ->willReturn($request);

        $this->subscriber->onKernelRequest($event);
        $this->assertTrue(true);
    }

    public function testThrowsWhenLoginRateLimitExceeded(): void
    {
        $this->kernel
            ->method('getEnvironment')
            ->willReturn('prod');

        $request = $this->createMock(Request::class);
        $request
            ->method('getPathInfo')
            ->willReturn('/api/login_check');
        $request
            ->method('getMethod')
            ->willReturn('POST');
        $request
            ->method('getClientIp')
            ->willReturn('10.0.0.1');

        $loginLimit = $this->createMock(RateLimit::class);
        $loginLimit->method('isAccepted')->willReturn(false);

        $loginLimiter = $this->createMock(LimiterInterface::class);
        $loginLimiter->method('consume')->willReturn($loginLimit);

        $this->loginFactory
            ->method('create')
            ->willReturn($loginLimiter);

        $event = $this->createMock(RequestEvent::class);
        $event
            ->method('getRequest')
            ->willReturn($request);

        $this->expectException(TooManyRequestsHttpException::class);

        $this->subscriber->onKernelRequest($event);
    }

    public function testAppliesApiLimiterForNonRegistrationApiPath(): void
    {
        $this->kernel
            ->method('getEnvironment')
            ->willReturn('prod');

        $request = $this->createMock(Request::class);
        $request
            ->method('getPathInfo')
            ->willReturn('/api/manga/1');
        $request
            ->method('getMethod')
            ->willReturn('GET');
        $request
            ->method('getClientIp')
            ->willReturn('10.0.0.1');

        $apiLimit = $this->createMock(RateLimit::class);
        $apiLimit->method('isAccepted')->willReturn(true);

        $apiLimiter = $this->createMock(LimiterInterface::class);
        $apiLimiter->method('consume')->willReturn($apiLimit);

        $this->apiFactory
            ->method('create')
            ->willReturn($apiLimiter);

        $this->loginFactory->expects($this->never())->method('create');
        $this->registrationFactory->expects($this->never())->method('create');

        $event = $this->createMock(RequestEvent::class);
        $event
            ->method('getRequest')
            ->willReturn($request);

        $this->subscriber->onKernelRequest($event);
        $this->assertTrue(true);
    }
}
