<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Controllers\Admin;

use BSPhotoGalerie\Config\PublicAppearance;
use BSPhotoGalerie\Controllers\BaseController;
use BSPhotoGalerie\Core\Flash;
use BSPhotoGalerie\Core\HttpContext;
use BSPhotoGalerie\Models\SettingsRepository;
use BSPhotoGalerie\Services\Application\SettingsUpdateService;
use BSPhotoGalerie\Services\AuthService;

/**
 * Globale Galerie-Einstellungen (Diashow, Musik).
 */
final class SettingsController extends BaseController
{
    public function __construct(
        HttpContext $http,
        private SettingsRepository $settings,
        private AuthService $auth,
        private SettingsUpdateService $settingsUpdate
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
                'slideshowIntervalChoices' => SettingsUpdateService::SLIDESHOW_INTERVALS,
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
        $error = $this->settingsUpdate->applyFromRequest($this->http->request());
        if ($error !== null) {
            Flash::set('error', $error);
            $this->http->redirect('/admin/settings');

            return;
        }

        Flash::set('success', 'Einstellungen gespeichert.');
        $this->http->redirect('/admin/settings');
    }
}
