#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * CLI-Import (Cron-fähig): Ordner public/import scannen, optional FTP und DB-Bereinigung.
 *
 * Nutzung: php bin/import.php [--ftp] [--prune] [--category=ID]
 */

$root = dirname(__DIR__);

if (! is_file($root . '/vendor/autoload.php')) {
    fwrite(STDERR, "vendor/autoload.php fehlt. composer install im Projektroot ausführen.\n");
    exit(1);
}

require $root . '/vendor/autoload.php';

if (is_file($root . '/config/.env')) {
    Dotenv\Dotenv::createImmutable($root . '/config')->safeLoad();
}

if (! is_file($root . '/storage/locks/install.lock')) {
    fwrite(STDERR, "Installation nicht abgeschlossen (install.lock fehlt).\n");
    exit(1);
}

$longopts = ['ftp', 'prune', 'category::'];
$opts = getopt('', $longopts);

$pullFtp = isset($opts['ftp']);
$prune = isset($opts['prune']);
$categoryRaw = $opts['category'] ?? null;
$categoryId = null;
if ($categoryRaw !== null && $categoryRaw !== '' && ctype_digit((string) $categoryRaw)) {
    $categoryId = (int) $categoryRaw;
}

try {
    $app = new BSPhotoGalerie\Core\Application($root);
} catch (Throwable $e) {
    fwrite(STDERR, 'Konfiguration: ' . $e->getMessage() . "\n");
    exit(1);
}

if ($categoryId !== null) {
    $ok = false;
    foreach ($app->categoryRepository()->listAllOrdered() as $row) {
        if ($row['id'] === $categoryId) {
            $ok = true;
            break;
        }
    }
    if (! $ok) {
        fwrite(STDERR, "Ungültige Kategorie-ID.\n");
        exit(1);
    }
}

$result = $app->mediaImportService()->run($categoryId, $pullFtp, $prune);

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

exit($result['errors'] !== [] ? 2 : 0);
