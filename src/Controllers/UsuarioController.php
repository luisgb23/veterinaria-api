<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Models\RefreshToken;
use App\Models\Usuario;
use App\Validators\UsuarioValidator;
use PDOException;

/**
 * Gestión de usuarios del sistema (solo administradores).
 */
class UsuarioController extends Controller
{
    private Usuario $usuarios;
    private RefreshToken $refreshTokens;

    public function __construct()
    {
        $db = Database::connection();
        $this->usuarios = new Usuario($db);
        $this->refreshTokens = new RefreshToken($db);
    }

    public function index(Request $request, array $params): void
    {
        Response::success('Listado de usuarios.', $this->usuarios->all());
    }

    public function show(Request $request, array $params): void
    {
        Response::success('Usuario obtenido.', Usuario::sanitize($this->findOrFail((int) $params['id'])));
    }

    /** POST /usuarios — nombre, usuario, password, rol ("user" por defecto) */
    public function store(Request $request, array $params): void
    {
        $input = $request->body();

        $errors = UsuarioValidator::validate($input, true);
        if (!empty($errors)) {
            Response::error('Datos inválidos.', 422, $errors);
        }

        $data = UsuarioValidator::clean($input);

        if ($this->usuarios->findByUser($data['usuario']) !== null) {
            Response::error('Ese nombre de usuario ya existe.', 409);
        }

        try {
            $id = $this->usuarios->create([
                'nombre'        => $data['nombre'],
                'usuario'       => $data['usuario'],
                'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
                'rol'           => $data['rol'],
            ]);
        } catch (PDOException $e) {
            // Dos altas simultáneas con el mismo usuario: gana el índice único
            if ($e->getCode() === '23000') {
                Response::error('Ese nombre de usuario ya existe.', 409);
            }
            throw $e;
        }

        Response::success('Usuario creado correctamente.', Usuario::sanitize($this->usuarios->findById($id)), 201);
    }

    /** PUT /usuarios/{id} — nombre, rol y, opcionalmente, password (para resetearla) */
    public function update(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $actual = $this->findOrFail($id);

        $input = $request->body();

        $errors = UsuarioValidator::validate($input, false);
        if (!empty($errors)) {
            Response::error('Datos inválidos.', 422, $errors);
        }

        $data = UsuarioValidator::clean($input);
        $data['rol'] = $input['rol'] ?? $actual['UsuarioRol'];

        if ($actual['UsuarioRol'] === 'admin' && $data['rol'] !== 'admin') {
            $this->evitarQuedarSinAdmins();
        }

        $this->usuarios->update($id, [
            'nombre'        => $data['nombre'],
            'rol'           => $data['rol'],
            'password_hash' => $data['password'] !== null ? password_hash($data['password'], PASSWORD_DEFAULT) : null,
        ]);

        // Si se le cambió la contraseña o el rol, sus sesiones abiertas dejan de renovarse
        if ($data['password'] !== null || $data['rol'] !== $actual['UsuarioRol']) {
            $this->refreshTokens->revokeAllForUser($id);
        }

        Response::success('Usuario actualizado correctamente.', Usuario::sanitize($this->usuarios->findById($id)));
    }

    /** DELETE /usuarios/{id} — baja lógica: ya no puede iniciar sesión */
    public function destroy(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $usuario = $this->findOrFail($id);

        if ($id === $request->user()['id']) {
            Response::error('No podés darte de baja a vos mismo.', 409);
        }

        if ($usuario['UsuarioRol'] === 'admin') {
            $this->evitarQuedarSinAdmins();
        }

        $this->usuarios->deactivate($id);
        $this->refreshTokens->revokeAllForUser($id);

        Response::success('Usuario dado de baja correctamente.');
    }

    private function evitarQuedarSinAdmins(): void
    {
        if ($this->usuarios->countActiveAdmins() <= 1) {
            Response::error('Tiene que quedar al menos un administrador activo.', 409);
        }
    }

    private function findOrFail(int $id): array
    {
        $usuario = $this->usuarios->findById($id);

        if ($usuario === null || !Usuario::isActive($usuario)) {
            Response::error('Usuario no encontrado.', 404);
        }

        return $usuario;
    }
}
