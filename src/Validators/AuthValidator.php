<?php

namespace App\Validators;

class AuthValidator extends Validator
{
    /**
     * @return string[] lista de errores (vacía si los datos son válidos)
     */
    public static function login(array $data): array
    {
        $errors = [];

        if (!isset($data['usuario']) || !is_string($data['usuario']) || trim($data['usuario']) === '') {
            $errors[] = 'El campo "usuario" es obligatorio.';
        }

        if (!isset($data['password']) || !is_string($data['password']) || $data['password'] === '') {
            $errors[] = 'El campo "password" es obligatorio.';
        }

        return $errors;
    }

    /**
     * Valida que venga el refresh token (para /auth/refresh y /auth/logout).
     *
     * @return string[]
     */
    public static function refreshToken(array $data): array
    {
        if (!isset($data['refresh_token']) || !is_string($data['refresh_token']) || $data['refresh_token'] === '') {
            return ['El campo "refresh_token" es obligatorio.'];
        }

        return [];
    }

    /**
     * Cambio de la contraseña propia: password_actual y password_nueva.
     *
     * @return string[]
     */
    public static function cambiarPassword(array $data): array
    {
        $errors = [];

        if (!isset($data['password_actual']) || !is_string($data['password_actual']) || $data['password_actual'] === '') {
            $errors[] = 'El campo "password_actual" es obligatorio.';
        }

        $error = self::passwordError($data['password_nueva'] ?? null, 'password_nueva');
        if ($error !== null) {
            $errors[] = $error;
        }

        return $errors;
    }

    /** Reglas de contraseña compartidas con UsuarioValidator y bin/crear-usuario.php. */
    public static function passwordError($password, string $field = 'password'): ?string
    {
        if (!is_string($password) || $password === '') {
            return "El campo \"{$field}\" es obligatorio y debe ser texto.";
        }

        if (strlen($password) < 8) {
            return "El campo \"{$field}\" debe tener al menos 8 caracteres.";
        }

        if (strlen($password) > 72) {
            // bcrypt solo considera los primeros 72 bytes
            return "El campo \"{$field}\" no puede superar los 72 caracteres.";
        }

        return null;
    }
}
