<?php

namespace App\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\UserUpdateDto;
use App\Entity\User;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * @implements ProcessorInterface<UserUpdateDto, User>
 */
final class UserUpdateProcessor implements ProcessorInterface
{
    public function __construct(
        /** @var ProcessorInterface<UserUpdateDto, User> */
        private ProcessorInterface $decorated,
        private UserPasswordHasherInterface $passwordHasher
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): User
    {
        if ($data instanceof UserUpdateDto && isset($context['previous_data'])) {
            /** @var User $user */
            $user = $context['previous_data'];

            if ($data->getUsername() !== null) {
                $user->setUsername($data->getUsername());
            }

            if ($data->getPassword() !== null) {
                $hashedPassword = $this->passwordHasher->hashPassword($user, $data->getPassword());
                $user->setPassword($hashedPassword);
            }

            return $this->decorated->process($user, $operation, $uriVariables, $context);
        }

        return $this->decorated->process($data, $operation, $uriVariables, $context);
    }
}
