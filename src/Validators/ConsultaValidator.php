<?php

namespace App\Validators;

class ConsultaValidator extends Validator
{
    /** Límite razonable para diagnóstico y tratamiento (la columna admite mucho más). */
    private const MAX_TEXTO = 20000;

    /**
     * @return string[] lista de errores (vacía si los datos son válidos)
     */
    public static function validate(array $data): array
    {
        $errors = [];

        self::checkDate($data, 'fecha', true, $errors);
        self::checkId($data, 'mascota_id', true, $errors);
        self::checkText($data, 'motivo', 255, true, $errors);
        self::checkText($data, 'diagnostico', self::MAX_TEXTO, false, $errors);
        self::checkText($data, 'tratamiento', self::MAX_TEXTO, false, $errors);

        return $errors;
    }

    /** Normaliza los datos ya validados. */
    public static function clean(array $data): array
    {
        return [
            'fecha'       => $data['fecha'],
            'mascota_id'  => (int) $data['mascota_id'],
            'motivo'      => trim($data['motivo']),
            'diagnostico' => self::textOrNull($data, 'diagnostico'),
            'tratamiento' => self::textOrNull($data, 'tratamiento'),
        ];
    }
}
