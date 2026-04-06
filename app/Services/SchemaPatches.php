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

        try {
            $row = $db->fetchOne(
                'SELECT COUNT(*) AS n FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
                ['media', 'idx_media_captured']
            );
            $hasIndex = $row !== null && (int) ($row['n'] ?? 0) > 0;

            $row = $db->fetchOne(
                'SELECT COUNT(*) AS n FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                ['media', 'captured_at']
            );
            if ($row !== null && (int) ($row['n'] ?? 0) === 0) {
                $db->execute(
                    'ALTER TABLE media ADD COLUMN captured_at DATETIME NULL AFTER exif_json'
                );
                $hasIndex = false;
            }
            if (! $hasIndex) {
                $db->execute('ALTER TABLE media ADD KEY idx_media_captured (captured_at)');
            }

            $db->execute(
                'UPDATE media SET captured_at = COALESCE(
                    STR_TO_DATE(JSON_UNQUOTE(JSON_EXTRACT(exif_json, \'$.EXIF.DateTimeOriginal\')), \'%Y:%m:%d %H:%i:%s\'),
                    STR_TO_DATE(JSON_UNQUOTE(JSON_EXTRACT(exif_json, \'$.IFD0.DateTime\')), \'%Y:%m:%d %H:%i:%s\')
                )
                WHERE exif_json IS NOT NULL AND exif_json != \'\' AND captured_at IS NULL'
            );
        } catch (\Throwable) {
            // siehe oben
        }
    }
}
