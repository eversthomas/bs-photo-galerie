<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Controllers\Admin;

use BSPhotoGalerie\Config\PublicAppearance;
use BSPhotoGalerie\Controllers\BaseController;
use BSPhotoGalerie\Core\Flash;
use BSPhotoGalerie\Core\HttpContext;
use BSPhotoGalerie\Models\SettingsRepository;
use BSPhotoGalerie\Services\AuthService;

/**
 * Globale Galerie-Einstellungen (Diashow, Musik).
 */
final class SettingsController extends BaseController
{
    /** @var list<int> */
    private const SLIDESHOW_INTERVALS = [3, 5, 8, 10, 15, 30, 60];

    public function __construct(
        HttpContext $http,
        private SettingsRepository $settings,
        private AuthService $auth
    ) {
        parent::__construct($http);
    }

    public function index(): void
    {
        $this->render(
            'admin/settings',
            [
                'title' => 'Einstellungen',
                'user' => $this->auth->user(),
                'flash' => Flash::pull(),
                'slideshowEnabled' => $this->settings->get('slideshow_enabled', '0') === '1',
                'slideshowInterval' => (int) $this->settings->get('slideshow_interval_seconds', '5'),
                'slideshowIntervalChoices' => self::SLIDESHOW_INTERVALS,
                'musicEnabled' => $this->settings->get('background_music_enabled', '0') === '1',
                'musicPlaylist' => $this->settings->get('music_playlist', ''),
                'publicTheme' => PublicAppearance::normalizeTheme($this->settings->get('public_theme', 'default')),
                'publicLayoutWidth' => PublicAppearance::normalizeLayoutWidth($this->settings->get('public_layout_width', 'standard')),
                'publicBaseUrl' => $this->settings->get('public_base_url', ''),
            ],
            'admin/layout'
        );
    }

    public function update(): void
    {
        $slideshowOn = $this->http->request()->post('slideshow_enabled', '') === '1'
            ? '1'
            : '0';

        $interval = (int) $this->http->request()->post('slideshow_interval_seconds', '5');
        if (! in_array($interval, self::SLIDESHOW_INTERVALS, true)) {
            $interval = 5;
        }

        $musicOn = $this->http->request()->post('background_music_enabled', '') === '1'
            ? '1'
            : '0';

        $rawPlaylist = (string) $this->http->request()->post('music_playlist', '');
        $playlistError = $this->validateMusicPlaylist($rawPlaylist);
        if ($playlistError !== null) {
            Flash::set('error', $playlistError);
            $this->http->redirect('/admin/settings');

            return;
        }

        $normalizedPlaylist = $this->normalizeMusicPlaylist($rawPlaylist);

        $theme = PublicAppearance::normalizeTheme(
            (string) $this->http->request()->post('public_theme', 'default')
        );
        $layoutWidth = PublicAppearance::normalizeLayoutWidth(
            (string) $this->http->request()->post('public_layout_width', 'standard')
        );

        $this->settings->set('slideshow_enabled', $slideshowOn);
        $this->settings->set('slideshow_interval_seconds', (string) $interval);
        $this->settings->set('background_music_enabled', $musicOn);
        $this->settings->set('music_playlist', $normalizedPlaylist);
        $this->settings->set('public_theme', $theme);
        $this->settings->set('public_layout_width', $layoutWidth);

        $baseUrl = trim((string) $this->http->request()->post('public_base_url', ''));
        if ($baseUrl !== '' && filter_var($baseUrl, FILTER_VALIDATE_URL) === false) {
            Flash::set('error', 'Öffentliche Basis-URL ist ungültig (vollständige URL mit https:// …).');
            $this->http->redirect('/admin/settings');

            return;
        }
        if ($baseUrl !== '' && str_contains($baseUrl, "\n")) {
            Flash::set('error', 'Öffentliche Basis-URL ungültig.');
            $this->http->redirect('/admin/settings');

            return;
        }
        $this->settings->set('public_base_url', $baseUrl);

        Flash::set('success', 'Einstellungen gespeichert.');
        $this->http->redirect('/admin/settings');
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
