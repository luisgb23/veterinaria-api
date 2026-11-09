<?php

namespace App\Validators;

class MascotaValidator extends Validator
{
    /**
     * @return string[] lista de errores (vacía si los datos son válidos)
     */
    public static function validate(array $data): array
    {
        $errors = [];

        self::checkText($data, 'nombre', 100, true, $errors);
        self::checkId($data, 'especie_id', true, $errors);
        self::checkId($data, 'propietario_id', true, $errors);
        self::checkDate($data, 'fecha_nacimiento', false, $errors);

        if (self::filled($data, 'fecha_nacimiento') && is_string($data['fecha_nacimiento'])
            && self::isDate($data['fecha_nacimiento']) && $data['fecha_nacimiento'] > date('Y-m-d')) {
            $errors[] = 'El campo "fecha_nacimiento" no puede ser una fecha futura.';
        }

        return $errors;
    }

    /** Normaliza los datos ya validados. */
    public static function clean(array $data): array
    {
        return [
            'nombre'           => trim($data['nombre']),
            'especie_id'       => (int) $data['especie_id'],
            'propietario_id'   => (int) $data['propietario_id'],
            'fecha_nacimiento' => self::textOrNull($data, 'fecha_nacimiento'),
        ];
    }
}
