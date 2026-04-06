<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Services;

use BSPhotoGalerie\Core\AuditLog;
use BSPhotoGalerie\Models\User;
use BSPhotoGalerie\Models\UserRepository;

/**
 * Session-basierte Authentifizierung für das Backend.
 */
final class AuthService
{
    private const SESSION_USER_ID = 'auth_user_id';

    private const SESSION_LAST_ACTIVITY = '_bsphoto_last_activity';

    /** Maximale Inaktivität in Sekunden (0 = nicht erzwingen). */
    private const MAX_IDLE_CAP_SECONDS = 604800;

    private ?User $cachedUser = null;

    public function __construct(
        private UserRepository $users,
        private AuditLog $audit
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
            $this->audit->record('auth.login.failed', ['username' => $username]);

            return false;
        }

        if (! password_verify($password, $row['password_hash'])) {
            $this->audit->record('auth.login.failed', ['username' => $username]);

            return false;
        }

        session_regenerate_id(true);
        $_SESSION[self::SESSION_USER_ID] = $row['id'];
        $this->cachedUser = new User($row['id'], $row['username'], $row['email'], $row['role']);
        $this->touchActivity();
        $this->audit->record('auth.login.success', [
            'user_id' => (int) $row['id'],
            'username' => (string) $row['username'],
        ]);

        return true;
    }

    /**
     * Aktualisiert den Idle-Zeitstempel (nach erfolgreicher Auth-Prüfung).
     */
    public function touchActivity(): void
    {
        if (! $this->check()) {
            return;
        }

        $_SESSION[self::SESSION_LAST_ACTIVITY] = time();
    }

    /**
     * Ob die Sitzung wegen Inaktivität beendet werden soll (nur wenn BSPHOTO_SESSION_IDLE_SECONDS > 0).
     */
    public function idleExpired(): bool
    {
        $max = self::maxIdleSecondsFromEnv();
        if ($max <= 0 || ! $this->check()) {
            return false;
        }

        $last = $_SESSION[self::SESSION_LAST_ACTIVITY] ?? null;
        if (! is_int($last) || $last < 1) {
            return true;
        }

        return (time() - $last) > $max;
    }

    public function logout(): void
    {
        $id = $this->id();
        $name = $this->user()?->username;
        unset($_SESSION[self::SESSION_USER_ID], $_SESSION[self::SESSION_LAST_ACTIVITY]);
        $this->cachedUser = null;
        session_regenerate_id(true);
        if ($id !== null) {
            $this->audit->record('auth.logout', [
                'user_id' => $id,
                'username' => $name ?? '',
            ]);
        }
    }

    private static function maxIdleSecondsFromEnv(): int
    {
        $raw = $_ENV['BSPHOTO_SESSION_IDLE_SECONDS'] ?? getenv('BSPHOTO_SESSION_IDLE_SECONDS');
        if ($raw === false || $raw === null || $raw === '') {
            return 0;
        }

        $v = (int) trim((string) $raw);

        return max(0, min($v, self::MAX_IDLE_CAP_SECONDS));
    }
}
