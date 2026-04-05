<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Core;

/**
 * Nur interne Weiterleitung nach Login (Open-Redirect-Schutz).
 */
final class LoginRedirect
{
    public static function sanitize(?string $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        $raw = trim($raw);
        if ($raw === '' || ! str_starts_with($raw, '/') || str_starts_with($raw, '//')) {
            return null;
        }
        if (str_contains($raw, "\0") || str_contains($raw, "\n") || str_contains($raw, "\r")) {
            return null;
        }
        if (str_contains($raw, ':')) {
            return null;
        }
        if (! str_starts_with($raw, '/galerie')) {
            return null;
        }

        return $raw;
    }
}
