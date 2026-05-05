<?php

namespace App\EventSubscriber;

use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class JwtCookieSubscriber
{
    public function __construct(
        #[Autowire('%kernel.environment%')]
        private string $appEnv
    ) {}

    #[AsEventListener(event: AuthenticationSuccessEvent::class)]
    public function onAuthenticationSuccess(AuthenticationSuccessEvent $event): void
    {
        $response = $event->getResponse();
        $jwt = $event->getData()['token'] ?? null;
        
        if ($jwt) {
            $response->headers->setCookie(
                name: 'mangadex_jwt_token',
                value: $jwt,
                path: '/',
                secure: $this->appEnv === 'prod',
                httpOnly: true,
                sameSite: 'lax'
            );
        }
    }
}
