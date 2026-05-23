<?php

declare(strict_types=1);

namespace App\Tests\State\Processor;

use ApiPlatform\Metadata\Operation;
use App\Entity\Comment;
use App\Entity\User;
use App\State\Processor\CommentProcessor;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

#[AllowMockObjectsWithoutExpectations]
class CommentProcessorTest extends TestCase
{
    private CommentProcessor $processor;
    private \ApiPlatform\State\ProcessorInterface $decoratedMock;
    private Security $securityMock;

    protected function setUp(): void
    {
        $this->decoratedMock = $this->createMock(\ApiPlatform\State\ProcessorInterface::class);
        $this->securityMock = $this->createMock(Security::class);

        $this->processor = new CommentProcessor($this->decoratedMock, $this->securityMock);
    }

    public function testProcessSetsUserWhenNull(): void
    {
        $user = new User();
        $comment = new Comment();
        $comment->setContent('Great manga!');

        $this->securityMock->method('getUser')
            ->willReturn($user);

        $this->decoratedMock->expects($this->once())->method('process')
            ->with($comment, $this->anything(), [], [])
            ->willReturn($comment);

        $operation = $this->createStub(Operation::class);
        $result = $this->processor->process($comment, $operation);

        $this->assertSame($comment, $result);
        $this->assertSame($user, $comment->getUser());
    }

    public function testProcessDoesNotOverrideExistingUser(): void
    {
        $owner = new User();
        $comment = new Comment();
        $comment->setContent('Already has author');
        $comment->setUser($owner);

        $this->securityMock->expects($this->never())->method('getUser');

        $this->decoratedMock->expects($this->once())->method('process')
            ->with($comment, $this->anything(), [], [])
            ->willReturn($comment);

        $operation = $this->createStub(Operation::class);
        $result = $this->processor->process($comment, $operation);

        $this->assertSame($comment, $result);
        $this->assertSame($owner, $comment->getUser());
    }

    public function testProcessDoesNotSetUserWhenNoAuthenticatedUser(): void
    {
        $comment = new Comment();
        $comment->setContent('Anonymous?');

        $this->securityMock->method('getUser')
            ->willReturn(null);

        $this->decoratedMock->expects($this->once())->method('process')
            ->willReturn($comment);

        $operation = $this->createStub(Operation::class);
        $result = $this->processor->process($comment, $operation);

        $this->assertNull($comment->getUser());
    }
}
