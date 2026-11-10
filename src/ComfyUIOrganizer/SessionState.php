<?php

declare(strict_types=1);

namespace Mistralys\ComfyUIOrganizer;

/**
 * Typed access to `$_SESSION`: an absent or wrong-typed value yields the default.
 * The only code that touches `$_SESSION`.
 */
class SessionState
{
    public static function getString(string $key, string $default = '') : string
    {
        $value = $_SESSION[$key] ?? null;

        return is_string($value) ? $value : $default;
    }

    public static function getBool(string $key) : bool
    {
        return ($_SESSION[$key] ?? null) === true;
    }

    public static function set(string $key, string|bool $value) : void
    {
        $_SESSION[$key] = $value;
    }
}
