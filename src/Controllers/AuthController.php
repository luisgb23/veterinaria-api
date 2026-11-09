<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Env;
use App\Core\Jwt;
use App\Core\Request;
use App\Core\Response;
use App\Models\LoginIntento;
use App\Models\RefreshToken;
use App\Models\Usuario;
use App\Validators\AuthValidator;

/**
 * Login con usuario y contraseña (tabla "usuario" del sistema anterior).
 * No hay registro público: los usuarios los crea un admin (POST /usuarios o bin/crear-usuario.php).
 */
class AuthController
{
    /**
     * Hash de relleno: si el usuario no existe se verifica igualmente contra este hash,
     * para que el tiempo de respuesta no revele qué usuarios existen.
     */
    private const HASH_RELLENO = '$2y$10$.ZqYc70bmlSWouKSQrdTxOmo.w.M5pw503U9954NDadHn.F3iHyOW';

    private Usuario $usuarios;
    private RefreshToken $refreshTokens;
    private LoginIntento $intentos;

    public function __construct()
    {
        $db = Database::connection();
        $this->usuarios = new Usuario($db);
        $this->refreshTokens = new RefreshToken($db);
        $this->intentos = new LoginIntento($db);
    }

    /** POST /auth/login */
    public function login(Request $request, array $params): void
    {
        $input = $request->body();

        $errors = AuthValidator::login($input);
        if (!empty($errors)) {
            Response::error('Datos inválidos.', 422, $errors);
        }

        $usuario = strtolower(trim($input['usuario']));
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'desconocida';

        if ($this->intentos->demasiados($usuario, $ip)) {
            header('Retry-After: ' . LoginIntento::VENTANA);
            Response::error('Demasiados intentos fallidos. Probá de nuevo en unos minutos.', 429);
        }

        $user = $this->usuarios->findByUser($usuario);
        $passwordOk = $this->verificarPassword($input['password'], $user);

        if ($user === null || !$passwordOk || !Usuario::isActive($user)) {
            $this->intentos->registrar($usuario, $ip);
            // Mismo mensaje si falla el usuario, la contraseña o si está dado de baja
            Response::error('Credenciales inválidas.', 401);
        }

        $this->intentos->limpiar($usuario, $ip);

        Response::success('Inicio de sesión correcto.', [
            'user'   => Usuario::sanitize($user),
            'tokens' => $this->issueTokens($user),
        ]);
    }

    /** POST /auth/refresh — canjea un refresh token por un par nuevo (rotación). */
    public function refresh(Request $request, array $params): void
    {
        $input = $request->body();

        $errors = AuthValidator::refreshToken($input);
        if (!empty($errors)) {
            Response::error('Datos inválidos.', 422, $errors);
        }

        $row = $this->refreshTokens->findByHash(hash('sha256', $input['refresh_token']));

        if ($row === null) {
            Response::error('Refresh token inválido.', 401);
        }

        // Un token ya usado que vuelve a presentarse puede significar que fue robado:
        // se revocan todas las sesiones de ese usuario por precaución.
        if ((int) $row['revocado'] === 1) {
            $this->refreshTokens->revokeAllForUser((int) $row['usuario_id']);
            Response::error('Refresh token inválido.', 401);
        }

        if (strtotime($row['expira_en'] . ' UTC') <= time()) {
            Response::error('Refresh token expirado. Iniciá sesión nuevamente.', 401);
        }

        $user = $this->usuarios->findById((int) $row['usuario_id']);
        if ($user === null || !Usuario::isActive($user)) {
            Response::error('Refresh token inválido.', 401);
        }

        // Un refresh token solo sirve una vez
        if (!$this->refreshTokens->revoke((int) $row['id'])) {
            $this->refreshTokens->revokeAllForUser((int) $row['usuario_id']);
            Response::error('Refresh token inválido.', 401);
        }

        Response::success('Token renovado correctamente.', $this->issueTokens($user));
    }

