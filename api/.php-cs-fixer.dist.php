<?php

declare(strict_types=1);

/*
 * PHP-CS-Fixer (issue #17) : style de code. Base `@Symfony` (standard du projet, cf. AGENTS.md),
 * plus les règles « risky » qui durcissent le code (strict_types, comparaisons strictes...).
 */
$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude('var')
    ->notPath([
        'config/bundles.php',
        'config/reference.php',
    ])
;

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setParallelConfig(PhpCsFixer\Runner\Parallel\ParallelConfigFactory::detect())
    ->setCacheFile(__DIR__.'/var/cache/.php-cs-fixer.cache')
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        '@PHP8x5Migration' => true,
        '@PHPUnit11x0Migration:risky' => true,
        'declare_strict_types' => true,
        'strict_comparison' => true,
        'strict_param' => true,
        'mb_str_functions' => true,
        // Tests : `self::assert*()` plutôt que `$this->assert*()` (méthodes statiques de PHPUnit).
        'php_unit_test_case_static_method_calls' => ['call_type' => 'self'],
        'ordered_class_elements' => true,
        'global_namespace_import' => ['import_classes' => false, 'import_constants' => false, 'import_functions' => false],
    ])
    ->setFinder($finder)
;
