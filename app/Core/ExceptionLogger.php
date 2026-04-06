<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Core;

use Throwable;

/**
 * Schreibt unbehandelte Exceptions in PHP error_log und in storage/logs/ (tägliche Datei).
 */
final class ExceptionLogger
{
    private const MAX_FILE_BYTES = 2_097_152;

    public static function logThrowable(string $projectRoot, Throwable $e, ?string $requestSummary = null): void
    {
        $line = self::formatOneLine($e, $requestSummary);
        error_log('[BSPHOTO] ' . $line);

        $dir = rtrim($projectRoot, '/') . '/storage/logs';
        try {
            if (! is_dir($dir) && ! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
                return;
            }

            $file = $dir . '/exceptions-' . date('Y-m-d') . '.log';
            $size = is_file($file) ? filesize($file) : 0;
            if ($size !== false && $size > self::MAX_FILE_BYTES) {
                error_log('[BSPHOTO] Logdatei zu groß (' . $file . '), Eintrag nur in error_log.');

                return;
            }

            $block = '[' . date('c') . '] ' . $line . "\n" . $e->getTraceAsString() . "\n\n";
            @file_put_contents($file, $block, FILE_APPEND | LOCK_EX);
        } catch (\Throwable) {
        }
    }

    private static function formatOneLine(Throwable $e, ?string $requestSummary): string
    {
        $req = $requestSummary !== null && $requestSummary !== '' ? ' | ' . $requestSummary : '';

        return $e::class . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() . $req;
    }
}
