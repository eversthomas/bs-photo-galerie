<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Core;

/**
 * Einmalige Benachrichtigungen über die Session.
 */
final class Flash
{
    private const KEY = '_flash_message';

    public static function set(string $type, string $message): void
    {
        $_SESSION[self::KEY] = ['type' => $type, 'message' => $message];
    }

    /**
     * @return array{type:string,message:string}|null
     */
    public static function pull(): ?array
    {
        if (empty($_SESSION[self::KEY]) || ! is_array($_SESSION[self::KEY])) {
            return null;
        }

        $data = $_SESSION[self::KEY];
        unset($_SESSION[self::KEY]);

        $type = isset($data['type']) && is_string($data['type']) ? $data['type'] : 'info';
        $message = isset($data['message']) && is_string($data['message']) ? $data['message'] : '';

        if ($message === '') {
            return null;
        }

        return ['type' => $type, 'message' => $message];
    }
}
