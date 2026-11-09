<?php

namespace App\Validators;

class CuotaValidator extends Validator
{
    /**
     * @return string[] lista de errores (vacía si los datos son válidos)
     */
    public static function validate(array $data): array
    {
        $errors = [];

        self::checkDate($data, 'fecha', true, $errors);
        self::checkId($data, 'mascota_id', true, $errors);
        self::checkText($data, 'observacion', 255, false, $errors);

        // La columna es BIGINT: el valor va en pesos enteros, sin decimales.
        if (!isset($data['valor']) || filter_var($data['valor'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) === false) {
            $errors[] = 'El campo "valor" es obligatorio y debe ser un entero mayor o igual a 0 (sin decimales).';
        }

        return $errors;
    }

    /** Normaliza los datos ya validados. */
    public static function clean(array $data): array
    {
        return [
            'fecha'       => $data['fecha'],
            'mascota_id'  => (int) $data['mascota_id'],
            'valor'       => (int) $data['valor'],
            'observacion' => self::textOrNull($data, 'observacion'),
        ];
    }
}
