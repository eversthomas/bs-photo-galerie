<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Models;

use BSPhotoGalerie\Services\Database;

/**
 * Schlanke Zugriffe auf die Tabelle settings.
 */
final class SettingsRepository
{
    public function __construct(
        private Database $database
    ) {
    }

    public function get(string $key, string $default = ''): string
    {
        $row = $this->database->fetchOne(
            'SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1',
            [$key]
        );
        if ($row === null || ! isset($row['setting_value'])) {
            return $default;
        }
        $v = $row['setting_value'];

        return is_string($v) ? $v : $default;
    }

    public function set(string $key, string $value): void
    {
        $this->database->execute(
            'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
            [$key, $value]
        );
    }
}
