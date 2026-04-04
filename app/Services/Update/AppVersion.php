<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Services\Update;

/**
 * Lokale Versionskennung (Datei VERSION im Projektroot).
 */
final class AppVersion
{
    public static function readFromProjectRoot(string $root): string
    {
        $path = rtrim($root, '/') . '/VERSION';
        if (! is_file($path) || ! is_readable($path)) {
            return '0.0.0';
        }
        $raw = file_get_contents($path);
        if ($raw === false) {
            return '0.0.0';
        }
        $v = trim($raw);

        return self::normalize($v !== '' ? $v : '0.0.0');
    }

    public static function normalize(string $version): string
    {
        $v = trim($version);
        $v = preg_replace('/^v+/i', '', $v) ?? $v;

        return $v !== '' ? $v : '0.0.0';
    }

    public static function isNewerThan(string $remote, string $local): bool
    {
        return version_compare(self::normalize($remote), self::normalize($local), '>');
    }
}
