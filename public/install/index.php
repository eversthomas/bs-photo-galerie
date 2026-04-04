<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

require $root . '/vendor/autoload.php';

if (is_file($root . '/config/.env')) {
    Dotenv\Dotenv::createImmutable($root . '/config')->safeLoad();
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$controller = new BSPhotoGalerie\Controllers\InstallController($root);
$controller->handle();
