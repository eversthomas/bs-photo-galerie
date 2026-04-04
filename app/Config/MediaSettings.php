<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Config;

/**
 * Upload- und Bildparameter mit sinnvollen Standardwerten.
 */
final class MediaSettings
{
    /** @param array<string, mixed> $config Gesamte App-Konfiguration */
    public function __construct(
        private array $config
    ) {
    }

    /**
     * @param array<string, mixed> $appConfig
     */
    public static function fromAppConfig(array $appConfig): self
    {
        return new self($appConfig);
    }

    public function maxUploadBytes(): int
    {
        $m = $this->config['media'] ?? null;
        if (is_array($m) && isset($m['max_upload_bytes']) && is_numeric($m['max_upload_bytes'])) {
            $v = (int) $m['max_upload_bytes'];

            return max(1024, min($v, 2_147_483_647));
        }

        return 20 * 1024 * 1024;
    }

    public function thumbnailMaxWidth(): int
    {
        $m = $this->config['media'] ?? null;
        if (is_array($m) && isset($m['thumbnail_width']) && is_numeric($m['thumbnail_width'])) {
            $v = (int) $m['thumbnail_width'];

            return max(64, min($v, 4096));
        }

        return 480;
    }

    /**
     * Obere Grenze für Breite × Höhe (Decompression/DoS-Schutz). Standard 40 Megapixel.
     */
    public function maxImagePixels(): int
    {
        $m = $this->config['media'] ?? null;
        if (is_array($m) && isset($m['max_image_pixels']) && is_numeric($m['max_image_pixels'])) {
            $v = (int) $m['max_image_pixels'];

            return max(1_000_000, min($v, 200_000_000));
        }

        return 40_000_000;
    }

    /**
     * @return list<string> Erlaubte MIME-Typen (Content-Type).
     */
    public function allowedMimeTypes(): array
    {
        $m = $this->config['media'] ?? null;
        if (is_array($m) && isset($m['allowed_mime']) && is_array($m['allowed_mime'])) {
            $out = [];
            foreach ($m['allowed_mime'] as $t) {
                if (is_string($t) && $t !== '') {
                    $out[] = strtolower($t);
                }
            }

            return $out !== [] ? array_values(array_unique($out)) : self::defaultMimes();
        }

        return self::defaultMimes();
    }

    /**
     * @return list<string>
     */
    private static function defaultMimes(): array
    {
        return [
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/gif',
        ];
    }
}
