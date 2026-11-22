<?php

declare(strict_types=1);

use App\Kernel;

require_once __DIR__.'/../vendor/autoload_runtime.php';

// $context est fourni par symfony/runtime : APP_ENV y est toujours une chaîne, mais le tableau n'est pas typé.
// @phpstan-ignore argument.type
return static fn (array $context): Kernel => new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