    /** POST /auth/logout — revoca el refresh token indicado. */
    public function logout(Request $request, array $params): void
    {
        $input = $request->body();

        $errors = AuthValidator::refreshToken($input);
        if (!empty($errors)) {
            Response::error('Datos inválidos.', 422, $errors);
        }

        $row = $this->refreshTokens->findByHash(hash('sha256', $input['refresh_token']));

        if ($row !== null) {
            $this->refreshTokens->revoke((int) $row['id']);
        }

        // Siempre 200: no se revela si el token existía
        Response::success('Sesión cerrada correctamente.');
    }

    /** GET /auth/me (requiere AuthMiddleware) */
    public function me(Request $request, array $params): void
    {
        $user = $this->usuarios->findById($request->user()['id']);

        if ($user === null || !Usuario::isActive($user)) {
            Response::error('Usuario no encontrado.', 404);
        }

        Response::success('Usuario autenticado.', Usuario::sanitize($user));
    }

    /** PUT /auth/password — el usuario autenticado cambia su propia contraseña. */
    public function cambiarPassword(Request $request, array $params): void
    {
        $input = $request->body();

        $errors = AuthValidator::cambiarPassword($input);
        if (!empty($errors)) {
            Response::error('Datos inválidos.', 422, $errors);
        }

        $id = $request->user()['id'];
        $user = $this->usuarios->findById($id);

        if ($user === null || !$this->verificarPassword($input['password_actual'], $user)) {
            Response::error('La contraseña actual no es correcta.', 422, ['La contraseña actual no es correcta.']);
        }

        $this->usuarios->setPassword($id, password_hash($input['password_nueva'], PASSWORD_DEFAULT));

        // Cierra las demás sesiones: los refresh tokens anteriores dejan de servir
        $this->refreshTokens->revokeAllForUser($id);

        Response::success('Contraseña actualizada. Volvé a iniciar sesión en los otros dispositivos.', [
            'tokens' => $this->issueTokens($user),
        ]);
    }

    /**
     * Verifica la contraseña. Acepta también los hashes SHA-1 del sistema viejo
     * y, si coinciden, los reemplaza por bcrypt en ese mismo momento.
     */
    private function verificarPassword(string $password, ?array $user): bool
    {
        $hash = (string) ($user['UsuarioPwd'] ?? '');

        if ($user !== null && preg_match('/^[0-9a-f]{40}$/i', $hash)) {
            if (!hash_equals(strtolower($hash), sha1($password))) {
                return false;
            }

            $this->usuarios->setPassword((int) $user['UsuarioId'], password_hash($password, PASSWORD_DEFAULT));
            return true;
        }

        $ok = password_verify($password, $user !== null && $hash !== '' ? $hash : self::HASH_RELLENO);

        if ($ok && $user !== null && password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            $this->usuarios->setPassword((int) $user['UsuarioId'], password_hash($password, PASSWORD_DEFAULT));
        }

        return $ok && $user !== null;
    }

    /**
     * Genera el access token (JWT de vida corta) y un refresh token opaco guardado hasheado.
     */
    private function issueTokens(array $user): array
    {
        $accessTtl = (int) Env::get('JWT_TTL', '3600');
        $refreshTtl = (int) Env::get('REFRESH_TTL', '2592000');

        $accessToken = Jwt::encode([
            'sub'     => (string) $user['UsuarioId'],
            'usuario' => $user['UsuarioUser'],
            'rol'     => $user['UsuarioRol'],
        ], $accessTtl);

        $refreshToken = bin2hex(random_bytes(32));

        $this->refreshTokens->create(
            (int) $user['UsuarioId'],
            hash('sha256', $refreshToken),
            gmdate('Y-m-d H:i:s', time() + $refreshTtl)
        );

        return [
            'access_token'       => $accessToken,
            'token_type'         => 'Bearer',
            'expires_in'         => $accessTtl,
            'refresh_token'      => $refreshToken,
            'refresh_expires_in' => $refreshTtl,
        ];
    }
}
