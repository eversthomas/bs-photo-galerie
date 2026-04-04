<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Controllers;

use BSPhotoGalerie\Core\CsrfToken;
use BSPhotoGalerie\Services\Install\InstallerService;

/**
 * Dünner Controller für den Webbasierten Installer.
 */
final class InstallController
{
    private InstallerService $installer;

    public function __construct(
        private string $projectRoot
    ) {
        $this->installer = new InstallerService($this->projectRoot);
    }

    public function handle(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if ($method === 'POST') {
            $this->handlePost();
            return;
        }

        $this->handleGet();
    }

    private function handleGet(): void
    {
        $locked = $this->installer->isInstallLocked();
        $devReset = $this->isDevResetEnabled();

        $flash = null;
        if (! empty($_SESSION['install_flash']) && is_array($_SESSION['install_flash'])) {
            $flash = $_SESSION['install_flash'];
            unset($_SESSION['install_flash']);
        }

        $this->render('installer.php', [
            'csrfToken' => CsrfToken::installToken(),
            'locked' => $locked,
            'devReset' => $devReset,
            'flash' => $flash,
            'baseUrl' => $this->installBaseUrl(),
        ]);
    }

    private function handlePost(): void
    {
        if (! CsrfToken::validateInstall($_POST['_csrf'] ?? null)) {
            $this->flash('error', 'Ungültiges Sicherheitstoken (CSRF). Bitte Seite neu laden.');
            $this->redirectBack();
        }

        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'test_db') {
            $this->postTestDb();
            return;
        }

        if ($action === 'install') {
            $this->postInstall();
            return;
        }

        if ($action === 'dev_reset') {
            $this->postDevReset();
            return;
        }

        $this->flash('error', 'Unbekannte Aktion.');
        $this->redirectBack();
    }

    private function postTestDb(): void
    {
        if ($this->installer->isInstallLocked() && ! $this->isDevResetEnabled()) {
            $this->flash('error', 'Installation ist abgeschlossen.');
            $this->redirectBack();
        }

        $db = $this->readDbFromPost();
        if (is_string($db)) {
            $this->flash('error', $db);
            $this->redirectBack();
        }

        $result = $this->installer->testConnection($db);
        if ($result === true) {
            $this->flash('success', 'Datenbankverbindung erfolgreich.');
        } else {
            $this->flash('error', 'Verbindung fehlgeschlagen: ' . $result);
        }

        $this->redirectBack();
    }

    private function postInstall(): void
    {
        if ($this->installer->isInstallLocked() && ! $this->isDevResetEnabled()) {
            $this->flash('error', 'Installation ist bereits abgeschlossen.');
            $this->redirectBack();
        }

        if ($this->installer->isInstallLocked() && $this->isDevResetEnabled()) {
            $this->flash('error', 'Zuerst „Installation zurücksetzen“ ausführen oder Dev-Reset nutzen.');
            $this->redirectBack();
        }

        $db = $this->readDbFromPost();
        if (is_string($db)) {
            $this->flash('error', $db);
            $this->redirectBack();
        }

        $admin = $this->readAdminFromPost();
        if (is_string($admin)) {
            $this->flash('error', $admin);
            $this->redirectBack();
        }

        $out = $this->installer->runInstall($db, $admin);
        if ($out['ok']) {
            CsrfToken::rotateInstall();
            $this->flash('success', $out['message']);
        } else {
            $this->flash('error', $out['message']);
        }

        $this->redirectBack();
    }

    private function postDevReset(): void
    {
        if (! $this->isDevResetEnabled()) {
            $this->flash('error', 'Dev-Reset ist nicht aktiviert (INSTALL_DEV_RESET in .env).');
            $this->redirectBack();
        }

        $confirm = isset($_POST['dev_reset_confirm']) && $_POST['dev_reset_confirm'] === '1';
        if (! $confirm) {
            $this->flash('error', 'Bitte „Installation zurücksetzen“ bestätigen.');
            $this->redirectBack();
        }

        $wipeDb = isset($_POST['dev_reset_wipe_db']) && $_POST['dev_reset_wipe_db'] === '1';
        $removeConfig = isset($_POST['dev_reset_remove_config']) && $_POST['dev_reset_remove_config'] === '1';

        $db = null;
        if ($wipeDb) {
            $read = $this->readDbFromPost();
            $db = is_array($read) ? $read : null;
        }

        $out = $this->installer->runDevReset(
            $db,
            $wipeDb,
            $removeConfig
        );

        if ($out['ok']) {
            CsrfToken::rotateInstall();
            $this->flash('success', $out['message']);
        } else {
            $this->flash('error', $out['message']);
        }

        $this->redirectBack();
    }

    /**
     * @return array{host:string,port:int,name:string,user:string,password:string}|string
     */
    private function readDbFromPost(): array|string
    {
        $host = trim((string) ($_POST['db_host'] ?? ''));
        $port = (int) ($_POST['db_port'] ?? 3306);
        $name = trim((string) ($_POST['db_name'] ?? ''));
        $user = trim((string) ($_POST['db_user'] ?? ''));
        $password = (string) ($_POST['db_password'] ?? '');

        if ($host === '' || $name === '' || $user === '') {
            return 'Bitte Host, Datenbankname und Benutzer ausfüllen.';
        }
        if ($port < 1 || $port > 65535) {
            return 'Ungültiger Port.';
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
     * @return array{username:string,password:string,email:string}|string
     */
    private function readAdminFromPost(): array|string
    {
        $username = trim((string) ($_POST['admin_username'] ?? ''));
        $password = (string) ($_POST['admin_password'] ?? '');
        $email = trim((string) ($_POST['admin_email'] ?? ''));

        return [
            'username' => $username,
            'password' => $password,
            'email' => $email,
        ];
    }

    private function flash(string $type, string $message): void
    {
        $_SESSION['install_flash'] = ['type' => $type, 'message' => $message];
    }

    private function redirectBack(): void
    {
        $url = $this->installIndexUrl();
        header('Location: ' . $url, true, 303);
        exit;
    }

    private function installIndexUrl(): string
    {
        return $this->installBaseUrl() . '/index.php';
    }

    private function installBaseUrl(): string
    {
        $script = $_SERVER['SCRIPT_NAME'] ?? '/install/index.php';
        $script = str_replace('\\', '/', (string) $script);
        $dir = dirname($script);

        if ($dir === '/' || $dir === '.') {
            return '/install';
        }

        return rtrim($dir, '/');
    }

    /**
     * @param array<string, mixed> $data
     */
    private function render(string $template, array $data): void
    {
        $contentPath = $this->projectRoot . '/templates/install/' . $template;

        header('Content-Type: text/html; charset=utf-8');

        if (! is_file($contentPath)) {
            http_response_code(500);
            echo 'Vorlage fehlt.';

            return;
        }

        $data['contentTemplate'] = $contentPath;
        extract($data, EXTR_SKIP);
        require $this->projectRoot . '/templates/install/layout.php';
    }

    private function isDevResetEnabled(): bool
    {
        $raw = $_ENV['INSTALL_DEV_RESET'] ?? getenv('INSTALL_DEV_RESET');
        if ($raw === false || $raw === null) {
            return false;
        }

        $v = strtolower(trim((string) $raw));

        return in_array($v, ['1', 'true', 'yes', 'on'], true);
    }
}
