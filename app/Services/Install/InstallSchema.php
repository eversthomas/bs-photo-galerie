<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Services\Install;

/**
 * Initiales Datenbankschema für BS Photo Galerie (Installer).
 * Reihenfolge beachten wegen Fremdschlüsseln.
 */
final class InstallSchema
{
    /**
     * @return list<string>
     */
    public static function createTableStatements(): array
    {
        return [
            <<<'SQL'
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(64) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL DEFAULT '',
    role ENUM('admin', 'editor') NOT NULL DEFAULT 'admin',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    UNIQUE KEY uq_users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(128) NOT NULL,
    setting_value MEDIUMTEXT NULL,
    UNIQUE KEY uq_settings_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parent_id INT UNSIGNED NULL,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    KEY idx_categories_parent (parent_id),
    UNIQUE KEY uq_categories_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS media (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id INT UNSIGNED NULL,
    filename VARCHAR(255) NOT NULL,
    storage_path VARCHAR(512) NOT NULL,
    file_hash CHAR(64) NOT NULL DEFAULT '',
    mime_type VARCHAR(128) NOT NULL DEFAULT '',
    width INT UNSIGNED NULL,
    height INT UNSIGNED NULL,
    title VARCHAR(255) NOT NULL DEFAULT '',
    description TEXT NULL,
    exif_json LONGTEXT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_visible TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    KEY idx_media_category (category_id),
    KEY idx_media_hash (file_hash),
    CONSTRAINT fk_media_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS tags (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(128) NOT NULL,
    slug VARCHAR(128) NOT NULL,
    UNIQUE KEY uq_tags_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS media_tags (
    media_id INT UNSIGNED NOT NULL,
    tag_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (media_id, tag_id),
    CONSTRAINT fk_media_tags_media FOREIGN KEY (media_id) REFERENCES media (id) ON DELETE CASCADE,
    CONSTRAINT fk_media_tags_tag FOREIGN KEY (tag_id) REFERENCES tags (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        ];
    }

    /**
     * @return list<string>
     */
    public static function dropTableStatements(): array
    {
        return [
            'DROP TABLE IF EXISTS media_tags',
            'DROP TABLE IF EXISTS tags',
            'DROP TABLE IF EXISTS media',
            'DROP TABLE IF EXISTS categories',
            'DROP TABLE IF EXISTS settings',
            'DROP TABLE IF EXISTS users',
        ];
    }
}
