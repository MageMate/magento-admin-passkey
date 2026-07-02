<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 *
 * Unit-test bootstrap for MageMate_AdminPasskey.
 *
 * The module ships inside a Composer "path" repository, so its autoload-dev
 * (the Test\ PSR-4 mapping) is not registered by the root autoloader. We load
 * the repository autoloader for production + third-party classes and register
 * the test namespace ourselves so the suite can run in isolation via:
 *
 *   vendor/bin/phpunit -c package-source/magemate/magento-admin-passkey/phpunit.xml.dist
 */
declare(strict_types=1);

$repositoryAutoloader = __DIR__ . '/../../../../vendor/autoload.php';
if (!is_file($repositoryAutoloader)) {
    throw new \RuntimeException("Repository Composer autoloader not found at {$repositoryAutoloader}");
}

require $repositoryAutoloader;

spl_autoload_register(
    static function (string $class): void {
        $prefix = 'MageMate\\AdminPasskey\\Test\\';
        if (!str_starts_with($class, $prefix)) {
            return;
        }

        $relative = substr($class, strlen($prefix));
        $path = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';
        if (is_file($path)) {
            require $path;
        }
    }
);
