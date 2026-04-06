<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Core;

use BSPhotoGalerie\Models\SettingsRepository;

/**
 * HTTP-Hilfen für Controller (URL, Redirect, Fehlerseiten) ohne vollen Application-Service-Locator.
 */
final class HttpContext
{
    public function __construct(
        private Request $request,
        private string $projectRoot,
        private SettingsRepository $settings
    ) {
    }

    public function request(): Request
    {
        return $this->request;
    }

    public function root(): string
    {
        return $this->projectRoot;
    }

    public function settingsRepository(): SettingsRepository
    {
        return $this->settings;
    }

    /**
     * Öffentliche URL inkl. Basis-Pfad unterhalb von public/.
     */
    public function url(string $path): string
    {
        $script = $this->request->server('SCRIPT_NAME') ?? '/index.php';
        $script = str_replace('\\', '/', (string) $script);
        $base = rtrim(dirname($script), '/');

        if ($base === '' || $base === '.') {
            $prefix = '';
        } else {
            $prefix = $base;
        }

        $path = '/' . ltrim($path, '/');
        if ($path === '//') {
            $path = '/';
        }

        return $prefix . $path;
    }

    /**
     * Öffentliche URL mit optional gesetzter Hauptdomain (Einstellung public_base_url), sonst wie url().
     */
    public function publicUrl(string $path): string
    {
        $raw = trim($this->settings->get('public_base_url', ''));
        if ($raw === '') {
            return $this->url($path);
        }
        $path = '/' . ltrim(str_replace('\\', '/', $path), '/');
        if ($path === '//') {
            $path = '/';
        }

        return rtrim($raw, '/') . $path;
    }

    public function redirect(string $path, int $status = 302): void
    {
        header('Location: ' . $this->url($path), true, $status);
        exit;
    }

    public function abort(int $code, string $message): void
    {
        http_response_code($code);
        header('Content-Type: text/html; charset=utf-8');

        if ($code === 404) {
            $file = $this->projectRoot . '/templates/errors/404.php';
            if (is_file($file)) {
                require $file;
                exit;
            }
        }

        echo '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8"><title>Fehler</title></head><body>';
        echo '<p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>';
        echo '</body></html>';
        exit;
    }
}
