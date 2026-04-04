<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Models;

use BSPhotoGalerie\Services\Database;

/**
 * Datenzugriff für Benutzer (PDO Prepared Statements).
 */
final class UserRepository
{
    public function __construct(
        private Database $database
    ) {
    }

    public function findById(int $id): ?User
    {
        $row = $this->database->fetchOne(
            'SELECT id, username, email, role FROM users WHERE id = ? LIMIT 1',
            [$id]
        );
        if ($row === null) {
            return null;
        }

        return $this->map($row);
    }

    public function findByUsername(string $username): ?User
    {
        $row = $this->database->fetchOne(
            'SELECT id, username, email, role FROM users WHERE username = ? LIMIT 1',
            [$username]
        );
        if ($row === null) {
            return null;
        }

        return $this->map($row);
    }

    /**
     * Liefert Daten inklusive Passwort-Hash für die Authentifizierung.
     *
     * @return array{id:int,username:string,email:string,role:string,password_hash:string}|null
     */
    public function findWithHashByUsername(string $username): ?array
    {
        $row = $this->database->fetchOne(
            'SELECT id, username, email, role, password_hash FROM users WHERE username = ? LIMIT 1',
            [$username]
        );
        if ($row === null) {
            return null;
        }

        $id = isset($row['id']) ? (int) $row['id'] : 0;
        $hash = isset($row['password_hash']) && is_string($row['password_hash']) ? $row['password_hash'] : '';

        if ($id < 1 || $hash === '') {
            return null;
        }

        return [
            'id' => $id,
            'username' => (string) ($row['username'] ?? ''),
            'email' => (string) ($row['email'] ?? ''),
            'role' => (string) ($row['role'] ?? ''),
            'password_hash' => $hash,
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function map(array $row): User
    {
        return new User(
            (int) ($row['id'] ?? 0),
            (string) ($row['username'] ?? ''),
            (string) ($row['email'] ?? ''),
            (string) ($row['role'] ?? '')
        );
    }
}
