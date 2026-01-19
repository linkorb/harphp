<?php
/**
 * Wrapper entry point for FrankenPHP standalone builds.
 * 
 * When embedded in FrankenPHP, this script detects if it's being run
 * as a standalone CLI and bootstraps the application accordingly.
 */

declare(strict_types=1);

// When running embedded in FrankenPHP, the app is at /app/
$embeddedPath = '/app/';

// Check if we're running embedded (FrankenPHP)
if (is_dir($embeddedPath) && file_exists($embeddedPath . 'bin/harphp')) {
    $baseDir = $embeddedPath;
    require $embeddedPath . 'vendor/autoload.php';
} else {
    // Fallback to standard paths for development
    $binDir = dirname(__FILE__);
    $baseDir = dirname($binDir);
    
    $autoloadPaths = [
        $baseDir . '/vendor/autoload.php',
        $baseDir . '/../../autoload.php',
    ];
    
    foreach ($autoloadPaths as $autoloadPath) {
        if (file_exists($autoloadPath)) {
            require $autoloadPath;
            break;
        }
    }
}

use HarPhp\Application;

// Load .env.local from cwd
$envPath = getcwd() . '/.env.local';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                $value = substr($value, 1, -1);
            }
            if (getenv($key) === false) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
            }
        }
    }
}

$application = new Application($baseDir);
$application->run();
