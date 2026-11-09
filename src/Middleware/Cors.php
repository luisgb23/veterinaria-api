<?php

namespace App\Middleware;

use App\Core\Env;

/**
 * Cabeceras CORS y respuesta a las peticiones de preflight (OPTIONS).
 */
class Cors
{
    public static function handle(): void
    {
        $origin = Env::get('CORS_ALLOWED_ORIGIN', '*');

        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');

        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }
}
