<?php

declare(strict_types=1);

namespace App\Tests\Dto;

use App\Dto\UserRegistrationDto;
use PHPUnit\Framework\TestCase;

class UserRegistrationDtoTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        $dto = new UserRegistrationDto();

        $dto->setEmail('test@example.com');
        $this->assertSame('test@example.com', $dto->getEmail());

        $dto->setUsername('testuser');
        $this->assertSame('testuser', $dto->getUsername());

        $dto->setPassword('password123');
        $this->assertSame('password123', $dto->getPassword());
    }

    public function testSetEmailReturnsStatic(): void
    {
        $dto = new UserRegistrationDto();
        $result = $dto->setEmail('test@example.com');
        $this->assertSame($dto, $result);
    }

    public function testSetUsernameReturnsStatic(): void
    {
        $dto = new UserRegistrationDto();
        $result = $dto->setUsername('testuser');
        $this->assertSame($dto, $result);
    }

    public function testSetPasswordReturnsStatic(): void
    {
        $dto = new UserRegistrationDto();
        $result = $dto->setPassword('password123');
        $this->assertSame($dto, $result);
    }
}
