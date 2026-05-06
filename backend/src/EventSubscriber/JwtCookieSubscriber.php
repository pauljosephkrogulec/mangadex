<?php

namespace App\EventSubscriber;

use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Cookie;

class JwtCookieSubscriber
{
    public function __construct(
        #[Autowire('%kernel.environment%')]
        private string $appEnv
    ) {
    }

    #[AsEventListener(event: AuthenticationSuccessEvent::class)]
    public function onAuthenticationSuccess(AuthenticationSuccessEvent $event): void
    {
        $response = $event->getResponse();
        $jwt = $event->getData()['token'] ?? null;

        if (is_string($jwt)) {
            $cookie = new Cookie(
                'mangadex_jwt_token',
                $jwt,
                null,
                '/',
                null,
                $this->appEnv === 'prod',
                true,
                false,
                'lax'
            );
            $response->headers->setCookie($cookie);
        }
    }
}
