<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Services\Update;

use RuntimeException;

/**
 * Aktualisiert die Installation aus dem offiziellen GitHub-ZIP-Archiv (ohne .git).
 *
 * Überschreibt Projektdateien, behält config/.env, config/config.php, storage/ und public/uploads/.
 */
final class ZipReleaseUpdater
{
    private const REPO_PREFIX = '/eversthomas/bs-photo-galerie/';

    public function __construct(
        private string $projectRoot
    ) {
    }

    public static function hasZipExtension(): bool
    {
        return class_exists(\ZipArchive::class);
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
    public function run(string $zipUrl): array
    {
        $log = [];
        if (! WebUpdatePolicy::isWebUpdateAllowed()) {
            return ['ok' => false, 'log' => ['Web-Updates sind nicht freigeschaltet (z. B. BSPHOTO_ALLOW_WEB_UPDATE=1 in config/.env).']];
        }
        if (! self::hasZipExtension()) {
            return ['ok' => false, 'log' => ['PHP-Erweiterung zip (ZipArchive) fehlt.']];
        }
        if (! $this->canRunShell()) {
            return ['ok' => false, 'log' => ['proc_open ist nicht verfügbar (composer install nach dem Entpacken).']];
        }

        $this->assertTrustedZipUrl($zipUrl);

        $root = rtrim($this->projectRoot, '/');
        $workBase = $root . '/storage/cache/update_work';
        if (is_dir($workBase)) {
            self::removeDir($workBase);
        }
        if (! @mkdir($workBase, 0755, true) && ! is_dir($workBase)) {
            return ['ok' => false, 'log' => ['Temporäres Verzeichnis konnte nicht angelegt werden: ' . $workBase]];
        }

        $zipPath = $workBase . '/release.zip';
        $extractDir = $workBase . '/extract';

        try {
            $this->downloadToFile($zipUrl, $zipPath, $log);
            if (! is_file($zipPath) || filesize($zipPath) === 0) {
                throw new RuntimeException('Download leer oder fehlgeschlagen.');
            }

            if (! @mkdir($extractDir, 0755, true)) {
                throw new RuntimeException('Entpack-Verzeichnis fehlt.');
            }

            $zip = new \ZipArchive();
            if ($zip->open($zipPath) !== true) {
                throw new RuntimeException('ZIP-Datei konnte nicht geöffnet werden.');
            }
            if (! $zip->extractTo($extractDir)) {
                $zip->close();
                throw new RuntimeException('ZIP konnte nicht entpackt werden.');
            }
            $zip->close();

            $sourceRoot = $this->detectArchiveRoot($extractDir);
            $log[] = 'Archiv-Stamm: ' . basename($sourceRoot);

            $this->mirrorIntoProject($sourceRoot, $root, $log);

            $composer = $this->runComposerInstall($root);
            $log = array_merge($log, $composer['lines']);
            if (! $composer['ok']) {
                return ['ok' => false, 'log' => $log];
            }

            if (function_exists('opcache_reset')) {
                @opcache_reset();
                $log[] = 'Opcache zurückgesetzt (falls aktiv).';
            }
        } catch (\Throwable $e) {
            $log[] = 'Fehler: ' . $e->getMessage();

            return ['ok' => false, 'log' => $log];
        } finally {
            if (is_dir($workBase)) {
                self::removeDir($workBase);
            }
        }

        return ['ok' => true, 'log' => $log];
    }

    private function assertTrustedZipUrl(string $url): void
    {
        if (strlen($url) > 2048) {
            throw new RuntimeException('ZIP-URL ungültig.');
        }
        $p = parse_url($url);
        if (($p['scheme'] ?? '') !== 'https') {
            throw new RuntimeException('Nur https-URLs erlaubt.');
        }
        $host = strtolower($p['host'] ?? '');
        if (! in_array($host, ['github.com', 'codeload.github.com'], true)) {
            throw new RuntimeException('ZIP nur von GitHub (github.com / codeload.github.com).');
        }
        $path = $p['path'] ?? '';
        if (! str_starts_with($path, self::REPO_PREFIX)) {
            throw new RuntimeException('ZIP-URL gehört nicht zum erwarteten Repository.');
        }
    }

    /**
     * @param list<string> $log
     */
    private function downloadToFile(string $url, string $dest, array &$log): void
    {
        $token = $_ENV['GITHUB_API_TOKEN'] ?? getenv('GITHUB_API_TOKEN');
        $token = is_string($token) ? trim($token) : '';

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch === false) {
                throw new RuntimeException('curl_init fehlgeschlagen.');
            }
            $fp = fopen($dest, 'wb');
            if ($fp === false) {
                curl_close($ch);
                throw new RuntimeException('ZIP-Zieldatei nicht beschreibbar.');
            }
            $headers = [
                'Accept: application/octet-stream',
                'User-Agent: BSPhotoGalerie-ZipUpdate/1.0',
            ];
            if ($token !== '') {
                $headers[] = 'Authorization: Bearer ' . $token;
            }
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 8);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_TIMEOUT, 600);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
            $ok = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            fclose($fp);
            if ($ok === false || $code < 200 || $code >= 300) {
                @unlink($dest);
                throw new RuntimeException('HTTP-Download fehlgeschlagen (Code ' . $code . ').');
            }
            $log[] = 'GitHub-ZIP heruntergeladen (HTTP ' . $code . ').';

            return;
        }

        $header = "Accept: application/octet-stream\r\nUser-Agent: BSPhotoGalerie-ZipUpdate/1.0\r\n";
        if ($token !== '') {
            $header .= 'Authorization: Bearer ' . $token . "\r\n";
        }
        $ctx = stream_context_create([
            'http' => [
                'header' => $header,
                'timeout' => 600,
                'ignore_errors' => true,
                'follow_location' => 1,
                'max_redirects' => 8,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);
        $in = @fopen($url, 'rb', false, $ctx);
        if ($in === false) {
            throw new RuntimeException('ZIP-Download fehlgeschlagen (weder curl noch stream-URL lesbar).');
        }
        $out = @fopen($dest, 'wb');
        if ($out === false) {
            fclose($in);
            throw new RuntimeException('ZIP-Zieldatei nicht beschreibbar.');
        }
        $copied = stream_copy_to_stream($in, $out);
        fclose($in);
        fclose($out);
        if ($copied === false || $copied === 0) {
            @unlink($dest);
            throw new RuntimeException('ZIP-Download hat keine Daten geliefert.');
        }
        $log[] = 'GitHub-ZIP per HTTPS heruntergeladen (ohne curl).';
    }

    private function detectArchiveRoot(string $extractDir): string
    {
        $entries = array_values(array_diff(scandir($extractDir) ?: [], ['.', '..']));
        if (count($entries) === 1 && is_dir($extractDir . '/' . $entries[0])) {
            return $extractDir . '/' . $entries[0];
        }

        return $extractDir;
    }

    /**
     * @param list<string> $log
     */
    private function mirrorIntoProject(string $from, string $toRoot, array &$log): void
    {
        $from = rtrim($from, '/');
        $toRoot = rtrim($toRoot, '/');
        $excludeExact = [
            'config/.env',
            'config/config.php',
        ];
        $excludePrefixes = [
            'storage/',
            'public/uploads/',
        ];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($from, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $info) {
            if (! $info instanceof \SplFileInfo) {
                continue;
            }
            $absolute = $info->getPathname();
            $rel = substr($absolute, strlen($from) + 1);
            $rel = str_replace('\\', '/', $rel);
            if ($rel === false || $rel === '') {
                continue;
            }
            if (str_contains($rel, '..')) {
                throw new RuntimeException('Unzulässiger Pfad im Archiv: ' . $rel);
            }

            foreach ($excludePrefixes as $prefix) {
                if (str_starts_with($rel, $prefix)) {
                    continue 2;
                }
            }
            foreach ($excludeExact as $ex) {
                if ($rel === $ex || str_starts_with($rel, $ex . '/')) {
                    continue 2;
                }
            }

            $target = $toRoot . '/' . $rel;
            if ($info->isDir()) {
                if (! is_dir($target) && ! @mkdir($target, 0755, true) && ! is_dir($target)) {
                    throw new RuntimeException('Verzeichnis nicht anlegbar: ' . $rel);
                }

                continue;
            }

            $parent = dirname($target);
            if (! is_dir($parent) && ! @mkdir($parent, 0755, true) && ! is_dir($parent)) {
                throw new RuntimeException('Zielverzeichnis fehlt: ' . $rel);
            }
            if (! @copy($absolute, $target)) {
                throw new RuntimeException('Datei nicht kopierbar: ' . $rel);
            }
            @chmod($target, 0644);
        }

        $log[] = 'Dateien aus Archiv übernommen (ohne config/.env, config/config.php, storage/, public/uploads/).';
    }

    private static function removeDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $info) {
            $p = $info->getPathname();
            if ($info->isDir()) {
                @rmdir($p);
            } else {
                @unlink($p);
            }
        }
        @rmdir($dir);
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
