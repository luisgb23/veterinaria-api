<?php

use App\Core\Env;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Middleware\Cors;

/**
 * Único punto de entrada de la API (front controller).
 * Todas las peticiones pasan por acá.
 */

require dirname(__DIR__) . '/bootstrap.php';

// Los errores de PHP no deben contaminar el JSON; se registran en el log/terminal.
ini_set('display_errors', '0');

Cors::handle();

$router = new Router();
require BASE_DIR . '/routes/api.php';

try {
    $router->dispatch(new Request());
} catch (Throwable $e) {
    error_log('[API] ' . get_class($e) . ': ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());

    $debug = Env::get('APP_DEBUG', 'false') === 'true';
    Response::error($debug ? $e->getMessage() : 'Error interno del servidor.', 500);
}
