<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Controllers;

use BSPhotoGalerie\Core\HttpContext;
use BSPhotoGalerie\Models\MediaRepository;
use BSPhotoGalerie\Services\AuthService;

/**
 * Liefert JPEG-Vorschaubilder aus storage/thumbnails (öffentlich, keine Authentifizierung).
 */
final class ThumbController
{
    public function __construct(
        private AuthService $auth,
        private MediaRepository $media,
        private HttpContext $http
    ) {
    }

    public function show(string $id): void
    {
        $mediaId = (int) $id;
        if ($mediaId < 1) {
            http_response_code(404);

            return;
        }

        if (! $this->auth->check() && ! $this->media->isPublicGuestAccessible($mediaId)) {
            http_response_code(404);

            return;
        }

        $path = $this->http->root() . '/storage/thumbnails/' . $mediaId . '.jpg';
        if (! is_file($path) || ! is_readable($path)) {
            http_response_code(404);

            return;
        }

        header('Content-Type: image/jpeg');
        header('Cache-Control: public, max-age=604800');
        $size = filesize($path);
        if ($size !== false) {
            header('Content-Length: ' . (string) $size);
        }
        readfile($path);
        exit;
    }
}
