<?php

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
        $request = $event->getRequest();

        if (! $request->query->has('include')) {
            return;
        }

        $includeParam = $request->query->get('include');
        if (! is_string($includeParam)) {
            return;
        }
        $includes = array_map('trim', explode(',', $includeParam));

        /** @var array<string> $groups */
        $groups = $request->attributes->get('_api_normalization_context', []);
        if (! is_array($groups)) {
            $groups = [];
        }

        foreach ($includes as $include) {
            $group = 'manga:include:' . $include;
            if (! in_array($group, $groups, true)) {
                $groups[] = $group;
            }
        }

        $request->attributes->set('_api_normalization_context', $groups);
    }
}
