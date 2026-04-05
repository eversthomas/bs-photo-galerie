<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Controllers;

use BSPhotoGalerie\Core\Application;

/**
 * Öffentliche Bildgalerie (Lightbox, Kategoriefilter).
 */
final class GalleryController extends BaseController
{
    /**
     * @return array{slideshowEnabled: bool, slideshowInterval: int, musicEnabled: bool, musicUrls: list<string>}
     */
    private function galleryRuntimeConfig(): array
    {
        $settings = $this->app->settingsRepository();
        $interval = (int) $settings->get('slideshow_interval_seconds', '5');
        $interval = max(3, min(120, $interval));

        $rawLines = self::parseMusicPlaylistLines($settings->get('music_playlist', ''));
        $musicUrls = [];
        foreach ($rawLines as $line) {
            $musicUrls[] = self::resolvePublicMediaUrl($this->app, $line);
        }

        return [
            'slideshowEnabled' => $settings->get('slideshow_enabled', '0') === '1',
            'slideshowInterval' => $interval,
            'musicEnabled' => $settings->get('background_music_enabled', '0') === '1',
            'musicUrls' => $musicUrls,
        ];
    }

    /**
     * @return list<string>
     */
    private static function parseMusicPlaylistLines(string $raw): array
    {
        $lines = preg_split('/\R/', $raw) ?: [];
        $out = [];
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line !== '') {
                $out[] = $line;
            }
        }

        return $out;
    }

    /**
     * Stellt absolute Wiedergabe-URLs für das Hintergrund-Audio sicher (v. a. bei gesetzter public_base_url).
     */
    private static function resolvePublicMediaUrl(Application $app, string $line): string
    {
        if (str_starts_with($line, '/')) {
            return $app->publicUrl($line);
        }

        return $line;
    }

    public function index(): void
    {
        $guest = ! $this->app->auth()->check();
        $items = $this->app->mediaRepository()->listPublicVisible(400, 0, null, $guest);
        $siteTitle = $this->app->settingsRepository()->get('site_title', 'BS Photo Galerie');
        $description = $this->app->settingsRepository()->get('site_description', '');
        $categories = $guest
            ? $this->app->categoryRepository()->listPublicOrdered()
            : $this->app->categoryRepository()->listAllOrdered();

        $this->render(
            'gallery/index',
            [
                'pageTitle' => 'Galerie – ' . $siteTitle,
                'siteTitle' => $siteTitle,
                'siteDescription' => $description,
                'items' => $items,
                'categories' => $categories,
                'currentCategory' => null,
                'includeGalleryAssets' => true,
                'galleryRuntimeConfig' => $this->galleryRuntimeConfig(),
            ],
            'public/layout'
        );
    }

    public function category(string $slug): void
    {
        $cat = $this->app->categoryRepository()->findBySlug($slug);
        if ($cat === null) {
            $this->app->abort(404, 'Kategorie nicht gefunden.');
        }

        $guest = ! $this->app->auth()->check();
        if ($guest && ! $cat['is_public']) {
            $path = $this->app->request()->path();
            $this->app->redirect('/admin/login?redirect=' . rawurlencode($path));

            return;
        }

        $items = $this->app->mediaRepository()->listPublicVisible(400, 0, $cat['id'], $guest);
        $siteTitle = $this->app->settingsRepository()->get('site_title', 'BS Photo Galerie');
        $description = $this->app->settingsRepository()->get('site_description', '');

        $categories = $guest
            ? $this->app->categoryRepository()->listPublicOrdered()
            : $this->app->categoryRepository()->listAllOrdered();

        $this->render(
            'gallery/index',
            [
                'pageTitle' => $cat['name'] . ' – Galerie – ' . $siteTitle,
                'siteTitle' => $siteTitle,
                'siteDescription' => $description,
                'items' => $items,
                'categories' => $categories,
                'currentCategory' => $cat,
                'includeGalleryAssets' => true,
                'galleryRuntimeConfig' => $this->galleryRuntimeConfig(),
            ],
            'public/layout'
        );
    }
}
