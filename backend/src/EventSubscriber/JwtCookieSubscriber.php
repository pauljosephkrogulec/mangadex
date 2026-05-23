<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Cookie;

class JwtCookieSubscriber
{
    public function __construct(
        #[Autowire('%kernel.environment%')]
        private string $appEnv,
    ) {
    }

    #[AsEventListener(event: AuthenticationSuccessEvent::class)]
    public function onAuthenticationSuccess(AuthenticationSuccessEvent $event): void
    {
        $response = $event->getResponse();
        $data = $event->getData();
        $jwt = $data['token'] ?? null;
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

        if (is_string($jwt)) {
            $cookie = new Cookie(
                'mangadex_jwt_token',
                $jwt,
                0,
                '/',
                null,
                'prod' === $this->appEnv,
                true,
                false,
                'lax'
            );
            $response->headers->setCookie($cookie);
        }
    }
}
