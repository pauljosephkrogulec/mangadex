<?php

namespace App\Tests;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function testEntity(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setPassword('hashed_password_here');
        $user->setRoles(['ROLE_ADMIN']);

        $this->assertEquals('test@example.com', $user->getEmail());
        $this->assertEquals('hashed_password_here', $user->getPassword());
        $this->assertContains('ROLE_ADMIN', $user->getRoles());
        $this->assertContains('ROLE_USER', $user->getRoles()); // Default role
        $this->assertNull($user->getId()); // New entity has no ID
    }

    public function testUserIdentifier(): void
    {
        $user = new User();
        $user->setEmail('user@test.com');

        $this->assertEquals('user@test.com', $user->getUserIdentifier());
    }

    public function testDefaultRoles(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');

        $roles = $user->getRoles();
        $this->assertContains('ROLE_USER', $roles);
    }

    public function testEraseCredentials(): void
    {
        $user = new User();
        // Should not throw any exception
        $user->eraseCredentials();
        $this->assertTrue(true);
    }

    public function testGetIdReturnsNullForNewEntity(): void
    {
        $user = new User();
        $this->assertNull($user->getId());
    }
}
