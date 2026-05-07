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
        $this->decoratedMock = $this->createMock(SerializerContextBuilderInterface::class);
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
        $request = new Request(['include' => 'authors,artists']);
        $this->decoratedMock->method('createFromRequest')
            ->willReturn(['groups' => ['manga:read']]);

        $context = $this->builder->createFromRequest($request, true);

        $this->assertContains('manga:include:authors', $context['groups']);
        $this->assertContains('manga:include:artists', $context['groups']);
        $this->assertContains('manga:read', $context['groups']);
    }

    public function testCreateFromRequestDenormalization(): void
    {
        $request = new Request(['include' => 'authors']);
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
        $request = new Request(['include' => 'authors']);
        $this->decoratedMock->method('createFromRequest')
            ->willReturn([]);

        $context = $this->builder->createFromRequest($request, true);

        $this->assertContains('manga:include:authors', $context['groups']);
    }

    public function testCreateFromRequestTrimsIncludes(): void
    {
        $request = new Request(['include' => ' authors , artists ']);
        $this->decoratedMock->method('createFromRequest')
            ->willReturn([]);

        $context = $this->builder->createFromRequest($request, true);

        $this->assertContains('manga:include:authors', $context['groups']);
        $this->assertContains('manga:include:artists', $context['groups']);
    }

    public function testCreateFromRequestDoesNotDuplicateGroups(): void
    {
        $request = new Request(['include' => 'authors']);
        $this->decoratedMock->method('createFromRequest')
            ->willReturn(['groups' => ['manga:include:authors']]);

        $context = $this->builder->createFromRequest($request, true);

        $authorsCount = count(array_filter($context['groups'], fn ($g) => $g === 'manga:include:authors'));
        $this->assertSame(1, $authorsCount);
    }

    public function testCreateFromRequestWithExistingNonArrayGroups(): void
    {
        $request = new Request(['include' => 'authors']);
        $this->decoratedMock->method('createFromRequest')
            ->willReturn(['groups' => 'not-an-array']);

        $context = $this->builder->createFromRequest($request, true);

        // Should handle non-array groups gracefully
        $this->assertContains('manga:include:authors', $context['groups']);
    }
}
