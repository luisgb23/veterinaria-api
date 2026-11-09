<?php

namespace App\Models;

use PDO;

/**
 * Acceso a datos de la tabla "usuario" (la del sistema anterior, con la columna UsuarioRol agregada).
 */
class Usuario
{
    public function __construct(private PDO $db)
    {
    }

    public function all(): array
    {
        $stmt = $this->db->query('SELECT * FROM usuario ORDER BY UsuarioNombre');

        return array_map([self::class, 'sanitize'], $stmt->fetchAll());
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM usuario WHERE UsuarioId = :id');
        $stmt->execute([':id' => $id]);

        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findByUser(string $usuario): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM usuario WHERE UsuarioUser = :usuario');
        $stmt->execute([':usuario' => $usuario]);

        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO usuario (UsuarioNombre, UsuarioUser, UsuarioPwd, UsuarioRol, UsuarioFchCreacion, UsuarioEstado)
             VALUES (:nombre, :usuario, :pwd, :rol, :creacion, 1)'
        );
        $stmt->execute([
            ':nombre'   => $data['nombre'],
            ':usuario'  => $data['usuario'],
            ':pwd'      => $data['password_hash'],
            ':rol'      => $data['rol'],
            ':creacion' => date('Y-m-d'),
        ]);

        return (int) $this->db->lastInsertId();
    }

    /** Actualiza nombre y rol, y la contraseña solo si se indica una nueva. */
    public function update(int $id, array $data): void
    {
        $sql = 'UPDATE usuario SET UsuarioNombre = :nombre, UsuarioRol = :rol';
        $params = [':nombre' => $data['nombre'], ':rol' => $data['rol'], ':id' => $id];

        if ($data['password_hash'] !== null) {
            $sql .= ', UsuarioPwd = :pwd';
            $params[':pwd'] = $data['password_hash'];
        }

        $stmt = $this->db->prepare($sql . ' WHERE UsuarioId = :id');
        $stmt->execute($params);
    }

    public function setRol(int $id, string $rol): void
    {
        $stmt = $this->db->prepare('UPDATE usuario SET UsuarioRol = :rol, UsuarioEstado = 1 WHERE UsuarioId = :id');
        $stmt->execute([':rol' => $rol, ':id' => $id]);
    }

    public function setPassword(int $id, string $hash): void
    {
        $stmt = $this->db->prepare('UPDATE usuario SET UsuarioPwd = :pwd WHERE UsuarioId = :id');
        $stmt->execute([':pwd' => $hash, ':id' => $id]);
    }

    /** Baja lógica: el usuario ya no puede iniciar sesión. */
    public function deactivate(int $id): void
    {
        $stmt = $this->db->prepare('UPDATE usuario SET UsuarioEstado = 0 WHERE UsuarioId = :id');
        $stmt->execute([':id' => $id]);
    }

    public function countActiveAdmins(): int
    {
        $stmt = $this->db->query("SELECT COUNT(*) FROM usuario WHERE UsuarioRol = 'admin' AND UsuarioEstado = 1");

        return (int) $stmt->fetchColumn();
    }

    public static function isActive(array $row): bool
    {
        return (int) $row['UsuarioEstado'] === 1;
    }

    /** Datos del usuario aptos para devolver en la API (sin el hash de la contraseña). */
    public static function sanitize(array $row): array
    {
        return [
            'id'        => (int) $row['UsuarioId'],
            'nombre'    => $row['UsuarioNombre'],
            'usuario'   => $row['UsuarioUser'],
            'rol'       => $row['UsuarioRol'],
            'activo'    => (int) $row['UsuarioEstado'] === 1,
            'creado_en' => $row['UsuarioFchCreacion'],
        ];
    }
}
