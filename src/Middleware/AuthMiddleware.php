<?php

namespace App\Middleware;

use App\Core\Jwt;
use App\Core\Request;
use App\Core\Response;

/**
 * Exige un access token JWT válido en "Authorization: Bearer <token>".
 * Si es válido, deja al usuario disponible en $request->user().
 */
class AuthMiddleware
{
    public function handle(Request $request): void
    {
        $token = $request->bearerToken();

        if ($token === null) {
            $this->unauthorized('Token de acceso no proporcionado.');
        }

        $payload = Jwt::decode($token);

        if ($payload === null || !isset($payload['sub'])) {
            $this->unauthorized('Token inválido o expirado.');
        }

        $request->setUser([
            'id'      => (int) $payload['sub'],
            'usuario' => $payload['usuario'] ?? null,
            'rol'     => $payload['rol'] ?? 'user',
        ]);
    }

    private function unauthorized(string $message): void
    {
        header('WWW-Authenticate: Bearer');
        Response::error($message, 401);
    }
}
