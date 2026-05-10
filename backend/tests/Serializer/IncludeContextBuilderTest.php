<?php

declare(strict_types=1);

namespace App\Tests\Serializer;

use ApiPlatform\State\SerializerContextBuilderInterface;
use App\Serializer\IncludeContextBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class IncludeContextBuilderTest extends TestCase
{
    private IncludeContextBuilder $builder;
    private SerializerContextBuilderInterface $decoratedMock;

    protected function setUp(): void
    {
        $this->decoratedMock = $this->createStub(SerializerContextBuilderInterface::class);
        $this->builder = new IncludeContextBuilder($this->decoratedMock);
    }

    public function testCreateFromRequestWithNoIncludeParameter(): void
    {
        $request = new Request();
        $this->decoratedMock->method('createFromRequest')
            ->willReturn(['groups' => ['default']]);

        $context = $this->builder->createFromRequest($request, true);

        $this->assertSame(['groups' => ['default']], $context);
    }

    public function testCreateFromRequestWithIncludeParameter(): void
    {
        $request = new Request(['include' => 'creators,tags']);
        $this->decoratedMock->method('createFromRequest')
            ->willReturn(['groups' => ['manga:read']]);

        $context = $this->builder->createFromRequest($request, true);

        $this->assertContains('manga:include:creators', $context['groups']);
        $this->assertContains('manga:include:tags', $context['groups']);
        $this->assertContains('manga:read', $context['groups']);
    }

    public function testCreateFromRequestDenormalization(): void
    {
        $request = new Request(['include' => 'chapters']);
        $this->decoratedMock->method('createFromRequest')
            ->willReturn([]);

        $context = $this->builder->createFromRequest($request, false);

        // Should return early for denormalization
        $this->assertArrayNotHasKey('groups', $context);
    }

    public function testCreateFromRequestWithNonStringInclude(): void
    {
        $request = new Request(['include' => 123]);
        $this->decoratedMock->method('createFromRequest')
            ->willReturn(['groups' => ['default']]);

        $context = $this->builder->createFromRequest($request, true);

        // Should return early without processing (non-string value)
        $this->assertSame(['groups' => ['default']], $context);
    }

    public function testCreateFromRequestWithEmptyGroups(): void
    {
        $request = new Request(['include' => 'chapters']);
        $this->decoratedMock->method('createFromRequest')
            ->willReturn([]);

        $context = $this->builder->createFromRequest($request, true);

        $this->assertContains('manga:include:chapters', $context['groups']);
    }

    public function testCreateFromRequestTrimsIncludes(): void
    {
        $request = new Request(['include' => ' chapters , coverArt ']);
        $this->decoratedMock->method('createFromRequest')
            ->willReturn([]);

        $context = $this->builder->createFromRequest($request, true);

        $this->assertContains('manga:include:chapters', $context['groups']);
        $this->assertContains('manga:include:coverArt', $context['groups']);
    }

    public function testCreateFromRequestDoesNotDuplicateGroups(): void
    {
        $request = new Request(['include' => 'chapters']);
        $this->decoratedMock->method('createFromRequest')
            ->willReturn(['groups' => ['manga:include:chapters']]);

        $context = $this->builder->createFromRequest($request, true);

        $chaptersCount = count(array_filter($context['groups'], fn ($g) => $g === 'manga:include:chapters'));
        $this->assertSame(1, $chaptersCount);
    }

    public function testCreateFromRequestWithExistingNonArrayGroups(): void
    {
        $request = new Request(['include' => 'chapters']);
        $this->decoratedMock->method('createFromRequest')
            ->willReturn(['groups' => 'not-an-array']);

        $context = $this->builder->createFromRequest($request, true);

        // Should handle non-array groups gracefully
        $this->assertContains('manga:include:chapters', $context['groups']);
    }

    public function testCreateFromRequestSkipsNonAllowlistedIncludes(): void
    {
        $request = new Request(['include' => 'authors,artists']);
        $this->decoratedMock->method('createFromRequest')
            ->willReturn(['groups' => ['manga:read']]);

        $context = $this->builder->createFromRequest($request, true);

        // 'authors' and 'artists' are not in the allowlist, so they should be skipped
        $this->assertNotContains('manga:include:authors', $context['groups']);
        $this->assertNotContains('manga:include:artists', $context['groups']);
        $this->assertContains('manga:read', $context['groups']);
        // Default groups should be the only ones present
        $this->assertCount(1, $context['groups']);
    }
}
