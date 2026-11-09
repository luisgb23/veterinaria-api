<?php

namespace App\Core;

/**
 * Router simple. Los parámetros se declaran como {id} y solo aceptan dígitos.
 *
 * El handler puede ser [Controlador::class, 'metodo'] o una función anónima.
 * El tercer argumento es una lista opcional de middleware: cada entrada es
 * Clase::class o [Clase::class, 'arg1', 'arg2'] (los argumentos van al constructor).
 */
class Router
{
    private array $routes = [];

    public function add(string $method, string $pattern, $handler, array $middleware = []): void
    {
        $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>\d+)', $pattern) . '$#';
        $this->routes[] = [strtoupper($method), $regex, $handler, $middleware];
    }

    public function get(string $pattern, $handler, array $middleware = []): void
    {
        $this->add('GET', $pattern, $handler, $middleware);
    }

    public function post(string $pattern, $handler, array $middleware = []): void
    {
        $this->add('POST', $pattern, $handler, $middleware);
    }

    public function put(string $pattern, $handler, array $middleware = []): void
    {
        $this->add('PUT', $pattern, $handler, $middleware);
    }

    public function delete(string $pattern, $handler, array $middleware = []): void
    {
        $this->add('DELETE', $pattern, $handler, $middleware);
    }

    public function dispatch(Request $request): void
    {
        $path = $request->path();
        $method = $request->method();
        $allowed = [];

        foreach ($this->routes as [$routeMethod, $regex, $handler, $middleware]) {
            if (!preg_match($regex, $path, $matches)) {
                continue;
            }

            if ($routeMethod !== $method) {
                $allowed[] = $routeMethod;
                continue;
            }

            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

            // Los middleware corren en orden y cortan la petición si responden con error.
            foreach ($middleware as $entry) {
                $args = [];

                if (is_array($entry)) {
                    $class = array_shift($entry);
                    $args = $entry;
                } else {
                    $class = $entry;
                }

                (new $class(...$args))->handle($request);
            }

            if (is_array($handler)) {
                [$class, $action] = $handler;
                (new $class())->$action($request, $params);
            } else {
                $handler($request, $params);
            }

            return;
        }

        if (!empty($allowed)) {
            header('Allow: ' . implode(', ', array_unique($allowed)));
            Response::error('Método no permitido.', 405);
        }

        Response::error('Ruta no encontrada.', 404);
    }
}
