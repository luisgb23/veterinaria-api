<?php

namespace App\Core;

/**
 * Encapsula los datos de la petición HTTP entrante.
 */
class Request
{
    private ?array $body = null;
    private ?array $user = null;

    public function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public function path(): string
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        // Permite desplegar la API en una subcarpeta (APP_BASE_PATH=/mi-api)
        $base = rtrim(Env::get('APP_BASE_PATH', ''), '/');
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
        }

        return '/' . trim($path, '/');
    }

    public function query(string $key, $default = null)
    {
        return $_GET[$key] ?? $default;
    }

    /**
     * Valor de una cabecera HTTP (por ejemplo "Authorization") o null si no vino.
     */
    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));

        if (isset($_SERVER[$key])) {
            return $_SERVER[$key];
        }

        // Algunas configuraciones de Apache la entregan con este nombre
        if (strcasecmp($name, 'Authorization') === 0 && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            return $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }

        return null;
    }

    /**
     * Token de la cabecera "Authorization: Bearer <token>", o null.
     */
    public function bearerToken(): ?string
    {
        $header = $this->header('Authorization');

        if ($header !== null && preg_match('/^Bearer\s+(\S+)$/i', trim($header), $matches)) {
            return $matches[1];
        }

        return null;
    }

    /** Usuario autenticado (lo fija AuthMiddleware): ['id' => int, 'usuario' => string, 'rol' => string]. */
    public function user(): ?array
    {
        return $this->user;
    }

    public function setUser(array $user): void
    {
        $this->user = $user;
    }

    /**
     * Archivo subido con multipart/form-data, o null si no vino ninguno en ese campo.
     */
    public function file(string $field): ?array
    {
        $file = $_FILES[$field] ?? null;

        if (!is_array($file) || is_array($file['name'] ?? null) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        return $file;
    }

    /**
     * Cuerpo JSON de la petición como array. Responde 400 si el JSON es inválido.
     */
    public function body(): array
    {
        if ($this->body === null) {
            $raw = file_get_contents('php://input');
            $decoded = json_decode($raw ?: '', true);

            if (trim((string) $raw) !== '' && (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded))) {
                Response::error('El cuerpo de la petición debe ser un JSON válido.', 400);
            }

            $this->body = is_array($decoded) ? $decoded : [];
        }

        return $this->body;
    }
}
