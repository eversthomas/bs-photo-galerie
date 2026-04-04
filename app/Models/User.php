<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Models;

/**
 * Administrativer Benutzer (ohne Passwort-Hash im Domain-Modell).
 */
final class User
{
    public function __construct(
        public int $id,
        public string $username,
        public string $email,
        public string $role
    ) {
    }
}
