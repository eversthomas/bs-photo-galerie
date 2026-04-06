#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * CLI: EXIF für gespeicherte Medien neu einlesen (Cron / Wartung).
 *
 * Nutzung: php bin/refresh-exif.php [--limit=N]
 * Ohne --limit werden alle Einträge verarbeitet (in Batching-Schritten).
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

$opts = getopt('', ['limit::']);
$limitRaw = $opts['limit'] ?? null;
$maxTotal = null;
if ($limitRaw !== null && $limitRaw !== '' && ctype_digit((string) $limitRaw)) {
    $maxTotal = max(1, (int) $limitRaw);
}

try {
    $app = new BSPhotoGalerie\Core\Application($root);
} catch (Throwable $e) {
    fwrite(STDERR, 'Konfiguration: ' . $e->getMessage() . "\n");
    exit(1);
}

$repo = $app->mediaRepository();
$items = $app->mediaItemApplicationService();

$totals = [
    'with_exif' => 0,
    'without_exif' => 0,
    'failed' => 0,
    'processed' => 0,
];
$offset = 0;

while (true) {
    if ($maxTotal !== null && $totals['processed'] >= $maxTotal) {
        break;
    }

    $batch = 500;
    if ($maxTotal !== null) {
        $batch = min(500, $maxTotal - $totals['processed']);
        if ($batch <= 0) {
            break;
        }
    }

    $list = $repo->listByUploadPeriod('all', $batch, $offset);
    if ($list === []) {
        break;
    }

    $ids = array_map(static fn ($m) => $m->id, $list);
    while ($ids !== []) {
        $chunk = array_splice($ids, 0, 200);
        $r = $items->refreshExifForIds($chunk);
        $totals['with_exif'] += $r['with_exif'];
        $totals['without_exif'] += $r['without_exif'];
        $totals['failed'] += $r['failed'];
    }

    $n = count($list);
    $totals['processed'] += $n;
    $offset += $n;

    if ($n < $batch) {
        break;
    }
}

echo json_encode($totals, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

exit($totals['failed'] > 0 ? 2 : 0);
