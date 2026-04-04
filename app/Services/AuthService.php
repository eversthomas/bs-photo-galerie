<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Services;

use BSPhotoGalerie\Models\User;
use BSPhotoGalerie\Models\UserRepository;

/**
 * Session-basierte Authentifizierung für das Backend.
 */
final class AuthService
{
    private const SESSION_USER_ID = 'auth_user_id';

    private ?User $cachedUser = null;

    public function __construct(
        private UserRepository $users
    ) {
    }

    public function check(): bool
    {
        return $this->id() !== null;
    }

    public function id(): ?int
    {
        if (! isset($_SESSION[self::SESSION_USER_ID])) {
            return null;
        }

        $raw = $_SESSION[self::SESSION_USER_ID];
        if (is_int($raw)) {
            return $raw > 0 ? $raw : null;
        }

        if (is_string($raw) && ctype_digit($raw)) {
            $id = (int) $raw;

            return $id > 0 ? $id : null;
        }

        return null;
    }

    public function user(): ?User
    {
        $id = $this->id();
        if ($id === null) {
            return null;
        }

        if ($this->cachedUser !== null && $this->cachedUser->id === $id) {
            return $this->cachedUser;
        }

        $this->cachedUser = $this->users->findById($id);

        return $this->cachedUser;
    }

    public function attempt(string $username, string $password): bool
    {
        $username = trim($username);
        if ($username === '' || $password === '') {
            return false;
        }

        $row = $this->users->findWithHashByUsername($username);
        if ($row === null) {
            return false;
        }

        if (! password_verify($password, $row['password_hash'])) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION[self::SESSION_USER_ID] = $row['id'];
        $this->cachedUser = new User($row['id'], $row['username'], $row['email'], $row['role']);

        return true;
    }

    public function logout(): void
    {
        unset($_SESSION[self::SESSION_USER_ID]);
        $this->cachedUser = null;
        session_regenerate_id(true);
    }
}
