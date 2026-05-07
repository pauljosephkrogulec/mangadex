<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactory;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 20)]
final class RateLimiterSubscriber
{
    public function __construct(
        #[Autowire(service: 'limiter.registration')]
        private readonly RateLimiterFactory $registration,
        #[Autowire(service: 'limiter.api')]
        private readonly RateLimiterFactory $api,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (! str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }

        if ($request->getMethod() === 'POST' && $request->getPathInfo() === '/api/users') {
            $limiter = $this->registration->create($request->getClientIp());
            $consume = $limiter->consume();
            if (! $consume->isAccepted()) {
                throw new TooManyRequestsHttpException(60);
            }
        }

        $limiter = $this->api->create($request->getClientIp());
        $consume = $limiter->consume();
        if (! $consume->isAccepted()) {
            throw new TooManyRequestsHttpException(60);
        }
    }
}
