<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Core;

/**
 * CSRF-Schutz für Formulare (Anwendung und Installer).
 */
final class CsrfToken
{
    private const INSTALL_KEY = '_csrf_installer';

    private const APP_KEY = '_csrf_app';

    /** Token für normale Anwendung (Backend/Frontend). */
    public static function token(): string
    {
        return self::ensure(self::APP_KEY);
    }

    public static function validate(?string $submitted): bool
    {
        return self::equals(self::APP_KEY, $submitted);
    }

    public static function rotate(): void
    {
        $_SESSION[self::APP_KEY] = bin2hex(random_bytes(32));
    }

    /** Token nur für den Installer (Separate Session-Key). */
    public static function installToken(): string
    {
        return self::ensure(self::INSTALL_KEY);
    }

    public static function validateInstall(?string $submitted): bool
    {
        return self::equals(self::INSTALL_KEY, $submitted);
    }

    public static function rotateInstall(): void
    {
        $_SESSION[self::INSTALL_KEY] = bin2hex(random_bytes(32));
    }

    private static function ensure(string $key): string
    {
        if (empty($_SESSION[$key]) || ! is_string($_SESSION[$key])) {
            $_SESSION[$key] = bin2hex(random_bytes(32));
        }

        return $_SESSION[$key];
    }

    private static function equals(string $key, ?string $submitted): bool
    {
        $stored = $_SESSION[$key] ?? null;

        if (! is_string($stored) || $stored === '' || ! is_string($submitted) || $submitted === '') {
            return false;
        }

        return hash_equals($stored, $submitted);
    }
}
