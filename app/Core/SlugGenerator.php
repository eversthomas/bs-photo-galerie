<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Core;

/**
 * URL-freundliche Slugs (ASCII, Kleinbuchstaben, Bindestriche).
 */
final class SlugGenerator
{
    public static function slugify(string $text): string
    {
        $t = mb_strtolower(trim($text));
        $map = [
            'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss',
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'è' => 'e', 'é' => 'e',
            'ì' => 'i', 'í' => 'i', 'ò' => 'o', 'ó' => 'o', 'ù' => 'u', 'ú' => 'u',
        ];
        $t = strtr($t, $map);
        $conv = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $t);
        if (is_string($conv) && $conv !== '') {
            $t = $conv;
        }
        $t = strtolower($t);
        $t = preg_replace('/[^a-z0-9]+/', '-', $t) ?? '';
        $t = trim((string) $t, '-');

        return $t !== '' ? $t : 'eintrag';
    }
}
