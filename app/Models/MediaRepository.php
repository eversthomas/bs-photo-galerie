<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Models;

use BSPhotoGalerie\Services\Database;
use DateTimeImmutable;

/**
 * Persistenz für die Tabelle media.
 */
final class MediaRepository
{
    public function __construct(
        private Database $database
    ) {
    }

    public function findIdByHash(string $hash): ?int
    {
        if ($hash === '' || strlen($hash) !== 64) {
            return null;
        }
        $row = $this->database->fetchOne(
            'SELECT id FROM media WHERE file_hash = ? LIMIT 1',
            [$hash]
        );
        if ($row === null) {
            return null;
        }

        $id = (int) ($row['id'] ?? 0);

        return $id > 0 ? $id : null;
    }

    public function findById(int $id): ?Media
    {
        $row = $this->database->fetchOne(
            'SELECT id, category_id, filename, storage_path, file_hash, mime_type, width, height, title, description, is_visible, created_at
             FROM media WHERE id = ? LIMIT 1',
            [$id]
        );
        if ($row === null) {
            return null;
        }

        return $this->map($row);
    }

    /**
     * @return list<Media>
     */
    public function listRecent(int $limit = 100, int $offset = 0): array
    {
        $limit = max(1, min($limit, 500));
        $offset = max(0, $offset);

        $rows = $this->database->fetchAll(
            'SELECT id, category_id, filename, storage_path, file_hash, mime_type, width, height, title, description, is_visible, created_at
             FROM media ORDER BY sort_order DESC, id DESC LIMIT ? OFFSET ?',
            [$limit, $offset]
        );

        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->map($row);
        }

        return $out;
    }

    /**
     * Nur für die öffentliche Galerie: sichtbare Bilder, sortiert wie im Backend.
     *
     * @return list<Media>
     */
    public function listPublicVisible(int $limit = 300, int $offset = 0, ?int $categoryId = null): array
    {
        $limit = max(1, min($limit, 500));
        $offset = max(0, $offset);

        if ($categoryId === null) {
            $rows = $this->database->fetchAll(
                'SELECT id, category_id, filename, storage_path, file_hash, mime_type, width, height, title, description, is_visible, created_at
                 FROM media WHERE is_visible = 1
                 ORDER BY sort_order DESC, id DESC LIMIT ? OFFSET ?',
                [$limit, $offset]
            );
        } else {
            $rows = $this->database->fetchAll(
                'SELECT id, category_id, filename, storage_path, file_hash, mime_type, width, height, title, description, is_visible, created_at
                 FROM media WHERE is_visible = 1 AND category_id = ?
                 ORDER BY sort_order DESC, id DESC LIMIT ? OFFSET ?',
                [$categoryId, $limit, $offset]
            );
        }

        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->map($row);
        }

        return $out;
    }

    public function nextSortOrder(): int
    {
        $row = $this->database->fetchOne('SELECT COALESCE(MAX(sort_order), 0) + 1 AS n FROM media');

        return (int) ($row['n'] ?? 1);
    }

    /**
     * @param array{category_id:?int,filename:string,storage_path:string,file_hash:string,mime_type:string,width:?int,height:?int,title:string,description:?string,exif_json:?string,sort_order:int} $data
     */
    public function insert(array $data): int
    {
        $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');

        $this->database->execute(
            'INSERT INTO media (category_id, filename, storage_path, file_hash, mime_type, width, height, title, description, exif_json, sort_order, is_visible, created_at, updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,1,?,NULL)',
            [
                $data['category_id'],
                $data['filename'],
                $data['storage_path'],
                $data['file_hash'],
                $data['mime_type'],
                $data['width'],
                $data['height'],
                $data['title'],
                $data['description'] ?? '',
                $data['exif_json'],
                $data['sort_order'],
                $now,
            ]
        );

        $id = $this->database->lastInsertId();
        if ($id < 1) {
            throw new \RuntimeException('Medium konnte nicht gespeichert werden.');
        }

        return $id;
    }

    public function deleteById(int $id): void
    {
        $this->database->execute('DELETE FROM media WHERE id = ?', [$id]);
    }

    /**
     * @param list<int> $idsOrdered Von „oben“ nach „unten“; erstes Element erhält höchste sort_order.
     */
    public function reorderByOrderedIds(array $idsOrdered): void
    {
        $n = count($idsOrdered);
        if ($n === 0) {
            return;
        }

        $pos = 0;
        foreach ($idsOrdered as $id) {
            $id = (int) $id;
            if ($id < 1) {
                continue;
            }
            ++$pos;
            $sort = ($n - $pos + 1) * 10;
            $this->database->execute(
                'UPDATE media SET sort_order = ?, updated_at = ? WHERE id = ?',
                [$sort, (new DateTimeImmutable('now'))->format('Y-m-d H:i:s'), $id]
            );
        }
    }

    public function updateMetadata(
        int $id,
        string $title,
        string $description,
        ?int $categoryId,
        bool $isVisible
    ): void {
        $this->database->execute(
            'UPDATE media SET title = ?, description = ?, category_id = ?, is_visible = ?, updated_at = ? WHERE id = ?',
            [
                mb_substr($title, 0, 255),
                $description,
                $categoryId,
                $isVisible ? 1 : 0,
                (new DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
                $id,
            ]
        );
    }

    public function updateTitle(int $id, string $title): void
    {
        $this->database->execute(
            'UPDATE media SET title = ?, updated_at = ? WHERE id = ?',
            [
                mb_substr($title, 0, 255),
                (new DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
                $id,
            ]
        );
    }

    /**
     * Für Abgleich: alle gespeicherten Pfade unter public/.
     *
     * @return list<array{id:int, storage_path:string}>
     */
    public function listAllStoragePaths(): array
    {
        $rows = $this->database->fetchAll(
            'SELECT id, storage_path FROM media ORDER BY id ASC'
        );
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'id' => (int) ($row['id'] ?? 0),
                'storage_path' => (string) ($row['storage_path'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function map(array $row): Media
    {
        $cat = $row['category_id'] ?? null;

        return new Media(
            (int) ($row['id'] ?? 0),
            $cat !== null ? (int) $cat : null,
            (string) ($row['filename'] ?? ''),
            (string) ($row['storage_path'] ?? ''),
            (string) ($row['file_hash'] ?? ''),
            (string) ($row['mime_type'] ?? ''),
            isset($row['width']) ? (int) $row['width'] : null,
            isset($row['height']) ? (int) $row['height'] : null,
            (string) ($row['title'] ?? ''),
            (string) ($row['description'] ?? ''),
            ! empty($row['is_visible']),
            (string) ($row['created_at'] ?? '')
        );
    }
}
