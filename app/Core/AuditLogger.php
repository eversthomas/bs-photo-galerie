<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Core;

/**
 * Append-only Audit-Trail unter storage/logs/ (keine Passwörter, nur skalare Kontextfelder).
 */
final class AuditLogger
{
    private const MAX_FILE_BYTES = 2_097_152;

    /**
     * @param array<string, mixed> $context
     */
    public static function append(string $projectRoot, string $action, array $context = []): void
    {
        $safe = self::sanitizeContext($context);
        $payload = json_encode($safe, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($payload === false) {
            $payload = '{}';
        }

        $line = '[' . date('c') . '] ' . $action . ' ' . $payload;
        error_log('[BSPHOTO][audit] ' . $line);

        $dir = rtrim($projectRoot, '/') . '/storage/logs';
        try {
            if (! is_dir($dir) && ! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
                return;
            }

            $file = $dir . '/audit-' . date('Y-m-d') . '.log';
            $size = is_file($file) ? filesize($file) : 0;
            if ($size !== false && $size > self::MAX_FILE_BYTES) {
                error_log('[BSPHOTO][audit] Logdatei zu groß (' . $file . '), Eintrag nur in error_log.');

                return;
            }

            @file_put_contents($file, $line . "\n", FILE_APPEND | LOCK_EX);
        } catch (\Throwable) {
        }
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, bool|float|int|string|null>
     */
    private static function sanitizeContext(array $context): array
    {
        $out = [];
        foreach ($context as $k => $v) {
            if (! is_string($k) || $k === '') {
                continue;
            }
            if ($v === null || is_bool($v) || is_int($v)) {
                $out[$k] = $v;
            } elseif (is_float($v)) {
                $out[$k] = $v;
            } elseif (is_string($v)) {
                $t = str_replace(["\r", "\n"], ' ', $v);
                $out[$k] = mb_substr($t, 0, 2000);
            } else {
                $out[$k] = '[omitted]';
            }
        }

        return $out;
    }
}
