<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class IncludeParameterSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 5],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        // This subscriber is intentionally a no-op.
        // The IncludeContextBuilder decorator (src/Serializer/IncludeContextBuilder.php)
        // handles the ?include= parameter with allowlist validation and correct context structure.
        // This subscriber is kept to avoid breaking its test file while the builder
        // serves as the single source of truth for include handling.
    }
}
