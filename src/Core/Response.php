<?php

namespace App\Core;

/**
 * Respuestas JSON estandarizadas. Cada método envía la respuesta y termina la ejecución.
 */
class Response
{
    public static function json(int $status, array $payload): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function success(string $message, $data = null, int $status = 200, array $meta = []): void
    {
        $payload = ['success' => true, 'message' => $message];

        if ($data !== null) {
            $payload['data'] = $data;
        }

        if (!empty($meta)) {
            $payload['meta'] = $meta;
        }

        self::json($status, $payload);
    }

    public static function error(string $message, int $status = 400, $errors = null): void
    {
        $payload = ['success' => false, 'message' => $message];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        self::json($status, $payload);
    }
}
