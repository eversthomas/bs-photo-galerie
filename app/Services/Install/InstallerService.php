<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Services\Install;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PDOException;
use RuntimeException;

/**
 * Orchestriert Installation: DB-Test, Schema-Migration, Admin-Anlage, config.php, Install-Lock.
 */
final class InstallerService
{
    public function __construct(
        private string $projectRoot
    ) {
    }

    /**
     * @param array{host:string,port:int,name:string,user:string,password:string} $db
     */
    public function testConnection(array $db): true|string
    {
        try {
            $pdo = $this->createPdo($db, checkDb: true);
            $pdo->query('SELECT 1');

            return true;
        } catch (PDOException $e) {
            return $e->getMessage();
        }
    }

    /**
     * @param array{host:string,port:int,name:string,user:string,password:string} $db
     * @param array{username:string,password:string,email:string} $admin
     *
     * @return array{ok:bool,message:string}
     */
    public function runInstall(array $db, array $admin): array
    {
        $connCheck = $this->testConnection($db);
        if ($connCheck !== true) {
            return ['ok' => false, 'message' => 'Datenbankverbindung fehlgeschlagen: ' . $connCheck];
        }

        $validation = $this->validateAdmin($admin);
        if ($validation !== true) {
            return ['ok' => false, 'message' => $validation];
        }

        try {
            $pdo = $this->createPdo($db, checkDb: true);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            foreach (InstallSchema::createTableStatements() as $sql) {
                $pdo->exec($sql);
            }

            if ($this->tableHasRows($pdo, 'users')) {
                return [
                    'ok' => false,
                    'message' => 'In der Datenbank existieren bereits Benutzer. Für eine Neuinstallation bitte zuerst „Installation zurücksetzen“ mit geleerter Datenbank ausführen.',
                ];
            }

            $this->insertDefaultSettings($pdo);
            $this->insertAdminUser($pdo, $admin);

            $installedAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DateTimeImmutable::ATOM);

            $config = [
                'installed' => true,
                'installed_at' => $installedAt,
                'db' => [
                    'host' => $db['host'],
                    'port' => $db['port'],
                    'name' => $db['name'],
                    'user' => $db['user'],
                    'password' => $db['password'],
                    'charset' => 'utf8mb4',
                ],
                'media' => [
                    'max_upload_bytes' => 20 * 1024 * 1024,
                    'max_image_pixels' => 40_000_000,
                    'thumbnail_width' => 480,
                    'allowed_mime' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
                ],
                'import' => [
                    'local_path' => 'public/import',
                    'delete_source_after_import' => true,
                    'ftp' => [
                        'enabled' => false,
                        'host' => '',
                        'port' => 21,
                        'username' => '',
                        'password' => '',
                        'passive' => true,
                        'remote_dir' => '/',
                    ],
                ],
            ];

            $this->writeConfigFile($config);
            $this->writeInstallLock($installedAt);
            $this->writeVersionFile();

            return ['ok' => true, 'message' => 'Installation erfolgreich abgeschlossen.'];
        } catch (PDOException $e) {
            return ['ok' => false, 'message' => 'SQL-Fehler: ' . $e->getMessage()];
        } catch (RuntimeException $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Dev-Reset: Install-Lock entfernen und optional alle Tabellen löschen.
     *
     * @param array{host:string,port:int,name:string,user:string,password:string}|null $db Wenn gesetzt und wipeDb, werden Tabellen per DROP geleert
     *
     * @return array{ok:bool,message:string}
     */
    public function runDevReset(?array $db, bool $wipeDb, bool $removeConfig): array
    {
        try {
            if ($wipeDb) {
                if ($db === null) {
                    $db = $this->loadDbFromConfigOrFail();
                }
                $pdo = $this->createPdo($db, checkDb: true);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                foreach (InstallSchema::dropTableStatements() as $sql) {
                    $pdo->exec($sql);
                }
            }

            $lockPath = $this->lockFilePath();
            if (is_file($lockPath)) {
                if (! @unlink($lockPath)) {
                    return ['ok' => false, 'message' => 'Install-Lock konnte nicht gelöscht werden: ' . $lockPath];
                }
            }

            if ($removeConfig) {
                $configPath = $this->configFilePath();
                if (is_file($configPath) && ! @unlink($configPath)) {
                    return ['ok' => false, 'message' => 'config.php konnte nicht gelöscht werden: ' . $configPath];
                }
            }

            return ['ok' => true, 'message' => 'Installation zurückgesetzt. Sie können neu installieren.'];
        } catch (PDOException $e) {
            return ['ok' => false, 'message' => 'Datenbank beim Zurücksetzen: ' . $e->getMessage()];
        } catch (RuntimeException $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function isInstallLocked(): bool
    {
        return is_file($this->lockFilePath());
    }

    /**
     * @return array{host:string,port:int,name:string,user:string,password:string}
     */
    private function loadDbFromConfigOrFail(): array
    {
        $path = $this->configFilePath();
        if (! is_file($path)) {
            throw new RuntimeException('config.php nicht gefunden – für DB-Reset bitte Zugangsdaten im Formular angeben.');
        }

        /** @var array<string, mixed>|false $cfg */
        $cfg = include $path;
        if (! is_array($cfg) || empty($cfg['db']) || ! is_array($cfg['db'])) {
            throw new RuntimeException('Ungültige config.php.');
        }

        $db = $cfg['db'];
        $host = (string) ($db['host'] ?? '');
        $name = (string) ($db['name'] ?? '');
        $user = (string) ($db['user'] ?? '');
        $password = (string) ($db['password'] ?? '');
        $port = (int) ($db['port'] ?? 3306);

        if ($host === '' || $name === '') {
            throw new RuntimeException('config.php enthält keine vollständigen DB-Daten.');
        }

        return [
            'host' => $host,
            'port' => $port,
            'name' => $name,
            'user' => $user,
            'password' => $password,
        ];
    }

    /**
     * @param array{host:string,port:int,name:string,user:string,password:string} $db
     */
    private function createPdo(array $db, bool $checkDb): PDO
    {
        $host = $db['host'];
        $port = $db['port'];
        $name = $db['name'];
        $charset = 'utf8mb4';

        $dsn = $checkDb
            ? sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $name, $charset)
            : sprintf('mysql:host=%s;port=%d;charset=%s', $host, $port, $charset);

        return new PDO($dsn, $db['user'], $db['password'], [
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    private function insertDefaultSettings(PDO $pdo): void
    {
        $defaults = [
            ['site_title', 'BS Photo Galerie'],
            ['site_description', ''],
            ['slideshow_enabled', '0'],
            ['slideshow_interval_seconds', '5'],
            ['background_music_enabled', '0'],
            ['music_playlist', ''],
            ['public_theme', 'default'],
            ['public_layout_width', 'standard'],
            ['public_base_url', ''],
        ];

        $stmt = $pdo->prepare(
            'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );

        foreach ($defaults as [$key, $value]) {
            $stmt->execute([$key, $value]);
        }
    }

    /**
     * @param array{username:string,password:string,email:string} $admin
     */
    private function insertAdminUser(PDO $pdo, array $admin): void
    {
        $hash = password_hash($admin['password'], PASSWORD_DEFAULT);
        if ($hash === false) {
            throw new RuntimeException('Passwort-Hash konnte nicht erzeugt werden.');
        }

        $stmt = $pdo->prepare(
            'INSERT INTO users (username, password_hash, email, role, created_at) VALUES (?, ?, ?, ?, ?)'
        );

        $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');

        $stmt->execute([
            $admin['username'],
            $hash,
            $admin['email'],
            'admin',
            $now,
        ]);
    }

    /**
     * @param array{username:string,password:string,email:string} $admin
     */
    private function validateAdmin(array $admin): true|string
    {
        $user = trim($admin['username']);
        if ($user === '' || strlen($user) < 3) {
            return 'Der Admin-Benutzername muss mindestens 3 Zeichen haben.';
        }
        if (strlen($user) > 64) {
            return 'Der Admin-Benutzername ist zu lang.';
        }
        if (strlen($admin['password']) < 10) {
            return 'Das Admin-Passwort muss mindestens 10 Zeichen haben.';
        }
        $email = trim($admin['email']);
        if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'Die E-Mail-Adresse ist ungültig.';
        }

        return true;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function writeConfigFile(array $config): void
    {
        $path = $this->configFilePath();
        $dir = dirname($path);
        if (! is_dir($dir) && ! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new RuntimeException('Konfigurationsverzeichnis konnte nicht angelegt werden.');
        }

        $exported = var_export($config, true);
        $php = <<<PHP
<?php

declare(strict_types=1);

return {$exported};

PHP;

        if (file_put_contents($path, $php, LOCK_EX) === false) {
            throw new RuntimeException('config.php konnte nicht geschrieben werden.');
        }

        @chmod($path, 0640);
    }

    private function writeVersionFile(): void
    {
        $path = $this->projectRoot . '/VERSION';
        if (is_file($path)) {
            return;
        }
        @file_put_contents($path, "0.1.2\n", LOCK_EX);
    }

    private function writeInstallLock(string $installedAt): void
    {
        $dir = dirname($this->lockFilePath());
        if (! is_dir($dir) && ! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new RuntimeException('Lock-Verzeichnis konnte nicht angelegt werden.');
        }

        $payload = json_encode(
            [
                'installed_at' => $installedAt,
                'app' => 'bs-photo-galerie',
                'schema' => '1.0.0',
            ],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
        );

        if (file_put_contents($this->lockFilePath(), $payload . "\n", LOCK_EX) === false) {
            throw new RuntimeException('Install-Lock konnte nicht gesetzt werden.');
        }

        @chmod($this->lockFilePath(), 0640);
    }

    private function configFilePath(): string
    {
        return $this->projectRoot . '/config/config.php';
    }

    private function lockFilePath(): string
    {
        return $this->projectRoot . '/storage/locks/install.lock';
    }

    private function tableHasRows(PDO $pdo, string $table): bool
    {
        $safe = str_replace('`', '``', $table);
        $stmt = $pdo->query('SELECT COUNT(*) AS c FROM `' . $safe . '`');
        $row = $stmt !== false ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        if (! is_array($row)) {
            return false;
        }

        return (int) ($row['c'] ?? 0) > 0;
    }
}
