<?php

namespace App\Validators;

class EspecieValidator extends Validator
{
    /**
     * @return string[] lista de errores (vacía si los datos son válidos)
     */
    public static function validate(array $data): array
    {
        $errors = [];

        self::checkText($data, 'nombre', 100, true, $errors);

        return $errors;
    }

    /** Normaliza los datos ya validados. */
    public static function clean(array $data): array
    {
        return ['nombre' => trim($data['nombre'])];
    }
}
