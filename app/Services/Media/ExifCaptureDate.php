<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Services\Media;

/**
 * Extrahiert ein SQL-DATETIME (Aufnahmezeit) aus EXIF-Daten (DateTimeOriginal, sonst IFD0.DateTime).
 */
final class ExifCaptureDate
{
    /**
     * @param array<string, mixed> $exifRoot Wurzel-Array von exif_read_data(..., true)
     */
    public static function fromExifRootArray(array $exifRoot): ?string
    {
        $exifSection = $exifRoot['EXIF'] ?? null;
        $ifd0 = $exifRoot['IFD0'] ?? null;

        $raw = null;
        if (is_array($exifSection) && isset($exifSection['DateTimeOriginal']) && is_string($exifSection['DateTimeOriginal'])) {
            $raw = $exifSection['DateTimeOriginal'];
        } elseif (is_array($ifd0) && isset($ifd0['DateTime']) && is_string($ifd0['DateTime'])) {
            $raw = $ifd0['DateTime'];
        }

        return is_string($raw) ? self::normalizeExifDatetimeString(trim($raw)) : null;
    }

    public static function fromExifJsonString(?string $json): ?string
    {
        if ($json === null || $json === '') {
            return null;
        }

        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (! is_array($data)) {
            return null;
        }

        return self::fromExifRootArray($data);
    }

    private static function normalizeExifDatetimeString(string $raw): ?string
    {
        if ($raw === '') {
            return null;
        }

        if (preg_match('/^(\d{4}):(\d{2}):(\d{2}) (\d{2}):(\d{2}):(\d{2})$/', $raw, $m)) {
            return "{$m[1]}-{$m[2]}-{$m[3]} {$m[4]}:{$m[5]}:{$m[6]}";
        }

        return null;
    }
}
