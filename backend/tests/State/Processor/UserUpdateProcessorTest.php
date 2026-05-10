<?php

declare(strict_types=1);

namespace App\Tests\State\Processor;

use ApiPlatform\Metadata\Operation;
use App\Dto\UserUpdateDto;
use App\Entity\User;
use App\State\Processor\UserUpdateProcessor;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AllowMockObjectsWithoutExpectations]
class UserUpdateProcessorTest extends TestCase
{
    private UserUpdateProcessor $processor;
    private UserPasswordHasherInterface $passwordHasherMock;
    private \ApiPlatform\State\ProcessorInterface $decoratedMock;
    private Security $securityMock;

    protected function setUp(): void
    {
        $this->passwordHasherMock = $this->createMock(UserPasswordHasherInterface::class);
        $this->decoratedMock = $this->createMock(\ApiPlatform\State\ProcessorInterface::class);
        $this->securityMock = $this->createMock(Security::class);

        $this->processor = new UserUpdateProcessor($this->decoratedMock, $this->passwordHasherMock, $this->securityMock);
    }

    public function testProcessWithDtoAndPreviousData(): void
    {
        $dto = new UserUpdateDto();
        $dto->setUsername('newusername');
        $dto->setPassword('newpassword');

        $user = new User();
        $user->setUsername('oldusername');
        $user->setPassword('oldpassword');

        $this->passwordHasherMock->expects($this->once())->method('hashPassword')
            ->with($user, 'newpassword')
            ->willReturn('hashed_password');

        $this->securityMock->method('getUser')
            ->willReturn($user);

        $this->decoratedMock->expects($this->once())->method('process')
            ->with($user, $this->anything(), [], ['previous_data' => $user])
            ->willReturn($user);

        $operation = $this->createStub(Operation::class);
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

        $this->securityMock->method('getUser')
            ->willReturn($user);

        $this->decoratedMock->method('process')
            ->willReturn($user);

        $operation = $this->createStub(Operation::class);
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

        $this->passwordHasherMock->expects($this->once())->method('hashPassword')
            ->willReturn('hashed_password');

        $this->securityMock->method('getUser')
            ->willReturn($user);

        $this->decoratedMock->method('process')
            ->willReturn($user);

        $operation = $this->createStub(Operation::class);
        $context = ['previous_data' => $user];

        $result = $this->processor->process($dto, $operation, [], $context);

        $this->assertSame('oldusername', $user->getUsername()); // Username unchanged
        $this->assertSame('hashed_password', $user->getPassword());
    }

    public function testProcessWithoutPreviousData(): void
    {
        $dto = new UserUpdateDto();

        $this->decoratedMock->expects($this->once())->method('process')
            ->with($dto, $this->anything(), [], [])
            ->willReturn(new User());

        $operation = $this->createStub(Operation::class);

        $result = $this->processor->process($dto, $operation);

        $this->assertInstanceOf(User::class, $result);
    }

    public function testProcessWithNonDto(): void
    {
        $data = new \stdClass();

        $this->decoratedMock->expects($this->once())->method('process')
            ->with($data, $this->anything(), [], [])
            ->willReturn(new User());

        $operation = $this->createStub(Operation::class);

        $result = $this->processor->process($data, $operation);

        $this->assertInstanceOf(User::class, $result);
    }

    public function testProcessWithAdminCanUpdateAnyUser(): void
    {
        $dto = new UserUpdateDto();
        $dto->setUsername('hacked');

        $targetUser = new User();
        $targetUser->setUsername('target');

        $otherUser = new User();
        $otherUser->setUsername('other');

        $this->securityMock->method('getUser')
            ->willReturn($otherUser);
        $this->securityMock->method('isGranted')
            ->with('ROLE_ADMIN')
            ->willReturn(true);

        $this->decoratedMock->expects($this->once())->method('process')
            ->with($targetUser, $this->anything(), [], ['previous_data' => $targetUser])
            ->willReturn($targetUser);

        $operation = $this->createStub(Operation::class);
        $context = ['previous_data' => $targetUser];

        $result = $this->processor->process($dto, $operation, [], $context);

        $this->assertSame($targetUser, $result);
        $this->assertSame('hacked', $targetUser->getUsername());
    }

    public function testProcessWithNonAdminCannotUpdateOtherUser(): void
    {
        $dto = new UserUpdateDto();
        $dto->setUsername('hacked');

        $targetUser = new User();
        $targetUser->setUsername('target');

        $otherUser = new User();
        $otherUser->setUsername('other');

        $this->securityMock->method('getUser')
            ->willReturn($otherUser);
        $this->securityMock->method('isGranted')
            ->with('ROLE_ADMIN')
            ->willReturn(false);

        $operation = $this->createStub(Operation::class);
        $context = ['previous_data' => $targetUser];

        $this->expectException(\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException::class);
        $this->expectExceptionMessage('You can only update your own profile');

        $this->processor->process($dto, $operation, [], $context);
    }
}
