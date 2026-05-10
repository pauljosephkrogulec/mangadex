<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 20)]
final class RateLimiterSubscriber
{
    public function __construct(
        #[Autowire(service: 'limiter.login')]
        private readonly RateLimiterFactoryInterface $login,
        #[Autowire(service: 'limiter.registration')]
        private readonly RateLimiterFactoryInterface $registration,
        #[Autowire(service: 'limiter.api')]
        private readonly RateLimiterFactoryInterface $api,
        private readonly KernelInterface $kernel,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        // Disable rate limiting in test environment
        if ($this->kernel->getEnvironment() === 'test') {
            return;
        }

        $request = $event->getRequest();

        if (! str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }

        // Rate limit login endpoint: 5 attempts/minute per IP
        if ($request->getMethod() === 'POST' && $request->getPathInfo() === '/api/login_check') {
            $limiter = $this->login->create($request->getClientIp());
            $consume = $limiter->consume();
            if (! $consume->isAccepted()) {
                throw new TooManyRequestsHttpException(60);
            }
            return;
        }

        // Rate limit registration: token bucket 3 burst, 1 per 3 min
        if ($request->getMethod() === 'POST' && $request->getPathInfo() === '/api/users') {
            $limiter = $this->registration->create($request->getClientIp());
            $consume = $limiter->consume();
            if (! $consume->isAccepted()) {
                throw new TooManyRequestsHttpException(60);
            }
            return;
        }

        // General API rate limit: 60 requests/minute per IP
        $limiter = $this->api->create($request->getClientIp());
        $consume = $limiter->consume();
        if (! $consume->isAccepted()) {
            throw new TooManyRequestsHttpException(60);
        }
    }
}
