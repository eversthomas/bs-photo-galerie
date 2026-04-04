<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Services\Update;

/**
 * Aktualisiert die Installation per Git + Composer (nur wenn explizit erlaubt und .git vorhanden).
 */
final class GitApplicationUpdater
{
    public function __construct(
        private string $projectRoot
    ) {
    }

    public static function isWebGitUpdateAllowed(): bool
    {
        $raw = $_ENV['BSPHOTO_ALLOW_GIT_UPDATE'] ?? getenv('BSPHOTO_ALLOW_GIT_UPDATE');
        if ($raw === false || $raw === null) {
            return false;
        }

        return in_array(strtolower(trim((string) $raw)), ['1', 'true', 'yes', 'on'], true);
    }

    public function isGitWorkingCopy(): bool
    {
        return is_dir(rtrim($this->projectRoot, '/') . '/.git');
    }

    public function canRunShell(): bool
    {
        if (! function_exists('proc_open')) {
            return false;
        }
        $disabled = ini_get('disable_functions');
        if (! is_string($disabled) || $disabled === '') {
            return true;
        }
        foreach (array_map('trim', explode(',', $disabled)) as $fn) {
            if ($fn === 'proc_open') {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{ok: bool, log: list<string>}
     */
    public function run(string $tagName): array
    {
        $log = [];
        if (! self::isWebGitUpdateAllowed()) {
            return ['ok' => false, 'log' => ['Web-Git-Updates sind nicht aktiviert (Umgebungsvariable BSPHOTO_ALLOW_GIT_UPDATE).']];
        }
        if (! $this->isGitWorkingCopy()) {
            return ['ok' => false, 'log' => ['Kein Git-Arbeitsverzeichnis (.git fehlt). Deployment war vermutlich per ZIP/FTP.']];
        }
        if (! $this->canRunShell()) {
            return ['ok' => false, 'log' => ['proc_open ist auf diesem Server nicht verfügbar (PHP-Konfiguration).']];
        }

        $root = rtrim($this->projectRoot, '/');
        $tag = trim($tagName);
        if ($tag === '' || ! preg_match('/^[a-zA-Z0-9._-]+$/', $tag)) {
            return ['ok' => false, 'log' => ['Ungültiger Tag-Name.']];
        }

        $status = $this->runProcess($root, ['git', 'status', '--porcelain']);
        $log = array_merge($log, $status['lines']);
        if (! $status['ok']) {
            return ['ok' => false, 'log' => $log];
        }
        if (trim(implode("\n", $status['lines'])) !== '') {
            return [
                'ok' => false,
                'log' => array_merge(
                    $log,
                    ['Abbruch: Es gibt lokale Änderungen. Bitte zuerst committen, stashen oder bereinigen (git status).']
                ),
            ];
        }

        $fetch = $this->runProcess($root, ['git', 'fetch', 'origin', '--tags']);
        $log = array_merge($log, $fetch['lines']);
        if (! $fetch['ok']) {
            return ['ok' => false, 'log' => $log];
        }

        $checkout = $this->runProcess($root, ['git', 'checkout', '--force', $tag]);
        $log = array_merge($log, $checkout['lines']);
        if (! $checkout['ok']) {
            return ['ok' => false, 'log' => $log];
        }

        $composer = $this->runComposerInstall($root);
        $log = array_merge($log, $composer['lines']);
        if (! $composer['ok']) {
            return ['ok' => false, 'log' => $log];
        }

        if (function_exists('opcache_reset')) {
            @opcache_reset();
            $log[] = 'Opcache zurückgesetzt (falls aktiv).';
        }

        $vPath = $root . '/VERSION';
        if (is_file($vPath) && is_readable($vPath)) {
            $log[] = 'Hinweis: Datei VERSION im Projektroot ggf. manuell an Release anpassen (oder aus Repository übernehmen).';
        }

        return ['ok' => true, 'log' => $log];
    }

    /**
     * @return array{ok: bool, lines: list<string>}
     */
    private function runComposerInstall(string $root): array
    {
        $php = (defined('PHP_BINARY') && is_string(PHP_BINARY) && PHP_BINARY !== '')
            ? PHP_BINARY
            : 'php';

        $composerPhar = $root . '/composer.phar';
        if (is_file($composerPhar)) {
            return $this->runProcess($root, [
                $php,
                $composerPhar,
                'install',
                '--no-dev',
                '--no-interaction',
                '--optimize-autoloader',
            ]);
        }

        $shellCmd = 'cd ' . escapeshellarg($root)
            . ' && composer install --no-dev --no-interaction --optimize-autoloader';

        return $this->runProcess($root, ['/bin/sh', '-c', $shellCmd]);
    }

    /**
     * @param list<string> $command
     * @return array{ok: bool, lines: list<string>}
     */
    private function runProcess(string $cwd, array $command): array
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $proc = proc_open($command, $descriptors, $pipes, $cwd, null);
        if (! is_resource($proc)) {
            return ['ok' => false, 'lines' => ['proc_open fehlgeschlagen für: ' . implode(' ', $command)]];
        }
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $code = proc_close($proc);
        $lines = [];
        foreach (preg_split('/\R/', trim((string) $stdout . "\n" . (string) $stderr)) ?: [] as $line) {
            $line = trim((string) $line);
            if ($line !== '') {
                $lines[] = $line;
            }
        }

        return ['ok' => $code === 0, 'lines' => $lines];
    }
}
