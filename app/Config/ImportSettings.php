<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Config;

/**
 * Konfiguration für Ordner-/FTP-Import (relativ zum Projektroot, außer absolute local_path_override).
 */
final class ImportSettings
{
    /** @param array<string, mixed> $appConfig */
    public function __construct(
        private array $appConfig
    ) {
    }

    /**
     * Relativer Pfad zum Eingangsordner (Dateien hier werden importiert, z. B. per FTP synchronisiert).
     */
    public function localRelativePath(): string
    {
        $i = $this->appConfig['import'] ?? null;
        if (is_array($i) && isset($i['local_path']) && is_string($i['local_path']) && $i['local_path'] !== '') {
            return trim(str_replace('\\', '/', $i['local_path']), '/');
        }

        return 'public/import';
    }

    public function deleteSourceAfterSuccess(): bool
    {
        $i = $this->appConfig['import'] ?? null;
        if (is_array($i) && array_key_exists('delete_source_after_import', $i)) {
            return (bool) $i['delete_source_after_import'];
        }

        return true;
    }

    public function ftpEnabled(): bool
    {
        $i = $this->appConfig['import'] ?? null;
        if (! is_array($i) || empty($i['ftp']) || ! is_array($i['ftp'])) {
            return false;
        }

        return ! empty($i['ftp']['enabled']);
    }

    /**
     * @return array{host:string,port:int,username:string,password:string,passive:bool,remote_dir:string}|null
     */
    public function ftpCredentials(): ?array
    {
        if (! $this->ftpEnabled()) {
            return null;
        }
        $i = $this->appConfig['import'];
        $f = $i['ftp'];
        $host = isset($f['host']) && is_string($f['host']) ? trim($f['host']) : '';
        if ($host === '') {
            return null;
        }

        return [
            'host' => $host,
            'port' => isset($f['port']) ? max(1, min(65535, (int) $f['port'])) : 21,
            'username' => isset($f['username']) && is_string($f['username']) ? $f['username'] : '',
            'password' => isset($f['password']) && is_string($f['password']) ? $f['password'] : '',
            'passive' => ! isset($f['passive']) || (bool) $f['passive'],
            'remote_dir' => isset($f['remote_dir']) && is_string($f['remote_dir']) ? $f['remote_dir'] : '/',
        ];
    }
}
