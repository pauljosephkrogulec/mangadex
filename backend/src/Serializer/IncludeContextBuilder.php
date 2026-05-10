<?php

declare(strict_types=1);

namespace App\Serializer;

use ApiPlatform\State\SerializerContextBuilderInterface;
use Symfony\Component\HttpFoundation\Request;

final class IncludeContextBuilder implements SerializerContextBuilderInterface
{
    public function __construct(
        private SerializerContextBuilderInterface $decorated,
    ) {
    }

    public function createFromRequest(Request $request, bool $normalization, ?array $extractedAttributes = null): array
    {
        $context = $this->decorated->createFromRequest($request, $normalization, $extractedAttributes);

        if (! $normalization || ! $request->query->has('include')) {
            return $context;
        }

        $includeParam = $request->query->get('include');
        if (! is_string($includeParam)) {
            return $context;
        }
        $includes = array_map('trim', explode(',', $includeParam));

        if (! isset($context['groups'])) {
            $context['groups'] = [];
        }

        /** @var array<string> $groups */
        $groups = $context['groups'];
        if (! is_array($groups)) {
            $groups = [];
        }

        $allowedIncludes = ['creators', 'tags', 'chapters', 'coverArt'];
        foreach ($includes as $include) {
            if (! in_array($include, $allowedIncludes, true)) {
                continue;
            }
            $group = 'manga:include:' . $include;
            if (! in_array($group, $groups, true)) {
                $groups[] = $group;
            }
        }

        $context['groups'] = $groups;

        return $context;
    }
}
