<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Config;

/**
 * Erlaubte öffentliche Themes und Layout-Breiten (Einstellungen → Darstellung).
 */
final class PublicAppearance
{
    /** @var list<string> */
    public const THEMES = ['default', 'dark', 'warm'];

    /** @var list<string> */
    public const LAYOUT_WIDTHS = ['standard', 'wide'];

    public static function normalizeTheme(string $value): string
    {
        return in_array($value, self::THEMES, true) ? $value : 'default';
    }

    public static function normalizeLayoutWidth(string $value): string
    {
        return in_array($value, self::LAYOUT_WIDTHS, true) ? $value : 'standard';
    }
}
