<?php

use App\Core\Env;

/**
 * Inicialización común: constante BASE_DIR, autoloader PSR-4, variables de entorno
 * y zona horaria. Lo usan tanto public/index.php como los scripts de la carpeta bin/.
 */

define('BASE_DIR', __DIR__);

// App\Core\Router => src/Core/Router.php
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';

    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = BASE_DIR . '/src/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

// Librerías de Composer (solo dompdf, para el PDF de las consultas). El resto de la API funciona sin ellas.
if (is_file(BASE_DIR . '/vendor/autoload.php')) {
    require BASE_DIR . '/vendor/autoload.php';
}

Env::load(BASE_DIR . '/.env');

// Las fechas de creación/modificación se guardan en hora local, como en el sistema anterior.
date_default_timezone_set(Env::get('APP_TIMEZONE', 'America/Montevideo'));
