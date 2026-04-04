<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Services\Media;

/**
 * Normalisiert PHP-$_FILES-Einträge für Mehrfach-Uploads (images[]).
 */
final class UploadedFiles
{
    /**
     * @return list<array{name:string,tmp_name:string,error:int,size:int,type:string}>
     */
    public static function normalizeList(mixed $field): array
    {
        if (! is_array($field) || ! isset($field['name']) || ! is_array($field['name'])) {
            return [];
        }

        $out = [];
        foreach ($field['name'] as $index => $name) {
            $out[] = [
                'name' => is_string($name) ? $name : '',
                'tmp_name' => is_string($field['tmp_name'][$index] ?? null) ? $field['tmp_name'][$index] : '',
                'error' => (int) ($field['error'][$index] ?? UPLOAD_ERR_NO_FILE),
                'size' => (int) ($field['size'][$index] ?? 0),
                'type' => is_string($field['type'][$index] ?? null) ? $field['type'][$index] : '',
            ];
        }

        return $out;
    }
}
