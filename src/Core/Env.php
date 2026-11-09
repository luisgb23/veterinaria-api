<?php

namespace App\Core;

/**
 * Lector mínimo de archivos .env (CLAVE=valor, una por línea).
 */
class Env
{
    private static array $vars = [];

    public static function load(string $path): void
    {
        if (!is_file($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $line = trim($line); // también elimina el \r de Windows

            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            self::$vars[trim($key)] = trim(trim($value), "\"'");
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        if (array_key_exists($key, self::$vars)) {
            return self::$vars[$key];
        }

        $env = getenv($key);

        return $env !== false ? $env : $default;
    }
}
