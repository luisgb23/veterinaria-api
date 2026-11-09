<?php

use App\Core\Database;
use App\Models\Usuario;
use App\Validators\UsuarioValidator;

/**
 * Crea un usuario (admin por defecto) o, si ya existe, lo reactiva con el rol indicado
 * y opcionalmente le pone una contraseña nueva. Sirve también para recuperar el acceso.
 *
 * Uso:
 *   php bin/crear-usuario.php "Nombre Apellido" usuario [admin|user]
 *
 * La contraseña se pide por la terminal (se verá al escribir).
 */

if (PHP_SAPI !== 'cli') {
    exit('Este script solo se puede ejecutar desde la terminal.');
}

require dirname(__DIR__) . '/bootstrap.php';

[, $nombre, $usuario] = array_pad($argv, 3, null);
$rol = $argv[3] ?? 'admin';

if ($nombre === null || $usuario === null) {
    fwrite(STDERR, "Uso: php bin/crear-usuario.php \"Nombre Apellido\" usuario [admin|user]\n");
    exit(1);
}

$usuarios = new Usuario(Database::connection());
$usuario = strtolower(trim($usuario));
$existente = $usuarios->findByUser($usuario);

echo $existente !== null
    ? "El usuario {$usuario} ya existe. Contraseña nueva (Enter para no cambiarla): "
    : 'Contraseña (mínimo 8 caracteres, se verá al escribir): ';
$password = rtrim((string) fgets(STDIN), "\r\n");

$datos = ['nombre' => $nombre, 'usuario' => $usuario, 'password' => $password, 'rol' => $rol];
$errors = UsuarioValidator::validate($datos, $existente === null);

if (!empty($errors)) {
    fwrite(STDERR, "Datos inválidos:\n - " . implode("\n - ", $errors) . "\n");
    exit(1);
}

if ($existente !== null) {
    $id = (int) $existente['UsuarioId'];
    $usuarios->setRol($id, $rol);

    if ($password !== '') {
        $usuarios->setPassword($id, password_hash($password, PASSWORD_DEFAULT));
    }

    echo "Usuario {$usuario} actualizado: rol {$rol}, activo" . ($password !== '' ? ', contraseña cambiada' : '') . ".\n";
    exit(0);
}

$usuarios->create([
    'nombre'        => trim($nombre),
    'usuario'       => $usuario,
    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    'rol'           => $rol,
]);

echo "Usuario {$usuario} creado con rol {$rol}.\n";
