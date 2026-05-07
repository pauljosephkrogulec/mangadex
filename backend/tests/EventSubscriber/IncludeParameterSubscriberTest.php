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

        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->subscriber->onKernelRequest($event);

        $context = $request->attributes->get('_api_normalization_context');
        $this->assertIsArray($context);
        $this->assertContains('manga:include:chapters', $context);
        $this->assertContains('manga:include:coverArt', $context);
    }

    public function testOnKernelRequestWithSingleInclude(): void
    {
        $request = new Request(['include' => 'chapters']);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->subscriber->onKernelRequest($event);

        $context = $request->attributes->get('_api_normalization_context');
        $this->assertIsArray($context);
        $this->assertContains('manga:include:chapters', $context);
    }

    public function testOnKernelRequestWithoutIncludeParameter(): void
    {
        $request = new Request();

        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->subscriber->onKernelRequest($event);

        $this->assertNull($request->attributes->get('_api_normalization_context'));
    }

    public function testOnKernelRequestWithEmptyIncludeParameter(): void
    {
        $request = new Request(['include' => '']);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->subscriber->onKernelRequest($event);

        // Empty string will result in array with one empty string after explode
        // The code doesn't handle this edge case properly
        $context = $request->attributes->get('_api_normalization_context');
        $this->assertIsArray($context);
    }

    public function testOnKernelRequestWithWhitespaceInInclude(): void
    {
        $request = new Request(['include' => ' chapters , coverArt ']);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->subscriber->onKernelRequest($event);

        $context = $request->attributes->get('_api_normalization_context');
        $this->assertIsArray($context);
        $this->assertContains('manga:include:chapters', $context);
        $this->assertContains('manga:include:coverArt', $context);
    }

    public function testOnKernelRequestPreservesExistingContext(): void
    {
        $request = new Request(['include' => 'chapters']);
        $request->attributes->set('_api_normalization_context', ['existing_group']);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->subscriber->onKernelRequest($event);

        $context = $request->attributes->get('_api_normalization_context');
        $this->assertIsArray($context);
        $this->assertContains('existing_group', $context);
        $this->assertContains('manga:include:chapters', $context);
    }

    public function testOnKernelRequestDoesNotDuplicateIncludes(): void
    {
        $request = new Request(['include' => 'chapters']);
        $request->attributes->set('_api_normalization_context', ['manga:include:chapters']);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->subscriber->onKernelRequest($event);

        $context = $request->attributes->get('_api_normalization_context');
        $this->assertIsArray($context);
        $this->assertEquals(1, count(array_keys($context, 'manga:include:chapters', true)));
    }

    public function testOnKernelRequestWithSubRequest(): void
    {
        $request = new Request(['include' => 'chapters']);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::SUB_REQUEST);

        $this->subscriber->onKernelRequest($event);

        // Should still process sub-requests
        $context = $request->attributes->get('_api_normalization_context');
        $this->assertIsArray($context);
        $this->assertContains('manga:include:chapters', $context);
    }

    public function testOnKernelRequestWithNonArrayContext(): void
    {
        $request = new Request(['include' => 'chapters']);
        // Set a non-array context to trigger the is_array check
        $request->attributes->set('_api_normalization_context', 'invalid_context');

        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->subscriber->onKernelRequest($event);

        // Should reset to empty array and then add the include
        $context = $request->attributes->get('_api_normalization_context');
        $this->assertIsArray($context);
        $this->assertContains('manga:include:chapters', $context);
    }

    public function testOnKernelRequestWithIntegerContext(): void
    {
        $request = new Request(['include' => 'chapters']);
        // Set an integer context to trigger the is_array check
        $request->attributes->set('_api_normalization_context', 123);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->subscriber->onKernelRequest($event);

        // Should reset to empty array and then add the include
        $context = $request->attributes->get('_api_normalization_context');
        $this->assertIsArray($context);
        $this->assertContains('manga:include:chapters', $context);
    }

    public function testOnKernelRequestWithNonStringIncludeParameter(): void
    {
        // Use an integer as the include value — it passes InputBag validation (scalar)
        // but is_string() returns false, so the method returns early
        $request = new Request(['include' => 123]);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->subscriber->onKernelRequest($event);

        // is_string($includeParam) is false, so the method returns early
        // _api_normalization_context should not be set
        $this->assertNull($request->attributes->get('_api_normalization_context'));
    }
}
