<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Config;

use RuntimeException;

/**
 * Lädt die installationsgenerierte Konfiguration aus config/config.php.
 */
final class ConfigRepository
{
    /**
     * @return array<string, mixed>
     */
    public function load(string $projectRoot): array
    {
        $path = $projectRoot . '/config/config.php';
        if (! is_file($path)) {
            throw new RuntimeException('Konfigurationsdatei fehlt. Bitte den Installer ausführen.');
        }

        /** @var mixed $cfg */
        $cfg = require $path;
        if (! is_array($cfg) || empty($cfg['db']) || ! is_array($cfg['db'])) {
            throw new RuntimeException('Ungültige Konfiguration: Datenbankabschnitt fehlt.');
        }

        /** @var array<string, mixed> $cfg */
        return $cfg;
    }
}
