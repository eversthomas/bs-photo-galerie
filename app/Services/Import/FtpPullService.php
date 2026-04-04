<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Services\Import;

use RuntimeException;

/**
 * Holt Dateien aus einem einfachen FTP-Verzeichnis (nicht rekursiv) in den lokalen Import-Ordner.
 */
final class FtpPullService
{
    /**
     * @param array{host:string,port:int,username:string,password:string,passive:bool,remote_dir:string} $cfg
     *
     * @return array{downloaded:int, skipped:int, errors:list<string>}
     */
    public function pullFlatDirectory(string $localAbsoluteDir, array $cfg): array
    {
        if (! function_exists('ftp_connect')) {
            return [
                'downloaded' => 0,
                'skipped' => 0,
                'errors' => ['PHP-Erweiterung ftp ist nicht verfügbar.'],
            ];
        }

        if (! is_dir($localAbsoluteDir) && ! mkdir($localAbsoluteDir, 0755, true) && ! is_dir($localAbsoluteDir)) {
            return [
                'downloaded' => 0,
                'skipped' => 0,
                'errors' => ['Lokaler Import-Ordner konnte nicht angelegt werden.'],
            ];
        }

        $errors = [];
        $downloaded = 0;
        $skipped = 0;

        $conn = @ftp_connect($cfg['host'], $cfg['port'], 15);
        if ($conn === false) {
            return ['downloaded' => 0, 'skipped' => 0, 'errors' => ['FTP-Verbindung fehlgeschlagen.']];
        }

        try {
            if (! @ftp_login($conn, $cfg['username'], $cfg['password'])) {
                throw new RuntimeException('FTP-Anmeldung fehlgeschlagen.');
            }

            if ($cfg['passive']) {
                @ftp_pasv($conn, true);
            }

            $remote = $cfg['remote_dir'] === '' ? '/' : $cfg['remote_dir'];
            if (! @ftp_chdir($conn, $remote)) {
                throw new RuntimeException('FTP-Zielverzeichnis nicht erreichbar: ' . $remote);
            }

            $list = @ftp_nlist($conn, '.');
            if (! is_array($list)) {
                throw new RuntimeException('FTP-Dateiliste konnte nicht gelesen werden.');
            }

            $allowedExt = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

            foreach ($list as $name) {
                if (! is_string($name) || $name === '.' || $name === '..') {
                    continue;
                }

                $base = basename(str_replace('\\', '/', $name));
                if ($base === '' || str_starts_with($base, '.')) {
                    continue;
                }

                $ext = strtolower(pathinfo($base, PATHINFO_EXTENSION));
                if (! in_array($ext, $allowedExt, true)) {
                    ++$skipped;
                    continue;
                }

                $localFile = rtrim($localAbsoluteDir, '/') . '/' . $base;
                if (@ftp_get($conn, $localFile, $base, FTP_BINARY)) {
                    ++$downloaded;
                } else {
                    $errors[] = 'FTP GET fehlgeschlagen: ' . $base;
                }
            }
        } catch (\Throwable $e) {
            $errors[] = $e->getMessage();
        } finally {
            if (isset($conn) && $conn !== false) {
                ftp_close($conn);
            }
        }

        return ['downloaded' => $downloaded, 'skipped' => $skipped, 'errors' => $errors];
    }
}
