<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

class JwtCookieSubscriber
{
    #[AsEventListener(event: Events::AUTHENTICATION_SUCCESS)]
    public function onAuthenticationSuccess(AuthenticationSuccessEvent $event): void
    {
        $data = $event->getData();
        $user = $event->getUser();

        if ($user instanceof User) {
            $data['user'] = [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'username' => $user->getUsername(),
                'createdAt' => $user->getCreatedAt()->format(\DateTime::ATOM),
                'roles' => $user->getRoles(),
            ];
            $event->setData($data);
        }
    }
}
