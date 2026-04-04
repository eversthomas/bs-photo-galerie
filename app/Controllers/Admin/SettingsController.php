<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Controllers\Admin;

use BSPhotoGalerie\Config\PublicAppearance;
use BSPhotoGalerie\Controllers\BaseController;
use BSPhotoGalerie\Core\Flash;

/**
 * Globale Galerie-Einstellungen (Diashow, Musik).
 */
final class SettingsController extends BaseController
{
    /** @var list<int> */
    private const SLIDESHOW_INTERVALS = [3, 5, 8, 10, 15, 30, 60];

    public function index(): void
    {
        $settings = $this->app->settingsRepository();
        $this->render(
            'admin/settings',
            [
                'title' => 'Einstellungen',
                'user' => $this->app->auth()->user(),
                'flash' => Flash::pull() ?? [],
                'slideshowEnabled' => $settings->get('slideshow_enabled', '0') === '1',
                'slideshowInterval' => (int) $settings->get('slideshow_interval_seconds', '5'),
                'slideshowIntervalChoices' => self::SLIDESHOW_INTERVALS,
                'musicEnabled' => $settings->get('background_music_enabled', '0') === '1',
                'musicPlaylist' => $settings->get('music_playlist', ''),
                'publicTheme' => PublicAppearance::normalizeTheme($settings->get('public_theme', 'default')),
                'publicLayoutWidth' => PublicAppearance::normalizeLayoutWidth($settings->get('public_layout_width', 'standard')),
            ],
            'admin/layout'
        );
    }

    public function update(): void
    {
        $repo = $this->app->settingsRepository();

        $slideshowOn = $this->app->request()->post('slideshow_enabled', '') === '1'
            ? '1'
            : '0';

        $interval = (int) $this->app->request()->post('slideshow_interval_seconds', '5');
        if (! in_array($interval, self::SLIDESHOW_INTERVALS, true)) {
            $interval = 5;
        }

        $musicOn = $this->app->request()->post('background_music_enabled', '') === '1'
            ? '1'
            : '0';

        $rawPlaylist = (string) $this->app->request()->post('music_playlist', '');
        $playlistError = $this->validateMusicPlaylist($rawPlaylist);
        if ($playlistError !== null) {
            Flash::set('error', $playlistError);
            $this->app->redirect('/admin/settings');

            return;
        }

        $normalizedPlaylist = $this->normalizeMusicPlaylist($rawPlaylist);

        $theme = PublicAppearance::normalizeTheme(
            (string) $this->app->request()->post('public_theme', 'default')
        );
        $layoutWidth = PublicAppearance::normalizeLayoutWidth(
            (string) $this->app->request()->post('public_layout_width', 'standard')
        );

        $repo->set('slideshow_enabled', $slideshowOn);
        $repo->set('slideshow_interval_seconds', (string) $interval);
        $repo->set('background_music_enabled', $musicOn);
        $repo->set('music_playlist', $normalizedPlaylist);
        $repo->set('public_theme', $theme);
        $repo->set('public_layout_width', $layoutWidth);

        Flash::set('success', 'Einstellungen gespeichert.');
        $this->app->redirect('/admin/settings');
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
