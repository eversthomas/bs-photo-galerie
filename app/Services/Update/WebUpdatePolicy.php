<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Services\Update;

/**
 * Erlaubt Git- oder ZIP-Updates aus der Admin-Oberfläche (bewusst freischaltbar).
 */
final class WebUpdatePolicy
{
    private function __construct()
    {
    }

    /**
     * Mindestens eine der Variablen auf 1/true/yes/on setzen.
     */
    public static function isWebUpdateAllowed(): bool
    {
        foreach (['BSPHOTO_ALLOW_WEB_UPDATE', 'BSPHOTO_ALLOW_GIT_UPDATE', 'BSPHOTO_ALLOW_ZIP_UPDATE'] as $key) {
            if (self::envFlag($key)) {
                return true;
            }
        }

        return false;
    }

    private static function envFlag(string $key): bool
    {
        $raw = $_ENV[$key] ?? getenv($key);
        if ($raw === false || $raw === null) {
            return false;
        }

        return in_array(strtolower(trim((string) $raw)), ['1', 'true', 'yes', 'on'], true);
    }
}
