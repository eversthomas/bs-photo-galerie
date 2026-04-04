<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Controllers;

/**
 * Öffentliche Startseite (Platzhalter bis Galerie-Frontend).
 */
final class HomeController extends BaseController
{
    public function index(): void
    {
        $siteTitle = $this->app->settingsRepository()->get('site_title', 'BS Photo Galerie');
        $description = $this->app->settingsRepository()->get('site_description', '');
        $preview = $this->app->mediaRepository()->listPublicVisible(12, 0, null);

        $this->render(
            'home',
            [
                'pageTitle' => $siteTitle,
                'siteTitle' => $siteTitle,
                'siteDescription' => $description,
                'previewItems' => $preview,
                'includeGalleryAssets' => false,
            ],
            'public/layout'
        );
    }
}
