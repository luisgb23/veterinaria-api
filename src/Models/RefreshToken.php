<?php

namespace App\Models;

use PDO;

/**
 * Refresh tokens. En la base solo se guarda el hash SHA-256 del token,
 * así una filtración de la tabla no permite usarlos.
 */
class RefreshToken
{
    public function __construct(private PDO $db)
    {
    }

    /** $expiraEn en formato 'Y-m-d H:i:s' (UTC). */
    public function create(int $usuarioId, string $tokenHash, string $expiraEn): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO refresh_tokens (usuario_id, token_hash, expira_en)
             VALUES (:usuario_id, :token_hash, :expira_en)'
        );
        $stmt->execute([
            ':usuario_id' => $usuarioId,
            ':token_hash' => $tokenHash,
            ':expira_en'  => $expiraEn,
        ]);
    }

    public function findByHash(string $tokenHash): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM refresh_tokens WHERE token_hash = :token_hash');
        $stmt->execute([':token_hash' => $tokenHash]);

        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * Revoca un token. Devuelve false si ya estaba revocado (otra petición lo usó antes).
     */
    public function revoke(int $id): bool
    {
        $stmt = $this->db->prepare('UPDATE refresh_tokens SET revocado = 1 WHERE id = :id AND revocado = 0');
        $stmt->execute([':id' => $id]);

        return $stmt->rowCount() > 0;
    }

    public function revokeAllForUser(int $usuarioId): void
    {
        $stmt = $this->db->prepare('UPDATE refresh_tokens SET revocado = 1 WHERE usuario_id = :usuario_id');
        $stmt->execute([':usuario_id' => $usuarioId]);
    }
}
