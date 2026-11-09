<?php

namespace App\Core;

/**
 * Archivos adjuntos de las consultas, guardados en storage/consultas/ (fuera de public/,
 * así solo se pueden descargar a través de la API y con token).
 */
class Storage
{
    /** Extensiones permitidas y los tipos MIME reales que se aceptan para cada una. */
    public const PERMITIDOS = [
        'pdf'  => ['application/pdf'],
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'webp' => ['image/webp'],
        'gif'  => ['image/gif'],
    ];

    public static function dir(): string
    {
        $dir = BASE_DIR . '/storage/consultas';

        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException("No se pudo crear la carpeta {$dir}");
        }

        return $dir;
    }

    public static function maxBytes(): int
    {
        return max(1, (int) Env::get('UPLOAD_MAX_MB', '10')) * 1024 * 1024;
    }

    /**
     * Revisa un archivo de $_FILES. Devuelve el mensaje de error, o null si es válido.
     */
    public static function validar(array $file): ?string
    {
        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;

        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE || ($file['size'] ?? 0) > self::maxBytes()) {
            return 'El archivo supera el tamaño máximo permitido (' . (self::maxBytes() / 1024 / 1024) . ' MB).';
        }

        if ($error !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')) {
            return 'No se pudo recibir el archivo. Intentá de nuevo.';
        }

        $extension = self::extension((string) ($file['name'] ?? ''));

        if (!isset(self::PERMITIDOS[$extension])) {
            return 'Tipo de archivo no permitido. Se aceptan: ' . implode(', ', array_keys(self::PERMITIDOS)) . '.';
        }

        $mime = self::mime($file['tmp_name']);
        if ($mime !== null && !in_array($mime, self::PERMITIDOS[$extension], true)) {
            return 'El contenido del archivo no coincide con su extensión.';
        }

        return null;
    }

    /**
     * Guarda un archivo ya validado con un nombre único y devuelve ese nombre.
     * El nombre original del usuario no se usa en el disco (evita sobrescrituras y rutas maliciosas).
     */
    public static function guardar(array $file, string $prefijo): string
    {
        $nombre = $prefijo . '_' . bin2hex(random_bytes(8)) . '.' . self::extension($file['name']);

        if (!move_uploaded_file($file['tmp_name'], self::dir() . '/' . $nombre)) {
            throw new \RuntimeException('No se pudo guardar el archivo en storage/consultas.');
        }

        return $nombre;
    }

    /** Ruta absoluta de un archivo guardado, o null si no existe. Nunca sale de storage/consultas. */
    public static function ruta(string $nombre): ?string
    {
        $nombre = basename($nombre);
        $ruta = realpath(self::dir() . '/' . $nombre);

        if ($ruta === false || !is_file($ruta) || dirname($ruta) !== realpath(self::dir())) {
            return null;
        }

        return $ruta;
    }

    public static function borrar(string $nombre): void
    {
        $ruta = self::ruta($nombre);

        if ($ruta !== null) {
            unlink($ruta);
        }
    }

    /** Envía el archivo al navegador y termina la ejecución. */
    public static function enviar(string $ruta, string $nombre, bool $descargar): void
    {
        $tipo = self::mime($ruta) ?? 'application/octet-stream';
        $nombreSeguro = preg_replace('/[^A-Za-z0-9._-]/', '_', $nombre);

        header('Content-Type: ' . $tipo);
        header('Content-Length: ' . filesize($ruta));
        header('Content-Disposition: ' . ($descargar ? 'attachment' : 'inline') . '; filename="' . $nombreSeguro . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');

        readfile($ruta);
        exit;
    }

    private static function extension(string $nombre): string
    {
        return strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
    }

    private static function mime(string $ruta): ?string
    {
        if (!function_exists('finfo_open')) {
            return null; // sin la extensión fileinfo solo se controla la extensión
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $ruta) ?: null;
        finfo_close($finfo);

        return $mime;
    }
}
