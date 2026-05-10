<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

class UserUpdateDto
{
    #[Assert\Length(min: 3, max: 255)]
    #[Groups(['user:write'])]
    private ?string $username = null;

    #[Assert\Length(min: 8, max: 4096)]
    #[Groups(['user:write'])]
    private ?string $password = null;

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(?string $username): static
    {
        $this->username = $username;
        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): static
    {
        $this->password = $password;
        return $this;
    }
}
