<?php

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;

/**
 * Restringe la ruta a ciertos roles. Debe ir DESPUÉS de AuthMiddleware.
 *
 * Uso: [RoleMiddleware::class, 'admin']  o  [RoleMiddleware::class, 'admin', 'editor']
 */
class RoleMiddleware
{
    private array $roles;

    public function __construct(string ...$roles)
    {
        $this->roles = $roles;
    }

    public function handle(Request $request): void
    {
        $user = $request->user();

        if ($user === null) {
            Response::error('Autenticación requerida.', 401);
        }

        if (!in_array($user['rol'], $this->roles, true)) {
            Response::error('No tenés permisos para realizar esta acción.', 403);
        }
    }
}
