<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Controllers;

use BSPhotoGalerie\Core\HttpContext;
use BSPhotoGalerie\Models\CategoryRepository;
use BSPhotoGalerie\Models\MediaRepository;
use BSPhotoGalerie\Models\SettingsRepository;
use BSPhotoGalerie\Services\AuthService;

/**
 * Öffentliche Bildgalerie (Lightbox, Kategoriefilter).
 */
final class GalleryController extends BaseController
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

    /**
     * @return array{slideshowEnabled: bool, slideshowInterval: int, musicEnabled: bool, musicUrls: list<string>}
     */
    private function galleryRuntimeConfig(): array
    {
        $interval = (int) $this->settings->get('slideshow_interval_seconds', '5');
        $interval = max(3, min(120, $interval));

        $rawLines = self::parseMusicPlaylistLines($this->settings->get('music_playlist', ''));
        $musicUrls = [];
        foreach ($rawLines as $line) {
            $musicUrls[] = self::resolvePublicMediaUrl($this->http, $line);
        }

        return [
            'slideshowEnabled' => $this->settings->get('slideshow_enabled', '0') === '1',
            'slideshowInterval' => $interval,
            'musicEnabled' => $this->settings->get('background_music_enabled', '0') === '1',
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

    private static function resolvePublicMediaUrl(HttpContext $http, string $line): string
    {
        if (str_starts_with($line, '/')) {
            return $http->publicUrl($line);
        }

        return $line;
    }

    /**
     * @return 'manual'|'exif_date'|'upload_date'
     */
    private function publicGallerySort(): string
    {
        $raw = strtolower(trim((string) ($this->http->request()->query('sort', '') ?? '')));

        return match ($raw) {
            'exif', 'exif_date' => 'exif_date',
            'upload', 'uploaded', 'created' => 'upload_date',
            default => 'manual',
        };
    }

    public function index(): void
    {
        $guest = ! $this->auth->check();
        $sort = $this->publicGallerySort();
        $items = $this->media->listPublicVisible(400, 0, null, $guest, $sort);
        $siteTitle = $this->settings->get('site_title', 'BS Photo Galerie');
        $description = $this->settings->get('site_description', '');
        $cats = $guest
            ? $this->categories->listPublicOrdered()
            : $this->categories->listAllOrdered();

        $this->render(
            'gallery/index',
            [
                'pageTitle' => 'Galerie – ' . $siteTitle,
                'siteTitle' => $siteTitle,
                'siteDescription' => $description,
                'items' => $items,
                'categories' => $cats,
                'currentCategory' => null,
                'gallerySort' => match ($sort) {
                    'exif_date' => 'exif',
                    'upload_date' => 'uploaded',
                    default => 'manual',
                },
                'includeGalleryAssets' => true,
                'galleryRuntimeConfig' => $this->galleryRuntimeConfig(),
            ],
            'public/layout'
        );
    }

    public function category(string $slug): void
    {
        $cat = $this->categories->findBySlug($slug);
        if ($cat === null) {
            $this->http->abort(404, 'Kategorie nicht gefunden.');
        }

        $guest = ! $this->auth->check();
        if ($guest && ! $cat['is_public']) {
            $path = $this->http->request()->path();
            $this->http->redirect('/admin/login?redirect=' . rawurlencode($path));

            return;
        }

        $sort = $this->publicGallerySort();
        $items = $this->media->listPublicVisible(400, 0, $cat['id'], $guest, $sort);
        $siteTitle = $this->settings->get('site_title', 'BS Photo Galerie');
        $description = $this->settings->get('site_description', '');

        $cats = $guest
            ? $this->categories->listPublicOrdered()
            : $this->categories->listAllOrdered();

        $this->render(
            'gallery/index',
            [
                'pageTitle' => $cat['name'] . ' – Galerie – ' . $siteTitle,
                'siteTitle' => $siteTitle,
                'siteDescription' => $description,
                'items' => $items,
                'categories' => $cats,
                'currentCategory' => $cat,
                'gallerySort' => match ($sort) {
                    'exif_date' => 'exif',
                    'upload_date' => 'uploaded',
                    default => 'manual',
                },
                'includeGalleryAssets' => true,
                'galleryRuntimeConfig' => $this->galleryRuntimeConfig(),
            ],
            'public/layout'
        );
    }
}
