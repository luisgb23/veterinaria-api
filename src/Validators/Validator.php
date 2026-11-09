<?php

namespace App\Validators;

/**
 * Base común para los validadores.
 *
 * Los métodos "check..." agregan un mensaje a $errors si el campo no es válido.
 */
abstract class Validator
{
    /** Longitud en caracteres (usa mbstring si está habilitada). */
    protected static function length(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
    }

    /** true si el campo vino con algún valor (ni ausente, ni null, ni texto vacío). */
    protected static function filled(array $data, string $field): bool
    {
        return isset($data[$field]) && !(is_string($data[$field]) && trim($data[$field]) === '');
    }

    /** Texto obligatorio (o opcional) con largo máximo. */
    protected static function checkText(array $data, string $field, int $max, bool $required, array &$errors): void
    {
        if (!self::filled($data, $field)) {
            if ($required) {
                $errors[] = "El campo \"{$field}\" es obligatorio.";
            }
            return;
        }

        if (!is_string($data[$field])) {
            $errors[] = "El campo \"{$field}\" debe ser texto.";
        } elseif (self::length(trim($data[$field])) > $max) {
            $errors[] = "El campo \"{$field}\" no puede superar los {$max} caracteres.";
        }
    }

    /** Fecha con formato AAAA-MM-DD y que exista en el calendario. */
    protected static function checkDate(array $data, string $field, bool $required, array &$errors): void
    {
        if (!self::filled($data, $field)) {
            if ($required) {
                $errors[] = "El campo \"{$field}\" es obligatorio (formato AAAA-MM-DD).";
            }
            return;
        }

        if (!is_string($data[$field]) || !self::isDate($data[$field])) {
            $errors[] = "El campo \"{$field}\" debe ser una fecha válida con formato AAAA-MM-DD.";
        }
    }

    /** Entero positivo (para ids de otras tablas). */
    protected static function checkId(array $data, string $field, bool $required, array &$errors): void
    {
        if (!self::filled($data, $field)) {
            if ($required) {
                $errors[] = "El campo \"{$field}\" es obligatorio.";
            }
            return;
        }

        if (filter_var($data[$field], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            $errors[] = "El campo \"{$field}\" debe ser un entero positivo.";
        }
    }

    protected static function isDate(string $value): bool
    {
        $date = \DateTime::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }

    /** Texto recortado, o null si vino vacío o no vino. */
    protected static function textOrNull(array $data, string $field): ?string
    {
        if (!isset($data[$field]) || !is_string($data[$field])) {
            return null;
        }

        $value = trim($data[$field]);

        return $value === '' ? null : $value;
    }
}
