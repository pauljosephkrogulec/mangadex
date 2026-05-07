<?php

declare(strict_types=1);

namespace App\Tests\State\Processor;

use ApiPlatform\Metadata\Operation;
use App\Dto\UserUpdateDto;
use App\Entity\User;
use App\State\Processor\UserUpdateProcessor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserUpdateProcessorTest extends TestCase
{
    private UserUpdateProcessor $processor;
    private UserPasswordHasherInterface $passwordHasherMock;
    private \ApiPlatform\State\ProcessorInterface $decoratedMock;

    protected function setUp(): void
    {
        $this->passwordHasherMock = $this->createMock(UserPasswordHasherInterface::class);
        $this->decoratedMock = $this->createMock(\ApiPlatform\State\ProcessorInterface::class);

        $this->processor = new UserUpdateProcessor($this->decoratedMock, $this->passwordHasherMock);
    }

    public function testProcessWithDtoAndPreviousData(): void
    {
        $dto = new UserUpdateDto();
        $dto->setUsername('newusername');
        $dto->setPassword('newpassword');

        $user = new User();
        $user->setUsername('oldusername');
        $user->setPassword('oldpassword');

        $this->passwordHasherMock->method('hashPassword')
            ->with($user, 'newpassword')
            ->willReturn('hashed_password');

        $this->decoratedMock->method('process')
            ->with($user, $this->anything(), [], ['previous_data' => $user])
            ->willReturn($user);

        $operation = $this->createMock(Operation::class);
        $context = ['previous_data' => $user];

        $result = $this->processor->process($dto, $operation, [], $context);

        $this->assertSame($user, $result);
        $this->assertSame('newusername', $user->getUsername());
        $this->assertSame('hashed_password', $user->getPassword());
    }

    public function testProcessWithDtoOnlyUsername(): void
    {
        $dto = new UserUpdateDto();
        $dto->setUsername('newusername');

        $user = new User();
        $user->setUsername('oldusername');
        $user->setPassword('oldpassword');

        $this->decoratedMock->method('process')
            ->willReturn($user);

        $operation = $this->createMock(Operation::class);
        $context = ['previous_data' => $user];

        $result = $this->processor->process($dto, $operation, [], $context);

        $this->assertSame('newusername', $user->getUsername());
        $this->assertSame('oldpassword', $user->getPassword()); // Password unchanged
    }

    public function testProcessWithDtoOnlyPassword(): void
    {
        $dto = new UserUpdateDto();
        $dto->setPassword('newpassword');

        $user = new User();
        $user->setUsername('oldusername');
        $user->setPassword('oldpassword');

        $this->passwordHasherMock->method('hashPassword')
            ->willReturn('hashed_password');

        $this->decoratedMock->method('process')
            ->willReturn($user);

        $operation = $this->createMock(Operation::class);
        $context = ['previous_data' => $user];

        $result = $this->processor->process($dto, $operation, [], $context);

        $this->assertSame('oldusername', $user->getUsername()); // Username unchanged
        $this->assertSame('hashed_password', $user->getPassword());
    }

    public function testProcessWithoutPreviousData(): void
    {
        $dto = new UserUpdateDto();

        $this->decoratedMock->method('process')
            ->with($dto, $this->anything(), [], [])
            ->willReturn(new User());

        $operation = $this->createMock(Operation::class);

        $result = $this->processor->process($dto, $operation);

        $this->assertInstanceOf(User::class, $result);
    }

    public function testProcessWithNonDto(): void
    {
        $data = new \stdClass();

        $this->decoratedMock->method('process')
            ->with($data, $this->anything(), [], [])
            ->willReturn(new User());

        $operation = $this->createMock(Operation::class);

        $result = $this->processor->process($data, $operation);

        $this->assertInstanceOf(User::class, $result);
    }
}
