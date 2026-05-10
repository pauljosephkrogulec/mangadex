<?php

declare(strict_types=1);

namespace App\Tests\State\Processor;

use ApiPlatform\Metadata\Operation;
use App\Entity\CustomList;
use App\Entity\User;
use App\State\Processor\CustomListProcessor;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

#[AllowMockObjectsWithoutExpectations]
class CustomListProcessorTest extends TestCase
{
    private CustomListProcessor $processor;
    private \ApiPlatform\State\ProcessorInterface $decoratedMock;
    private Security $securityMock;

    protected function setUp(): void
    {
        $this->decoratedMock = $this->createMock(\ApiPlatform\State\ProcessorInterface::class);
        $this->securityMock = $this->createMock(Security::class);

        $this->processor = new CustomListProcessor($this->decoratedMock, $this->securityMock);
    }

    public function testProcessSetsUserWhenNull(): void
    {
        $user = new User();
        $customList = new CustomList();

        $this->securityMock->method('getUser')
            ->willReturn($user);

        $this->decoratedMock->expects($this->once())->method('process')
            ->with($customList, $this->anything(), [], [])
            ->willReturn($customList);

        $operation = $this->createStub(Operation::class);
        $result = $this->processor->process($customList, $operation);

        $this->assertSame($customList, $result);
        $this->assertSame($user, $customList->getUser());
    }

    public function testProcessDoesNotOverrideExistingUser(): void
    {
        $owner = new User();
        $customList = new CustomList();
        $customList->setUser($owner);

        $this->securityMock->expects($this->never())->method('getUser');

        $this->decoratedMock->expects($this->once())->method('process')
            ->with($customList, $this->anything(), [], [])
            ->willReturn($customList);

        $operation = $this->createStub(Operation::class);
        $result = $this->processor->process($customList, $operation);

        $this->assertSame($customList, $result);
    }

    public function testProcessDoesNotSetUserWhenNoAuthenticatedUser(): void
    {
        $customList = new CustomList();

        $this->securityMock->method('getUser')
            ->willReturn(null);

        $this->decoratedMock->expects($this->once())->method('process')
            ->willReturn($customList);

        $operation = $this->createStub(Operation::class);
        $result = $this->processor->process($customList, $operation);

        $this->assertNull($customList->getUser());
    }

}
