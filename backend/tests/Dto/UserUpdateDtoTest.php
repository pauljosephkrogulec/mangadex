<?php

declare(strict_types=1);

namespace App\Tests\Dto;

use App\Dto\UserUpdateDto;
use PHPUnit\Framework\TestCase;

class UserUpdateDtoTest extends TestCase
{
    public function testGettersAndSettersWithValues(): void
    {
        $dto = new UserUpdateDto();

        $dto->setUsername('updateduser');
        $this->assertSame('updateduser', $dto->getUsername());

        $dto->setPassword('newpassword123');
        $this->assertSame('newpassword123', $dto->getPassword());
    }

    public function testGettersReturnNullWhenNotSet(): void
    {
        $dto = new UserUpdateDto();

        $this->assertNull($dto->getUsername());
        $this->assertNull($dto->getPassword());
    }

    public function testSetUsernameToNull(): void
    {
        $dto = new UserUpdateDto();
        $dto->setUsername('test');
        $dto->setUsername(null);
        $this->assertNull($dto->getUsername());
    }

    public function testSetPasswordToNull(): void
    {
        $dto = new UserUpdateDto();
        $dto->setPassword('test');
        $dto->setPassword(null);
        $this->assertNull($dto->getPassword());
    }

    public function testSetUsernameReturnsStatic(): void
    {
        $dto = new UserUpdateDto();
        $result = $dto->setUsername('test');
        $this->assertSame($dto, $result);
    }

    public function testSetPasswordReturnsStatic(): void
    {
        $dto = new UserUpdateDto();
        $result = $dto->setPassword('test');
        $this->assertSame($dto, $result);
    }
}
