<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Controllers;

use BSPhotoGalerie\Core\HttpContext;
use BSPhotoGalerie\Models\CategoryRepository;
use BSPhotoGalerie\Models\MediaRepository;
use BSPhotoGalerie\Models\SettingsRepository;
use BSPhotoGalerie\Services\AuthService;

/**
 * Öffentliche Startseite (Platzhalter bis Galerie-Frontend).
 */
final class HomeController extends BaseController
{
    public function __construct(
        HttpContext $http,
        private SettingsRepository $settings,
        private MediaRepository $media,
        private CategoryRepository $categories,
        private AuthService $auth
    ) {
        parent::__construct($http);
    }

    public function index(): void
    {
        $siteTitle = $this->settings->get('site_title', 'BS Photo Galerie');
        $description = $this->settings->get('site_description', '');
        $guest = ! $this->auth->check();
        $preview = $this->media->listPublicVisible(12, 0, null, $guest);
        $cats = $guest
            ? $this->categories->listPublicOrdered()
            : $this->categories->listAllOrdered();

        $this->render(
            'home',
            [
                'pageTitle' => $siteTitle,
                'siteTitle' => $siteTitle,
                'siteDescription' => $description,
                'previewItems' => $preview,
                'categories' => $cats,
                'includeGalleryAssets' => false,
            ],
            'public/layout'
        );
    }
}
