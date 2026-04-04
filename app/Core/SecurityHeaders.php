<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Core;

/**
 * Standard-HTTP-Header gegen MIME-Sniffing, Clickjacking und unnötige Browser-Features.
 */
final class SecurityHeaders
{
    public static function sendForApp(): void
    {
        if (headers_sent()) {
            return;
        }

        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header(
            'Permissions-Policy: accelerometer=(), ambient-light-sensor=(), battery=(), camera=(), display-capture=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), payment=(), usb=()'
        );
    }
}
