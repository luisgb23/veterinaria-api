<?php

namespace App\Validators;

class UsuarioValidator extends Validator
{
    public const ROLES = ['user', 'admin'];

    /**
     * Al crear la contraseña es obligatoria; al actualizar es opcional (solo se cambia si viene).
     *
     * @return string[] lista de errores (vacía si los datos son válidos)
     */
    public static function validate(array $data, bool $creando): array
    {
        $errors = [];

        self::checkText($data, 'nombre', 100, true, $errors);

        if ($creando) {
            $usuario = $data['usuario'] ?? null;

            if (!is_string($usuario) || !preg_match('/^[A-Za-z0-9._-]{3,100}$/', trim($usuario))) {
                $errors[] = 'El campo "usuario" es obligatorio: de 3 a 100 caracteres, solo letras, números, punto, guion y guion bajo.';
            }
        }

        if ($creando || self::filled($data, 'password')) {
            $error = AuthValidator::passwordError($data['password'] ?? null);
            if ($error !== null) {
                $errors[] = $error;
            }
        }

        if (isset($data['rol']) && !in_array($data['rol'], self::ROLES, true)) {
            $errors[] = 'El campo "rol" debe ser "user" o "admin".';
        }

        return $errors;
    }

    /** Normaliza los datos ya validados. "password" queda en null si no hay que cambiarla. */
    public static function clean(array $data): array
    {
        return [
            'nombre'   => trim($data['nombre']),
            'usuario'  => isset($data['usuario']) && is_string($data['usuario']) ? strtolower(trim($data['usuario'])) : null,
            'password' => self::filled($data, 'password') ? $data['password'] : null,
            'rol'      => $data['rol'] ?? 'user',
        ];
    }
}
