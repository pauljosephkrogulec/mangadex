<?php

namespace App\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\UserRegistrationDto;
use App\Entity\User;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * @implements ProcessorInterface<UserRegistrationDto, User>
 */
final class UserRegistrationProcessor implements ProcessorInterface
{
    public function __construct(
        /** @var ProcessorInterface<UserRegistrationDto, User> */
        private ProcessorInterface $decorated,
        private UserPasswordHasherInterface $passwordHasher
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): User
    {
        if ($data instanceof UserRegistrationDto) {
            $user = new User();
            $user->setEmail($data->getEmail());
            $user->setUsername($data->getUsername());

            $hashedPassword = $this->passwordHasher->hashPassword($user, $data->getPassword());
            $user->setPassword($hashedPassword);

            return $this->decorated->process($user, $operation, $uriVariables, $context);
        }

        return $this->decorated->process($data, $operation, $uriVariables, $context);
    }
}
