<?php

declare(strict_types=1);

namespace App\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\CustomList;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProcessorInterface<CustomList, CustomList>
 */
final class CustomListProcessor implements ProcessorInterface
{
    public function __construct(
        /** @var ProcessorInterface<CustomList, CustomList> */
        private readonly ProcessorInterface $decorated,
        private readonly Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CustomList
    {
        if ($data instanceof CustomList && $data->getUser() === null) {
            $user = $this->security->getUser();
            if ($user !== null) {
                $data->setUser($user);
            }
        }

        return $this->decorated->process($data, $operation, $uriVariables, $context);
    }
}
