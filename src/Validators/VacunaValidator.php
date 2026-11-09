<?php

namespace App\Validators;

class VacunaValidator extends Validator
{
    /**
     * @return string[] lista de errores (vacía si los datos son válidos)
     */
    public static function validate(array $data): array
    {
        $errors = [];

        self::checkDate($data, 'fecha_ingreso', true, $errors);
        self::checkId($data, 'mascota_id', true, $errors);
        self::checkDate($data, 'fecha_vencimiento', true, $errors);

        if (empty($errors) && $data['fecha_vencimiento'] < $data['fecha_ingreso']) {
            $errors[] = 'El campo "fecha_vencimiento" no puede ser anterior a "fecha_ingreso".';
        }

        return $errors;
    }

    /** Normaliza los datos ya validados. */
    public static function clean(array $data): array
    {
        return [
            'fecha_ingreso'     => $data['fecha_ingreso'],
            'mascota_id'        => (int) $data['mascota_id'],
            'fecha_vencimiento' => $data['fecha_vencimiento'],
        ];
    }
}
