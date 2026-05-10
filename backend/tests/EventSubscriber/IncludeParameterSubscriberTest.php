<?php

declare(strict_types=1);

namespace App\Tests\EventSubscriber;

use App\EventSubscriber\IncludeParameterSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class IncludeParameterSubscriberTest extends TestCase
{
    private IncludeParameterSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->subscriber = new IncludeParameterSubscriber();
    }

    public function testGetSubscribedEvents(): void
    {
        $events = IncludeParameterSubscriber::getSubscribedEvents();

        $this->assertIsArray($events);
        $this->assertArrayHasKey('kernel.request', $events);
        $this->assertEquals(['onKernelRequest', 5], $events['kernel.request']);
    }

    public function testOnKernelRequestWithIncludeParameter(): void
    {
        $request = new Request(['include' => 'chapters,coverArt']);

        $kernel = $this->createStub(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->subscriber->onKernelRequest($event);

        // Subscriber is intentionally a no-op; IncludeContextBuilder handles includes
        $this->assertNull($request->attributes->get('_api_normalization_context'));
    }

    public function testOnKernelRequestWithSingleInclude(): void
    {
        $request = new Request(['include' => 'chapters']);

        $kernel = $this->createStub(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->subscriber->onKernelRequest($event);

        // Subscriber is intentionally a no-op
        $this->assertNull($request->attributes->get('_api_normalization_context'));
    }

    public function testOnKernelRequestWithoutIncludeParameter(): void
    {
        $request = new Request();

        $kernel = $this->createStub(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->subscriber->onKernelRequest($event);

        $this->assertNull($request->attributes->get('_api_normalization_context'));
    }

    public function testOnKernelRequestWithEmptyIncludeParameter(): void
    {
        $request = new Request(['include' => '']);

        $kernel = $this->createStub(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->subscriber->onKernelRequest($event);

        // Subscriber is intentionally a no-op
        $this->assertNull($request->attributes->get('_api_normalization_context'));
    }

    public function testOnKernelRequestWithWhitespaceInInclude(): void
    {
        $request = new Request(['include' => ' chapters , coverArt ']);

        $kernel = $this->createStub(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->subscriber->onKernelRequest($event);

        // Subscriber is intentionally a no-op
        $this->assertNull($request->attributes->get('_api_normalization_context'));
    }

    public function testOnKernelRequestPreservesExistingContext(): void
    {
        $request = new Request(['include' => 'chapters']);
        $request->attributes->set('_api_normalization_context', ['existing_group']);

        $kernel = $this->createStub(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->subscriber->onKernelRequest($event);

        // Subscriber is intentionally a no-op; existing context should be preserved as-is
        $context = $request->attributes->get('_api_normalization_context');
        $this->assertSame(['existing_group'], $context);
    }

    public function testOnKernelRequestDoesNotDuplicateIncludes(): void
    {
        $request = new Request(['include' => 'chapters']);
        $request->attributes->set('_api_normalization_context', ['manga:include:chapters']);

        $kernel = $this->createStub(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->subscriber->onKernelRequest($event);

        // Subscriber is intentionally a no-op; context should remain unchanged
        $context = $request->attributes->get('_api_normalization_context');
        $this->assertSame(['manga:include:chapters'], $context);
    }

    public function testOnKernelRequestWithSubRequest(): void
    {
        $request = new Request(['include' => 'chapters']);

        $kernel = $this->createStub(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::SUB_REQUEST);

        $this->subscriber->onKernelRequest($event);

        // Subscriber is intentionally a no-op
        $this->assertNull($request->attributes->get('_api_normalization_context'));
    }

    public function testOnKernelRequestWithNonArrayContext(): void
    {
        $request = new Request(['include' => 'chapters']);
        $request->attributes->set('_api_normalization_context', 'invalid_context');

        $kernel = $this->createStub(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->subscriber->onKernelRequest($event);

        // Subscriber is intentionally a no-op; context should remain unchanged
        $this->assertSame('invalid_context', $request->attributes->get('_api_normalization_context'));
    }

    public function testOnKernelRequestWithIntegerContext(): void
    {
        $request = new Request(['include' => 'chapters']);
        $request->attributes->set('_api_normalization_context', 123);

        $kernel = $this->createStub(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->subscriber->onKernelRequest($event);

        // Subscriber is intentionally a no-op; context should remain unchanged
        $this->assertSame(123, $request->attributes->get('_api_normalization_context'));
    }

    public function testOnKernelRequestWithNonStringIncludeParameter(): void
    {
        $request = new Request(['include' => 123]);

        $kernel = $this->createStub(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->subscriber->onKernelRequest($event);

        // Subscriber is intentionally a no-op
        $this->assertNull($request->attributes->get('_api_normalization_context'));
    }
}
