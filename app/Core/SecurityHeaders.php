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

        if (self::cspReportOnlyEnabled()) {
            header('Content-Security-Policy-Report-Only: ' . self::cspReportOnlyDirectives());
        }
    }

    /**
     * Aktivieren: in config/.env BSPHOTO_CSP_REPORT_ONLY=1
     * (blockiert nichts; dient der Erfassung von CSP-Verletzungen in der Konsole / Report-URI später).
     */
    private static function cspReportOnlyEnabled(): bool
    {
        $raw = $_ENV['BSPHOTO_CSP_REPORT_ONLY'] ?? getenv('BSPHOTO_CSP_REPORT_ONLY');
        if ($raw === false || $raw === null || $raw === '') {
            return false;
        }

        return in_array(strtolower(trim((string) $raw)), ['1', 'true', 'yes', 'on'], true);
    }

    private static function cspReportOnlyDirectives(): string
    {
        return implode(
            '; ',
            [
                "default-src 'self'",
                "script-src 'self' 'unsafe-inline'",
                "style-src 'self' 'unsafe-inline'",
                "img-src 'self' data: https: blob:",
                "font-src 'self' data:",
                "connect-src 'self' https:",
                "frame-ancestors 'self'",
                "base-uri 'self'",
                "form-action 'self'",
            ]
        );
    }
}
