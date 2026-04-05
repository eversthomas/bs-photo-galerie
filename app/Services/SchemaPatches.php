<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Services;

/**
 * Idempotente Schema-Anpassungen für bestehende Datenbanken (ohne Re-Installer).
 */
final class SchemaPatches
{
    public static function ensure(Database $db): void
    {
        try {
            $row = $db->fetchOne(
                'SELECT COUNT(*) AS n FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                ['categories', 'is_public']
            );
            if ($row !== null && (int) ($row['n'] ?? 0) === 0) {
                $db->execute('ALTER TABLE categories ADD COLUMN is_public TINYINT(1) NOT NULL DEFAULT 1');
            }
        } catch (\Throwable) {
            // z. B. keine DB-Rechte auf information_schema — Installation ggf. manuell migrieren
        }
    }
}
