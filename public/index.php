<?php

declare(strict_types=1);

$root = dirname(__DIR__);

if (! is_file($root . '/vendor/autoload.php')) {
    header('Content-Type: text/html; charset=utf-8', true, 503);
    echo '<p>Abhängigkeiten fehlen. Bitte im Projektroot <code>composer install</code> ausführen.</p>';
    exit;
}

require $root . '/vendor/autoload.php';

if (is_file($root . '/config/.env')) {
    Dotenv\Dotenv::createImmutable($root . '/config')->safeLoad();
}

$lock = $root . '/storage/locks/install.lock';

if (! is_file($lock)) {
    header('Content-Type: text/html; charset=utf-8');
    $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    if ($base === '' || $base === '.') {
        $url = '/install/';
    } else {
        $url = $base . '/install/';
    }
    header('Location: ' . $url, true, 302);
    exit;
}

try {
    $app = new BSPhotoGalerie\Core\Application($root);
    $app->run();
} catch (Throwable $e) {
    BSPhotoGalerie\Core\Application::handleException($e);
}
