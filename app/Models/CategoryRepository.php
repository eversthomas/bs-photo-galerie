<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Models;

use BSPhotoGalerie\Services\Database;
use DateTimeImmutable;

/**
 * Kategorien (CRUD, Sortierung).
 */
final class CategoryRepository
{
    public function __construct(
        private Database $database
    ) {
    }

    /**
     * @return list<array{id:int,name:string,slug:string,sort_order:int}>
     */
    public function listAllOrdered(): array
    {
        $rows = $this->database->fetchAll(
            'SELECT id, name, slug, sort_order FROM categories ORDER BY sort_order ASC, name ASC'
        );
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'id' => (int) ($row['id'] ?? 0),
                'name' => (string) ($row['name'] ?? ''),
                'slug' => (string) ($row['slug'] ?? ''),
                'sort_order' => (int) ($row['sort_order'] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * @return array{id:int,name:string,slug:string,sort_order:int}|null
     */
    public function findBySlug(string $slug): ?array
    {
        $slug = trim($slug);
        if ($slug === '') {
            return null;
        }
        $row = $this->database->fetchOne(
            'SELECT id, name, slug, sort_order FROM categories WHERE slug = ? LIMIT 1',
            [$slug]
        );
        if ($row === null) {
            return null;
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'name' => (string) ($row['name'] ?? ''),
            'slug' => (string) ($row['slug'] ?? ''),
            'sort_order' => (int) ($row['sort_order'] ?? 0),
        ];
    }

    /**
     * @return array{id:int,name:string,slug:string,sort_order:int}|null
     */
    public function findById(int $id): ?array
    {
        $row = $this->database->fetchOne(
            'SELECT id, name, slug, sort_order FROM categories WHERE id = ? LIMIT 1',
            [$id]
        );
        if ($row === null) {
            return null;
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'name' => (string) ($row['name'] ?? ''),
            'slug' => (string) ($row['slug'] ?? ''),
            'sort_order' => (int) ($row['sort_order'] ?? 0),
        ];
    }

    public function slugExists(string $slug, ?int $exceptId = null): bool
    {
        if ($exceptId !== null) {
            $row = $this->database->fetchOne(
                'SELECT id FROM categories WHERE slug = ? AND id != ? LIMIT 1',
                [$slug, $exceptId]
            );
        } else {
            $row = $this->database->fetchOne(
                'SELECT id FROM categories WHERE slug = ? LIMIT 1',
                [$slug]
            );
        }

        return $row !== null;
    }

    public function nextSortOrder(): int
    {
        $row = $this->database->fetchOne('SELECT COALESCE(MAX(sort_order), 0) + 1 AS n FROM categories');

        return (int) ($row['n'] ?? 1);
    }

    public function insert(string $name, string $slug): int
    {
        $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        $sort = $this->nextSortOrder();

        $this->database->execute(
            'INSERT INTO categories (parent_id, name, slug, sort_order, created_at) VALUES (NULL,?,?,?,?)',
            [$name, $slug, $sort, $now]
        );

        return $this->database->lastInsertId();
    }

    public function update(int $id, string $name, string $slug): void
    {
        $this->database->execute(
            'UPDATE categories SET name = ?, slug = ? WHERE id = ?',
            [$name, $slug, $id]
        );
    }

    public function delete(int $id): void
    {
        $this->database->execute('DELETE FROM categories WHERE id = ?', [$id]);
    }
}
