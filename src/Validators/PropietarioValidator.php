<?php

namespace App\Validators;

class PropietarioValidator extends Validator
{
    /**
     * @return string[] lista de errores (vacía si los datos son válidos)
     */
    public static function validate(array $data): array
    {
        $errors = [];

        self::checkText($data, 'nombre', 100, true, $errors);
        self::checkText($data, 'apellido', 100, true, $errors);
        self::checkText($data, 'direccion', 255, false, $errors);

        // La columna es VARCHAR(10): alcanza para celulares (09XXXXXXX) y fijos de Uruguay.
        if (self::filled($data, 'telefono')) {
            $telefono = is_scalar($data['telefono']) ? preg_replace('/[\s-]/', '', (string) $data['telefono']) : '';

            if (!preg_match('/^\+?\d{6,10}$/', $telefono) || strlen($telefono) > 10) {
                $errors[] = 'El campo "telefono" debe tener entre 6 y 10 dígitos (se permiten espacios y guiones).';
            }
        }

        if (self::filled($data, 'email')) {
            if (!is_string($data['email']) || !filter_var(trim($data['email']), FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'El campo "email" debe ser un email válido.';
            } elseif (strlen(trim($data['email'])) > 100) {
                $errors[] = 'El campo "email" no puede superar los 100 caracteres.';
            }
        }

        return $errors;
    }

    /** Normaliza los datos ya validados. */
    public static function clean(array $data): array
    {
        $telefono = self::filled($data, 'telefono') ? preg_replace('/[\s-]/', '', (string) $data['telefono']) : null;
        $email = self::textOrNull($data, 'email');

        return [
            'nombre'    => trim($data['nombre']),
            'apellido'  => trim($data['apellido']),
            'telefono'  => $telefono,
            'direccion' => self::textOrNull($data, 'direccion'),
            'email'     => $email !== null ? strtolower($email) : null,
        ];
    }
}
