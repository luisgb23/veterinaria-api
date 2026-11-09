<?php

namespace App\Models;

use PDO;

/**
 * Registro de intentos de login fallidos para frenar la fuerza bruta
 * (máximo 5 por combinación usuario + IP dentro de 15 minutos).
 */
class LoginIntento
{
    public const MAX_INTENTOS = 5;
    public const VENTANA = 900; // segundos

    public function __construct(private PDO $db)
    {
    }

    public function demasiados(string $usuario, string $ip): bool
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM login_intentos
             WHERE usuario = :usuario AND ip = :ip AND creado_en >= :desde'
        );
        $stmt->execute([
            ':usuario' => $usuario,
            ':ip'      => $ip,
            ':desde'   => gmdate('Y-m-d H:i:s', time() - self::VENTANA),
        ]);

        return (int) $stmt->fetchColumn() >= self::MAX_INTENTOS;
    }

    public function registrar(string $usuario, string $ip): void
    {
        // Limpieza de registros viejos para que la tabla no crezca indefinidamente
        $clean = $this->db->prepare('DELETE FROM login_intentos WHERE creado_en < :limite');
        $clean->execute([':limite' => gmdate('Y-m-d H:i:s', time() - self::VENTANA)]);

        $stmt = $this->db->prepare(
            'INSERT INTO login_intentos (usuario, ip, creado_en) VALUES (:usuario, :ip, :creado_en)'
        );
        $stmt->execute([
            ':usuario'   => $usuario,
            ':ip'        => $ip,
            ':creado_en' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    public function limpiar(string $usuario, string $ip): void
    {
        $stmt = $this->db->prepare('DELETE FROM login_intentos WHERE usuario = :usuario AND ip = :ip');
        $stmt->execute([':usuario' => $usuario, ':ip' => $ip]);
    }
}
