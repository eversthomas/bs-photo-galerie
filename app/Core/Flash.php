<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Core;

/**
 * Einmalige Benachrichtigungen über die Session (mehrere Einträge pro Request-Kette möglich).
 */
final class Flash
{
    private const KEY = '_flash_queue';

    /**
     * Leert die Warteschlange und setzt genau eine Meldung (bisheriges Standardverhalten).
     */
    public static function set(string $type, string $message): void
    {
        $_SESSION[self::KEY] = [];
        self::add($type, $message);
    }

    /**
     * Hängt eine Meldung an (z. B. Erfolg + gesonderter Hinweis).
     */
    public static function add(string $type, string $message): void
    {
        if ($message === '') {
            return;
        }
        if (! isset($_SESSION[self::KEY]) || ! is_array($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = [];
        }
        $_SESSION[self::KEY][] = ['type' => $type, 'message' => $message];
    }

    /**
     * @return list<array{type:string, message:string}>
     */
    public static function pull(): array
    {
        if (empty($_SESSION[self::KEY]) || ! is_array($_SESSION[self::KEY])) {
            return [];
        }

        $items = $_SESSION[self::KEY];
        unset($_SESSION[self::KEY]);
        $out = [];
        foreach ($items as $row) {
            if (! is_array($row)) {
                continue;
            }
            $type = isset($row['type']) && is_string($row['type']) ? $row['type'] : 'info';
            $message = isset($row['message']) && is_string($row['message']) ? $row['message'] : '';
            if ($message !== '') {
                $out[] = ['type' => $type, 'message' => $message];
            }
        }

        return $out;
    }
}
