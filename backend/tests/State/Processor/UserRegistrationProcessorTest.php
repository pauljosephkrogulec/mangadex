<?php

declare(strict_types=1);

namespace App\Tests\State\Processor;

use ApiPlatform\Metadata\Operation;
use App\Dto\UserRegistrationDto;
use App\Entity\User;
use App\State\Processor\UserRegistrationProcessor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserRegistrationProcessorTest extends TestCase
{
    private UserRegistrationProcessor $processor;
    private UserPasswordHasherInterface $passwordHasherMock;
    private \ApiPlatform\State\ProcessorInterface $decoratedMock;

    protected function setUp(): void
    {
        $this->passwordHasherMock = $this->createMock(UserPasswordHasherInterface::class);
        $this->decoratedMock = $this->createMock(\ApiPlatform\State\ProcessorInterface::class);

        $this->processor = new UserRegistrationProcessor($this->decoratedMock, $this->passwordHasherMock);
    }

    public function testProcessWithDto(): void
    {
        $dto = new UserRegistrationDto();
        $dto->setEmail('test@example.com');
        $dto->setUsername('testuser');
        $dto->setPassword('password123');

        $this->passwordHasherMock->method('hashPassword')
            ->willReturn('hashed_password');

        $createdUser = new User();
        $this->decoratedMock->method('process')
            ->willReturnCallback(function ($user) use (&$createdUser) {
                $createdUser = $user;
                return $user;
            });

        $operation = $this->createMock(Operation::class);
        $result = $this->processor->process($dto, $operation);

        $this->assertInstanceOf(User::class, $result);
        $this->assertSame('test@example.com', $result->getEmail());
        $this->assertSame('testuser', $result->getUsername());
        $this->assertSame('hashed_password', $result->getPassword());
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

    public function testProcessCallsDecoratedProcessor(): void
    {
        $dto = new UserRegistrationDto();
        $dto->setEmail('test@example.com');
        $dto->setUsername('testuser');
        $dto->setPassword('password123');

        $this->passwordHasherMock->method('hashPassword')
            ->willReturn('hashed_password');

        $this->decoratedMock->expects($this->once())
            ->method('process')
            ->with($this->callback(function ($user) {
                return $user instanceof User
                    && $user->getEmail() === 'test@example.com'
                    && $user->getUsername() === 'testuser';
            }))
            ->willReturn(new User());

        $operation = $this->createMock(Operation::class);
        $this->processor->process($dto, $operation);
    }

    public function testProcessSetsHashedPassword(): void
    {
        $dto = new UserRegistrationDto();
        $dto->setEmail('test@example.com');
        $dto->setUsername('testuser');
        $dto->setPassword('password123');

        $this->passwordHasherMock->method('hashPassword')
            ->willReturn('my_hashed_password');

        $returnedUser = new User();
        $this->decoratedMock->method('process')
            ->willReturnCallback(function ($user) use (&$returnedUser) {
                $returnedUser = $user;
                return $user;
            });

        $operation = $this->createMock(Operation::class);
        $result = $this->processor->process($dto, $operation);

        $this->assertSame('my_hashed_password', $result->getPassword());
    }
}
