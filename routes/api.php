<?php

use App\Controllers\AuthController;
use App\Controllers\ConsultaController;
use App\Controllers\CuotaController;
use App\Controllers\EspecieController;
use App\Controllers\MascotaController;
use App\Controllers\PropietarioController;
use App\Controllers\UsuarioController;
use App\Controllers\VacunaController;
use App\Core\Response;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;

/** @var \App\Core\Router $router */

// Atajos de middleware
$auth = [AuthMiddleware::class];                                    // cualquier usuario autenticado
$admin = [AuthMiddleware::class, [RoleMiddleware::class, 'admin']]; // solo administradores

// Raíz: documentación rápida de la API (pública, no muestra datos)
$router->get('/', function () {
    Response::success('API de la Veterinaria Itapebí (PHP puro + MySQL + JWT). Todo requiere token salvo /auth/login, /auth/refresh y /auth/logout.', [
        'auth' => [
            'POST /auth/login',
            'POST /auth/refresh',
            'POST /auth/logout',
            'GET /auth/me',
            'PUT /auth/password',
        ],
        'especies'     => ['GET /especies', 'GET /especies/{id}', 'POST /especies', 'PUT /especies/{id}', 'DELETE /especies/{id} (admin)'],
        'propietarios' => ['GET /propietarios?page=1&per_page=20&search=texto', 'GET /propietarios/{id}', 'GET /propietarios/{id}/mascotas', 'POST /propietarios', 'PUT /propietarios/{id}', 'DELETE /propietarios/{id} (admin)'],
        'mascotas'     => ['GET /mascotas?especie_id=&propietario_id=&search=', 'GET /mascotas/{id}', 'POST /mascotas', 'PUT /mascotas/{id}', 'DELETE /mascotas/{id} (admin)'],
        'consultas'    => [
            'GET /consultas?mascota_id=&desde=&hasta=&search=',
            'GET /consultas/{id}',
            'POST /consultas',
            'PUT /consultas/{id}',
            'DELETE /consultas/{id} (admin)',
            'POST /consultas/{id}/archivos/{1-3} (multipart, campo "archivo")',
            'GET /consultas/{id}/archivos/{1-3}',
            'DELETE /consultas/{id}/archivos/{1-3}',
            'GET /consultas/{id}/pdf',
        ],
        'vacunas'      => ['GET /vacunas?mascota_id=', 'GET /vacunas/vencimientos?filtro=todas|vencidas|proximas&dias=30&historico=0', 'GET /vacunas/{id}', 'POST /vacunas', 'PUT /vacunas/{id}', 'DELETE /vacunas/{id} (admin)'],
        'cuotas'       => ['GET /cuotas?mascota_id=&desde=&hasta=&search=', 'GET /cuotas/{id}', 'POST /cuotas', 'PUT /cuotas/{id}', 'DELETE /cuotas/{id} (admin)'],
        'usuarios'     => ['GET /usuarios', 'GET /usuarios/{id}', 'POST /usuarios', 'PUT /usuarios/{id}', 'DELETE /usuarios/{id} (todo solo admin)'],
    ]);
});

// Autenticación (no hay registro público: los usuarios los crea un admin)
$router->post('/auth/login', [AuthController::class, 'login']);
$router->post('/auth/refresh', [AuthController::class, 'refresh']);
$router->post('/auth/logout', [AuthController::class, 'logout']);
$router->get('/auth/me', [AuthController::class, 'me'], $auth);
$router->put('/auth/password', [AuthController::class, 'cambiarPassword'], $auth);

// Son datos clínicos y personales: todo requiere token. Los DELETE (bajas) solo admin.

// Especies
$router->get('/especies', [EspecieController::class, 'index'], $auth);
$router->get('/especies/{id}', [EspecieController::class, 'show'], $auth);
$router->post('/especies', [EspecieController::class, 'store'], $auth);
$router->put('/especies/{id}', [EspecieController::class, 'update'], $auth);
$router->delete('/especies/{id}', [EspecieController::class, 'destroy'], $admin);

// Propietarios
$router->get('/propietarios', [PropietarioController::class, 'index'], $auth);
$router->get('/propietarios/{id}', [PropietarioController::class, 'show'], $auth);
$router->get('/propietarios/{id}/mascotas', [PropietarioController::class, 'mascotas'], $auth);
$router->post('/propietarios', [PropietarioController::class, 'store'], $auth);
$router->put('/propietarios/{id}', [PropietarioController::class, 'update'], $auth);
$router->delete('/propietarios/{id}', [PropietarioController::class, 'destroy'], $admin);

// Mascotas
$router->get('/mascotas', [MascotaController::class, 'index'], $auth);
$router->get('/mascotas/{id}', [MascotaController::class, 'show'], $auth);
$router->post('/mascotas', [MascotaController::class, 'store'], $auth);
$router->put('/mascotas/{id}', [MascotaController::class, 'update'], $auth);
$router->delete('/mascotas/{id}', [MascotaController::class, 'destroy'], $admin);

// Consultas (historia clínica), sus archivos adjuntos y el PDF
$router->get('/consultas', [ConsultaController::class, 'index'], $auth);
$router->get('/consultas/{id}', [ConsultaController::class, 'show'], $auth);
$router->post('/consultas', [ConsultaController::class, 'store'], $auth);
$router->put('/consultas/{id}', [ConsultaController::class, 'update'], $auth);
$router->delete('/consultas/{id}', [ConsultaController::class, 'destroy'], $admin);
$router->post('/consultas/{id}/archivos/{numero}', [ConsultaController::class, 'subirArchivo'], $auth);
$router->get('/consultas/{id}/archivos/{numero}', [ConsultaController::class, 'descargarArchivo'], $auth);
$router->delete('/consultas/{id}/archivos/{numero}', [ConsultaController::class, 'eliminarArchivo'], $auth);
$router->get('/consultas/{id}/pdf', [ConsultaController::class, 'pdf'], $auth);

// Vacunas y vencimientos
$router->get('/vacunas', [VacunaController::class, 'index'], $auth);
$router->get('/vacunas/vencimientos', [VacunaController::class, 'vencimientos'], $auth);
$router->get('/vacunas/{id}', [VacunaController::class, 'show'], $auth);
$router->post('/vacunas', [VacunaController::class, 'store'], $auth);
$router->put('/vacunas/{id}', [VacunaController::class, 'update'], $auth);
$router->delete('/vacunas/{id}', [VacunaController::class, 'destroy'], $admin);

// Cuotas
$router->get('/cuotas', [CuotaController::class, 'index'], $auth);
$router->get('/cuotas/{id}', [CuotaController::class, 'show'], $auth);
$router->post('/cuotas', [CuotaController::class, 'store'], $auth);
$router->put('/cuotas/{id}', [CuotaController::class, 'update'], $auth);
$router->delete('/cuotas/{id}', [CuotaController::class, 'destroy'], $admin);

// Usuarios del sistema (solo admin)
$router->get('/usuarios', [UsuarioController::class, 'index'], $admin);
$router->get('/usuarios/{id}', [UsuarioController::class, 'show'], $admin);
$router->post('/usuarios', [UsuarioController::class, 'store'], $admin);
$router->put('/usuarios/{id}', [UsuarioController::class, 'update'], $admin);
$router->delete('/usuarios/{id}', [UsuarioController::class, 'destroy'], $admin);
