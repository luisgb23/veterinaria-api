<?php

namespace App\Reports;

use App\Core\Response;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * PDF con el resumen de una consulta (historia clínica). Usa dompdf (composer install).
 */
class ConsultaPdf
{
    /** Envía el PDF al navegador y termina la ejecución. */
    public static function enviar(array $consulta, bool $descargar): void
    {
        if (!class_exists(Dompdf::class)) {
            Response::error('Falta la librería dompdf. Ejecutá "composer install" en la carpeta de la API.', 500);
        }

        $options = new Options();
        $options->set('isRemoteEnabled', false); // el logo va incrustado, no hace falta descargar nada
        $options->set('defaultFont', 'DejaVu Sans'); // soporta tildes y ñ

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(self::html($consulta), 'UTF-8');
        $dompdf->setPaper('letter');
        $dompdf->render();

        $mascota = preg_replace('/[^A-Za-z0-9_-]/', '_', (string) $consulta['mascota_nombre']);
        $archivo = "consulta_{$consulta['id']}_{$mascota}.pdf";

        header('Content-Type: application/pdf');
        header('Content-Disposition: ' . ($descargar ? 'attachment' : 'inline') . '; filename="' . $archivo . '"');
        header('Cache-Control: private, no-store');

        echo $dompdf->output();
        exit;
    }

    public static function html(array $c): string
    {
        $e = fn ($valor) => htmlspecialchars((string) ($valor ?? ''), ENT_QUOTES, 'UTF-8');
        $texto = fn ($valor) => $valor !== null && $valor !== '' ? nl2br($e($valor)) : '<em>Sin datos</em>';

        $fecha = $c['fecha'] ? date('d/m/Y', strtotime($c['fecha'])) : '';
        $propietario = trim(($c['propietario_nombre'] ?? '') . ' ' . ($c['propietario_apellido'] ?? ''));
        $emitido = date('d/m/Y H:i');

        $logo = '';
        $logoPath = BASE_DIR . '/resources/img/logo_reporte.png';
        if (is_file($logoPath)) {
            $logo = '<img class="logo" src="data:image/png;base64,' . base64_encode(file_get_contents($logoPath)) . '">';
        }

        $paciente = $e($c['mascota_nombre']);
        if ($c['especie_nombre']) {
            $paciente .= ' (' . $e($c['especie_nombre']) . ')';
        }
        if ($c['mascota_edad']) {
            $paciente .= ' — ' . $e($c['mascota_edad']);
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; color: #222; }
    .encabezado { border-bottom: 2px solid #2b6c5f; padding-bottom: 8px; margin-bottom: 16px; }
    .logo { height: 60px; float: right; }
    h1 { font-size: 20px; margin: 0; color: #2b6c5f; }
    h2 { font-size: 14px; margin: 4px 0 0; font-weight: normal; }
    h3 { font-size: 13px; margin: 16px 0 4px; color: #2b6c5f; }
    table.datos td { padding: 2px 12px 2px 0; vertical-align: top; }
    .pie { position: fixed; bottom: 0; font-size: 9px; color: #777; }
</style>
</head>
<body>
    <div class="encabezado">
        {$logo}
        <h1>Veterinaria Itapebí</h1>
        <h2>Resumen de historia clínica</h2>
        <div style="clear: both"></div>
    </div>

    <table class="datos">
        <tr><td><strong>Fecha de consulta:</strong></td><td>{$fecha}</td></tr>
        <tr><td><strong>Paciente:</strong></td><td>{$paciente}</td></tr>
        <tr><td><strong>Propietario:</strong></td><td>{$e($propietario)}</td></tr>
        <tr><td><strong>Teléfono:</strong></td><td>{$e($c['propietario_telefono'])}</td></tr>
    </table>

    <h3>Motivo de consulta</h3>
    <p>{$texto($c['motivo'])}</p>

    <h3>Diagnóstico</h3>
    <p>{$texto($c['diagnostico'])}</p>

    <h3>Tratamiento</h3>
    <p>{$texto($c['tratamiento'])}</p>

    <div class="pie">Consulta N.º {$e($c['id'])} — emitido el {$emitido}</div>
</body>
</html>
HTML;
    }
}
