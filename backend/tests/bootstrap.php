<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    new Dotenv()->bootEnv(dirname(__DIR__).'/.env');
}

if ($_SERVER['APP_DEBUG']) {
    umask(0o000);
}

// Handle test database creation and cleanup
if (($_SERVER['APP_ENV'] ?? '') === 'test') {
    $testDbPath = dirname(__DIR__).'/var/test.db';

    // Delete database BEFORE tests
    if (file_exists($testDbPath)) {
        unlink($testDbPath);
    }

    // Create database and schema
    $console = dirname(__DIR__).'/bin/console';
    if (file_exists($console)) {
        $commands = [
            'php '.$console.' doctrine:database:create --env=test --no-interaction',
            'php '.$console.' doctrine:schema:create --env=test --no-interaction',
        ];

        foreach ($commands as $cmd) {
            exec($cmd.' 2>&1');
        }
    }

    // Delete database AFTER tests using shutdown function
    register_shutdown_function(function () use ($testDbPath) {
        if (file_exists($testDbPath)) {
            unlink($testDbPath);
        }
    });
}
