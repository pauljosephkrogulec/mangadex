<?php

declare(strict_types=1);

namespace App\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\UserUpdateDto;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * @implements ProcessorInterface<UserUpdateDto, User>
 */
final class UserUpdateProcessor implements ProcessorInterface
{
    public function __construct(
        /** @var ProcessorInterface<UserUpdateDto, User> */
        private ProcessorInterface $decorated,
        private UserPasswordHasherInterface $passwordHasher,
        private Security $security
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): User
    {
        if ($data instanceof UserUpdateDto && isset($context['previous_data'])) {
            // Defense-in-depth: verify the current user owns this record
            $currentUser = $this->security->getUser();
            if ($currentUser !== $context['previous_data'] && ! $this->security->isGranted('ROLE_ADMIN')) {
                throw new AccessDeniedHttpException('You can only update your own profile');
            }
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
