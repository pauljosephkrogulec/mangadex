<?php

declare(strict_types=1);

namespace App\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Comment;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProcessorInterface<Comment, Comment>
 */
final class CommentProcessor implements ProcessorInterface
{
    public function __construct(
        /** @var ProcessorInterface<Comment, Comment> */
        private readonly ProcessorInterface $decorated,
        private readonly Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Comment
    {
        if ($data instanceof Comment && null === $data->getUser()) {
            $user = $this->security->getUser();
            if (null !== $user) {
                $data->setUser($user);
            }
        }

        return $this->decorated->process($data, $operation, $uriVariables, $context);
    }
}
