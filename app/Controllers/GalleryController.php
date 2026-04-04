<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Controllers;

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

        return [
            'slideshowEnabled' => $settings->get('slideshow_enabled', '0') === '1',
            'slideshowInterval' => $interval,
            'musicEnabled' => $settings->get('background_music_enabled', '0') === '1',
            'musicUrls' => self::parseMusicPlaylistLines($settings->get('music_playlist', '')),
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

    public function index(): void
    {
        $items = $this->app->mediaRepository()->listPublicVisible(400, 0, null);
        $siteTitle = $this->app->settingsRepository()->get('site_title', 'BS Photo Galerie');
        $description = $this->app->settingsRepository()->get('site_description', '');
        $categories = $this->app->categoryRepository()->listAllOrdered();

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

        $items = $this->app->mediaRepository()->listPublicVisible(400, 0, $cat['id']);
        $siteTitle = $this->app->settingsRepository()->get('site_title', 'BS Photo Galerie');
        $description = $this->app->settingsRepository()->get('site_description', '');

        $this->render(
            'gallery/index',
            [
                'pageTitle' => $cat['name'] . ' – Galerie – ' . $siteTitle,
                'siteTitle' => $siteTitle,
                'siteDescription' => $description,
                'items' => $items,
                'categories' => $this->app->categoryRepository()->listAllOrdered(),
                'currentCategory' => $cat,
                'includeGalleryAssets' => true,
                'galleryRuntimeConfig' => $this->galleryRuntimeConfig(),
            ],
            'public/layout'
        );
    }
}
