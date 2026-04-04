<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Services\Media;

/**
 * Liest EXIF-Metadaten und liefert JSON für die Spalte exif_json (ohne Binärdaten).
 */
final class ExifExtractor
{
    public function extractAsJson(string $absolutePath, string $mimeType): ?string
    {
        if (! in_array($mimeType, ['image/jpeg', 'image/tiff'], true)) {
            return null;
        }
        if (! function_exists('exif_read_data') || ! is_readable($absolutePath)) {
            return null;
        }

        $raw = @exif_read_data($absolutePath, 'ANY_TAG', true);
        if (! is_array($raw)) {
            return null;
        }

        $clean = $this->sanitize($raw);
        $json = json_encode(
            $clean,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );

        return $json === false ? null : $json;
    }

    private function sanitize(mixed $value): mixed
    {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                if (! is_string($k) && ! is_int($k)) {
                    continue;
                }
                $key = is_int($k) ? (string) $k : $k;
                if (is_string($key) && str_contains(strtolower($key), 'makernote')) {
                    continue;
                }
                $out[$key] = $this->sanitize($v);
            }

            return $out;
        }
        if (is_string($value)) {
            if (strlen($value) > 4096) {
                return null;
            }

            return $value;
        }
        if (is_scalar($value) || $value === null) {
            return $value;
        }

        return null;
    }
}
