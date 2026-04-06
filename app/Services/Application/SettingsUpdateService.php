<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Services\Application;

use BSPhotoGalerie\Config\PublicAppearance;
use BSPhotoGalerie\Core\Request;
use BSPhotoGalerie\Models\SettingsRepository;

/**
 * Anwendungsfall: Globale Galerie-Einstellungen aus POST speichern.
 */
final class SettingsUpdateService
{
    /** @var list<int> */
    public const SLIDESHOW_INTERVALS = [3, 5, 8, 10, 15, 30, 60];

    public function __construct(
        private SettingsRepository $settings
    ) {
    }

    /**
     * @return string|null Fehlermeldung oder null bei Erfolg (Werte sind gespeichert)
     */
    public function applyFromRequest(Request $request): ?string
    {
        $slideshowOn = $request->post('slideshow_enabled', '') === '1'
            ? '1'
            : '0';

        $interval = (int) $request->post('slideshow_interval_seconds', '5');
        if (! in_array($interval, self::SLIDESHOW_INTERVALS, true)) {
            $interval = 5;
        }

        $musicOn = $request->post('background_music_enabled', '') === '1'
            ? '1'
            : '0';

        $rawPlaylist = (string) $request->post('music_playlist', '');
        $playlistError = $this->validateMusicPlaylist($rawPlaylist);
        if ($playlistError !== null) {
            return $playlistError;
        }

        $normalizedPlaylist = $this->normalizeMusicPlaylist($rawPlaylist);

        $theme = PublicAppearance::normalizeTheme(
            (string) $request->post('public_theme', 'default')
        );
        $layoutWidth = PublicAppearance::normalizeLayoutWidth(
            (string) $request->post('public_layout_width', 'standard')
        );

        $this->settings->set('slideshow_enabled', $slideshowOn);
        $this->settings->set('slideshow_interval_seconds', (string) $interval);
        $this->settings->set('background_music_enabled', $musicOn);
        $this->settings->set('music_playlist', $normalizedPlaylist);
        $this->settings->set('public_theme', $theme);
        $this->settings->set('public_layout_width', $layoutWidth);

        $baseUrl = trim((string) $request->post('public_base_url', ''));
        if ($baseUrl !== '' && filter_var($baseUrl, FILTER_VALIDATE_URL) === false) {
            return 'Öffentliche Basis-URL ist ungültig (vollständige URL mit https:// …).';
        }
        if ($baseUrl !== '' && str_contains($baseUrl, "\n")) {
            return 'Öffentliche Basis-URL ungültig.';
        }
        $this->settings->set('public_base_url', $baseUrl);

        return null;
    }

    private function validateMusicPlaylist(string $raw): ?string
    {
        $lines = preg_split('/\R/', $raw) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if ($this->isValidMusicUrl($line) === false) {
                return 'Ungültige Musik-URL (Zeile): Nur Pfade ab „/“ oder http(s)-Links, ohne „..“.';
            }
        }

        return null;
    }

    private function isValidMusicUrl(string $line): bool
    {
        if (str_starts_with($line, '/')) {
            if (str_contains($line, '..') || strlen($line) > 2048) {
                return false;
            }

            return true;
        }

        if (preg_match('#^https?://#i', $line) === 1) {
            return filter_var($line, FILTER_VALIDATE_URL) !== false && strlen($line) <= 2048;
        }

        return false;
    }

    private function normalizeMusicPlaylist(string $raw): string
    {
        $lines = preg_split('/\R/', $raw) ?: [];
        $out = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $out[] = $line;
        }

        return implode("\n", $out);
    }
}
