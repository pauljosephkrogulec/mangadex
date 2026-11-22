<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\PHPUnit\CodeQuality\Rector\Class_\PreferPHPUnitThisCallRector;

/*
 * Rector (issue #17) : modernisation et refactorings automatiques.
 * `make lint` le lance en dry-run (la CI échoue s'il propose un changement) ; `make fix` applique.
 * Le style (espaces, imports...) reste du ressort de PHP-CS-Fixer, qui passe après Rector.
 */
return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/config',
        __DIR__.'/public',
        __DIR__.'/src',
        __DIR__.'/tests',
    ])
    ->withSkip([
        __DIR__.'/config/reference.php',
        __DIR__.'/config/bundles.php',
        // Convention du projet : `self::assert*()` (imposé par PHP-CS-Fixer), pas `$this->assert*()`.
        PreferPHPUnitThisCallRector::class,
    ])
    ->withCache(__DIR__.'/var/cache/rector')
    ->withImportNames(importShortClasses: false, removeUnusedImports: true)
    // Version de PHP lue dans composer.json (config.platform.php = 8.6).
    ->withPhpSets()
    // Règles Symfony / PHPUnit / Doctrine adaptées aux versions installées (composer.lock).
    ->withComposerBased(doctrine: true, phpunit: true, symfony: true)
    ->withSymfonyContainerXml(__DIR__.'/var/cache/dev/App_KernelDevDebugContainer.xml')
    ->withAttributesSets()
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
        privatization: true,
        instanceOf: true,
        earlyReturn: true,
        phpunitCodeQuality: true,
        symfonyCodeQuality: true,
        symfonyConfigs: true,
    )
;
